@extends('layouts.public')

@section('title', $course->localizedTitle($currentLocale ?? app()->getLocale()))

@section('content')
    @php
        $locale = $currentLocale ?? app()->getLocale();
    @endphp

    <section class="ma-hero py-5">
        <div class="container position-relative" style="z-index: 1;">
            <a href="{{ route('courses.index') }}" class="btn btn-outline-light btn-sm mb-3">
                <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('messages.back_to_courses') }}
            </a>
            <div class="row align-items-center gy-4">
                <div class="col-lg-8">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        @if ($course->is_featured)
                            <span class="badge bg-navy-accent">{{ __('messages.featured') }}</span>
                        @endif
                        <span class="badge bg-dark border border-light border-opacity-25">
                            <i class="bi bi-lock-fill me-1" aria-hidden="true"></i>{{ __('messages.locked_by_default') }}
                        </span>
                    </div>
                    <h1 class="h2 fw-bold text-white mb-2">{{ $course->localizedTitle($locale) }}</h1>
                    <p class="text-white-50 mb-3">{{ $course->localizedShortDescription($locale) }}</p>
                    <div class="d-flex flex-wrap gap-3 small text-white-50">
                        <span><i class="bi bi-person-badge me-1" aria-hidden="true"></i>{{ __('messages.instructor') }}: <strong class="text-white">{{ $course->creator?->name ?? 'Mash Academy' }}</strong></span>
                        <span><i class="bi bi-camera-video me-1" aria-hidden="true"></i>{{ __('messages.lessons_count', ['count' => $course->publishedLessons->count()]) }}</span>
                        <span><i class="bi bi-patch-question me-1" aria-hidden="true"></i>{{ __('messages.quizzes_count', ['count' => $course->quizzes->count()]) }}</span>
                        <span><i class="bi bi-file-earmark-check me-1" aria-hidden="true"></i>{{ __('messages.assignments_count', ['count' => $course->assignments->count()]) }}</span>
                        @if (! empty($reviewsEnabled) && ! empty($averageRating))
                            <span class="text-warning fw-semibold">
                                <i class="bi bi-star-fill me-1" aria-hidden="true"></i>{{ number_format($averageRating, 1) }} / 5 ({{ $course->reviews->count() }})
                            </span>
                        @endif
                    </div>

                    @if (! empty($demoVideosEnabled) && $course->hasActiveDemoVideo())
                        <div class="mt-3">
                            <button type="button" class="btn btn-outline-light btn-sm px-3" data-bs-toggle="modal" data-bs-target="#courseDemoVideoModal">
                                <i class="bi bi-play-circle-fill me-1" aria-hidden="true"></i>{{ __('messages.watch_free_demo') }}
                            </button>
                        </div>
                    @endif
                </div>
                <div class="col-lg-4">
                    <div class="ma-card p-4 text-body">
                        <div class="small text-muted text-uppercase fw-semibold mb-1">{{ __('messages.price') }}</div>
                        <div class="display-6 fw-bold text-navy mb-3">{{ $course->formattedPrice() }}</div>

                        @if (! empty($activeEnrollment))
                            <div class="alert alert-success small mb-3">
                                <i class="bi bi-unlock-fill me-1" aria-hidden="true"></i>
                                <strong>{{ __('messages.unlocked') }}</strong> — {{ __('messages.expires_on', ['date' => $activeEnrollment->expires_at->format('M d, Y')]) }}
                                ({{ __('messages.days_remaining', ['days' => $activeEnrollment->remainingDays()]) }}).
                            </div>
                            <a href="{{ route('student.courses.access', $course) }}" class="btn btn-ma-primary w-100">
                                <i class="bi bi-play-circle me-1" aria-hidden="true"></i>{{ __('messages.open_course') }}
                            </a>
                        @elseif (auth()->check() && auth()->user()->isStudent())
                            <div class="alert alert-secondary small mb-3">
                                <i class="bi bi-qr-code me-1" aria-hidden="true"></i>
                                Subscribe via InstaPay QR (Promo codes supported) to unlock this course.
                            </div>
                            <a href="{{ route('student.courses.subscribe', $course) }}" class="btn btn-ma-primary w-100">
                                <i class="bi bi-qr-code me-1" aria-hidden="true"></i>{{ __('messages.subscribe_to_course') }}
                            </a>
                        @elseif (auth()->check() && auth()->user()->isSuperAdmin())
                            <div class="alert alert-secondary small mb-3">
                                <i class="bi bi-shield-check me-1" aria-hidden="true"></i>
                                <strong>{{ __('messages.super_admin_preview') }}</strong> — Unrestricted access to all lessons, assessments, and checkout flow.
                            </div>
                            <div class="d-grid gap-2">
                                <a href="{{ route('student.courses.learn', $course) }}" class="btn btn-ma-primary">
                                    <i class="bi bi-play-circle me-1" aria-hidden="true"></i>{{ __('messages.open_course') }}
                                </a>
                                <a href="{{ route('student.courses.subscribe', $course) }}" class="btn btn-ma-outline btn-sm">
                                    <i class="bi bi-qr-code me-1" aria-hidden="true"></i>{{ __('messages.subscribe_to_course') }} (Preview)
                                </a>
                            </div>
                        @elseif (auth()->check())
                            <div class="alert alert-secondary small mb-0">
                                Signed in as staff ({{ auth()->user()->role->shortLabel() }}).
                            </div>
                        @else
                            <div class="alert alert-secondary small mb-3">
                                <i class="bi bi-lock-fill me-1" aria-hidden="true"></i>
                                Sign in or create a student account to subscribe via InstaPay.
                            </div>
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
                    @if ($course->thumbnailUrl())
                        <div class="mb-4">
                            <img src="{{ $course->thumbnailUrl() }}" alt="{{ $course->localizedTitle($locale) }}" class="img-fluid rounded-4 border w-100" style="max-height: 360px; object-fit: cover;">
                        </div>
                    @endif

                    <div class="ma-card p-4 mb-4">
                        <h2 class="h5 fw-bold mb-3">{{ __('messages.course_details') }}</h2>
                        <div class="text-muted" style="white-space: pre-line; line-height: 1.8;">{{ $course->localizedDescription($locale) }}</div>
                    </div>

                    <div class="ma-card p-4 mb-4">
                        <h2 class="h5 fw-bold mb-3">{{ __('messages.curriculum_outline') }}</h2>
                        @if ($course->publishedLessons->isEmpty())
                            <p class="text-muted small mb-0">Lessons will be published soon.</p>
                        @else
                            <div class="list-group list-group-flush">
                                @foreach ($course->publishedLessons as $lesson)
                                    <div class="list-group-item px-0 py-3 d-flex justify-content-between align-items-center gap-3 bg-transparent">
                                        <div class="d-flex align-items-start gap-3">
                                            <span class="badge bg-navy rounded-pill mt-1">{{ $lesson->position }}</span>
                                            <div>
                                                <div class="fw-semibold">{{ $lesson->title }}</div>
                                                @if ($lesson->description)
                                                    <div class="small text-muted">{{ $lesson->description }}</div>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="text-end text-nowrap">
                                            <span class="small text-muted me-2">{{ $lesson->formattedDuration() }}</span>
                                            <span class="badge bg-black-badge">
                                                <i class="bi bi-lock-fill me-1" aria-hidden="true"></i>{{ __('messages.locked_preview') }}
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Student Star Reviews --}}
                    @if (! empty($reviewsEnabled))
                        <div class="ma-card p-4">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                <h2 class="h5 fw-bold mb-0">
                                    <i class="bi bi-star-fill text-warning me-2" aria-hidden="true"></i>{{ __('messages.student_reviews') }} ({{ $course->reviews->count() }})
                                </h2>
                                @if (! empty($averageRating))
                                    <span class="badge bg-navy-accent px-3 py-2">{{ number_format($averageRating, 1) }} / 5.0</span>
                                @endif
                            </div>

                            @if ($course->reviews->isEmpty())
                                <p class="text-muted small mb-0">No student reviews yet. Enrolled students with ≥ 50% progress can rate this course.</p>
                            @else
                                <div class="d-flex flex-column gap-3">
                                    @foreach ($course->reviews as $review)
                                        <div class="border rounded-3 p-3">
                                            <div class="d-flex justify-content-between align-items-center gap-2 mb-1">
                                                <span class="fw-semibold small">{{ $review->user?->name ?? 'Student' }}</span>
                                                <span class="text-warning small" aria-label="{{ $review->rating }} out of 5 stars">
                                                    @for ($s = 1; $s <= 5; $s++)
                                                        <i class="bi {{ $s <= $review->rating ? 'bi-star-fill' : 'bi-star' }}" aria-hidden="true"></i>
                                                    @endfor
                                                </span>
                                            </div>
                                            @if ($review->comment)
                                                <p class="small text-muted mb-1">{{ $review->comment }}</p>
                                            @endif
                                            <div class="text-muted" style="font-size: 0.74rem;">{{ $review->created_at->format('M d, Y') }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="col-lg-4">
                    {{-- Quizzes & Assignments Summary --}}
                    <div class="ma-card p-4 mb-4">
                        <h3 class="h6 fw-bold mb-3">{{ __('messages.quizzes') }} &amp; {{ __('messages.assignments') }}</h3>
                        <ul class="list-unstyled small mb-0 d-flex flex-column gap-2">
                            @forelse ($course->quizzes as $quiz)
                                <li class="d-flex justify-content-between align-items-center border-bottom pb-2">
                                    <span><i class="bi bi-patch-question text-navy me-2" aria-hidden="true"></i>{{ $quiz->title }}</span>
                                    <span class="badge bg-secondary">{{ $quiz->questions_count }} Qs</span>
                                </li>
                            @empty
                                <li class="text-muted">No quizzes added yet.</li>
                            @endforelse

                            @foreach ($course->assignments as $assignment)
                                <li class="d-flex justify-content-between align-items-center border-bottom pb-2">
                                    <span><i class="bi bi-file-earmark-check text-navy me-2" aria-hidden="true"></i>{{ $assignment->title }}</span>
                                    <span class="badge bg-navy-accent">{{ $assignment->typeLabel() }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    {{-- Final Project & Certificate Preview --}}
                    @if ($course->finalProject && $course->finalProject->is_published)
                        <div class="ma-card p-4 border-start border-4" style="border-inline-start-color: #1e3a8a !important;">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="bi bi-award-fill text-navy fs-5" aria-hidden="true"></i>
                                <h3 class="h6 fw-bold mb-0">{{ __('messages.final_project_section') }}</h3>
                            </div>
                            <div class="fw-semibold small mb-1">{{ $course->finalProject->title }}</div>
                            <p class="small text-muted mb-2">
                                {{ __('messages.final_project_gate_note', ['percent' => $course->finalProject->required_completion_percentage]) }}
                            </p>
                            <span class="badge bg-black-badge">
                                <i class="bi bi-lock-fill me-1" aria-hidden="true"></i>Locked until {{ $course->finalProject->required_completion_percentage }}% completion
                            </span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{-- Free Demo / Preview Video Modal --}}
    @if (! empty($demoVideosEnabled) && $course->hasActiveDemoVideo())
        <div class="modal fade" id="courseDemoVideoModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title h6 fw-bold">
                            <i class="bi bi-play-btn-fill text-navy me-1" aria-hidden="true"></i>{{ __('messages.watch_free_demo') }} — {{ $course->localizedTitle($locale) }}
                        </h3>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-0">
                        <div class="ma-video-shell">
                            @if ($course->demo_video_type === 'upload' && $course->demoUploadUrl())
                                <video controls playsinline preload="metadata" class="ma-video-player">
                                    <source src="{{ $course->demoUploadUrl() }}" type="video/mp4">
                                </video>
                            @elseif ($course->demoEmbedUrl())
                                <iframe
                                    src="{{ $course->demoEmbedUrl() }}"
                                    class="ma-video-player border-0"
                                    allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture;"
                                    allowfullscreen
                                    title="Free Demo Video"
                                ></iframe>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
