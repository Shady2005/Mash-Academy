@extends('layouts.public')

@section('title', $package->localizedTitle($currentLocale ?? app()->getLocale()))

@section('content')
    @php
        $locale = $currentLocale ?? app()->getLocale();
        $savings = $package->savingsAmount();
        $individualTotal = $package->individualCoursesTotal();
    @endphp

    <section class="ma-hero py-5">
        <div class="container position-relative" style="z-index: 1;">
            <a href="{{ route('courses.index') }}" class="btn btn-outline-light btn-sm mb-3">
                <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('messages.back_to_courses') }}
            </a>
            <div class="row align-items-center gy-4">
                <div class="col-lg-8">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge bg-navy-accent">
                            <i class="bi bi-box-seam-fill me-1" aria-hidden="true"></i>{{ __('messages.courses_included_count', ['count' => $package->courses->count()]) }}
                        </span>
                        @if ($savings > 0)
                            <span class="badge bg-success">
                                {{ __('messages.bundle_savings', ['amount' => number_format($savings, 0).' '.$package->currency]) }}
                            </span>
                        @endif
                    </div>
                    <h1 class="h2 fw-bold text-white mb-2">{{ $package->localizedTitle($locale) }}</h1>
                    <p class="text-white-50 mb-0">{{ $package->localizedShortDescription($locale) }}</p>
                </div>
                <div class="col-lg-4">
                    <div class="ma-card p-4 text-body">
                        <div class="small text-muted text-uppercase fw-semibold mb-1">{{ __('messages.price') }}</div>
                        <div class="d-flex align-items-baseline gap-2 mb-3">
                            <span class="display-6 fw-bold text-navy">{{ $package->formattedPrice() }}</span>
                            @if ($savings > 0)
                                <span class="text-muted text-decoration-line-through small">{{ number_format($individualTotal, 0) }} {{ $package->currency }}</span>
                            @endif
                        </div>

                        @if (auth()->check() && (auth()->user()->isStudent() || auth()->user()->isSuperAdmin()))
                            <a href="{{ route('student.packages.subscribe', $package) }}" class="btn btn-ma-primary w-100">
                                <i class="bi bi-qr-code me-1" aria-hidden="true"></i>{{ __('messages.subscribe_to_package') }}
                            </a>
                        @elseif (auth()->check())
                            <div class="alert alert-secondary small mb-0">
                                Signed in as staff ({{ auth()->user()->role->shortLabel() }}).
                            </div>
                        @else
                            <a href="{{ route('register') }}" class="btn btn-ma-primary w-100 mb-2">
                                {{ __('messages.create_student_account') }}
                            </a>
                            <a href="{{ route('login') }}" class="btn btn-ma-outline w-100 btn-sm">
                                {{ __('messages.sign_in') }}
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-8">
                    @if ($package->thumbnailUrl())
                        <div class="mb-4">
                            <img src="{{ $package->thumbnailUrl() }}" alt="{{ $package->localizedTitle($locale) }}" class="img-fluid rounded-4 border w-100" style="max-height: 360px; object-fit: cover;">
                        </div>
                    @endif

                    <div class="ma-card p-4 mb-4">
                        <h2 class="h5 fw-bold mb-3">{{ __('messages.course_details') }}</h2>
                        <div class="text-muted" style="white-space: pre-line; line-height: 1.8;">{{ $package->localizedDescription($locale) }}</div>
                    </div>

                    <div class="ma-card p-4">
                        <h2 class="h5 fw-bold mb-3">
                            <i class="bi bi-journal-bookmark-fill me-2 text-navy" aria-hidden="true"></i>{{ __('messages.included_courses') }} ({{ $package->courses->count() }})
                        </h2>
                        <div class="list-group list-group-flush">
                            @foreach ($package->courses as $course)
                                @php $alreadyEnrolled = in_array($course->id, $enrolledCourseIds, true); @endphp
                                <div class="list-group-item px-0 py-3 d-flex flex-wrap justify-content-between align-items-center gap-3 bg-transparent">
                                    <div>
                                        <div class="fw-bold">
                                            <a href="{{ route('courses.show', $course) }}" class="text-decoration-none" style="color: inherit;">
                                                {{ $course->localizedTitle($locale) }}
                                            </a>
                                        </div>
                                        <div class="small text-muted mt-1">
                                            {{ __('messages.lessons_count', ['count' => $course->published_lessons_count]) }} ·
                                            {{ __('messages.quizzes_count', ['count' => $course->quizzes_count]) }} ·
                                            {{ __('messages.assignments_count', ['count' => $course->assignments_count]) }}
                                        </div>
                                    </div>
                                    <div class="text-end text-nowrap">
                                        @if ($alreadyEnrolled)
                                            <span class="badge bg-success me-2">{{ __('messages.unlocked') }}</span>
                                        @endif
                                        <span class="small text-muted">{{ $course->formattedPrice() }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="ma-card p-4 border-start border-4" style="border-inline-start-color: #1e3a8a !important;">
                        <h3 class="h6 fw-bold mb-2">How Package Unlocking Works</h3>
                        <ul class="small text-muted mb-0 ps-3 d-flex flex-column gap-2">
                            <li>Transfer the bundle price via InstaPay QR (promo codes supported) and upload your receipt screenshot.</li>
                            <li>Once approved by Super Admin, <strong>every course in this package unlocks automatically</strong> inside your regular <strong>My Courses</strong> view.</li>
                            <li>Each course keeps full sequential 100% video tracking, quizzes, flexible assignments, 95% Final Project gate, and certificate eligibility.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
