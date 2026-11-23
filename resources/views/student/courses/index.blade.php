@extends('layouts.student')

@section('title', __('messages.browse_courses'))

@section('content')
    @php
        $locale = $currentLocale ?? app()->getLocale();
    @endphp

    <div class="ma-card p-4 mb-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <span class="badge bg-navy-accent mb-2">{{ __('messages.student_portal') }}</span>
            <h1 class="h4 fw-bold mb-1">{{ __('messages.browse_courses') }}</h1>
            <p class="text-muted small mb-0">{{ __('messages.all_courses_locked_banner') }}</p>
        </div>
        <a href="{{ route('student.payments.index') }}" class="btn btn-ma-outline btn-sm">
            <i class="bi bi-receipt me-1" aria-hidden="true"></i>{{ __('messages.my_payments') }}
        </a>
    </div>

    @if ($courses->isEmpty())
        <div class="ma-card p-5 text-center">
            <p class="text-muted mb-0">{{ __('messages.no_courses_published') }}</p>
        </div>
    @else
        <div class="row g-4">
            @foreach ($courses as $course)
                @php
                    $isSuperAdmin = auth()->user()->isSuperAdmin();
                    $enrollment = $activeEnrollments->get($course->id);
                    $isPending = in_array($course->id, $pendingCourseIds, true);
                @endphp
                <div class="col-md-6 col-lg-4">
                    <article class="ma-course-card h-100 d-flex flex-column">
                        @if ($course->thumbnailUrl())
                            <img src="{{ $course->thumbnailUrl() }}" alt="{{ $course->localizedTitle($locale) }}" class="ma-course-thumb">
                        @else
                            <div class="ma-course-thumb p-4 text-center">
                                <i class="bi {{ ($enrollment || $isSuperAdmin) ? 'bi-unlock-fill' : 'bi-lock-fill' }} fs-1 opacity-75" aria-hidden="true"></i>
                            </div>
                        @endif

                        <div class="p-4 d-flex flex-column flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                <span class="badge bg-navy-accent">{{ $course->formattedPrice() }}</span>

                                @if ($isSuperAdmin)
                                    <span class="badge bg-navy">
                                        <i class="bi bi-shield-check me-1" aria-hidden="true"></i>{{ __('messages.super_admin_preview') }}
                                    </span>
                                @elseif ($enrollment)
                                    <span class="badge bg-success">
                                        <i class="bi bi-unlock-fill me-1" aria-hidden="true"></i>{{ __('messages.unlocked') }}
                                    </span>
                                @elseif ($isPending)
                                    <span class="badge bg-warning text-dark">
                                        <i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>{{ __('messages.payment_pending') }}
                                    </span>
                                @else
                                    <span class="badge bg-black-badge">
                                        <i class="bi bi-lock-fill me-1" aria-hidden="true"></i>{{ __('messages.locked_preview') }}
                                    </span>
                                @endif
                            </div>

                            <h2 class="h5 fw-bold mb-2">{{ $course->localizedTitle($locale) }}</h2>
                            <p class="text-muted small mb-3 flex-grow-1">{{ $course->localizedShortDescription($locale) }}</p>

                            @if ($enrollment)
                                <div class="small text-success fw-semibold mb-3">
                                    <i class="bi bi-calendar-check me-1" aria-hidden="true"></i>
                                    {{ __('messages.expires_on', ['date' => $enrollment->expires_at->format('M d, Y')]) }}
                                    · {{ __('messages.days_remaining', ['days' => $enrollment->remainingDays()]) }}
                                </div>
                            @endif

                            <div class="d-flex flex-wrap gap-2 small text-muted mb-3 pt-2 border-top">
                                <span><i class="bi bi-camera-video me-1" aria-hidden="true"></i>{{ __('messages.lessons_count', ['count' => $course->published_lessons_count]) }}</span>
                                <span><i class="bi bi-patch-question me-1" aria-hidden="true"></i>{{ __('messages.quizzes_count', ['count' => $course->quizzes_count]) }}</span>
                                <span><i class="bi bi-file-earmark-check me-1" aria-hidden="true"></i>{{ __('messages.assignments_count', ['count' => $course->assignments_count]) }}</span>
                            </div>

                            @if ($isSuperAdmin)
                                <div class="d-grid gap-2">
                                    <a href="{{ route('student.courses.learn', $course) }}" class="btn btn-ma-primary">
                                        <i class="bi bi-play-circle me-1" aria-hidden="true"></i>{{ __('messages.open_course') }}
                                    </a>
                                    <a href="{{ route('student.courses.subscribe', $course) }}" class="btn btn-ma-outline btn-sm">
                                        <i class="bi bi-qr-code me-1" aria-hidden="true"></i>{{ __('messages.subscribe_to_course') }} (Preview)
                                    </a>
                                </div>
                            @elseif ($enrollment)
                                <a href="{{ route('student.courses.learn', $course) }}" class="btn btn-ma-primary w-100">
                                    <i class="bi bi-play-circle me-1" aria-hidden="true"></i>{{ __('messages.open_course') }}
                                </a>
                            @elseif ($isPending)
                                <a href="{{ route('student.payments.index') }}" class="btn btn-ma-outline w-100">
                                    <i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>{{ __('messages.payment_pending') }}
                                </a>
                            @else
                                <div class="d-grid gap-2">
                                    <a href="{{ route('student.courses.subscribe', $course) }}" class="btn btn-ma-primary">
                                        <i class="bi bi-qr-code me-1" aria-hidden="true"></i>{{ __('messages.subscribe_to_course') }}
                                    </a>
                                    <a href="{{ route('courses.show', $course) }}" class="btn btn-ma-outline btn-sm">
                                        {{ __('messages.course_details') }}
                                    </a>
                                </div>
                            @endif
                        </div>
                    </article>
                </div>
            @endforeach
        </div>

        @if ($courses->hasPages())
            <div class="mt-4">{{ $courses->links() }}</div>
        @endif
    @endif

    {{-- Published Course Packages (Unlocks every included course directly in My Courses!) --}}
    @if (isset($packages) && $packages->isNotEmpty())
        <div class="mt-5 pt-4 border-top">
            <div class="mb-4">
                <span class="badge bg-black-badge mb-2">
                    <i class="bi bi-box-seam-fill me-1" aria-hidden="true"></i>{{ __('messages.packages') }}
                </span>
                <h2 class="h4 fw-bold mb-1">{{ __('messages.course_packages') }}</h2>
                <p class="text-muted small mb-0">{{ __('messages.course_packages_subtitle') }}</p>
            </div>

            <div class="row g-4">
                @foreach ($packages as $package)
                    @php
                        $isPendingPkg = in_array($package->id, $pendingPackageIds ?? [], true);
                        $savings = $package->savingsAmount();
                    @endphp
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
                                    @if ($isPendingPkg)
                                        <span class="badge bg-warning text-dark">
                                            <i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>{{ __('messages.payment_pending') }}
                                        </span>
                                    @elseif ($savings > 0)
                                        <span class="badge bg-success">{{ __('messages.bundle_savings', ['amount' => number_format($savings, 0).' '.$package->currency]) }}</span>
                                    @else
                                        <span class="badge bg-black-badge">{{ __('messages.courses_included_count', ['count' => $package->courses_count]) }}</span>
                                    @endif
                                </div>

                                <h3 class="h5 fw-bold mb-2">{{ $package->localizedTitle($locale) }}</h3>
                                <p class="text-muted small mb-3">{{ $package->localizedShortDescription($locale) }}</p>

                                <ul class="list-unstyled small text-muted mb-3 pt-2 border-top flex-grow-1 d-flex flex-column gap-1">
                                    @foreach ($package->courses as $pkgCourse)
                                        <li>
                                            <i class="bi {{ $activeEnrollments->has($pkgCourse->id) ? 'bi-unlock-fill text-success' : 'bi-check2-circle text-navy' }} me-1" aria-hidden="true"></i>
                                            {{ $pkgCourse->localizedTitle($locale) }}
                                        </li>
                                    @endforeach
                                </ul>

                                @if ($isPendingPkg)
                                    <a href="{{ route('student.payments.index') }}" class="btn btn-ma-outline w-100">
                                        <i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>{{ __('messages.payment_pending') }}
                                    </a>
                                @else
                                    <a href="{{ route('student.packages.subscribe', $package) }}" class="btn btn-ma-primary w-100">
                                        <i class="bi bi-qr-code me-1" aria-hidden="true"></i>{{ __('messages.subscribe_to_package') }}
                                    </a>
                                @endif
                            </div>
                        </article>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
@endsection
