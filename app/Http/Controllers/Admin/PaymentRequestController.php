<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademySetting;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\PaymentRequest;
use App\Services\ActivityLogger;
use App\Services\EnrollmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PaymentRequestController extends Controller
{
    public function __construct(
        private readonly EnrollmentService $enrollmentService
    ) {}

    public function index(Request $request): View
    {
        $statusFilter = trim((string) $request->query('status', ''));
        $courseFilter = $request->query('course_id');

        $payments = PaymentRequest::query()
            ->with(['user', 'course', 'coursePackage.courses', 'promoCode', 'reviewer', 'enrollment', 'enrollments'])
            ->when(
                in_array($statusFilter, [PaymentRequest::STATUS_PENDING, PaymentRequest::STATUS_APPROVED, PaymentRequest::STATUS_REJECTED], true),
                fn ($q) => $q->where('status', $statusFilter)
            )
            ->when($courseFilter, fn ($q) => $q->where('course_id', $courseFilter))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $counts = [
            'pending' => PaymentRequest::query()->where('status', PaymentRequest::STATUS_PENDING)->count(),
            'approved' => PaymentRequest::query()->where('status', PaymentRequest::STATUS_APPROVED)->count(),
            'rejected' => PaymentRequest::query()->where('status', PaymentRequest::STATUS_REJECTED)->count(),
        ];

        $courses = Course::query()->orderBy('title')->get(['id', 'title']);

        return view('admin.payments.index', [
            'payments' => $payments,
            'counts' => $counts,
            'statusFilter' => $statusFilter,
            'courseFilter' => $courseFilter,
            'courses' => $courses,
            'instapayQrUrl' => AcademySetting::instapayQrUrl(),
        ]);
    }

    public function screenshot(PaymentRequest $paymentRequest): BinaryFileResponse
    {
        abort_unless($paymentRequest->hasScreenshot(), 404, 'Transfer screenshot not found.');

        $fullPath = Storage::disk('local')->path($paymentRequest->screenshot_path);

        return response()->file($fullPath, [
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function approve(Request $request, PaymentRequest $paymentRequest): RedirectResponse
    {
        if (! $paymentRequest->isPending()) {
            return back()->withErrors(['payment' => 'Only pending payment requests can be approved.']);
        }

        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:1000'],
            'enroll_now' => ['nullable', 'boolean'],
            'duration_value' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'duration_unit' => ['nullable', Rule::in([Enrollment::UNIT_DAYS, Enrollment::UNIT_MONTHS])],
        ]);

        $enrollmentCreated = null;
        $packageEnrollments = collect();

        DB::transaction(function () use ($request, $paymentRequest, $validated, &$enrollmentCreated, &$packageEnrollments): void {
            $paymentRequest->status = PaymentRequest::STATUS_APPROVED;
            $paymentRequest->admin_note = isset($validated['admin_note']) && $validated['admin_note'] !== ''
                ? trim((string) $validated['admin_note'])
                : null;
            $paymentRequest->reviewed_by = $request->user()->id;
            $paymentRequest->reviewed_at = now();
            $paymentRequest->save();

            ActivityLogger::log(
                action: 'payment.approved',
                subject: $paymentRequest,
                meta: [
                    'student_id' => $paymentRequest->user_id,
                    'course_id' => $paymentRequest->course_id,
                    'course_package_id' => $paymentRequest->course_package_id,
                    'final_price' => $paymentRequest->final_price,
                    'promo_code' => $paymentRequest->promo_code_snapshot,
                ],
                actor: $request->user(),
                ip: $request->ip()
            );

            if ($request->boolean('enroll_now') && ! empty($validated['duration_value']) && ! empty($validated['duration_unit'])) {
                if ($paymentRequest->isPackagePayment() && $paymentRequest->coursePackage) {
                    $packageEnrollments = $this->enrollmentService->enrollPackage(
                        student: $paymentRequest->user,
                        package: $paymentRequest->coursePackage,
                        durationValue: (int) $validated['duration_value'],
                        durationUnit: (string) $validated['duration_unit'],
                        actor: $request->user(),
                        paymentRequest: $paymentRequest,
                        ip: $request->ip()
                    );
                } elseif ($paymentRequest->course) {
                    $enrollmentCreated = $this->enrollmentService->create(
                        student: $paymentRequest->user,
                        course: $paymentRequest->course,
                        durationValue: (int) $validated['duration_value'],
                        durationUnit: (string) $validated['duration_unit'],
                        actor: $request->user(),
                        paymentRequest: $paymentRequest,
                        ip: $request->ip()
                    );
                }
            }
        });

        if ($packageEnrollments->isNotEmpty()) {
            $expiresLabel = $packageEnrollments->first()->expires_at->format('M d, Y H:i');

            return redirect()
                ->route('admin.payments.index')
                ->with('status', "Package payment #{$paymentRequest->id} approved! {$packageEnrollments->count()} courses unlocked for {$paymentRequest->name} until {$expiresLabel}.");
        }

        if ($enrollmentCreated) {
            return redirect()
                ->route('admin.payments.index')
                ->with('status', "Payment #{$paymentRequest->id} approved and student enrolled until {$enrollmentCreated->expires_at->format('M d, Y H:i')}.");
        }

        return redirect()
            ->route('admin.enrollments.index', array_filter([
                'user_id' => $paymentRequest->user_id,
                'course_id_prefill' => $paymentRequest->course_id,
                'package_id_prefill' => $paymentRequest->course_package_id,
                'payment_request_id' => $paymentRequest->id,
            ]))
            ->with('status', "Payment #{$paymentRequest->id} approved. Select an access duration below to activate enrollment.");
    }

    public function reject(Request $request, PaymentRequest $paymentRequest): RedirectResponse
    {
        if (! $paymentRequest->isPending()) {
            return back()->withErrors(['payment' => 'Only pending payment requests can be rejected.']);
        }

        $validated = $request->validate([
            'admin_note' => ['required', 'string', 'min:3', 'max:1000'],
        ], [
            'admin_note.required' => 'Please provide a clear rejection reason for the student.',
        ]);

        DB::transaction(function () use ($request, $paymentRequest, $validated): void {
            $paymentRequest->status = PaymentRequest::STATUS_REJECTED;
            $paymentRequest->admin_note = trim((string) $validated['admin_note']);
            $paymentRequest->reviewed_by = $request->user()->id;
            $paymentRequest->reviewed_at = now();
            $paymentRequest->save();

            // Release promo code usage count if a promo code was reserved by this rejected request
            if ($paymentRequest->promoCode && $paymentRequest->promoCode->used_count > 0) {
                $paymentRequest->promoCode->decrement('used_count');
            }

            ActivityLogger::log(
                action: 'payment.rejected',
                subject: $paymentRequest,
                meta: [
                    'student_id' => $paymentRequest->user_id,
                    'course_id' => $paymentRequest->course_id,
                    'admin_note' => $paymentRequest->admin_note,
                ],
                actor: $request->user(),
                ip: $request->ip()
            );
        });

        return redirect()
            ->route('admin.payments.index')
            ->with('status', "Payment #{$paymentRequest->id} has been rejected with a note to the student.");
    }
}
