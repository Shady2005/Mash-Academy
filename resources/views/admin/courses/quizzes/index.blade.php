@extends('layouts.admin')

@section('title', __('messages.quizzes'))
@section('page-title', __('messages.quizzes'))

@section('content')
    @include('admin.courses.partials.tabs', ['course' => $course])

    <div class="row g-4">
        {{-- Create Standalone Quiz --}}
        <div class="col-lg-4">
            <div class="ma-card p-4">
                <h3 class="h6 fw-bold mb-1">Create Standalone Quiz</h3>
                <p class="small text-muted mb-3">Link a quiz to the whole course or to a specific lesson.</p>

                <form method="POST" action="{{ route('admin.courses.quizzes.store', $course) }}">
                    @csrf

                    <div class="mb-3">
                        <label for="quiz_title" class="form-label small fw-semibold">Quiz Title <span class="text-danger">*</span></label>
                        <input type="text" id="quiz_title" name="title" value="{{ old('title') }}" class="form-control" required maxlength="255">
                    </div>

                    <div class="mb-3">
                        <label for="quiz_lesson_id" class="form-label small fw-semibold">Linked Lesson (Optional)</label>
                        <select id="quiz_lesson_id" name="lesson_id" class="form-select">
                            <option value="">Course-level Quiz</option>
                            @foreach ($lessons as $lesson)
                                <option value="{{ $lesson->id }}">#{{ $lesson->position }} — {{ $lesson->title }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="quiz_description" class="form-label small fw-semibold">Description</label>
                        <textarea id="quiz_description" name="description" rows="2" class="form-control">{{ old('description') }}</textarea>
                    </div>

                    <div class="mb-3">
                        <label for="quiz_pass_mark" class="form-label small fw-semibold">Pass Mark (%) <span class="text-danger">*</span></label>
                        <input type="number" min="0" max="100" id="quiz_pass_mark" name="pass_mark" value="{{ old('pass_mark', 70) }}" class="form-control" required>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="is_required_for_next" name="is_required_for_next" value="1">
                        <label class="form-check-label small fw-semibold" for="is_required_for_next">
                            Require passing to unlock next lesson
                        </label>
                    </div>

                    <button type="submit" class="btn btn-ma-primary w-100">
                        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Create Quiz
                    </button>
                </form>
            </div>
        </div>

        {{-- Existing Quizzes & Question Builder --}}
        <div class="col-lg-8">
            @if ($quizzes->isEmpty())
                <div class="ma-card p-5 text-center">
                    <i class="bi bi-patch-question fs-1 text-muted d-block mb-2" aria-hidden="true"></i>
                    <p class="text-muted mb-0">No quizzes created for this course yet.</p>
                </div>
            @else
                <div class="d-flex flex-column gap-4">
                    @foreach ($quizzes as $quiz)
                        <div class="ma-card p-4">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 pb-3 mb-3 border-bottom">
                                <div>
                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                        <h3 class="h6 fw-bold mb-0">{{ $quiz->title }}</h3>
                                        <span class="badge bg-navy-accent">Pass: {{ $quiz->pass_mark }}%</span>
                                        @if ($quiz->is_required_for_next)
                                            <span class="badge bg-black-badge">Gates Next Lesson</span>
                                        @endif
                                    </div>
                                    <div class="small text-muted">
                                        {{ $quiz->lesson ? 'Lesson #'.$quiz->lesson->position.': '.$quiz->lesson->title : 'Course-level Quiz' }}
                                        · {{ $quiz->questions->count() }} questions
                                    </div>
                                    @if ($quiz->description)
                                        <div class="small text-muted mt-1">{{ $quiz->description }}</div>
                                    @endif
                                </div>
                                <form method="POST" action="{{ route('admin.courses.quizzes.destroy', [$course, $quiz]) }}" onsubmit="return confirm('Delete this quiz and all of its questions?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                        <i class="bi bi-trash me-1" aria-hidden="true"></i>Delete Quiz
                                    </button>
                                </form>
                            </div>

                            {{-- Questions List --}}
                            @if ($quiz->questions->isNotEmpty())
                                <div class="d-flex flex-column gap-2 mb-4">
                                    @foreach ($quiz->questions as $question)
                                        <div class="border rounded-3 p-3" style="background-color: var(--ma-surface-subtle);">
                                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                                <div class="fw-semibold small">
                                                    Q{{ $loop->iteration }}. {{ $question->question_text }}
                                                    <span class="badge bg-secondary ms-1">{{ $question->points }} pt</span>
                                                </div>
                                                <form method="POST" action="{{ route('admin.courses.quizzes.questions.destroy', [$course, $quiz, $question]) }}" onsubmit="return confirm('Remove this question?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2" title="Remove question">
                                                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                            </div>
                                            <ul class="list-unstyled mb-0 small d-flex flex-column gap-1">
                                                @foreach ($question->options as $option)
                                                    <li class="{{ $option->is_correct ? 'fw-bold text-success' : 'text-muted' }}">
                                                        <i class="bi {{ $option->is_correct ? 'bi-check-circle-fill' : 'bi-circle' }} me-1" aria-hidden="true"></i>
                                                        {{ $option->option_text }}
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Add Multiple-Choice Question Form --}}
                            <div class="border-top pt-3">
                                <h4 class="small fw-bold text-uppercase text-muted mb-2">Add Multiple-Choice Question</h4>
                                <form method="POST" action="{{ route('admin.courses.quizzes.questions.store', [$course, $quiz]) }}">
                                    @csrf
                                    <div class="row g-2 mb-2">
                                        <div class="col-md-9">
                                            <input type="text" name="question_text" class="form-control form-control-sm" placeholder="Enter question text..." required>
                                        </div>
                                        <div class="col-md-3">
                                            <input type="number" name="points" min="1" max="100" value="1" class="form-control form-control-sm" placeholder="Points" required>
                                        </div>
                                    </div>

                                    <div class="row g-2 mb-2">
                                        @for ($i = 0; $i < 4; $i++)
                                            <div class="col-md-6">
                                                <div class="input-group input-group-sm">
                                                    <div class="input-group-text">
                                                        <input class="form-check-input mt-0" type="radio" name="correct_option" value="{{ $i }}" {{ $i === 0 ? 'checked' : '' }} aria-label="Mark option {{ $i + 1 }} as correct">
                                                    </div>
                                                    <input type="text" name="options[{{ $i }}]" class="form-control" placeholder="Option {{ $i + 1 }}{{ $i < 2 ? ' (required)' : ' (optional)' }}" {{ $i < 2 ? 'required' : '' }}>
                                                </div>
                                            </div>
                                        @endfor
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="small text-muted">Select the radio circle next to the correct answer.</span>
                                        <button type="submit" class="btn btn-ma-primary btn-sm">
                                            <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Add Question
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection
