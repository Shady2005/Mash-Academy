@extends('layouts.student')

@section('title', $finalProject->title)

@section('content')
    <div class="mb-3">
        <a href="{{ route('student.courses.learn', $course) }}" class="btn btn-ma-outline btn-sm">
            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('messages.back_to_player') }}
        </a>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="ma-card p-4 border-start border-4" style="border-inline-start-color: #1e3a8a !important;">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3 pb-3 border-bottom">
                    <div>
                        <span class="badge bg-success mb-2">
                            <i class="bi bi-unlock-fill me-1" aria-hidden="true"></i>Unlocked ({{ $summary['completion_percentage'] }}% / {{ $summary['required_final_project_percentage'] }}% Required)
                        </span>
                        <h1 class="h4 fw-bold mb-1">{{ $finalProject->title }}</h1>
                        <div class="small text-muted">{{ $course->localizedTitle($currentLocale ?? app()->getLocale()) }}</div>
                    </div>
                    @if ($submission)
                        <span class="badge {{ $submission->status === 'reviewed' ? 'bg-success' : 'bg-navy-accent' }} fs-6">
                            {{ ucfirst($submission->status) }}
                        </span>
                    @endif
                </div>

                <div class="mb-4">
                    <h2 class="h6 fw-bold text-uppercase text-muted mb-2">Final Project Brief &amp; Instructions</h2>
                    <div class="p-3 rounded-3 border small" style="background-color: var(--ma-surface-subtle); white-space: pre-line; line-height: 1.75;">{{ $finalProject->instructions }}</div>
                </div>

                @if ($finalProject->hasAttachment())
                    <div class="mb-4">
                        <a href="{{ route('student.courses.final-project.brief', $course) }}" class="btn btn-ma-outline btn-sm">
                            <i class="bi bi-download me-1" aria-hidden="true"></i>Download Reference Brief ({{ $finalProject->attachment_original_name ?: 'Attachment' }})
                        </a>
                    </div>
                @endif

                <hr class="my-4">

                <h2 class="h6 fw-bold mb-3">
                    {{ $submission ? 'Resubmit Final Project Deliverable' : __('messages.submit_final_project') }}
                </h2>

                <form method="POST" action="{{ route('student.courses.final-project.submit', $course) }}" enctype="multipart/form-data">
                    @csrf

                    <div class="mb-3">
                        <label for="fp_file" class="form-label fw-semibold">Project Deliverable File <span class="text-danger">*</span></label>
                        <input
                            type="file"
                            id="fp_file"
                            name="file"
                            accept=".pdf,.doc,.docx,.zip,.png,.jpg,.jpeg"
                            class="form-control @error('file') is-invalid @enderror"
                            required
                        >
                        <div class="form-text">Allowed formats: PDF, DOC, DOCX, ZIP, PNG, JPG · Max 30 MB.</div>
                        @error('file')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-4">
                        <label for="fp_notes" class="form-label fw-semibold">Notes / Repository or Demo Link (Optional)</label>
                        <textarea id="fp_notes" name="notes" rows="3" class="form-control">{{ old('notes', $submission?->notes) }}</textarea>
                    </div>

                    <button type="submit" class="btn btn-ma-primary px-4">
                        <i class="bi bi-award me-1" aria-hidden="true"></i>{{ __('messages.submit_final_project') }}
                    </button>
                </form>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="ma-card p-4">
                <h2 class="h6 fw-bold mb-3">Your Final Project Submission</h2>
                @if (! $submission)
                    <p class="small text-muted mb-0">
                        You have unlocked the Final Project by reaching <strong>{{ $summary['completion_percentage'] }}%</strong> course completion. Upload your deliverable to complete the course.
                    </p>
                @else
                    <dl class="row small mb-3">
                        <dt class="col-5 text-muted">Status</dt>
                        <dd class="col-7 fw-semibold">{{ ucfirst($submission->status) }}</dd>

                        <dt class="col-5 text-muted">Submitted</dt>
                        <dd class="col-7">{{ $submission->submitted_at->format('M d, Y H:i') }}</dd>

                        @if ($submission->grade !== null)
                            <dt class="col-5 text-muted">Grade</dt>
                            <dd class="col-7 fw-bold text-navy">{{ $submission->grade }} / 100</dd>
                        @endif
                    </dl>

                    @if ($submission->feedback)
                        <div class="p-3 rounded border mb-3 small" style="background-color: var(--ma-surface-subtle);">
                            <div class="fw-semibold mb-1">Instructor Feedback</div>
                            <div>{{ $submission->feedback }}</div>
                        </div>
                    @endif

                    <a href="{{ route('student.courses.final-project.submission-file', $course) }}" class="btn btn-ma-outline btn-sm w-100 mb-2">
                        <i class="bi bi-download me-1" aria-hidden="true"></i>{{ $submission->file_original_name }}
                    </a>

                    <a href="{{ route('student.certificates.show', $course) }}" class="btn btn-ma-primary btn-sm w-100">
                        <i class="bi bi-award-fill me-1" aria-hidden="true"></i>View &amp; Download Certificate
                    </a>
                @endif
            </div>
        </div>
    </div>
@endsection
