<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\SubmitPaymentRequest;
use App\Models\CoursePackage;
use App\Models\PaymentRequest;
use App\Models\PromoCode;
use App\Services\ActivityLogger;
use App\Services\CourseAccessService;
use App\Services\NotificationDispatcher;
use App\Services\PromoCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PackageSubscriptionController extends Controller
{
    public function __construct(
        private readonly PromoCodeService $promoCodeService,
        private readonly CourseAccessService $courseAccess,
        private readonly NotificationDispatcher $notifications
    ) {}

    public function create(Request $request, CoursePackage $package): View
    {
        abort_unless($package->is_published, 404);

        $package->load(['creator', 'courses']);
        $student = $request->user();

        $pendingRequest = PaymentRequest::query()
            ->where('user_id', $student->id)
            ->where('course_package_id', $package->id)
            ->where('status', PaymentRequest::STATUS_PENDING)
            ->latest()
            ->first();

        $activeEnrollments = $this->courseAccess->activeEnrollmentsMapFor($student);
        $resolvedInstaPay = $package->resolveInstaPayDetails();

        return view('student.packages.subscribe', [
            'package' => $package,
            'student' => $student,
            'pendingRequest' => $pendingRequest,
            'activeEnrollments' => $activeEnrollments,
            'instapayQrUrl' => $resolvedInstaPay['url'],
            'instapayNote' => $resolvedInstaPay['note'],
            'instapaySource' => $resolvedInstaPay['source'],
            'instapayOwnerName' => $package->creator?->name,
        ]);
    }

    public function checkPromo(Request $request, CoursePackage $package): JsonResponse
    {
        abort_unless($package->is_published, 404);

        $rawCode = (string) $request->input('code', '');
        $result = $this->promoCodeService->evaluateForPackage($rawCode, $package);

        if (! $result['valid']) {
            return response()->json([
                'valid' => false,
                'message' => $result['error'],
                'original_price' => $result['original_price'],
                'discount_amount' => 0,
                'final_price' => $result['original_price'],
                'currency' => $result['currency'],
            ], 422);
        }

        /** @var PromoCode $promo */
        $promo = $result['promo'];

        return response()->json([
            'valid' => true,
            'code' => $promo->code,
            'discount_label' => $promo->formattedDiscount(),
            'original_price' => $result['original_price'],
            'discount_amount' => $result['discount_amount'],
            'final_price' => $result['final_price'],
            'currency' => $result['currency'],
            'message' => "Promo code {$promo->code} applied ({$promo->formattedDiscount()} off).",
        ]);
    }

    public function store(SubmitPaymentRequest $request, CoursePackage $package): RedirectResponse
    {
        abort_unless($package->is_published, 404);

        $student = $request->user();

        $duplicatePending = PaymentRequest::query()
            ->where('user_id', $student->id)
            ->where('course_package_id', $package->id)
            ->where('status', PaymentRequest::STATUS_PENDING)
            ->exists();

        if ($duplicatePending) {
            return redirect()
                ->route('student.payments.index')
                ->withErrors(['package' => 'You already have a pending payment request for this package. Please wait for Super Admin review.']);
        }

        $validated = $request->validated();
        $rawPromo = trim((string) ($validated['promo_code'] ?? ''));

        $originalPrice = round((float) $package->price, 2);
        $discountAmount = 0.0;
        $finalPrice = $originalPrice;
        $appliedPromo = null;

        if ($rawPromo !== '') {
            $eval = $this->promoCodeService->evaluateForPackage($rawPromo, $package);
            if (! $eval['valid']) {
                throw ValidationException::withMessages([
                    'promo_code' => $eval['error'],
                ]);
            }
            $appliedPromo = $eval['promo'];
            $discountAmount = $eval['discount_amount'];
            $finalPrice = $eval['final_price'];
        }

        $screenshotPath = $request->file('screenshot')->store("payment-proofs/user-{$student->id}", 'local');

        $paymentRequest = DB::transaction(function () use (
            $student,
            $package,
            $appliedPromo,
            $originalPrice,
            $discountAmount,
            $finalPrice,
            $validated,
            $screenshotPath,
            $request
        ): PaymentRequest {
            if ($appliedPromo) {
                $lockedPromo = PromoCode::query()->lockForUpdate()->find($appliedPromo->id);
                if (! $lockedPromo || ! $lockedPromo->isUsable()) {
                    throw ValidationException::withMessages([
                        'promo_code' => 'This promo code is no longer valid or has reached its usage limit.',
                    ]);
                }
                $lockedPromo->increment('used_count');
            }

            $payment = new PaymentRequest();
            $payment->user_id = $student->id;
            $payment->course_id = null;
            $payment->course_package_id = $package->id;
            $payment->promo_code_id = $appliedPromo?->id;
            $payment->promo_code_snapshot = $appliedPromo?->code;
            $payment->original_price = $originalPrice;
            $payment->discount_amount = $discountAmount;
            $payment->final_price = $finalPrice;
            $payment->currency = $package->currency ?: 'EGP';
            $payment->name = $student->name;
            $payment->email = $student->email;
            $payment->phone = trim((string) $validated['phone']);
            $payment->screenshot_path = $screenshotPath;
            $payment->status = PaymentRequest::STATUS_PENDING;
            $payment->save();

            ActivityLogger::log(
                action: 'payment.package_submitted',
                subject: $payment,
                meta: [
                    'course_package_id' => $package->id,
                    'package_title' => $package->title,
                    'original_price' => $originalPrice,
                    'discount_amount' => $discountAmount,
                    'final_price' => $finalPrice,
                    'promo_code' => $appliedPromo?->code,
                ],
                actor: $student,
                ip: $request->ip()
            );

            return $payment;
        });

        $this->notifications->notifyPaymentSubmitted($paymentRequest);

        return redirect()
            ->route('student.payments.index')
            ->with('status', "Package payment confirmation #{$paymentRequest->id} submitted! Once approved, all included courses will unlock automatically in My Courses.");
    }
}
