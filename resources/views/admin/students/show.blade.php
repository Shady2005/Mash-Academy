@extends('layouts.admin')

@section('title', 'Student Profile — '.$student->name)
@section('page-title', 'Student Profile (Read-Only)')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <a href="{{ route('admin.students.index') }}" class="btn btn-ma-outline btn-sm">
            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back to Students Directory
        </a>
        @if (auth()->user()->isSupport())
            <span class="badge bg-black-badge px-3 py-2">
                <i class="bi bi-eye me-1" aria-hidden="true"></i>{{ __('messages.read_only_badge') }}
            </span>
        @endif
    </div>

    {{-- Account & Contact Info --}}
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="ma-card p-4 h-100">
                <h2 class="h6 fw-bold text-uppercase text-muted mb-3">Account &amp; Contact Information</h2>
                <dl class="row small mb-0">
                    <dt class="col-4 text-muted">Full Name</dt>
                    <dd class="col-8 fw-semibold">{{ $student->name }}</dd>

                    <dt class="col-4 text-muted">Email</dt>
                    <dd class="col-8">{{ $student->email }}</dd>

                    <dt class="col-4 text-muted">Phone</dt>
                    <dd class="col-8">{{ $student->phone ?: 'Not provided' }}</dd>

                    <dt class="col-4 text-muted">Status</dt>
                    <dd class="col-8">
                        @if ($student->trashed())
                            <span class="badge bg-danger">Archived (Soft-Deleted)</span>
                        @else
                            <span class="badge {{ $student->is_active ? 'bg-success' : 'bg-danger' }}">
                                {{ $student->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        @endif
                    </dd>

                    <dt class="col-4 text-muted">Registered</dt>
                    <dd class="col-8">{{ $student->created_at->format('M d, Y H:i') }}</dd>

                    <dt class="col-4 text-muted">Last Login</dt>
                    <dd class="col-8">{{ $student->last_login_at ? $student->last_login_at->format('M d, Y H:i') : 'Never' }}</dd>
                </dl>
            </div>
        </div>

        <div class="col-md-6">
            <div class="ma-card p-4 h-100">
                <h2 class="h6 fw-bold text-uppercase text-muted mb-3">Policy Consent &amp; Certificates</h2>
                @if ($latestPolicyAcceptance)
                    <p class="small mb-3">
                        Accepted Policy <strong>v{{ $latestPolicyAcceptance->policy_version }}</strong>
                        on {{ $latestPolicyAcceptance->accepted_at->format('M d, Y H:i') }}
                        (IP: <code>{{ $latestPolicyAcceptance->ip_address ?? '—' }}</code>).
                    </p>
                @else
                    <p class="small text-muted mb-3">No policy acceptance recorded.</p>
                @endif

                <h3 class="small fw-bold text-uppercase text-muted mb-2">Issued Certificates ({{ $certificates->count() }})</h3>
                @if ($certificates->isEmpty())
                    <p class="small text-muted mb-0">No certificates earned yet.</p>
                @else
                    <ul class="list-unstyled small mb-0 d-flex flex-column gap-2">
                        @foreach ($certificates as $cert)
                            <li class="d-flex justify-content-between align-items-center border-bottom pb-2">
                                <span>{{ $cert->course?->title ?? 'Course' }}</span>
                                <span class="badge {{ $cert->isValid() ? 'bg-navy-accent' : 'bg-danger' }}">
                                    {{ $cert->certificate_code }}{{ $cert->isRevoked() ? ' (Revoked)' : '' }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    {{-- Enrollment & Course Progress --}}
    <div class="ma-card overflow-hidden mb-4">
        <div class="p-3 border-bottom">
            <h2 class="h6 fw-bold mb-0">Enrollments &amp; Learning Progress ({{ $progressRows->count() }})</h2>
        </div>
        @if ($progressRows->isEmpty())
            <div class="p-4 text-center text-muted small">This student has no course enrollments yet.</div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0 small">
                    <thead>
                        <tr>
                            <th>Course</th>
                            <th>Status &amp; Expiry</th>
                            <th>Videos Watched</th>
                            <th>Completion %</th>
                            <th>Quiz Scores</th>
                            <th>Assignments</th>
                            <th>Final Project</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($progressRows as $row)
                            <tr>
                                <td class="fw-semibold">{{ $row['course']?->title ?? 'Deleted' }}</td>
                                <td>
                                    <span class="badge {{ $row['effective_status'] === 'active' ? 'bg-navy-accent' : ($row['effective_status'] === 'expired' ? 'bg-secondary' : 'bg-danger') }}">
                                        {{ ucfirst($row['effective_status']) }}
                                    </span>
                                    <div class="text-muted mt-1">{{ $row['enrollment']->expires_at->format('M d, Y H:i') }}</div>
                                </td>
                                <td>{{ $row['videos_watched'] }} / {{ $row['total_videos'] }}</td>
                                <td class="fw-bold text-navy">{{ $row['completion_percentage'] }}%</td>
                                <td>
                                    @if ($row['quiz_attempts_count'] > 0)
                                        Best: {{ $row['quiz_best'] }}% · Avg: {{ $row['quiz_avg'] }}%
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $row['assignment_status'] === 'Submitted' ? 'bg-success' : ($row['assignment_status'] === 'Pending' ? 'bg-warning text-dark' : 'bg-secondary') }}">
                                        {{ $row['assignment_status'] }}
                                    </span>
                                    <span class="text-muted ms-1">({{ $row['submitted_assignments'] }}/{{ $row['total_assignments'] }})</span>
                                </td>
                                <td>
                                    <span class="badge {{ in_array($row['final_project_status'], ['Submitted', 'Reviewed'], true) ? 'bg-success' : 'bg-black-badge' }}">
                                        {{ $row['final_project_status'] }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Read-Only Payment History --}}
    <div class="ma-card overflow-hidden">
        <div class="p-3 border-bottom">
            <h2 class="h6 fw-bold mb-0">Payment Request History ({{ $payments->count() }})</h2>
        </div>
        @if ($payments->isEmpty())
            <div class="p-4 text-center text-muted small">No payment requests submitted.</div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0 small">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Course</th>
                            <th>Final Amount</th>
                            <th>Promo</th>
                            <th>Status</th>
                            <th>Admin Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments as $p)
                            <tr>
                                <td class="text-muted text-nowrap">{{ $p->created_at->format('M d, Y H:i') }}</td>
                                <td class="fw-semibold">{{ $p->course?->title ?? 'Course' }}</td>
                                <td>{{ number_format((float) $p->final_price, 2) }} {{ $p->currency }}</td>
                                <td>{{ $p->promo_code_snapshot ?: '—' }}</td>
                                <td>
                                    <span class="badge {{ $p->isApproved() ? 'bg-navy-accent' : ($p->isPending() ? 'bg-warning text-dark' : 'bg-danger') }}">
                                        {{ ucfirst($p->status) }}
                                    </span>
                                </td>
                                <td class="text-muted">{{ $p->admin_note ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
