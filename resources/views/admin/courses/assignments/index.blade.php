@extends('layouts.admin')

@section('title', __('messages.assignments'))
@section('page-title', __('messages.assignments'))

@section('content')
    @include('admin.courses.partials.tabs', ['course' => $course])

    <div class="row g-4">
        {{-- Create Flexible Assignment (File Upload / MCQ / Both) --}}
        <div class="col-lg-4">
            <div class="ma-card p-4">
                <h3 class="h6 fw-bold mb-1">Create Flexible Assignment</h3>
                <p class="small text-muted mb-3">
                    Choose whether students upload a solution file, answer interactive MCQs, or complete both combined.
                </p>

                <form method="POST" action="{{ route('admin.courses.assignments.store', $course) }}" data-assignment-type-form>
                    @csrf

                    <div class="mb-3">
                        <label for="assignment_title" class="form-label small fw-semibold">Assignment Title <span class="text-danger">*</span></label>
                        <input type="text" id="assignment_title" name="title" value="{{ old('title') }}" class="form-control" required maxlength="255">
                    </div>

                    <div class="mb-3">
                        <label for="submission_type" class="form-label small fw-semibold">Assignment Format <span class="text-danger">*</span></label>
                        <select id="submission_type" name="submission_type" class="form-select" data-submission-type-select required>
                            <option value="file" {{ old('submission_type', 'file') === 'file' ? 'selected' : '' }}>{{ __('messages.assignment_type_file') }}</option>
                            <option value="mcq" {{ old('submission_type') === 'mcq' ? 'selected' : '' }}>{{ __('messages.assignment_type_mcq') }}</option>
                            <option value="both" {{ old('submission_type') === 'both' ? 'selected' : '' }}>{{ __('messages.assignment_type_both') }}</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="assignment_lesson_id" class="form-label small fw-semibold">Linked Lesson (Optional)</label>
                        <select id="assignment_lesson_id" name="lesson_id" class="form-select">
                            <option value="">Course-level Assignment</option>
                            @foreach ($lessons as $lesson)
                                <option value="{{ $lesson->id }}">#{{ $lesson->position }} — {{ $lesson->title }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3" data-file-fields>
                        <label for="assignment_instructions" class="form-label small fw-semibold">Written Instructions <span class="text-danger" data-instructions-required>*</span></label>
                        <textarea id="assignment_instructions" name="instructions" rows="4" class="form-control" placeholder="Describe what the student must build/upload...">{{ old('instructions') }}</textarea>
                    </div>

                    <div class="mb-3 d-none" data-mcq-fields>
                        <label for="assignment_pass_mark" class="form-label small fw-semibold">MCQ Pass Mark (%) <span class="text-danger">*</span></label>
                        <input type="number" min="0" max="100" id="assignment_pass_mark" name="pass_mark" value="{{ old('pass_mark', 70) }}" class="form-control">
                        <div class="form-text">After creating the assignment, add MCQ questions to it on the right.</div>
                    </div>

                    <div class="mb-3">
                        <label for="due_at" class="form-label small fw-semibold">Optional Due Date</label>
                        <input type="datetime-local" id="due_at" name="due_at" value="{{ old('due_at') }}" class="form-control">
                    </div>

                    <button type="submit" class="btn btn-ma-primary w-100">
                        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Create Assignment
                    </button>
                </form>
            </div>
        </div>

        {{-- Existing Flexible Assignments --}}
        <div class="col-lg-8">
            @if ($assignments->isEmpty())
                <div class="ma-card p-5 text-center">
                    <i class="bi bi-file-earmark-check fs-1 text-muted d-block mb-2" aria-hidden="true"></i>
                    <p class="text-muted mb-0">No assignments created for this course yet.</p>
                </div>
            @else
                <div class="d-flex flex-column gap-4">
                    @foreach ($assignments as $assignment)
                        <div class="ma-card p-4">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 pb-3 mb-3 border-bottom">
                                <div>
                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                        <h3 class="h6 fw-bold mb-0">{{ $assignment->title }}</h3>
                                        <span class="badge bg-navy-accent">{{ $assignment->typeLabel() }}</span>
                                        @if ($assignment->supportsMcq() && $assignment->pass_mark !== null)
                                            <span class="badge bg-black-badge">MCQ Pass: {{ $assignment->pass_mark }}%</span>
                                        @endif
                                    </div>
                                    <div class="small text-muted">
                                        {{ $assignment->lesson ? 'Lesson #'.$assignment->lesson->position.': '.$assignment->lesson->title : 'Course-level Assignment' }}
                                        @if ($assignment->due_at)
                                            · Due {{ $assignment->due_at->format('M d, Y H:i') }}
                                        @endif
                                    </div>
                                </div>

                                <form method="POST" action="{{ route('admin.courses.assignments.destroy', [$course, $assignment]) }}" onsubmit="return confirm('Delete this assignment?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                        <i class="bi bi-trash me-1" aria-hidden="true"></i>Delete
                                    </button>
                                </form>
                            </div>

                            @if ($assignment->instructions)
                                <div class="mb-3">
                                    <div class="small fw-semibold text-muted text-uppercase mb-1">Instructions</div>
                                    <div class="small p-3 rounded border" style="background-color: var(--ma-surface-subtle); white-space: pre-line;">{{ $assignment->instructions }}</div>
                                </div>
                            @endif

                            @if ($assignment->supportsMcq())
                                <div class="mt-3">
                                    <div class="small fw-bold text-uppercase text-muted mb-2">
                                        Interactive MCQ Questions ({{ $assignment->questions->count() }})
                                    </div>

                                    @if ($assignment->questions->isNotEmpty())
                                        <div class="d-flex flex-column gap-2 mb-3">
                                            @foreach ($assignment->questions as $question)
                                                <div class="border rounded-3 p-3" style="background-color: var(--ma-surface-subtle);">
                                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                                        <div class="fw-semibold small">
                                                            Q{{ $loop->iteration }}. {{ $question->question_text }}
                                                            <span class="badge bg-secondary ms-1">{{ $question->points }} pt</span>
                                                        </div>
                                                        <form method="POST" action="{{ route('admin.courses.assignments.questions.destroy', [$course, $assignment, $question]) }}" onsubmit="return confirm('Remove this MCQ question?');">
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

                                    <div class="border-top pt-3">
                                        <h4 class="small fw-bold text-muted mb-2">Add MCQ Question to Assignment</h4>
                                        <form method="POST" action="{{ route('admin.courses.assignments.questions.store', [$course, $assignment]) }}">
                                            @csrf
                                            <div class="row g-2 mb-2">
                                                <div class="col-md-9">
                                                    <input type="text" name="question_text" class="form-control form-control-sm" placeholder="Enter MCQ question..." required>
                                                </div>
                                                <div class="col-md-3">
                                                    <input type="number" name="points" min="1" max="100" value="1" class="form-control form-control-sm" required>
                                                </div>
                                            </div>
                                            <div class="row g-2 mb-2">
                                                @for ($i = 0; $i < 4; $i++)
                                                    <div class="col-md-6">
                                                        <div class="input-group input-group-sm">
                                                            <div class="input-group-text">
                                                                <input class="form-check-input mt-0" type="radio" name="correct_option" value="{{ $i }}" {{ $i === 0 ? 'checked' : '' }}>
                                                            </div>
                                                            <input type="text" name="options[{{ $i }}]" class="form-control" placeholder="Option {{ $i + 1 }}{{ $i < 2 ? ' (required)' : ' (optional)' }}" {{ $i < 2 ? 'required' : '' }}>
                                                        </div>
                                                    </div>
                                                @endfor
                                            </div>
                                            <div class="d-flex justify-content-end">
                                                <button type="submit" class="btn btn-ma-primary btn-sm">
                                                    <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Add MCQ Question
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection
