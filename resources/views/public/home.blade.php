@extends('layouts.public')

@section('title', __('messages.home'))

@section('content')
    @php
        $locale = $currentLocale ?? app()->getLocale();
        $academyName = \App\Models\AcademySetting::localized('academy_name', $locale, $academySettings ?? null) ?: 'Mash Academy';
        $tagline = \App\Models\AcademySetting::localized('tagline', $locale, $academySettings ?? null);
        $about = \App\Models\AcademySetting::localized('about', $locale, $academySettings ?? null);
        $vision = \App\Models\AcademySetting::localized('vision', $locale, $academySettings ?? null);
    @endphp

    {{-- Hero Section (Crisp Black + Deep Navy Blue) --}}
    <section class="ma-hero py-5">
        <div class="container py-lg-5 position-relative" style="z-index: 1;">
            <div class="row align-items-center gy-4">
                <div class="col-lg-8">
                    <span class="badge bg-navy-accent px-3 py-2 mb-3 fw-semibold">
                        <i class="bi bi-mortarboard-fill me-1" aria-hidden="true"></i>
                        {{ __('messages.online_learning_platform') }}
                    </span>
                    <h1 class="display-5 fw-bold mb-3 text-white">
                        {{ $academyName }}
                    </h1>
                    <p class="lead text-white-50 mb-4" style="max-width: 650px;">
                        {{ $tagline }}
                    </p>
                    <div class="d-flex flex-wrap gap-3">
                        @auth
                            <a href="{{ auth()->user()->dashboardRoute() }}" class="btn btn-ma-primary btn-lg px-4">
                                {{ __('messages.go_to_dashboard') }} <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
                            </a>
                        @else
                            <a href="{{ route('register') }}" class="btn btn-ma-primary btn-lg px-4">
                                {{ __('messages.create_student_account') }} <i class="bi bi-person-plus ms-1" aria-hidden="true"></i>
                            </a>
                        @endauth
                        <a href="{{ route('courses.index') }}" class="btn btn-outline-light btn-lg px-4">
                            {{ __('messages.explore_courses') }}
                        </a>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="p-4 rounded-4 bg-white bg-opacity-10 border border-white border-opacity-10">
                        <div class="small text-uppercase fw-semibold text-white-50 mb-3">{{ $academyName }}</div>
                        <ul class="list-unstyled mb-0 small d-flex flex-column gap-2 text-white">
                            <li><i class="bi bi-check-circle-fill text-primary me-2" aria-hidden="true"></i>Sequential video mastery &amp; drip-lock</li>
                            <li><i class="bi bi-check-circle-fill text-primary me-2" aria-hidden="true"></i>Quizzes &amp; flexible File / MCQ assignments</li>
                            <li><i class="bi bi-check-circle-fill text-primary me-2" aria-hidden="true"></i>95%-gated Final Project &amp; Certification</li>
                            <li><i class="bi bi-check-circle-fill text-primary me-2" aria-hidden="true"></i>Verified student enrollments</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Flash Messages --}}
    <div class="container mt-4">
        @include('partials.flash')
    </div>

    {{-- Dynamic Bilingual About & Vision Section --}}
    <section class="py-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="ma-card p-4 h-100">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="ma-icon-box ma-icon-black">
                                <i class="bi bi-building" aria-hidden="true"></i>
                            </span>
                            <h2 class="h5 fw-bold mb-0">{{ __('messages.about_academy') }} {{ $academyName }}</h2>
                        </div>
                        <p class="text-muted mb-0" style="white-space: pre-line; line-height: 1.75;">{{ $about }}</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ma-card p-4 h-100 border-start border-4" style="border-inline-start-color: #1e3a8a !important;">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="ma-icon-box ma-icon-navy">
                                <i class="bi bi-eye-fill" aria-hidden="true"></i>
                            </span>
                            <h2 class="h5 fw-bold mb-0">{{ __('messages.our_vision') }}</h2>
                        </div>
                        <p class="text-muted mb-0" style="white-space: pre-line; line-height: 1.75;">{{ $vision }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- 3 Featured Courses Section --}}
    <section class="ma-section-subtle py-5">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <span class="badge bg-navy-accent mb-2">{{ __('messages.featured') }}</span>
                    <h2 class="h3 fw-bold mb-1">{{ __('messages.featured_courses') }}</h2>
                    <p class="text-muted mb-0">{{ __('messages.featured_courses_subtitle') }}</p>
                </div>
                @if ($totalPublishedCourses > 0)
                    <a href="{{ route('courses.index') }}" class="btn btn-ma-outline btn-sm px-3">
                        {{ __('messages.view_all_courses') }} ({{ $totalPublishedCourses }})
                    </a>
                @endif
            </div>

            @if ($featuredCourses->isEmpty())
                <div class="ma-card p-5 text-center">
                    <i class="bi bi-journal-bookmark fs-1 text-muted mb-2 d-block" aria-hidden="true"></i>
                    <p class="text-muted mb-0">{{ __('messages.no_featured_courses') }}</p>
                </div>
            @else
                <div class="row g-4">
                    @foreach ($featuredCourses as $course)
                        <div class="col-md-6 col-lg-4">
                            <article class="ma-course-card h-100 d-flex flex-column">
                                @if ($course->thumbnailUrl())
                                    <img src="{{ $course->thumbnailUrl() }}" alt="{{ $course->localizedTitle($locale) }}" class="ma-course-thumb">
                                @else
                                    <div class="ma-course-thumb p-4 text-center">
                                        <div>
                                            <i class="bi bi-play-btn-fill fs-1 opacity-75 d-block mb-2" aria-hidden="true"></i>
                                            <span class="small fw-semibold opacity-75">{{ $academyName }}</span>
                                        </div>
                                    </div>
                                @endif

                                <div class="p-4 d-flex flex-column flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                        <span class="badge bg-navy-accent">{{ $course->formattedPrice() }}</span>
                                        <span class="badge bg-black-badge">
                                            <i class="bi bi-lock-fill me-1" aria-hidden="true"></i>{{ __('messages.locked_preview') }}
                                        </span>
                                    </div>

                                    <h3 class="h5 fw-bold mb-2">
                                        <a href="{{ route('courses.show', $course) }}" class="text-decoration-none" style="color: inherit;">
                                            {{ $course->localizedTitle($locale) }}
                                        </a>
                                    </h3>

                                    <p class="text-muted small mb-3 flex-grow-1">
                                        {{ $course->localizedShortDescription($locale) }}
                                    </p>

                                    <div class="d-flex flex-wrap gap-2 small text-muted mb-3 pt-2 border-top">
                                        <span><i class="bi bi-camera-video me-1" aria-hidden="true"></i>{{ __('messages.lessons_count', ['count' => $course->published_lessons_count]) }}</span>
                                        <span><i class="bi bi-patch-question me-1" aria-hidden="true"></i>{{ __('messages.quizzes_count', ['count' => $course->quizzes_count]) }}</span>
                                        <span><i class="bi bi-file-earmark-check me-1" aria-hidden="true"></i>{{ __('messages.assignments_count', ['count' => $course->assignments_count]) }}</span>
                                        @if ($course->finalProject && $course->finalProject->is_published)
                                            <span class="text-navy fw-semibold"><i class="bi bi-award-fill me-1" aria-hidden="true"></i>{{ __('messages.final_project_included') }}</span>
                                        @endif
                                    </div>

                                    <a href="{{ route('courses.show', $course) }}" class="btn btn-ma-primary w-100">
                                        {{ __('messages.view_subscribe') }}
                                    </a>
                                </div>
                            </article>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    {{-- Course Packages / Bundles Section --}}
    @if (isset($packages) && $packages->isNotEmpty())
        <section class="py-5">
            <div class="container">
                <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                    <div>
                        <span class="badge bg-black-badge mb-2">
                            <i class="bi bi-box-seam-fill me-1" aria-hidden="true"></i>{{ __('messages.packages') }}
                        </span>
                        <h2 class="h3 fw-bold mb-1">{{ __('messages.course_packages') }}</h2>
                        <p class="text-muted mb-0">{{ __('messages.course_packages_subtitle') }}</p>
                    </div>
                </div>

                <div class="row g-4">
                    @foreach ($packages as $package)
                        @php $savings = $package->savingsAmount(); @endphp
                        <div class="col-md-6 col-lg-4">
                            <article class="ma-course-card h-100 d-flex flex-column">
                                @if ($package->thumbnailUrl())
                                    <img src="{{ $package->thumbnailUrl() }}" alt="{{ $package->localizedTitle($locale) }}" class="ma-course-thumb">
                                @else
                                    <div class="ma-course-thumb p-4 text-center">
                                        <i class="bi bi-box-seam-fill fs-1 opacity-75 d-block mb-1" aria-hidden="true"></i>
                                        <span class="small fw-semibold opacity-75">{{ __('messages.courses_included_count', ['count' => $package->courses_count]) }}</span>
                                    </div>
                                @endif

                                <div class="p-4 d-flex flex-column flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                        <span class="badge bg-navy-accent">{{ $package->formattedPrice() }}</span>
                                        @if ($savings > 0)
                                            <span class="badge bg-success">{{ __('messages.bundle_savings', ['amount' => number_format($savings, 0).' '.$package->currency]) }}</span>
                                        @else
                                            <span class="badge bg-black-badge">{{ __('messages.courses_included_count', ['count' => $package->courses_count]) }}</span>
                                        @endif
                                    </div>

                                    <h3 class="h5 fw-bold mb-2">
                                        <a href="{{ route('packages.show', $package) }}" class="text-decoration-none" style="color: inherit;">
                                            {{ $package->localizedTitle($locale) }}
                                        </a>
                                    </h3>

                                    <p class="text-muted small mb-3">{{ $package->localizedShortDescription($locale) }}</p>

                                    <ul class="list-unstyled small text-muted mb-3 pt-2 border-top flex-grow-1 d-flex flex-column gap-1">
                                        @foreach ($package->courses->take(4) as $pkgCourse)
                                            <li><i class="bi bi-check2-circle text-navy me-1" aria-hidden="true"></i>{{ $pkgCourse->localizedTitle($locale) }}</li>
                                        @endforeach
                                        @if ($package->courses->count() > 4)
                                            <li class="fw-semibold">+{{ $package->courses->count() - 4 }} more courses</li>
                                        @endif
                                    </ul>

                                    <div class="d-grid gap-2">
                                        @if (auth()->check() && (auth()->user()->isStudent() || auth()->user()->isSuperAdmin()))
                                            <a href="{{ route('student.packages.subscribe', $package) }}" class="btn btn-ma-primary">
                                                <i class="bi bi-box-seam me-1" aria-hidden="true"></i>{{ __('messages.subscribe_to_package') }}
                                            </a>
                                        @else
                                            <a href="{{ route('packages.show', $package) }}" class="btn btn-ma-primary">
                                                <i class="bi bi-box-seam me-1" aria-hidden="true"></i>{{ __('messages.subscribe_to_package') }}
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </article>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Optional Free Demo Videos Showcase --}}
    @if (! empty($demoVideosEnabled) && isset($demoCourses) && $demoCourses->isNotEmpty())
        <section class="ma-section-subtle py-5">
            <div class="container">
                <div class="mb-4">
                    <span class="badge bg-navy-accent mb-2">
                        <i class="bi bi-play-circle-fill me-1" aria-hidden="true"></i>{{ __('messages.free_demo_videos') }}
                    </span>
                    <h2 class="h3 fw-bold mb-1">{{ __('messages.free_demo_videos') }}</h2>
                    <p class="text-muted mb-0">{{ __('messages.free_demo_videos_subtitle') }}</p>
                </div>

                <div class="row g-4">
                    @foreach ($demoCourses as $demoCourse)
                        <div class="col-md-6 col-lg-4">
                            <div class="ma-card p-4 h-100 d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge bg-black-badge">Free Preview</span>
                                    <span class="small text-muted">{{ $demoCourse->creator?->name ?? 'Mash Academy' }}</span>
                                </div>
                                <h3 class="h6 fw-bold mb-2">{{ $demoCourse->localizedTitle($locale) }}</h3>
                                <p class="small text-muted mb-3 flex-grow-1">{{ $demoCourse->localizedShortDescription($locale) }}</p>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-ma-primary btn-sm flex-grow-1" data-bs-toggle="modal" data-bs-target="#homeDemoModal{{ $demoCourse->id }}">
                                        <i class="bi bi-play-fill me-1" aria-hidden="true"></i>{{ __('messages.watch_free_demo') }}
                                    </button>
                                    <a href="{{ route('courses.show', $demoCourse) }}" class="btn btn-ma-outline btn-sm">Details</a>
                                </div>
                            </div>
                        </div>

                        <div class="modal fade" id="homeDemoModal{{ $demoCourse->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h4 class="modal-title h6 fw-bold">{{ $demoCourse->localizedTitle($locale) }} — Free Preview</h4>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-0">
                                        <div class="ma-video-shell">
                                            @if ($demoCourse->demo_video_type === 'upload' && $demoCourse->demoUploadUrl())
                                                <video controls playsinline preload="metadata" class="ma-video-player">
                                                    <source src="{{ $demoCourse->demoUploadUrl() }}" type="video/mp4">
                                                </video>
                                            @elseif ($demoCourse->demoEmbedUrl())
                                                <iframe src="{{ $demoCourse->demoEmbedUrl() }}" class="ma-video-player border-0" allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture;" allowfullscreen></iframe>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Meet Our Instructors Section --}}
    @if (isset($instructors) && $instructors->isNotEmpty())
        <section id="meet-our-instructors" class="py-5">
            <div class="container">
                <div class="text-center mb-5">
                    <span class="badge bg-navy-accent px-3 py-2 mb-2">{{ __('messages.instructor') }}</span>
                    <h2 class="h3 fw-bold mb-2">{{ __('messages.meet_our_instructors') }}</h2>
                    <p class="text-muted mb-0 mx-auto" style="max-width: 640px;">
                        {{ __('messages.meet_our_instructors_subtitle') }}
                    </p>
                </div>

                <div class="row g-4 justify-content-center">
                    @foreach ($instructors as $instructor)
                        <div class="col-md-6 col-lg-4">
                            <article class="ma-card p-4 h-100 d-flex flex-column text-center">
                                <div class="mb-3">
                                    @if ($instructor->avatarUrl())
                                        <img src="{{ $instructor->avatarUrl() }}"
                                             alt="{{ $instructor->name }}"
                                             class="rounded-circle object-fit-cover border shadow-sm"
                                             style="width: 108px; height: 108px;">
                                    @else
                                        <div class="rounded-circle bg-navy text-white d-inline-flex align-items-center justify-content-center fw-bold fs-2 shadow-sm"
                                             style="width: 108px; height: 108px;">
                                            {{ strtoupper(mb_substr($instructor->name, 0, 1)) }}
                                        </div>
                                    @endif
                                </div>

                                <h3 class="h5 fw-bold mb-1">{{ $instructor->name }}</h3>
                                <div class="small fw-semibold text-navy mb-2">
                                    {{ $instructor->localizedInstructorTitle($locale) }}
                                </div>

                                @if ($instructor->localizedInstructorBio($locale))
                                    <p class="small text-muted mb-3 flex-grow-1" style="line-height: 1.7;">
                                        {{ $instructor->localizedInstructorBio($locale) }}
                                    </p>
                                @else
                                    <div class="flex-grow-1"></div>
                                @endif

                                <div class="pt-3 border-top d-flex flex-column gap-2">
                                    <div class="d-flex justify-content-center align-items-center gap-2 flex-wrap">
                                        <span class="badge bg-black-badge">
                                            <i class="bi bi-journal-bookmark me-1" aria-hidden="true"></i>{{ __('messages.courses_taught', ['count' => $instructor->published_courses_count ?? 0]) }}
                                        </span>
                                        @if ($instructor->linkedin_url)
                                            <a href="{{ $instructor->linkedin_url }}" target="_blank" rel="noopener noreferrer"
                                               class="btn btn-ma-outline btn-sm py-0 px-2" aria-label="{{ $instructor->name }} LinkedIn">
                                                <i class="bi bi-linkedin" aria-hidden="true"></i>
                                            </a>
                                        @endif
                                        @if ($instructor->github_url)
                                            <a href="{{ $instructor->github_url }}" target="_blank" rel="noopener noreferrer"
                                               class="btn btn-ma-outline btn-sm py-0 px-2" aria-label="{{ $instructor->name }} GitHub">
                                                <i class="bi bi-github" aria-hidden="true"></i>
                                            </a>
                                        @endif
                                    </div>

                                    @if ($instructor->portfolio_url)
                                        <a href="{{ $instructor->portfolio_url }}"
                                           target="_blank"
                                           rel="noopener noreferrer"
                                           class="btn btn-ma-primary btn-sm w-100 mt-1">
                                            <i class="bi bi-globe2 me-1" aria-hidden="true"></i>{{ __('messages.view_portfolio') }}
                                            <i class="bi bi-box-arrow-up-right ms-1 small" aria-hidden="true"></i>
                                        </a>
                                    @endif
                                </div>
                            </article>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Platform Policy Banner --}}
    <section class="ma-section-subtle py-5">
        <div class="container">
            <div class="ma-card p-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <h3 class="h6 fw-bold mb-1">{{ __('messages.policy_agreement_title') }}</h3>
                    <p class="text-muted small mb-0">
                        {{ __('messages.policy_agreement_desc') }}
                        @if ($activePolicy)
                            (v{{ $activePolicy->version }})
                        @endif
                    </p>
                </div>
                <a href="{{ route('policy.show') }}" class="btn btn-ma-outline btn-sm px-3">
                    {{ __('messages.read_active_policy') }} <i class="bi bi-file-earmark-text ms-1" aria-hidden="true"></i>
                </a>
            </div>
        </div>
    </section>
@endsection
