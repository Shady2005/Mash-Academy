@extends('layouts.student')

@section('title', $quiz->title)

@section('content')
    <div class="mb-3">
        <a href="{{ route('student.courses.learn', [$course, $quiz->lesson]) }}" class="btn btn-ma-outline btn-sm">
            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('messages.back_to_player') }}
        </a>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="ma-card p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3 pb-3 border-bottom">
                    <div>
                        <span class="badge bg-navy-accent mb-2">Quiz · Pass Mark {{ $quiz->pass_mark }}%</span>
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
                @else
                    <form method="POST" action="{{ route('student.courses.quizzes.submit', [$course, $quiz]) }}">
                        @csrf

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
                                                    required
                                                >
                                                <span class="small">{{ $option->option_text }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                            @endforeach
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-ma-primary px-4">
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
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <strong>Attempt #{{ $attempts->count() - $loop->index }}</strong>
                                    <span class="badge {{ $attempt->passed ? 'bg-success' : 'bg-danger' }}">
                                        {{ $attempt->passed ? 'Passed' : 'Not Passed' }}
                                    </span>
                                </div>
                                <div class="fw-semibold text-navy">{{ $attempt->score }} / {{ $attempt->max_score }} ({{ round((float) $attempt->percentage, 1) }}%)</div>
                                <div class="text-muted">{{ $attempt->submitted_at->format('M d, Y H:i') }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
