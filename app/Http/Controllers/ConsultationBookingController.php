<?php

namespace App\Http\Controllers;

use App\Models\AcademySetting;
use App\Models\Consultation;
use App\Models\PaymentRequest;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\NotificationDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ConsultationBookingController extends Controller
{
    public function __construct(
        private readonly NotificationDispatcher $notifications
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
        }

        $validated = $request->validate($rules);

        $screenshotPath = $isPaid
            ? $request->file('screenshot')->store("payment-proofs/user-{$user->id}", 'local')
            : null;

        [$consultation, $paymentRequest] = DB::transaction(function () use (
            $user,
            $validated,
            $duration,
            $pricingMode,
            $price,
            $currency,
            $isPaid,
            $screenshotPath,
            $request
        ): array {
            $consultation = Consultation::query()->create([
                'user_id' => $user->id,
                'instructor_id' => $validated['instructor_id'] ?? null,
                'preferred_date' => $validated['preferred_date'],
                'preferred_time' => trim((string) $validated['preferred_time']),
                'duration_minutes' => $duration,
                'pricing_mode' => $pricingMode,
                'price' => $price,
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
                $payment->original_price = $price;
                $payment->discount_amount = 0;
                $payment->final_price = $price;
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
