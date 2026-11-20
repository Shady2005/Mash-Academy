@extends('layouts.student')

@section('title', $assignment->title)

@section('content')
    <div class="mb-3">
        <a href="{{ route('student.courses.learn', [$course, $assignment->lesson]) }}" class="btn btn-ma-outline btn-sm">
            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('messages.back_to_player') }}
        </a>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="ma-card p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3 pb-3 border-bottom">
                    <div>
                        <span class="badge bg-navy-accent mb-2">{{ $assignment->typeLabel() }}</span>
                        <h1 class="h4 fw-bold mb-1">{{ $assignment->title }}</h1>
                        @if ($assignment->due_at)
                            <div class="small text-muted">Due: {{ $assignment->due_at->format('M d, Y H:i') }}</div>
                        @endif
                    </div>
                    @if ($submission)
                        <span class="badge {{ $submission->status === 'reviewed' ? 'bg-success' : 'bg-navy-accent' }} fs-6">
                            {{ ucfirst($submission->status) }}
                        </span>
                    @else
                        <span class="badge bg-secondary fs-6">Pending</span>
                    @endif
                </div>

                @if ($assignment->instructions)
                    <div class="mb-4">
                        <h2 class="h6 fw-bold text-uppercase text-muted mb-2">Assignment Instructions</h2>
                        <div class="p-3 rounded-3 border small" style="background-color: var(--ma-surface-subtle); white-space: pre-line; line-height: 1.7;">{{ $assignment->instructions }}</div>
                    </div>
                @endif

                <form method="POST" action="{{ route('student.courses.assignments.submit', [$course, $assignment]) }}" enctype="multipart/form-data">
                    @csrf

                    {{-- Interactive MCQ Section (if `mcq` or `both`) --}}
                    @if ($assignment->supportsMcq() && $assignment->questions->isNotEmpty())
                        <div class="mb-4">
                            <h2 class="h6 fw-bold mb-3">
                                Interactive Multiple-Choice Questions
                                @if ($assignment->pass_mark !== null)
                                    <span class="badge bg-black-badge ms-1">Pass Mark: {{ $assignment->pass_mark }}%</span>
                                @endif
                            </h2>
                            <div class="d-flex flex-column gap-3">
                                @foreach ($assignment->questions as $question)
                                    @php
                                        $prevChoice = $submission?->mcq_answers[$question->id]['option_id'] ?? null;
                                    @endphp
                                    <fieldset class="border rounded-3 p-3">
                                        <legend class="h6 fw-bold mb-2 fs-6">
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
                                                        {{ (int) $prevChoice === $option->id ? 'checked' : '' }}
                                                        required
                                                    >
                                                    <span class="small">{{ $option->option_text }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </fieldset>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- File Upload Section (if `file` or `both`) --}}
                    @if ($assignment->supportsFile())
                        <div class="mb-3">
                            <label for="assignment_file" class="form-label fw-semibold">
                                Upload Solution File
                                @if (! $submission || ! $submission->hasFile())
                                    <span class="text-danger">*</span>
                                @else
                                    <span class="text-muted small">(optional to replace existing file)</span>
                                @endif
                            </label>
                            <input
                                type="file"
                                id="assignment_file"
                                name="file"
                                accept=".pdf,.doc,.docx,.zip,.png,.jpg,.jpeg"
                                class="form-control @error('file') is-invalid @enderror"
                                {{ (! $submission || ! $submission->hasFile()) ? 'required' : '' }}
                            >
                            <div class="form-text">Allowed formats: PDF, DOC, DOCX, ZIP, PNG, JPG · Max 20 MB.</div>
                            @error('file')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    @endif

                    <div class="mb-4">
                        <label for="notes" class="form-label fw-semibold">Submission Notes (Optional)</label>
                        <textarea id="notes" name="notes" rows="3" class="form-control" placeholder="Add any comments or links for your instructor...">{{ old('notes', $submission?->notes) }}</textarea>
                    </div>

                    <button type="submit" class="btn btn-ma-primary px-4">
                        <i class="bi bi-send-check me-1" aria-hidden="true"></i>
                        {{ $submission ? 'Update / Resubmit Assignment' : __('messages.submit_assignment') }}
                    </button>
                </form>
            </div>
        </div>

        {{-- Submission Status Sidebar --}}
        <div class="col-lg-4">
            <div class="ma-card p-4">
                <h2 class="h6 fw-bold mb-3">Submission Status</h2>
                @if (! $submission)
                    <p class="small text-muted mb-0">Not submitted yet. Complete the required fields and submit.</p>
                @else
                    <dl class="row small mb-3">
                        <dt class="col-5 text-muted">Status</dt>
                        <dd class="col-7 fw-semibold">{{ ucfirst($submission->status) }}</dd>

                        <dt class="col-5 text-muted">Submitted</dt>
                        <dd class="col-7">{{ $submission->submitted_at?->format('M d, Y H:i') }}</dd>

                        @if ($submission->mcq_score !== null)
                            <dt class="col-5 text-muted">MCQ Score</dt>
                            <dd class="col-7 fw-bold text-navy">
                                {{ $submission->mcq_score }}/{{ $submission->mcq_max_score }} ({{ round((float) $submission->mcq_percentage, 1) }}%)
                                <span class="badge {{ $submission->mcq_passed ? 'bg-success' : 'bg-danger' }} ms-1">{{ $submission->mcq_passed ? 'Passed' : 'Failed' }}</span>
                            </dd>
                        @endif

                        @if ($submission->grade !== null)
                            <dt class="col-5 text-muted">Instructor Grade</dt>
                            <dd class="col-7 fw-bold">{{ $submission->grade }} / 100</dd>
                        @endif
                    </dl>

                    @if ($submission->feedback)
                        <div class="p-3 rounded border mb-3 small" style="background-color: var(--ma-surface-subtle);">
                            <div class="fw-semibold mb-1">Instructor Feedback</div>
                            <div>{{ $submission->feedback }}</div>
                        </div>
                    @endif

                    @if ($submission->hasFile())
                        <a href="{{ route('student.courses.assignments.file', [$course, $assignment]) }}" class="btn btn-ma-outline btn-sm w-100">
                            <i class="bi bi-download me-1" aria-hidden="true"></i>{{ $submission->file_original_name ?: 'Download Submitted File' }}
                        </a>
                    @endif
                @endif
            </div>
        </div>
    </div>
@endsection
