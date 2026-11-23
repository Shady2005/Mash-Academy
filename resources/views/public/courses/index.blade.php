@extends('layouts.public')

@section('title', __('messages.course_catalog'))

@section('content')
    @php
        $locale = $currentLocale ?? app()->getLocale();
        $academyName = \App\Models\AcademySetting::localized('academy_name', $locale, $academySettings ?? null) ?: 'Mash Academy';
    @endphp

    <section class="py-5">
        <div class="container">
            <div class="mb-4 pb-3 border-bottom d-flex flex-wrap justify-content-between align-items-end gap-3">
                <div>
                    <span class="badge bg-navy-accent mb-2">{{ $academyName }}</span>
                    <h1 class="h2 fw-bold mb-1">{{ __('messages.course_catalog') }}</h1>
                    <p class="text-muted mb-0">{{ __('messages.course_catalog_subtitle') }}</p>
                </div>
            </div>

            @if ($courses->isEmpty())
                <div class="ma-card p-5 text-center">
                    <i class="bi bi-journal-x fs-1 text-muted d-block mb-2" aria-hidden="true"></i>
                    <p class="text-muted mb-0">{{ __('messages.no_courses_published') }}</p>
                </div>
            @else
                <div class="row g-4">
                    @foreach ($courses as $course)
                        <div class="col-md-6 col-lg-4">
                            <article class="ma-course-card h-100 d-flex flex-column">
                                @if ($course->thumbnailUrl())
                                    <img src="{{ $course->thumbnailUrl() }}" alt="{{ $course->localizedTitle($locale) }}" class="ma-course-thumb">
                                @else
                                    <div class="ma-course-thumb p-4 text-center">
                                        <i class="bi bi-play-btn-fill fs-1 opacity-75" aria-hidden="true"></i>
                                    </div>
                                @endif

                                <div class="p-4 d-flex flex-column flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                        <span class="badge bg-navy-accent">{{ $course->formattedPrice() }}</span>
                                        <span class="badge bg-black-badge">
                                            <i class="bi bi-lock-fill me-1" aria-hidden="true"></i>{{ __('messages.locked_by_default') }}
                                        </span>
                                    </div>

                                    <h2 class="h5 fw-bold mb-2">{{ $course->localizedTitle($locale) }}</h2>
                                    <p class="text-muted small mb-3 flex-grow-1">{{ $course->localizedShortDescription($locale) }}</p>

                                    <div class="d-flex flex-wrap gap-2 small text-muted mb-3 pt-2 border-top">
                                        <span><i class="bi bi-camera-video me-1" aria-hidden="true"></i>{{ __('messages.lessons_count', ['count' => $course->published_lessons_count]) }}</span>
                                        <span><i class="bi bi-patch-question me-1" aria-hidden="true"></i>{{ __('messages.quizzes_count', ['count' => $course->quizzes_count]) }}</span>
                                        <span><i class="bi bi-file-earmark-check me-1" aria-hidden="true"></i>{{ __('messages.assignments_count', ['count' => $course->assignments_count]) }}</span>
                                    </div>

                                    <a href="{{ route('courses.show', $course) }}" class="btn btn-ma-primary w-100">
                                        {{ __('messages.view_subscribe') }}
                                    </a>
                                </div>
                            </article>
                        </div>
                    @endforeach
                </div>

                @if ($courses->hasPages())
                    <div class="mt-4">{{ $courses->links() }}</div>
                @endif
            @endif

            {{-- Published Course Packages / Bundles --}}
            @if (isset($packages) && $packages->isNotEmpty())
                <div class="mt-5 pt-4 border-top">
                    <div class="mb-4">
                        <span class="badge bg-black-badge mb-2">
                            <i class="bi bi-box-seam-fill me-1" aria-hidden="true"></i>{{ __('messages.packages') }}
                        </span>
                        <h2 class="h3 fw-bold mb-1">{{ __('messages.course_packages') }}</h2>
                        <p class="text-muted mb-0">{{ __('messages.course_packages_subtitle') }}</p>
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
                                            <i class="bi bi-box-seam-fill fs-1 opacity-75" aria-hidden="true"></i>
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

                                        <h3 class="h5 fw-bold mb-2">{{ $package->localizedTitle($locale) }}</h3>
                                        <p class="text-muted small mb-3">{{ $package->localizedShortDescription($locale) }}</p>

                                        <ul class="list-unstyled small text-muted mb-3 pt-2 border-top flex-grow-1 d-flex flex-column gap-1">
                                            @foreach ($package->courses as $pkgCourse)
                                                <li><i class="bi bi-check2-circle text-navy me-1" aria-hidden="true"></i>{{ $pkgCourse->localizedTitle($locale) }}</li>
                                            @endforeach
                                        </ul>

                                        <a href="{{ route('packages.show', $package) }}" class="btn btn-ma-primary w-100">
                                            <i class="bi bi-box-seam me-1" aria-hidden="true"></i>{{ __('messages.subscribe_to_package') }}
                                        </a>
                                    </div>
                                </article>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </section>
@endsection
