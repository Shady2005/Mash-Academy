<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\PaymentRequest;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\EnrollmentService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportStudentController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analytics,
        private readonly EnrollmentService $enrollmentService
    ) {}

    /**
     * Strictly read-only searchable directory of ALL registered Student accounts
     * showing active/expired enrollments and pending payment requests accurately.
     */
    public function index(Request $request): View
    {
        $this->enrollmentService->expireOverdue();

        $search = trim((string) $request->query('q', ''));
        $filter = trim((string) $request->query('filter', ''));
        $now = now();

        $students = User::withTrashed()
            ->where('role', UserRole::Student->value)
            ->with([
                'enrollments' => fn ($q) => $q->with(['course' => fn ($c) => $c->withTrashed()])->latest(),
                'paymentRequests' => fn ($q) => $q->with(['course' => fn ($c) => $c->withTrashed()])->latest(),
            ])
            ->withCount(['enrollments', 'certificates'])
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                    if (ctype_digit($search)) {
                        $sub->orWhere('id', (int) $search);
                    }
                });
            })
            ->when($filter === 'active', function ($q) use ($now): void {
                $q->whereHas('enrollments', fn ($eq) => $eq->currentlyActive($now));
            })
            ->when($filter === 'expired', function ($q): void {
                $q->whereHas('enrollments', fn ($eq) => $eq->whereIn('status', [Enrollment::STATUS_EXPIRED, Enrollment::STATUS_REVOKED]));
            })
            ->when($filter === 'pending_payment', function ($q): void {
                $q->whereHas('paymentRequests', fn ($pq) => $pq->where('status', PaymentRequest::STATUS_PENDING));
            })
            ->when($filter === 'none', function ($q): void {
                $q->whereDoesntHave('enrollments');
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.students.index', [
            'students' => $students,
            'search' => $search,
            'filter' => $filter,
        ]);
    }

    /**
     * Strictly read-only student detail dossier (contact details, all enrollments, pending payments, progress, certificates).
     */
    public function show(int $student): View
    {
        $studentModel = User::withTrashed()->findOrFail($student);
        abort_unless($studentModel->isStudent(), 404);

        $report = $this->analytics->studentDetailReport($studentModel);

        return view('admin.students.show', $report);
    }
}
