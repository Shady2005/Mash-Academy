@extends('layouts.admin')

@section('title', 'Admin Dashboard')
@section('page-title', 'Admin Dashboard')

@section('content')
    <div class="ma-card p-4 mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <span class="badge {{ $user->role->badgeClass() }} mb-2">{{ $user->role->label() }}</span>
                <h2 class="h4 fw-bold mb-1">Welcome back, {{ $user->name }}</h2>
                <p class="text-muted small mb-0">
                    @if ($user->isSuperAdmin())
                        You have full administrative access to branding, platform policy, staff hierarchy controls, and audit logs.
                    @elseif ($user->isTeacher())
                        You are signed in as Teacher Admin. Your course authoring tools will be enabled in Phase 2.
                    @elseif ($user->isSupport())
                        You are signed in as Customer Support Admin (read-only role). Student overview and analytics views will be enabled in Phase 5.
                    @endif
                </p>
            </div>
            @can('manage-settings')
                <div class="d-flex gap-2">
                    <a href="{{ route('admin.settings.edit') }}" class="btn btn-ma-primary btn-sm">
                        <i class="bi bi-sliders me-1" aria-hidden="true"></i>Academy Settings
                    </a>
                    <a href="{{ route('admin.settings.policy.edit') }}" class="btn btn-ma-accent btn-sm">
                        <i class="bi bi-file-earmark-text me-1" aria-hidden="true"></i>Edit Policy
                    </a>
                </div>
            @endcan
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="ma-card p-4 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Active Staff Accounts</div>
                        <div class="display-6 fw-bold mt-1">{{ $metrics['active_staff'] }} <span class="fs-6 text-muted fw-normal">/ 4</span></div>
                    </div>
                    <span class="ma-icon-box ma-icon-navy">
                        <i class="bi bi-person-badge" aria-hidden="true"></i>
                    </span>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="ma-card p-4 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Registered Students</div>
                        <div class="display-6 fw-bold mt-1">{{ $metrics['total_students'] }}</div>
                    </div>
                    <span class="ma-icon-box ma-icon-teal">
                        <i class="bi bi-mortarboard" aria-hidden="true"></i>
                    </span>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="ma-card p-4 h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Active Policy Version</div>
                        <div class="display-6 fw-bold mt-1">v{{ $metrics['policy_version'] }}</div>
                    </div>
                    <span class="ma-icon-box ma-icon-amber">
                        <i class="bi bi-shield-check" aria-hidden="true"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>

    @if ($user->isSuperAdmin())
        <div class="ma-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="h6 fw-bold mb-0">Recent Activity Logs</h3>
                <a href="{{ route('admin.activity-logs.index') }}" class="btn btn-outline-secondary btn-sm">View All Logs</a>
            </div>

            @if ($metrics['recent_logs']->isEmpty())
                <p class="text-muted small mb-0">No activity recorded yet.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
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
                                    <td><code class="text-primary">{{ $log->action }}</code></td>
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
                Administrative settings, staff management, and audit logs are restricted to Super Admins and enforced server-side.
            </p>
        </div>
    @endif
@endsection
