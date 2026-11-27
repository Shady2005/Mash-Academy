@extends('layouts.student')

@section('title', $quiz->title)

@section('content')
    <div class="mb-3">
        <a href="{{ route('student.courses.learn', [$course, $quiz->lesson]) }}" class="btn btn-ma-outline btn-sm">
            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('messages.back_to_player') }}
        </a>
    </div>

    {{-- Sticky Countdown Timer Bar (Active Timed Quiz Only) --}}
    @if ($quiz->isTimed() && ! empty($activeAttempt) && $remainingSeconds !== null)
        <div
            id="ma-quiz-countdown-bar"
            data-remaining-seconds="{{ $remainingSeconds }}"
            class="ma-card p-3 mb-4 d-flex flex-wrap justify-content-between align-items-center gap-2 border-2"
            style="position: sticky; top: 4.5rem; z-index: 1020; border-color: #1e3a8a;"
            role="timer"
            aria-live="polite"
        >
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-stopwatch-fill fs-5 text-navy" id="ma-quiz-timer-icon" aria-hidden="true"></i>
                <div>
                    <div class="fw-bold small">{{ __('messages.quiz_time_remaining') }}</div>
                    <div class="text-muted" style="font-size: 0.74rem;">
                        Your answers will auto-submit automatically when the timer reaches 00:00:00.
                    </div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span id="ma-quiz-timer-display" class="badge bg-navy-accent fs-5 font-monospace px-3 py-2">
                    {{ sprintf('%02d:%02d:%02d', intdiv($remainingSeconds, 3600), intdiv($remainingSeconds % 3600, 60), $remainingSeconds % 60) }}
                </span>
            </div>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="ma-card p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3 pb-3 border-bottom">
                    <div>
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            <span class="badge bg-navy-accent">Quiz · Pass Mark {{ $quiz->pass_mark }}%</span>
                            @if ($quiz->isTimed())
                                <span class="badge bg-warning text-dark">
                                    <i class="bi bi-stopwatch me-1" aria-hidden="true"></i>{{ __('messages.quiz_timer_enabled') }} · {{ $quiz->duration_minutes }} min
                                </span>
                            @endif
                        </div>
                        <h1 class="h4 fw-bold mb-1">{{ $quiz->title }}</h1>
                        @if ($quiz->description)
                            <p class="text-muted small mb-0">{{ $quiz->description }}</p>
                        @endif
                    </div>
                    @if ($bestAttempt)
                        <div class="text-end">
                            <span class="badge {{ $bestAttempt->passed ? 'bg-success' : 'bg-danger' }} fs-6">
                                Best: {{ $bestAttempt->score }}/{{ $bestAttempt->max_score }} ({{ round((float) $bestAttempt->percentage, 1) }}%)
                            </span>
                        </div>
                    @endif
                </div>

                @if ($quiz->questions->isEmpty())
                    <p class="text-muted mb-0">No questions have been added to this quiz yet.</p>
                @elseif ($quiz->isTimed() && empty($activeAttempt))
                    <div class="p-4 rounded-3 border text-center" style="background-color: var(--ma-surface-subtle);">
                        <i class="bi bi-stopwatch fs-1 text-navy d-block mb-2" aria-hidden="true"></i>
                        <h2 class="h6 fw-bold mb-1">Ready to retake this timed quiz?</h2>
                        <p class="small text-muted mb-3">
                            You have <strong>{{ $quiz->duration_minutes }} minutes</strong> once you start. Refreshing the page continues from your exact remaining server time.
                        </p>
                        <a href="{{ route('student.courses.quizzes.show', [$course, $quiz, 'retake' => 1]) }}" class="btn btn-ma-primary px-4">
                            <i class="bi bi-play-fill me-1" aria-hidden="true"></i>Start Timed Quiz ({{ $quiz->duration_minutes }} min)
                        </a>
                    </div>
                @else
                    <form id="ma-student-quiz-form" method="POST" action="{{ route('student.courses.quizzes.submit', [$course, $quiz]) }}">
                        @csrf
                        <input type="hidden" name="auto_submitted" id="ma-quiz-auto-submitted" value="0">

                        <div class="d-flex flex-column gap-4">
                            @foreach ($quiz->questions as $question)
                                <fieldset class="border rounded-3 p-3">
                                    <legend class="h6 fw-bold mb-3 fs-6">
                                        Q{{ $loop->iteration }}. {{ $question->question_text }}
                                        <span class="badge bg-secondary ms-1">{{ $question->points }} pt</span>
                                    </legend>

                                    <div class="d-flex flex-column gap-2">
                                        @foreach ($question->options as $option)
                                            <label class="d-flex align-items-center gap-2 p-2 rounded border" style="cursor: pointer;">
                                                <input
                                                    type="radio"
                                                    name="answers[{{ $question->id }}]"
                                                    value="{{ $option->id }}"
                                                    class="form-check-input mt-0"
                                                    {{ $quiz->isTimed() ? '' : 'required' }}
                                                >
                                                <span class="small">{{ $option->option_text }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                            @endforeach
                        </div>

                        <div class="mt-4">
                            <button type="submit" id="ma-quiz-submit-btn" class="btn btn-ma-primary px-4">
                                <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>{{ __('messages.submit_quiz') }}
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>

        {{-- Attempt History Sidebar --}}
        <div class="col-lg-4">
            <div class="ma-card p-4">
                <h2 class="h6 fw-bold mb-3">Your Attempts ({{ $attempts->count() }})</h2>
                @if ($attempts->isEmpty())
                    <p class="small text-muted mb-0">You have not attempted this quiz yet. Unlimited retakes are allowed; your best score is kept.</p>
                @else
                    <div class="d-flex flex-column gap-2">
                        @foreach ($attempts as $attempt)
                            <div class="border rounded-3 p-3 small">
                                <div class="d-flex justify-content-between align-items-center gap-1 mb-1">
                                    <strong>Attempt #{{ $attempts->count() - $loop->index }}</strong>
                                    <div class="d-flex gap-1">
                                        @if ($attempt->auto_submitted)
                                            <span class="badge bg-warning text-dark">{{ __('messages.auto_submitted') }}</span>
                                        @endif
                                        <span class="badge {{ $attempt->passed ? 'bg-success' : 'bg-danger' }}">
                                            {{ $attempt->passed ? 'Passed' : 'Not Passed' }}
                                        </span>
                                    </div>
                                </div>
                                <div class="fw-semibold text-navy">{{ $attempt->score }} / {{ $attempt->max_score }} ({{ round((float) $attempt->percentage, 1) }}%)</div>
                                <div class="text-muted">{{ $attempt->submitted_at?->format('M d, Y H:i') }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
