@extends('layouts.admin')

@section('title', __('messages.final_project'))
@section('page-title', __('messages.final_project'))

@section('content')
    @include('admin.courses.partials.tabs', ['course' => $course])

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="ma-card p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div>
                        <h3 class="h5 fw-bold mb-1">Course Final Project &amp; 95% Unlock Gate</h3>
                        <p class="small text-muted mb-0">
                            The Final Project remains strictly locked for students until they complete at least the required percentage (default <strong>95%</strong>) of the course. Completing it unlocks their Course Certificate in Phase 4.
                        </p>
                    </div>
                    @if ($finalProject)
                        <span class="badge bg-navy-accent">Configured ({{ $finalProject->required_completion_percentage }}% Gate)</span>
                    @endif
                </div>

                <form method="POST" action="{{ route('admin.courses.final-project.upsert', $course) }}" enctype="multipart/form-data" class="ma-form">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="fp_title" class="form-label fw-semibold">Final Project Title <span class="text-danger">*</span></label>
                        <input
                            type="text"
                            id="fp_title"
                            name="title"
                            value="{{ old('title', $finalProject?->title ?? 'Capstone Final Project') }}"
                            class="form-control @error('title') is-invalid @enderror"
                            required
                            maxlength="255"
                        >
                        @error('title')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="fp_instructions" class="form-label fw-semibold">Project Brief &amp; Deliverable Instructions <span class="text-danger">*</span></label>
                        <textarea
                            id="fp_instructions"
                            name="instructions"
                            rows="8"
                            class="form-control @error('instructions') is-invalid @enderror"
                            required
                            minlength="20"
                        >{{ old('instructions', $finalProject?->instructions ?? '') }}</textarea>
                        @error('instructions')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="required_completion_percentage" class="form-label fw-semibold">Required Course Completion Gate (%) <span class="text-danger">*</span></label>
                            <input
                                type="number"
                                min="50"
                                max="100"
                                id="required_completion_percentage"
                                name="required_completion_percentage"
                                value="{{ old('required_completion_percentage', $finalProject?->required_completion_percentage ?? 95) }}"
                                class="form-control @error('required_completion_percentage') is-invalid @enderror"
                                required
                            >
                            <div class="form-text">Recommended &amp; default: <strong>95%</strong>.</div>
                        </div>

                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="fp_published" name="is_published" value="1" {{ old('is_published', $finalProject?->is_published ?? true) ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="fp_published">Active &amp; Published on Course</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="fp_attachment" class="form-label fw-semibold">Reference Brief / Starter File (Optional)</label>
                        <input type="file" id="fp_attachment" name="attachment" accept=".pdf,.doc,.docx,.zip,.png,.jpg,.jpeg" class="form-control @error('attachment') is-invalid @enderror">
                        <div class="form-text">Stored privately. Allowed: PDF, DOC, DOCX, ZIP, PNG, JPG · Max 20 MB.</div>
                        @error('attachment')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <button type="submit" class="btn btn-ma-primary px-4">
                        <i class="bi bi-check-lg me-1" aria-hidden="true"></i>Save Final Project
                    </button>
                </form>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="ma-card p-4 mb-4">
                <h3 class="h6 fw-bold mb-2">Unlock &amp; Certificate Flow</h3>
                <ol class="small text-muted ps-3 mb-0 d-flex flex-column gap-2">
                    <li>Student watches lessons and completes required quizzes/assignments.</li>
                    <li>Final Project stays <strong>locked</strong> below <strong>{{ $finalProject?->required_completion_percentage ?? 95 }}%</strong> completion.</li>
                    <li>At <strong>≥ {{ $finalProject?->required_completion_percentage ?? 95 }}%</strong>, Final Project unlocks for submission (Phase 4).</li>
                    <li>Submitting/completing the Final Project unlocks the student's downloadable <strong>Certificate</strong> (Phase 4 &amp; 5).</li>
                </ol>
            </div>

            @if ($finalProject)
                <div class="ma-card p-4">
                    @if ($finalProject->hasAttachment())
                        <div class="mb-3 pb-3 border-bottom">
                            <div class="small fw-semibold mb-2">Current Reference Attachment</div>
                            <a href="{{ route('admin.courses.final-project.attachment', $course) }}" class="btn btn-ma-outline btn-sm w-100">
                                <i class="bi bi-download me-1" aria-hidden="true"></i>{{ $finalProject->attachment_original_name ?: 'Download Brief' }}
                            </a>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.courses.final-project.destroy', $course) }}" onsubmit="return confirm('Remove the Final Project from this course?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                            <i class="bi bi-trash me-1" aria-hidden="true"></i>Remove Final Project
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
@endsection
