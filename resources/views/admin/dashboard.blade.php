@extends('layouts.admin')

@section('title', __('messages.dashboard'))
@section('page-title', __('messages.dashboard'))

@section('content')
    <div class="ma-card p-4 mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <span class="badge bg-navy-accent mb-2">{{ $user->role->label() }}</span>
                <h2 class="h4 fw-bold mb-1">{{ __('messages.welcome_back') }}, {{ $user->name }}</h2>
                <p class="text-muted small mb-0">
                    @if ($user->isSuperAdmin())
                        Full administrative access to courses, lessons, flexible assessments, Final Projects, branding, bilingual Vision, policy, staff hierarchy, and audit logs.
                    @elseif ($user->isTeacher())
                        You can create courses, upload videos, build standalone quizzes, flexible File/MCQ assignments, and configure Final Projects for your own courses.
                    @elseif ($user->isSupport())
                        You are signed in as Customer Support Admin (strictly read-only role). View student accounts, enrollment statuses, progress analytics, and certificate verification.
                    @endif
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @can('view-analytics')
                    <a href="{{ route('admin.analytics.index') }}" class="btn btn-ma-primary btn-sm">
                        <i class="bi bi-graph-up-arrow me-1" aria-hidden="true"></i>{{ __('messages.analytics_dashboard') }}
                    </a>
                    <a href="{{ route('admin.students.index') }}" class="btn btn-ma-outline btn-sm">
                        <i class="bi bi-person-lines-fill me-1" aria-hidden="true"></i>{{ __('messages.student_directory') }}
                    </a>
                @endcan
                @can('manage-courses')
                    <a href="{{ route('admin.courses.index') }}" class="btn btn-ma-primary btn-sm">
                        <i class="bi bi-journal-bookmark-fill me-1" aria-hidden="true"></i>{{ __('messages.manage_courses') }}
                    </a>
                @endcan
                @can('manage-payments')
                    <a href="{{ route('admin.payments.index') }}" class="btn btn-ma-dark btn-sm">
                        <i class="bi bi-receipt-cutoff me-1" aria-hidden="true"></i>{{ __('messages.payments_inbox') }}
                        @if ($metrics['pending_payments'] > 0)
                            <span class="badge bg-warning text-dark ms-1">{{ $metrics['pending_payments'] }}</span>
                        @endif
                    </a>
                @endcan
                @can('manage-settings')
                    <a href="{{ route('admin.settings.edit') }}" class="btn btn-ma-outline btn-sm">
                        <i class="bi bi-sliders me-1" aria-hidden="true"></i>{{ __('messages.academy_branding') }}
                    </a>
                @endcan
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="ma-card p-4 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">{{ __('messages.courses') }}</div>
                        <div class="display-6 fw-bold mt-1">{{ $metrics['total_courses'] }}</div>
                        <div class="small text-muted">{{ $metrics['published_courses'] }} {{ __('messages.published') }}</div>
                    </div>
                    <span class="ma-icon-box ma-icon-navy">
                        <i class="bi bi-journal-bookmark" aria-hidden="true"></i>
                    </span>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="ma-card p-4 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Active Staff</div>
                        <div class="display-6 fw-bold mt-1">{{ $metrics['active_staff'] }} <span class="fs-6 text-muted fw-normal">/ 4</span></div>
                    </div>
                    <span class="ma-icon-box ma-icon-black">
                        <i class="bi bi-person-badge" aria-hidden="true"></i>
                    </span>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="ma-card p-4 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Students</div>
                        <div class="display-6 fw-bold mt-1">{{ $metrics['total_students'] }}</div>
                    </div>
                    <span class="ma-icon-box ma-icon-navy">
                        <i class="bi bi-mortarboard" aria-hidden="true"></i>
                    </span>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="ma-card p-4 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Policy Version</div>
                        <div class="display-6 fw-bold mt-1">v{{ $metrics['policy_version'] }}</div>
                    </div>
                    <span class="ma-icon-box ma-icon-black">
                        <i class="bi bi-shield-check" aria-hidden="true"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>

    @if ($user->isSuperAdmin())
        <div class="ma-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="h6 fw-bold mb-0">{{ __('messages.activity_logs') }}</h3>
                <a href="{{ route('admin.activity-logs.index') }}" class="btn btn-ma-outline btn-sm">View All</a>
            </div>

            @if ($metrics['recent_logs']->isEmpty())
                <p class="text-muted small mb-0">No activity recorded yet.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Action</th>
                                <th>Actor</th>
                                <th>IP Address</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            @foreach ($metrics['recent_logs'] as $log)
                                <tr>
                                    <td><code class="text-navy">{{ $log->action }}</code></td>
                                    <td>{{ $log->actor?->name ?? 'System' }}</td>
                                    <td class="text-muted">{{ $log->ip ?? '—' }}</td>
                                    <td class="text-muted">{{ $log->created_at?->format('M d, Y H:i:s') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @else
        <div class="ma-card p-4">
            <h3 class="h6 fw-bold mb-2">Role Permissions Summary</h3>
            <p class="text-muted small mb-0">
                Your menu reflects the permissions assigned to <strong>{{ $user->role->label() }}</strong>.
                @if ($user->isTeacher())
                    You can create and manage your own courses, lessons, quizzes, flexible assignments, and Final Projects. You cannot edit or delete Super Admin courses.
                @else
                    Customer Support is strictly read-only and has no course management or settings write access.
                @endif
            </p>
        </div>
    @endif
@endsection
