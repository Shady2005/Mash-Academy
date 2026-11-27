@extends('layouts.student')

@section('title', __('messages.dashboard'))

@section('content')
    <div class="ma-card p-4 mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <span class="badge bg-navy-accent mb-2">
                    {{ $student->isSuperAdmin() ? __('messages.super_admin_preview') : __('messages.student_portal') }}
                </span>
                <h1 class="h4 fw-bold mb-1">{{ __('messages.welcome_student', ['name' => $student->name]) }}</h1>
                <p class="text-muted small mb-0">
                    @if ($student->isSuperAdmin())
                        You are browsing the Student Portal with unrestricted Super Admin preview access across all courses, checkout pages, assessments, and certificates.
                    @else
                        {{ __('messages.all_courses_locked_banner') }}
                    @endif
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @if ($student->isSuperAdmin())
                    <a href="{{ route('student.my-courses.index') }}" class="btn btn-ma-primary btn-sm">
                        <i class="bi bi-play-btn me-1" aria-hidden="true"></i>{{ __('messages.my_courses') }}
                    </a>
                @endif
                <a href="{{ route('student.courses.index') }}" class="btn {{ $student->isSuperAdmin() ? 'btn-ma-outline' : 'btn-ma-primary' }} btn-sm">
                    <i class="bi bi-collection-play me-1" aria-hidden="true"></i>{{ __('messages.browse_courses') }}
                </a>
                <a href="{{ route('student.payments.index') }}" class="btn btn-ma-dark btn-sm">
                    <i class="bi bi-receipt me-1" aria-hidden="true"></i>{{ __('messages.my_payments') }}
                    @if ($pendingPaymentsCount > 0)
                        <span class="badge bg-warning text-dark ms-1">{{ $pendingPaymentsCount }}</span>
                    @endif
                </a>
                <a href="{{ route('account.password.edit') }}" class="btn btn-ma-outline btn-sm">
                    <i class="bi bi-key me-1" aria-hidden="true"></i>{{ __('messages.change_password') }}
                </a>
            </div>
        </div>
    </div>

    {{-- Global Site-Wide Announcements Card --}}
    @if (isset($globalAnnouncements) && $globalAnnouncements->isNotEmpty())
        @php $dashLocale = $currentLocale ?? app()->getLocale(); @endphp
        <div class="ma-card p-4 mb-4">
            <h2 class="h6 fw-bold text-uppercase text-muted mb-3">
                <i class="bi bi-megaphone-fill text-navy me-1" aria-hidden="true"></i>{{ __('messages.global_announcements') }} ({{ $globalAnnouncements->count() }})
            </h2>
            <div class="d-flex flex-column gap-2">
                @foreach ($globalAnnouncements as $ann)
                    <div class="border rounded-3 p-3 {{ $ann->isImportant() ? 'border-danger border-opacity-50' : '' }}" style="background-color: var(--ma-surface-subtle);">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
                            <div class="d-flex align-items-center gap-2">
                                @if ($ann->isImportant())
                                    <span class="badge bg-danger">Important</span>
                                @else
                                    <span class="badge bg-navy-accent">Announcement</span>
                                @endif
                                <strong class="small">{{ $ann->localizedTitle($dashLocale) }}</strong>
                            </div>
                            <span class="text-muted" style="font-size: 0.74rem;">
                                {{ $ann->author?->name ?? 'Mash Academy' }} · {{ $ann->created_at->format('M d, Y') }}
                            </span>
                        </div>
                        <p class="small text-muted mb-0" style="white-space: pre-line;">{{ $ann->localizedBody($dashLocale) }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Active Unlocked Courses --}}
    @if ($activeEnrollments->isNotEmpty())
        <div class="ma-card p-4 mb-4">
            <h2 class="h6 fw-bold text-uppercase text-muted mb-3">Your Active Unlocked Courses ({{ $activeEnrollments->count() }})</h2>
            <div class="row g-3">
                @foreach ($activeEnrollments as $enrollment)
                    @if ($enrollment->course)
                        <div class="col-md-6">
                            <div class="border rounded-3 p-3 d-flex justify-content-between align-items-center gap-3">
                                <div>
                                    <div class="fw-bold">{{ $enrollment->course->localizedTitle($currentLocale ?? app()->getLocale()) }}</div>
                                    <div class="small text-success fw-semibold mt-1">
                                        {{ __('messages.expires_on', ['date' => $enrollment->expires_at->format('M d, Y')]) }}
                                        · {{ __('messages.days_remaining', ['days' => $enrollment->remainingDays()]) }}
                                    </div>
                                </div>
                                <a href="{{ route('student.courses.learn', $enrollment->course) }}" class="btn btn-ma-primary btn-sm text-nowrap">
                                    {{ __('messages.open_course') }}
                                </a>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-md-6">
            <div class="ma-card p-4 h-100">
                <h2 class="h6 fw-bold text-uppercase text-muted mb-3">{{ __('messages.your_account_details') }}</h2>
                <dl class="row mb-0 small">
                    <dt class="col-sm-4 text-muted">Full Name</dt>
                    <dd class="col-sm-8 fw-semibold">{{ $student->name }}</dd>

                    <dt class="col-sm-4 text-muted">Email</dt>
                    <dd class="col-sm-8">{{ $student->email }}</dd>

                    <dt class="col-sm-4 text-muted">Phone</dt>
                    <dd class="col-sm-8">{{ $student->phone ?: 'Not provided' }}</dd>

                    <dt class="col-sm-4 text-muted">Role</dt>
                    <dd class="col-sm-8"><span class="badge bg-navy">{{ $student->role->label() }}</span></dd>
                </dl>
            </div>
        </div>

        <div class="col-md-6">
            <div class="ma-card p-4 h-100">
                <h2 class="h6 fw-bold text-uppercase text-muted mb-3">{{ __('messages.policy_consent_record') }}</h2>
                @if ($latestAcceptance)
                    <div class="d-flex align-items-start gap-3">
                        <span class="ma-icon-box ma-icon-navy">
                            <i class="bi bi-file-earmark-check" aria-hidden="true"></i>
                        </span>
                        <div class="small">
                            <div class="fw-semibold">{{ $latestAcceptance->policy?->title ?? 'Platform Policy' }}</div>
                            <div class="text-muted mt-1">
                                Accepted Version <strong>{{ $latestAcceptance->policy_version }}</strong>
                                on {{ $latestAcceptance->accepted_at->format('M d, Y \a\t H:i') }}.
                            </div>
                            <a href="{{ route('policy.show') }}" class="d-inline-block mt-2 text-decoration-none text-navy fw-semibold">
                                {{ __('messages.read_active_policy') }} <i class="bi bi-arrow-right" aria-hidden="true"></i>
                            </a>
                        </div>
                    </div>
                @else
                    <p class="text-muted small mb-0">Policy acceptance record not found.</p>
                @endif
            </div>
        </div>
    </div>
@endsection
