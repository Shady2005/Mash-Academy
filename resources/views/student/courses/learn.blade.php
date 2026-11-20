@extends('layouts.student')

@section('title', $course->localizedTitle($currentLocale ?? app()->getLocale()))

@section('content')
    @php
        $locale = $currentLocale ?? app()->getLocale();
        $courseTitle = $course->localizedTitle($locale);
    @endphp

    {{-- Top Course Header + Overall Progress Bar --}}
    <div class="ma-card p-3 p-md-4 mb-4">
        <div class="row align-items-center gy-3">
            <div class="col-lg-7">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <a href="{{ route('student.my-courses.index') }}" class="btn btn-ma-outline btn-sm">
                        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('messages.my_courses') }}
                    </a>
                    @if ($enrollment)
                        <span class="badge bg-navy-accent">
                            {{ __('messages.expires_on', ['date' => $enrollment->expires_at->format('M d, Y')]) }}
                        </span>
                        <span class="badge bg-black-badge">
                            {{ __('messages.days_remaining', ['days' => $enrollment->remainingDays()]) }}
                        </span>
                    @elseif (auth()->user()->isSuperAdmin())
                        <span class="badge bg-navy-accent">
                            <i class="bi bi-shield-check me-1" aria-hidden="true"></i>{{ __('messages.super_admin_preview') }} (Unrestricted)
                        </span>
                    @endif
                </div>
                <h1 class="h4 fw-bold mb-0">{{ $courseTitle }}</h1>
            </div>
            <div class="col-lg-5">
                <div class="d-flex justify-content-between small mb-1">
                    <span class="fw-semibold">{{ __('messages.course_progress') }}</span>
                    <span class="fw-bold text-navy" id="course-progress-pct">{{ $progressSummary['completion_percentage'] }}%</span>
                </div>
                <div class="progress" style="height: 10px;">
                    <div
                        id="course-progress-bar"
                        class="progress-bar bg-navy-accent"
                        role="progressbar"
                        style="width: {{ min(100, $progressSummary['completion_percentage']) }}%;"
                        aria-valuenow="{{ $progressSummary['completion_percentage'] }}"
                        aria-valuemin="0"
                        aria-valuemax="100"
                    ></div>
                </div>
                <div class="small text-muted mt-1">
                    {{ $progressSummary['completed_lessons'] }}/{{ $progressSummary['total_lessons'] }} lessons ·
                    {{ $progressSummary['submitted_assignments'] }}/{{ $progressSummary['total_assignments'] }} assignments
                    @if ($progressSummary['total_required_quizzes'] > 0)
                        · {{ $progressSummary['passed_required_quizzes'] }}/{{ $progressSummary['total_required_quizzes'] }} required quizzes
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- Main Player & Active Lesson Details --}}
        <div class="col-lg-8">
            @if ($currentLesson)
                <div class="ma-card overflow-hidden mb-4">
                    {{-- Video Player Container --}}
                    @if ($currentLesson->hasVideo())
                        <div class="bg-black position-relative">
                            <video
                                id="ma-lesson-video"
                                class="w-100 d-block"
                                style="max-height: 480px; background: #000;"
                                controls
                                controlsList="nodownload"
                                preload="metadata"
                                data-progress-url="{{ route('student.courses.lessons.progress', [$course, $currentLesson]) }}"
                                data-subscribe-url="{{ route('student.courses.subscribe', $course) }}"
                                data-resume-seconds="{{ $currentProgress?->last_position_seconds ?? 0 }}"
                                data-max-reached="{{ $currentProgress?->max_reached_seconds ?? 0 }}"
                                data-is-completed="{{ $currentCompleted ? '1' : '0' }}"
                                data-lesson-duration="{{ $currentLesson->duration_seconds ?? 0 }}"
                            >
                                <source src="{{ route('student.courses.lessons.stream', [$course, $currentLesson]) }}" type="{{ $currentLesson->video_mime ?: 'video/mp4' }}">
                                Your browser does not support HTML5 video playback.
                            </video>
                        </div>
                    @else
                        <div class="p-5 text-center bg-dark text-white">
                            <i class="bi bi-camera-video-off fs-1 opacity-50 d-block mb-2" aria-hidden="true"></i>
                            <div class="fw-semibold">No video uploaded for this lesson yet.</div>
                            <div class="small text-white-50">Please check back once the instructor uploads the lesson recording.</div>
                        </div>
                    @endif

                    <div class="p-4">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                            <div>
                                <span class="badge bg-navy mb-1">Lesson #{{ $currentLesson->position }}</span>
                                <h2 class="h5 fw-bold mb-0">{{ $currentLesson->title }}</h2>
                            </div>
                            <span id="lesson-status-badge" class="badge {{ $currentCompleted ? 'bg-success' : 'bg-navy-accent' }}">
                                @if ($currentCompleted)
                                    <i class="bi bi-check-circle-fill me-1" aria-hidden="true"></i>{{ __('messages.lesson_completed') }}
                                @else
                                    <i class="bi bi-play-fill me-1" aria-hidden="true"></i>{{ __('messages.lesson_current') }}
                                @endif
                            </span>
                        </div>

                        @if ($currentLesson->description)
                            <p class="text-muted small mb-3">{{ $currentLesson->description }}</p>
                        @endif

                        {{-- Live Anti-Skip / Watch Progress Feedback --}}
                        <div id="player-alert" class="alert small py-2 mb-3 {{ $currentCompleted ? 'alert-success' : 'alert-secondary' }}" role="status" aria-live="polite">
                            @if ($currentCompleted)
                                <i class="bi bi-check-circle-fill me-1" aria-hidden="true"></i>
                                You completed 100% of this video. You may now proceed to the next lesson or rewatch freely.
                            @else
                                <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                                {{ __('messages.watch_full_video_notice') }}
                            @endif
                        </div>

                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div class="small text-muted">
                                Watch progress: <strong id="lesson-watch-pct">{{ $currentCompleted ? '100' : ($currentLesson->duration_seconds ? min(100, round((($currentProgress?->watched_seconds ?? 0) / max(1, $currentLesson->duration_seconds)) * 100, 1)) : 0) }}%</strong>
                            </div>
                            <div id="next-lesson-cta">
                                @if ($nextState && $nextState['unlocked'])
                                    <a href="{{ route('student.courses.learn', [$course, $nextState['lesson']]) }}" class="btn btn-ma-primary btn-sm">
                                        Next Lesson: {{ $nextState['lesson']->title }} <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="ma-card p-5 text-center mb-4">
                    <p class="text-muted mb-0">No published lessons exist in this course yet.</p>
                </div>
            @endif

            {{-- Quizzes & Flexible Assignments Section --}}
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="ma-card p-4 h-100">
                        <h3 class="h6 fw-bold mb-3">
                            <i class="bi bi-patch-question text-navy me-1" aria-hidden="true"></i>{{ __('messages.quizzes') }} ({{ $course->quizzes->count() }})
                        </h3>
                        @if ($course->quizzes->isEmpty())
                            <p class="small text-muted mb-0">No quizzes in this course.</p>
                        @else
                            <div class="d-flex flex-column gap-2">
                                @foreach ($course->quizzes as $quiz)
                                    @php
                                        $quizLocked = $quiz->lesson_id && ! in_array($quiz->lesson_id, $unlockedLessonIds, true);
                                        $best = $bestAttemptsByQuiz->get($quiz->id);
                                    @endphp
                                    <div class="border rounded-3 p-3">
                                        <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                                            <div class="fw-semibold small">{{ $quiz->title }}</div>
                                            @if ($best)
                                                <span class="badge {{ $best->passed ? 'bg-success' : 'bg-danger' }}">
                                                    {{ $best->passed ? 'Passed' : 'Failed' }} ({{ round((float) $best->percentage) }}%)
                                                </span>
                                            @endif
                                        </div>
                                        <div class="small text-muted mb-2">
                                            Pass mark: {{ $quiz->pass_mark }}% · {{ $quiz->questions_count }} Qs
                                            @if ($quiz->is_required_for_next)
                                                · <strong class="text-navy">Required</strong>
                                            @endif
                                        </div>
                                        @if ($quizLocked)
                                            <button type="button" class="btn btn-ma-outline btn-sm w-100" disabled>
                                                <i class="bi bi-lock-fill me-1" aria-hidden="true"></i>Unlock linked lesson first
                                            </button>
                                        @else
                                            <a href="{{ route('student.courses.quizzes.show', [$course, $quiz]) }}" class="btn btn-ma-primary btn-sm w-100">
                                                {{ $best ? __('messages.retake_quiz') : __('messages.take_quiz') }}
                                            </a>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="ma-card p-4 h-100">
                        <h3 class="h6 fw-bold mb-3">
                            <i class="bi bi-file-earmark-check text-navy me-1" aria-hidden="true"></i>{{ __('messages.assignments') }} ({{ $course->assignments->count() }})
                        </h3>
                        @if ($course->assignments->isEmpty())
                            <p class="small text-muted mb-0">No assignments in this course.</p>
                        @else
                            <div class="d-flex flex-column gap-2">
                                @foreach ($course->assignments as $assignment)
                                    @php
                                        $assignLocked = $assignment->lesson_id && ! in_array($assignment->lesson_id, $unlockedLessonIds, true);
                                        $sub = $submissionsByAssignment->get($assignment->id);
                                    @endphp
                                    <div class="border rounded-3 p-3">
                                        <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                                            <div class="fw-semibold small">{{ $assignment->title }}</div>
                                            @if ($sub)
                                                <span class="badge {{ $sub->status === 'reviewed' ? 'bg-success' : 'bg-navy-accent' }}">
                                                    {{ ucfirst($sub->status) }}
                                                </span>
                                            @else
                                                <span class="badge bg-secondary">Pending</span>
                                            @endif
                                        </div>
                                        <div class="small text-muted mb-2">{{ $assignment->typeLabel() }}</div>
                                        @if ($assignLocked)
                                            <button type="button" class="btn btn-ma-outline btn-sm w-100" disabled>
                                                <i class="bi bi-lock-fill me-1" aria-hidden="true"></i>Unlock linked lesson first
                                            </button>
                                        @else
                                            <a href="{{ route('student.courses.assignments.show', [$course, $assignment]) }}" class="btn btn-ma-primary btn-sm w-100">
                                                {{ __('messages.open_assignment') }}
                                            </a>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Right Sidebar: Sequential Lesson List + Final Project 95% Gate --}}
        <div class="col-lg-4">
            <div class="ma-card p-3 mb-4">
                <h2 class="h6 fw-bold px-2 pt-1 pb-2 mb-2 border-bottom">
                    {{ __('messages.lessons_videos') }} ({{ $lessonStates->count() }})
                </h2>

                <div class="list-group list-group-flush">
                    @foreach ($lessonStates as $state)
                        @php
                            $item = $state['lesson'];
                            $isCurrent = $currentLesson && $currentLesson->id === $item->id;
                        @endphp
                        @if ($state['unlocked'])
                            <a
                                href="{{ route('student.courses.learn', [$course, $item]) }}"
                                class="list-group-item list-group-item-action rounded-2 mb-1 d-flex justify-content-between align-items-center gap-2 {{ $isCurrent ? 'bg-navy-accent text-white fw-semibold' : 'bg-transparent' }}"
                            >
                                <div class="d-flex align-items-center gap-2 min-w-0">
                                    @if ($state['completed'])
                                        <span class="{{ $isCurrent ? 'text-white' : 'text-success' }}" title="Completed">✔</span>
                                    @elseif ($isCurrent)
                                        <span title="Now Playing">▶</span>
                                    @else
                                        <span class="text-navy" title="Unlocked">▶</span>
                                    @endif
                                    <span class="small text-truncate">{{ $item->position }}. {{ $item->title }}</span>
                                </div>
                                <span class="small {{ $isCurrent ? 'text-white-50' : 'text-muted' }} text-nowrap">{{ $item->formattedDuration() }}</span>
                            </a>
                        @else
                            <button
                                type="button"
                                class="list-group-item list-group-item-action rounded-2 mb-1 d-flex justify-content-between align-items-center gap-2 bg-transparent text-muted"
                                data-bs-toggle="modal"
                                data-bs-target="#lockedLessonModal"
                                data-lock-reason="{{ $state['lock_reason'] }}"
                                data-lock-title="{{ $item->position }}. {{ $item->title }}"
                            >
                                <div class="d-flex align-items-center gap-2 min-w-0">
                                    <span title="Locked">🔒</span>
                                    <span class="small text-truncate">{{ $item->position }}. {{ $item->title }}</span>
                                </div>
                                <span class="small text-nowrap">{{ $item->formattedDuration() }}</span>
                            </button>
                        @endif
                    @endforeach
                </div>
            </div>

            {{-- Dedicated Final Project 95% Gate Card --}}
            @if ($progressSummary['has_final_project'])
                <div class="ma-card p-4 border-start border-4" style="border-inline-start-color: #1e3a8a !important;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h3 class="h6 fw-bold mb-0">
                            <i class="bi bi-award-fill text-navy me-1" aria-hidden="true"></i>{{ __('messages.final_project') }}
                        </h3>
                        @if ($progressSummary['final_project_unlocked'])
                            <span class="badge bg-success"><i class="bi bi-unlock-fill me-1" aria-hidden="true"></i>Unlocked</span>
                        @else
                            <span class="badge bg-black-badge"><i class="bi bi-lock-fill me-1" aria-hidden="true"></i>Locked</span>
                        @endif
                    </div>

                    <div class="fw-semibold small mb-1">{{ $course->finalProject->title }}</div>

                    @if ($progressSummary['final_project_unlocked'])
                        <p class="small text-muted mb-3">
                            You reached <strong>{{ $progressSummary['completion_percentage'] }}%</strong> (required: {{ $progressSummary['required_final_project_percentage'] }}%).
                            @if ($finalProjectSubmission)
                                Status: <strong class="text-success">{{ ucfirst($finalProjectSubmission->status) }}</strong>.
                            @endif
                        </p>
                        <a href="{{ route('student.courses.final-project.show', $course) }}" class="btn btn-ma-primary btn-sm w-100">
                            <i class="bi bi-award me-1" aria-hidden="true"></i>{{ __('messages.open_final_project') }}
                        </a>
                    @else
                        <p class="small text-muted mb-2">
                            {{ __('messages.final_project_locked_msg', ['percent' => $progressSummary['required_final_project_percentage']]) }}
                        </p>
                        <div class="small fw-semibold mb-2">
                            Current: <span class="text-navy">{{ $progressSummary['completion_percentage'] }}%</span> / {{ $progressSummary['required_final_project_percentage'] }}%
                        </div>
                        <button type="button" class="btn btn-ma-outline btn-sm w-100" disabled>
                            <i class="bi bi-lock-fill me-1" aria-hidden="true"></i>Locked ({{ $progressSummary['completion_percentage'] }}% / {{ $progressSummary['required_final_project_percentage'] }}%)
                        </button>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- Locked Lesson Explanation Modal --}}
    <div class="modal fade" id="lockedLessonModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title h6 fw-bold"><i class="bi bi-lock-fill me-1 text-navy" aria-hidden="true"></i>Lesson Locked</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="fw-semibold mb-2" id="locked-modal-title"></div>
                    <p class="small text-muted mb-0" id="locked-modal-reason"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ma-primary btn-sm" data-bs-dismiss="modal">Got it</button>
                </div>
            </div>
        </div>
    </div>
@endsection
