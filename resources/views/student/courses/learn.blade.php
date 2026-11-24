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
                    {{-- Video Player Container (Direct Upload OR Bunny.net Stream) --}}
                    @if ($currentLesson->isBunnyStream() && $currentLesson->bunnyEmbedUrl())
                        <div class="ma-video-shell">
                            <iframe
                                id="ma-bunny-player"
                                class="ma-video-player border-0"
                                src="{{ $currentLesson->bunnyEmbedUrl() }}"
                                loading="lazy"
                                allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture;"
                                allowfullscreen
                                title="{{ $currentLesson->title }}"
                                data-bunny-player
                                data-progress-url="{{ route('student.courses.lessons.progress', [$course, $currentLesson]) }}"
                                data-subscribe-url="{{ route('student.courses.subscribe', $course) }}"
                                data-resume-seconds="{{ $currentProgress?->last_position_seconds ?? 0 }}"
                                data-max-reached="{{ $currentProgress?->max_reached_seconds ?? 0 }}"
                                data-is-completed="{{ $currentCompleted ? '1' : '0' }}"
                                data-lesson-duration="{{ $currentLesson->duration_seconds ?? 0 }}"
                            ></iframe>
                        </div>
                    @elseif ($currentLesson->hasUploadedVideo())
                        <div class="ma-video-shell">
                            <video
                                id="ma-lesson-video"
                                class="ma-video-player"
                                controls
                                playsinline
                                controlsList="nodownload"
                                preload="metadata"
                                data-codec-check
                                data-codec-warning-target="#student-video-codec-warning"
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
                        <div id="student-video-codec-warning" class="alert alert-warning small m-3 mb-0 d-none" role="alert">
                            <div class="fw-semibold mb-1">
                                <i class="bi bi-exclamation-triangle-fill me-1" aria-hidden="true"></i>
                                Your browser could not decode this video track
                            </div>
                            <div data-codec-warning-detail>
                                Audio may play without picture if the video uses <strong>HEVC (H.265)</strong> instead of standard <strong>H.264 (AVC)</strong>. Try another browser (such as Edge/Safari) or contact your instructor to re-upload the lesson in standard H.264 MP4 format.
                            </div>
                        </div>
                    @else
                        <div class="p-5 text-center bg-dark text-white">
                            <i class="bi bi-camera-video-off fs-1 opacity-50 d-block mb-2" aria-hidden="true"></i>
                            <div class="fw-semibold">No video configured for this lesson yet.</div>
                            <div class="small text-white-50">Please check back once the instructor uploads a video or attaches a Bunny.net Stream video.</div>
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

            {{-- Course Q&A Section (Controlled by enable_course_qa toggle) --}}
            @if (! empty($qaEnabled))
                <div class="ma-card p-4 mt-4" id="course-qa">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <h3 class="h6 fw-bold mb-0">
                            <i class="bi bi-chat-dots-fill text-navy me-1" aria-hidden="true"></i>{{ __('messages.course_qa') }} ({{ $questions->count() }})
                        </h3>
                        <span class="small text-muted">Ask the instructor directly about this course</span>
                    </div>

                    <form method="POST" action="{{ route('student.courses.questions.store', $course) }}" class="mb-4">
                        @csrf
                        @if ($currentLesson)
                            <input type="hidden" name="lesson_id" value="{{ $currentLesson->id }}">
                        @endif
                        <div class="mb-2">
                            <label for="qa_question" class="form-label small fw-semibold">{{ __('messages.ask_question') }}</label>
                            <textarea
                                id="qa_question"
                                name="question"
                                rows="2"
                                required
                                minlength="5"
                                maxlength="2000"
                                class="form-control @error('question') is-invalid @enderror"
                                placeholder="Write your question clearly (e.g., about this lesson or assignment)..."
                            >{{ old('question') }}</textarea>
                            @error('question')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-ma-primary btn-sm">
                            <i class="bi bi-send me-1" aria-hidden="true"></i>{{ __('messages.post_question') }}
                        </button>
                    </form>

                    @if ($questions->isEmpty())
                        <p class="small text-muted mb-0">No questions asked in this course yet. Be the first to ask!</p>
                    @else
                        <div class="d-flex flex-column gap-3">
                            @foreach ($questions as $q)
                                <div class="border rounded-3 p-3">
                                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
                                        <div class="small fw-semibold">
                                            {{ $q->user?->name ?? 'Student' }}
                                            @if ($q->lesson)
                                                <span class="badge bg-secondary ms-1">Lesson #{{ $q->lesson->position }}</span>
                                            @endif
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            @if ($q->is_resolved)
                                                <span class="badge bg-success">Answered</span>
                                            @endif
                                            <span class="small text-muted">{{ $q->created_at->diffForHumans() }}</span>
                                        </div>
                                    </div>
                                    <p class="small mb-2" style="white-space: pre-line;">{{ $q->question }}</p>

                                    @if ($q->replies->isNotEmpty())
                                        <div class="ps-3 border-start border-2 d-flex flex-column gap-2 mb-2" style="border-inline-start-color: #1e3a8a !important;">
                                            @foreach ($q->replies as $rep)
                                                <div class="p-2 rounded bg-body-tertiary">
                                                    <div class="d-flex justify-content-between align-items-center gap-2 mb-1">
                                                        <span class="small fw-semibold">
                                                            {{ $rep->user?->name ?? 'User' }}
                                                            @if ($rep->is_instructor_reply)
                                                                <span class="badge bg-navy-accent ms-1">{{ __('messages.instructor_reply') }}</span>
                                                            @endif
                                                        </span>
                                                        <span class="text-muted" style="font-size: 0.72rem;">{{ $rep->created_at->diffForHumans() }}</span>
                                                    </div>
                                                    <div class="small" style="white-space: pre-line;">{{ $rep->reply }}</div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                    <form method="POST" action="{{ route('student.courses.questions.reply', [$course, $q]) }}" class="d-flex gap-2 mt-2">
                                        @csrf
                                        <input type="text" name="reply" required minlength="2" maxlength="2000" class="form-control form-control-sm" placeholder="{{ __('messages.post_reply') }}...">
                                        <button type="submit" class="btn btn-ma-outline btn-sm text-nowrap">{{ __('messages.post_reply') }}</button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
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

            {{-- Student Star Rating & Review Card (Unlocks at >= 50% completion) --}}
            @if (! empty($reviewsEnabled))
                <div class="ma-card p-4 mt-4" id="course-review">
                    <h3 class="h6 fw-bold mb-2">
                        <i class="bi bi-star-fill text-warning me-1" aria-hidden="true"></i>{{ __('messages.leave_review') }}
                    </h3>

                    @if ($canReview)
                        <form method="POST" action="{{ route('student.courses.reviews.store', $course) }}">
                            @csrf
                            <div class="mb-2">
                                <label for="review_rating" class="form-label small fw-semibold">{{ __('messages.rating') }} (1–5)</label>
                                <select id="review_rating" name="rating" required class="form-select form-select-sm @error('rating') is-invalid @enderror">
                                    @for ($stars = 5; $stars >= 1; $stars--)
                                        <option value="{{ $stars }}" @selected((int) old('rating', $myReview?->rating ?? 5) === $stars)>
                                            {{ str_repeat('★', $stars) }}{{ str_repeat('☆', 5 - $stars) }} ({{ $stars }}/5)
                                        </option>
                                    @endfor
                                </select>
                                @error('rating')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label for="review_comment" class="form-label small fw-semibold">Comment (Optional)</label>
                                <textarea
                                    id="review_comment"
                                    name="comment"
                                    rows="3"
                                    maxlength="1500"
                                    class="form-control form-control-sm @error('comment') is-invalid @enderror"
                                    placeholder="Share your learning experience with other students..."
                                >{{ old('comment', $myReview?->comment) }}</textarea>
                                @error('comment')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <button type="submit" class="btn btn-ma-primary btn-sm w-100">
                                <i class="bi bi-star me-1" aria-hidden="true"></i>{{ $myReview ? 'Update Review' : __('messages.submit_review') }}
                            </button>
                        </form>
                    @else
                        <p class="small text-muted mb-2">
                            {{ __('messages.review_locked_msg') }}
                        </p>
                        <div class="small fw-semibold">
                            Current Progress: <span class="text-navy">{{ $progressSummary['completion_percentage'] }}%</span> / 50%
                        </div>
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

    {{-- Student AI Course Tutor Drawer (Strictly hidden when enable_ai_student_tutor is OFF) --}}
    @if (! empty($aiTutorEnabled))
        <button
            type="button"
            id="ma-student-ai-tutor-trigger"
            class="btn btn-ma-dark shadow-lg rounded-pill px-3 py-2 d-inline-flex align-items-center gap-2"
            style="position: fixed; bottom: 1.5rem; inset-inline-end: 1.5rem; z-index: 1040;"
            data-bs-toggle="offcanvas"
            data-bs-target="#studentAiTutorDrawer"
            aria-controls="studentAiTutorDrawer"
        >
            <i class="bi bi-stars" aria-hidden="true"></i>
            <span class="fw-semibold small">{{ __('messages.ai_ask_tutor') }}</span>
        </button>

        <div class="offcanvas offcanvas-end" tabindex="-1" id="studentAiTutorDrawer" aria-labelledby="studentAiTutorDrawerLabel" style="width: min(440px, 100vw);">
            <div class="offcanvas-header border-bottom">
                <div>
                    <h2 class="offcanvas-title h6 fw-bold mb-0" id="studentAiTutorDrawerLabel">
                        <i class="bi bi-stars text-navy me-1" aria-hidden="true"></i>{{ __('messages.ai_course_tutor') }}
                    </h2>
                    <div class="small text-muted text-truncate" style="max-width: 300px;">
                        {{ $courseTitle }}@if ($currentLesson) · #{{ $currentLesson->position }} {{ $currentLesson->title }}@endif
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body d-flex flex-column p-0">
                <div class="px-3 py-2 border-bottom small d-flex justify-content-between align-items-center" style="background-color: var(--ma-surface-subtle);">
                    <span class="text-muted">Scoped to this course &amp; lesson</span>
                    <span class="badge bg-navy-accent" id="student-ai-tutor-quota">
                        {{ $aiTutorRemainingToday }} / {{ $aiTutorDailyLimit }} left today
                    </span>
                </div>

                <div id="student-ai-tutor-messages" class="p-3 flex-grow-1 d-flex flex-column gap-3 overflow-auto">
                    <div class="small text-muted text-center py-4" id="student-ai-tutor-empty">
                        <i class="bi bi-stars fs-2 d-block mb-2 text-navy opacity-75" aria-hidden="true"></i>
                        Ask any question about this lesson or course in English or Arabic.
                    </div>
                </div>

                <div id="student-ai-tutor-error" class="alert alert-danger small mx-3 mb-2 py-2 d-none" role="alert"></div>

                <form
                    id="student-ai-tutor-form"
                    class="p-3 border-top"
                    data-ask-url="{{ route('student.courses.ai-tutor.ask', $course) }}"
                    data-lesson-id="{{ $currentLesson?->id ?? '' }}"
                >
                    <div class="input-group">
                        <input
                            type="text"
                            id="student-ai-tutor-input"
                            class="form-control form-control-sm"
                            placeholder="Ask about this lesson..."
                            maxlength="1500"
                            required
                        >
                        <button type="submit" id="student-ai-tutor-send" class="btn btn-ma-primary btn-sm px-3">
                            <i class="bi bi-send" aria-hidden="true"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection
