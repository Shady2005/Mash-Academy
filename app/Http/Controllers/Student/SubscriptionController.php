<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\SubmitPaymentRequest;
use App\Models\AcademySetting;
use App\Models\Course;
use App\Models\PaymentRequest;
use App\Models\PromoCode;
use App\Services\ActivityLogger;
use App\Services\CourseAccessService;
use App\Services\PromoCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly PromoCodeService $promoCodeService,
        private readonly CourseAccessService $courseAccess
    ) {}

    public function create(Request $request, Course $course): View|RedirectResponse
    {
        abort_unless($course->is_published, 404);

        $student = $request->user();
        $activeEnrollment = $this->courseAccess->activeEnrollmentFor($student, $course);
        $pendingRequest = PaymentRequest::query()
            ->where('user_id', $student->id)
            ->where('course_id', $course->id)
            ->where('status', PaymentRequest::STATUS_PENDING)
            ->latest()
            ->first();

        $course->load('creator');
        $resolvedInstaPay = $course->resolveInstaPayDetails();

        return view('student.subscribe.create', [
            'course' => $course,
            'student' => $student,
            'activeEnrollment' => $activeEnrollment,
            'pendingRequest' => $pendingRequest,
            'instapayQrUrl' => $resolvedInstaPay['url'],
            'instapayNote' => $resolvedInstaPay['note'],
            'instapaySource' => $resolvedInstaPay['source'],
            'instapayOwnerName' => $course->creator?->name,
        ]);
    }

    public function checkPromo(Request $request, Course $course): JsonResponse
    {
        abort_unless($course->is_published, 404);

        $rawCode = (string) $request->input('code', '');
        $result = $this->promoCodeService->evaluate($rawCode, $course);

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

    public function store(SubmitPaymentRequest $request, Course $course): RedirectResponse
    {
        abort_unless($course->is_published, 404);

        $student = $request->user();

        $duplicatePending = PaymentRequest::query()
            ->where('user_id', $student->id)
            ->where('course_id', $course->id)
            ->where('status', PaymentRequest::STATUS_PENDING)
            ->exists();

        if ($duplicatePending) {
            return redirect()
                ->route('student.payments.index')
                ->withErrors(['course' => 'You already have a pending payment request for this course. Please wait for Super Admin review.']);
        }

        $validated = $request->validated();
        $rawPromo = trim((string) ($validated['promo_code'] ?? ''));

        $originalPrice = round((float) $course->price, 2);
        $discountAmount = 0.0;
        $finalPrice = $originalPrice;
        $appliedPromo = null;

        if ($rawPromo !== '') {
            $eval = $this->promoCodeService->evaluate($rawPromo, $course);
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
            $course,
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
            $payment->course_id = $course->id;
            $payment->promo_code_id = $appliedPromo?->id;
            $payment->promo_code_snapshot = $appliedPromo?->code;
            $payment->original_price = $originalPrice;
            $payment->discount_amount = $discountAmount;
            $payment->final_price = $finalPrice;
            $payment->currency = $course->currency ?: 'EGP';
            // Strictly enforce authenticated student identity (cannot be tampered with via request)
            $payment->name = $student->name;
            $payment->email = $student->email;
            $payment->phone = trim((string) $validated['phone']);
            $payment->screenshot_path = $screenshotPath;
            $payment->status = PaymentRequest::STATUS_PENDING;
            $payment->save();

            ActivityLogger::log(
                action: 'payment.submitted',
                subject: $payment,
                meta: [
                    'course_id' => $course->id,
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

        return redirect()
            ->route('student.payments.index')
            ->with('status', "Payment confirmation #{$paymentRequest->id} submitted! Status: Pending Super Admin review.");
    }

    public function index(Request $request): View
    {
        $payments = PaymentRequest::query()
            ->with(['course', 'coursePackage', 'enrollment', 'enrollments'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(12);

        return view('student.payments.index', [
            'payments' => $payments,
        ]);
    }

    public function screenshot(Request $request, PaymentRequest $paymentRequest): BinaryFileResponse
    {
        abort_unless($paymentRequest->user_id === $request->user()->id, 403);
        abort_unless($paymentRequest->hasScreenshot(), 404);

        return response()->file(Storage::disk('local')->path($paymentRequest->screenshot_path), [
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    /**
     * Protected by `course.access` middleware! Displays unlocked course status & curriculum preview (Phase 3).
     */
    public function access(Request $request, Course $course): View
    {
        $student = $request->user();
        $enrollment = $this->courseAccess->activeEnrollmentFor($student, $course);

        $course->load([
            'creator',
            'publishedLessons',
            'quizzes',
            'assignments',
            'finalProject',
        ]);

        return view('student.courses.access', [
            'course' => $course,
            'enrollment' => $enrollment,
        ]);
    }
}
