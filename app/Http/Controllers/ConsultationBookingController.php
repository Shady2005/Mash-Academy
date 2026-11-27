<?php

namespace App\Http\Controllers;

use App\Models\AcademySetting;
use App\Models\Consultation;
use App\Models\PaymentRequest;
use App\Models\PromoCode;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\NotificationDispatcher;
use App\Services\PromoCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ConsultationBookingController extends Controller
{
    public function __construct(
        private readonly NotificationDispatcher $notifications,
        private readonly PromoCodeService $promoCodeService
    ) {}

    public function index(Request $request): View
    {
        abort_unless(AcademySetting::isFeatureEnabled('enable_consultations'), 404);

        $settings = AcademySetting::allSettings();
        $pricingMode = ($settings['consultation_pricing_mode'] ?? 'free') === Consultation::PRICING_PAID
            ? Consultation::PRICING_PAID
            : Consultation::PRICING_FREE;
        $price = $pricingMode === Consultation::PRICING_PAID
            ? round((float) ($settings['consultation_price'] ?? 0), 2)
            : 0.0;
        $duration = max(15, (int) ($settings['consultation_duration_minutes'] ?? 30));
        $currency = $settings['consultation_currency'] ?? 'EGP';
        $availabilityNote = $settings['consultation_availability_note'] ?? null;

        $instructors = User::query()
            ->whereIn('role', User::INSTRUCTOR_ROLES)
            ->where('is_active', true)
            ->where('is_blocked', false)
            ->orderByRaw("CASE role WHEN 'super_admin_1' THEN 1 WHEN 'super_admin_2' THEN 2 WHEN 'teacher' THEN 3 ELSE 4 END ASC")
            ->get();

        $myConsultations = $request->user()
            ? Consultation::query()
                ->with(['instructor', 'paymentRequest'])
                ->where('user_id', $request->user()->id)
                ->latest()
                ->get()
            : collect();

        return view('public.consultations.index', [
            'pricingMode' => $pricingMode,
            'price' => $price,
            'duration' => $duration,
            'currency' => $currency,
            'availabilityNote' => $availabilityNote,
            'instapayQrUrl' => AcademySetting::consultationInstaPayQrUrl(),
            'instapayNote' => $settings['consultation_instapay_note'] ?? ($settings['instapay_account_note'] ?? null),
            'instructors' => $instructors,
            'myConsultations' => $myConsultations,
        ]);
    }

    public function checkPromo(Request $request): JsonResponse
    {
        abort_unless(AcademySetting::isFeatureEnabled('enable_consultations'), 404);

        $settings = AcademySetting::allSettings();
        $pricingMode = ($settings['consultation_pricing_mode'] ?? 'free') === Consultation::PRICING_PAID
            ? Consultation::PRICING_PAID
            : Consultation::PRICING_FREE;
        $price = $pricingMode === Consultation::PRICING_PAID
            ? round((float) ($settings['consultation_price'] ?? 0), 2)
            : 0.0;
        $currency = $settings['consultation_currency'] ?? 'EGP';

        $rawCode = (string) $request->input('code', '');
        $instructorId = $request->filled('instructor_id') ? (int) $request->input('instructor_id') : null;

        $result = $this->promoCodeService->evaluateForConsultation($rawCode, $instructorId, $price, $currency);

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

    public function store(Request $request): RedirectResponse
    {
        abort_unless(AcademySetting::isFeatureEnabled('enable_consultations'), 404);

        $user = $request->user();
        abort_unless($user && ($user->isStudent() || $user->isSuperAdmin()), 403);

        $settings = AcademySetting::allSettings();
        $pricingMode = ($settings['consultation_pricing_mode'] ?? 'free') === Consultation::PRICING_PAID
            ? Consultation::PRICING_PAID
            : Consultation::PRICING_FREE;
        $price = $pricingMode === Consultation::PRICING_PAID
            ? round((float) ($settings['consultation_price'] ?? 0), 2)
            : 0.0;
        $duration = max(15, (int) ($settings['consultation_duration_minutes'] ?? 30));
        $currency = $settings['consultation_currency'] ?? 'EGP';
        $isPaid = $pricingMode === Consultation::PRICING_PAID && $price > 0;

        $rules = [
            'instructor_id' => ['nullable', 'integer', 'exists:users,id'],
            'preferred_date' => ['required', 'date', 'after_or_equal:today'],
            'preferred_time' => ['required', 'string', 'max:32'],
            'topic' => ['required', 'string', 'min:3', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        if ($isPaid) {
            $rules['phone'] = ['required', 'string', 'min:8', 'max:30'];
            $rules['screenshot'] = ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'];
            $rules['promo_code'] = ['nullable', 'string', 'max:64'];
        }

        $validated = $request->validate($rules);

        $rawPromo = $isPaid ? trim((string) ($validated['promo_code'] ?? '')) : '';
        $discountAmount = 0.0;
        $finalPrice = $price;
        $appliedPromo = null;

        if ($isPaid && $rawPromo !== '') {
            $instructorId = isset($validated['instructor_id']) && $validated['instructor_id'] !== ''
                ? (int) $validated['instructor_id']
                : null;
            $eval = $this->promoCodeService->evaluateForConsultation($rawPromo, $instructorId, $price, $currency);
            if (! $eval['valid']) {
                throw ValidationException::withMessages([
                    'promo_code' => $eval['error'],
                ]);
            }
            $appliedPromo = $eval['promo'];
            $discountAmount = $eval['discount_amount'];
            $finalPrice = $eval['final_price'];
        }

        $screenshotPath = $isPaid
            ? $request->file('screenshot')->store("payment-proofs/user-{$user->id}", 'local')
            : null;

        [$consultation, $paymentRequest] = DB::transaction(function () use (
            $user,
            $validated,
            $duration,
            $pricingMode,
            $price,
            $discountAmount,
            $finalPrice,
            $appliedPromo,
            $currency,
            $isPaid,
            $screenshotPath,
            $request
        ): array {
            if ($appliedPromo) {
                $lockedPromo = PromoCode::query()->lockForUpdate()->find($appliedPromo->id);
                if (! $lockedPromo || ! $lockedPromo->isUsable()) {
                    throw ValidationException::withMessages([
                        'promo_code' => 'This promo code is no longer valid or has reached its usage limit.',
                    ]);
                }
                $lockedPromo->increment('used_count');
            }

            $consultation = Consultation::query()->create([
                'user_id' => $user->id,
                'instructor_id' => $validated['instructor_id'] ?? null,
                'preferred_date' => $validated['preferred_date'],
                'preferred_time' => trim((string) $validated['preferred_time']),
                'duration_minutes' => $duration,
                'pricing_mode' => $pricingMode,
                'price' => $finalPrice,
                'currency' => $currency,
                'topic' => trim((string) $validated['topic']),
                'notes' => isset($validated['notes']) && trim((string) $validated['notes']) !== ''
                    ? trim((string) $validated['notes'])
                    : null,
                'status' => Consultation::STATUS_PENDING,
            ]);

            $payment = null;
            if ($isPaid) {
                $payment = new PaymentRequest();
                $payment->user_id = $user->id;
                $payment->course_id = null;
                $payment->course_package_id = null;
                $payment->consultation_id = $consultation->id;
                $payment->promo_code_id = $appliedPromo?->id;
                $payment->promo_code_snapshot = $appliedPromo?->code;
                $payment->original_price = $price;
                $payment->discount_amount = $discountAmount;
                $payment->final_price = $finalPrice;
                $payment->currency = $currency;
                $payment->name = $user->name;
                $payment->email = $user->email;
                $payment->phone = trim((string) $validated['phone']);
                $payment->screenshot_path = $screenshotPath;
                $payment->status = PaymentRequest::STATUS_PENDING;
                $payment->save();

                $consultation->payment_request_id = $payment->id;
                $consultation->save();
            }

            ActivityLogger::log(
                action: 'consultation.booked',
                subject: $consultation,
                meta: [
                    'pricing_mode' => $pricingMode,
                    'price' => $price,
                    'preferred_date' => $consultation->preferred_date->toDateString(),
                    'payment_request_id' => $payment?->id,
                ],
                actor: $user,
                ip: $request->ip()
            );

            return [$consultation, $payment];
        });

        $this->notifications->notifyConsultationBooked($consultation);
        if ($paymentRequest) {
            $this->notifications->notifyPaymentSubmitted($paymentRequest);
        }

        $msg = $isPaid
            ? "Consultation request #{$consultation->id} and InstaPay transfer proof (#{$paymentRequest->id}) submitted! It will be confirmed upon Super Admin payment approval."
            : "Your free 1-on-1 consultation request #{$consultation->id} has been submitted! Our team will confirm your slot shortly.";

        return redirect()
            ->route('consultations.index')
            ->with('status', $msg);
    }
}
