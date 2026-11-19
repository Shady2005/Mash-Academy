@extends('layouts.student')

@section('title', 'Student Dashboard')

@section('content')
    <div class="ma-card p-4 mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <span class="badge bg-teal mb-2">Student Portal</span>
                <h1 class="h4 fw-bold mb-1">Welcome, {{ $student->name }}</h1>
                <p class="text-muted small mb-0">
                    Your {{ $academySettings['academy_name'] ?? 'Mash Academy' }} student account is active and verified against our Platform Policy.
                </p>
            </div>
            <a href="{{ route('account.password.edit') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-key me-1" aria-hidden="true"></i>Account Security
            </a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="ma-card p-4 h-100">
                <h2 class="h6 fw-bold text-uppercase text-muted mb-3">Your Account Details</h2>
                <dl class="row mb-0 small">
                    <dt class="col-sm-4 text-muted">Full Name</dt>
                    <dd class="col-sm-8 fw-semibold">{{ $student->name }}</dd>

                    <dt class="col-sm-4 text-muted">Email</dt>
                    <dd class="col-sm-8">{{ $student->email }}</dd>

                    <dt class="col-sm-4 text-muted">Phone</dt>
                    <dd class="col-sm-8">{{ $student->phone ?: 'Not provided' }}</dd>

                    <dt class="col-sm-4 text-muted">Role</dt>
                    <dd class="col-sm-8"><span class="badge bg-secondary">{{ $student->role->label() }}</span></dd>
                </dl>
            </div>
        </div>

        <div class="col-md-6">
            <div class="ma-card p-4 h-100">
                <h2 class="h6 fw-bold text-uppercase text-muted mb-3">Policy Consent Record</h2>
                @if ($latestAcceptance)
                    <div class="d-flex align-items-start gap-3">
                        <span class="ma-icon-box ma-icon-teal">
                            <i class="bi bi-file-earmark-check" aria-hidden="true"></i>
                        </span>
                        <div class="small">
                            <div class="fw-semibold">{{ $latestAcceptance->policy?->title ?? 'Platform Policy' }}</div>
                            <div class="text-muted mt-1">
                                Accepted Version <strong>{{ $latestAcceptance->policy_version }}</strong>
                                on {{ $latestAcceptance->accepted_at->format('M d, Y \a\t H:i') }}.
                            </div>
                            <a href="{{ route('policy.show') }}" class="d-inline-block mt-2 text-decoration-none">
                                View Active Platform Policy <i class="bi bi-arrow-right" aria-hidden="true"></i>
                            </a>
                        </div>
                    </div>
                @else
                    <p class="text-muted small mb-0">Policy acceptance record not found.</p>
                @endif
            </div>
        </div>
    </div>

    <div class="ma-card p-4 mt-4 border-start border-4 border-primary">
        <div class="d-flex align-items-start gap-3">
            <span class="ma-icon-box ma-icon-navy">
                <i class="bi bi-lock" aria-hidden="true"></i>
            </span>
            <div>
                <h2 class="h6 fw-bold mb-1">Course Catalog &amp; Learning Workspace</h2>
                <p class="text-muted small mb-0">
                    Your student dashboard shell is ready (Phase 1 Foundation). Course browsing, subscriptions, and sequential video lessons will become available here in upcoming phases.
                </p>
            </div>
        </div>
    </div>
@endsection
