<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEnrollmentRequest;
use App\Models\Course;
use App\Models\CoursePackage;
use App\Models\Enrollment;
use App\Models\PaymentRequest;
use App\Models\User;
use App\Services\EnrollmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EnrollmentController extends Controller
{
    public function __construct(
        private readonly EnrollmentService $enrollmentService
    ) {}

    public function index(Request $request): View
    {
        // Keep statuses fresh on view
        $this->enrollmentService->expireOverdue();

        $statusFilter = trim((string) $request->query('status', ''));
        $courseFilter = $request->query('course_id');
        $search = trim((string) $request->query('q', ''));

        $enrollments = Enrollment::query()
            ->with(['user', 'course', 'creator', 'paymentRequest'])
            ->when(
                in_array($statusFilter, [Enrollment::STATUS_ACTIVE, Enrollment::STATUS_EXPIRED, Enrollment::STATUS_REVOKED], true),
                fn ($q) => $q->where('status', $statusFilter)
            )
            ->when($courseFilter, fn ($q) => $q->where('course_id', $courseFilter))
            ->when($search !== '', function ($q) use ($search): void {
                $q->whereHas('user', function ($uq) use ($search): void {
                    $uq->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $students = User::query()
            ->where('role', UserRole::Student->value)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $courses = Course::query()
            ->orderBy('title')
            ->get(['id', 'title', 'price', 'currency']);

        $packages = CoursePackage::query()
            ->withCount('courses')
            ->orderBy('title')
            ->get(['id', 'title', 'price', 'currency']);

        return view('admin.enrollments.index', [
            'enrollments' => $enrollments,
            'students' => $students,
            'courses' => $courses,
            'packages' => $packages,
            'statusFilter' => $statusFilter,
            'courseFilter' => $courseFilter,
            'search' => $search,
            'prefillUserId' => $request->query('user_id'),
            'prefillCourseId' => $request->query('course_id_prefill', $request->query('course_id')),
            'prefillPackageId' => $request->query('package_id_prefill'),
            'prefillPaymentId' => $request->query('payment_request_id'),
        ]);
    }

    public function store(StoreEnrollmentRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $student = User::query()->findOrFail($validated['user_id']);
        $paymentRequest = ! empty($validated['payment_request_id'])
            ? PaymentRequest::query()->find($validated['payment_request_id'])
            : null;

        if (! empty($validated['course_package_id'])) {
            $package = CoursePackage::query()->with('courses')->findOrFail($validated['course_package_id']);
            $enrollments = $this->enrollmentService->enrollPackage(
                student: $student,
                package: $package,
                durationValue: (int) $validated['duration_value'],
                durationUnit: (string) $validated['duration_unit'],
                actor: $request->user(),
                paymentRequest: $paymentRequest,
                ip: $request->ip()
            );

            $expiresLabel = $enrollments->first()?->expires_at->format('M d, Y H:i') ?? 'N/A';

            return redirect()
                ->route('admin.enrollments.index')
                ->with('status', "Enrolled {$student->name} in package \"{$package->title}\" ({$enrollments->count()} courses unlocked) until {$expiresLabel}.");
        }

        $course = Course::query()->findOrFail($validated['course_id']);

        $enrollment = $this->enrollmentService->create(
            student: $student,
            course: $course,
            durationValue: (int) $validated['duration_value'],
            durationUnit: (string) $validated['duration_unit'],
            actor: $request->user(),
            paymentRequest: $paymentRequest,
            ip: $request->ip()
        );

        return redirect()
            ->route('admin.enrollments.index')
            ->with('status', "Enrolled {$student->name} in \"{$course->title}\" until {$enrollment->expires_at->format('M d, Y H:i')}.");
    }

    public function extend(Request $request, Enrollment $enrollment): RedirectResponse
    {
        $validated = $request->validate([
            'duration_value' => ['required', 'integer', 'min:1', 'max:3650'],
            'duration_unit' => ['required', Rule::in([Enrollment::UNIT_DAYS, Enrollment::UNIT_MONTHS])],
        ]);

        $updated = $this->enrollmentService->extend(
            enrollment: $enrollment,
            durationValue: (int) $validated['duration_value'],
            durationUnit: (string) $validated['duration_unit'],
            actor: $request->user(),
            ip: $request->ip()
        );

        return redirect()
            ->route('admin.enrollments.index')
            ->with('status', "Enrollment #{$updated->id} extended until {$updated->expires_at->format('M d, Y H:i')}.");
    }

    public function revoke(Request $request, Enrollment $enrollment): RedirectResponse
    {
        $this->enrollmentService->revoke(
            enrollment: $enrollment,
            actor: $request->user(),
            ip: $request->ip()
        );

        return redirect()
            ->route('admin.enrollments.index')
            ->with('status', "Enrollment #{$enrollment->id} has been revoked. The course is now locked immediately.");
    }
}
