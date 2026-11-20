@extends('layouts.student')

@section('title', __('messages.my_courses'))

@section('content')
    @php
        $locale = $currentLocale ?? app()->getLocale();
    @endphp

    <div class="ma-card p-4 mb-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <span class="badge bg-navy-accent mb-2">{{ __('messages.student_portal') }}</span>
            <h1 class="h4 fw-bold mb-1">{{ __('messages.my_courses') }}</h1>
            <p class="text-muted small mb-0">Resume your active courses sequentially and track your path toward the 95% Final Project unlock.</p>
        </div>
        <a href="{{ route('student.courses.index') }}" class="btn btn-ma-outline btn-sm">
            <i class="bi bi-collection-play me-1" aria-hidden="true"></i>{{ __('messages.browse_courses') }}
        </a>
    </div>

    {{-- Active Courses --}}
    <h2 class="h6 fw-bold text-uppercase text-muted mb-3">Active Courses ({{ $activeCourses->count() }})</h2>
    @if ($activeCourses->isEmpty())
        <div class="ma-card p-5 text-center mb-4">
            <i class="bi bi-play-btn fs-1 text-muted d-block mb-2" aria-hidden="true"></i>
            <p class="text-muted mb-3">You do not have any active enrolled courses yet.</p>
            <a href="{{ route('student.courses.index') }}" class="btn btn-ma-primary btn-sm">{{ __('messages.browse_courses') }}</a>
        </div>
    @else
        <div class="row g-4 mb-5">
            @foreach ($activeCourses as $item)
                @php
                    $course = $item['course'];
                    $enrollment = $item['enrollment'];
                    $progress = $item['progress'];
                @endphp
                <div class="col-md-6 col-lg-4">
                    <article class="ma-course-card h-100 d-flex flex-column">
                        @if ($course->thumbnailUrl())
                            <img src="{{ $course->thumbnailUrl() }}" alt="{{ $course->localizedTitle($locale) }}" class="ma-course-thumb">
                        @else
                            <div class="ma-course-thumb p-4 text-center">
                                <i class="bi bi-play-circle-fill fs-1 opacity-75" aria-hidden="true"></i>
                            </div>
                        @endif

                        <div class="p-4 d-flex flex-column flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                <span class="badge bg-success"><i class="bi bi-unlock-fill me-1" aria-hidden="true"></i>{{ __('messages.unlocked') }}</span>
                                @if ($enrollment)
                                    <span class="badge bg-black-badge">{{ __('messages.days_remaining', ['days' => $enrollment->remainingDays()]) }}</span>
                                @else
                                    <span class="badge bg-navy-accent">{{ __('messages.super_admin_preview') }}</span>
                                @endif
                            </div>

                            <h3 class="h5 fw-bold mb-2">{{ $course->localizedTitle($locale) }}</h3>

                            <div class="small text-muted mb-2">
                                @if ($enrollment)
                                    {{ __('messages.expires_on', ['date' => $enrollment->expires_at->format('M d, Y')]) }}
                                @else
                                    Unrestricted Super Admin course inspection access
                                @endif
                            </div>

                            {{-- Progress Bar --}}
                            <div class="mb-3 mt-auto pt-2">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span class="fw-semibold">{{ __('messages.course_progress') }}</span>
                                    <span class="fw-bold text-navy">{{ $progress['completion_percentage'] }}%</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-navy-accent" role="progressbar" style="width: {{ min(100, $progress['completion_percentage']) }}%;" aria-valuenow="{{ $progress['completion_percentage'] }}" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <div class="small text-muted mt-1">
                                    {{ $progress['completed_lessons'] }}/{{ $progress['total_lessons'] }} lessons · {{ $progress['submitted_assignments'] }}/{{ $progress['total_assignments'] }} assignments
                                </div>
                            </div>

                            <a href="{{ route('student.courses.learn', $course) }}" class="btn btn-ma-primary w-100">
                                <i class="bi bi-play-fill me-1" aria-hidden="true"></i>{{ __('messages.continue_learning') }}
                            </a>
                        </div>
                    </article>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Expired / Revoked Courses (Progress Preserved!) --}}
    @if ($expiredCourses->isNotEmpty())
        <h2 class="h6 fw-bold text-uppercase text-muted mb-3">Expired / Locked Enrollments ({{ $expiredCourses->count() }})</h2>
        <div class="row g-4">
            @foreach ($expiredCourses as $item)
                @php
                    $course = $item['course'];
                    $enrollment = $item['enrollment'];
                    $progress = $item['progress'];
                @endphp
                <div class="col-md-6 col-lg-4">
                    <div class="ma-card p-4 h-100 d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge bg-secondary">
                                <i class="bi bi-lock-fill me-1" aria-hidden="true"></i>{{ ucfirst($enrollment->effectiveStatus()) }}
                            </span>
                            <span class="small text-muted">Progress saved: {{ $progress['completion_percentage'] }}%</span>
                        </div>
                        <h3 class="h6 fw-bold mb-2">{{ $course->localizedTitle($locale) }}</h3>
                        <p class="small text-muted mb-3 flex-grow-1">
                            Your previous watch progress and submissions are preserved. Renew your subscription to resume right where you left off.
                        </p>
                        <a href="{{ route('student.courses.subscribe', $course) }}" class="btn btn-ma-dark btn-sm w-100">
                            <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>{{ __('messages.renew_access') }}
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
