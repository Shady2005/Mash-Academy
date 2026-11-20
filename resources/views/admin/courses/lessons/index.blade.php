@extends('layouts.admin')

@section('title', __('messages.lessons_videos'))
@section('page-title', __('messages.lessons_videos'))

@section('content')
    @include('admin.courses.partials.tabs', ['course' => $course])

    <div class="row g-4">
        {{-- Add Lesson & Upload Video --}}
        <div class="col-lg-5">
            <div class="ma-card p-4">
                <h3 class="h6 fw-bold mb-1">Add Lesson &amp; Upload Video</h3>
                <p class="text-muted small mb-3">
                    Videos are stored in <code>storage/app/private</code> and streamed only through authenticated routes.
                    Server PHP limits: <strong>upload_max_filesize={{ $phpUploadMax }}</strong>, <strong>post_max_size={{ $phpPostMax }}</strong> (App limit: {{ $appMaxMb }} MB).
                </p>

                <form
                    id="lesson-upload-form"
                    method="POST"
                    action="{{ route('admin.courses.lessons.store', $course) }}"
                    enctype="multipart/form-data"
                    data-upload-progress-form
                >
                    @csrf

                    <div class="mb-3">
                        <label for="lesson_title" class="form-label small fw-semibold">Lesson Title <span class="text-danger">*</span></label>
                        <input type="text" id="lesson_title" name="title" value="{{ old('title') }}" class="form-control @error('title') is-invalid @enderror" required maxlength="255">
                        @error('title')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="lesson_description" class="form-label small fw-semibold">Description</label>
                        <textarea id="lesson_description" name="description" rows="2" class="form-control">{{ old('description') }}</textarea>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="duration_seconds" class="form-label small fw-semibold">Duration (seconds)</label>
                            <input type="number" min="0" id="duration_seconds" name="duration_seconds" value="{{ old('duration_seconds') }}" class="form-control" placeholder="e.g. 600">
                        </div>
                        <div class="col-6 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="lesson_published" name="is_published" value="1" checked>
                                <label class="form-check-label small fw-semibold" for="lesson_published">Published</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="lesson_video" class="form-label small fw-semibold">Video File (MP4 / WebM)</label>
                        <input type="file" id="lesson_video" name="video" accept="video/mp4,video/webm,.mp4,.webm" class="form-control @error('video') is-invalid @enderror">
                        @error('video')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    {{-- Real-time Upload Progress Bar --}}
                    <div class="mb-3 d-none" data-upload-progress-container>
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="fw-semibold">Uploading video...</span>
                            <span data-upload-progress-label>0%</span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-navy-accent" role="progressbar" style="width: 0%;" data-upload-progress-bar></div>
                        </div>
                    </div>
                    <div class="alert alert-danger small d-none" data-upload-error></div>

                    <button type="submit" class="btn btn-ma-primary w-100" data-upload-submit>
                        <i class="bi bi-cloud-arrow-up me-1" aria-hidden="true"></i>Add Lesson
                    </button>
                </form>
            </div>
        </div>

        {{-- Ordered Lessons List --}}
        <div class="col-lg-7">
            <div class="ma-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="h6 fw-bold mb-0">Ordered Lessons ({{ $lessons->count() }})</h3>
                    <span class="small text-muted">Sequential drip-lock follows this exact order</span>
                </div>

                @if ($lessons->isEmpty())
                    <p class="text-muted small mb-0">No lessons added to this course yet.</p>
                @else
                    <div class="d-flex flex-column gap-3">
                        @foreach ($lessons as $lesson)
                            <div class="border rounded-3 p-3">
                                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                                    <div class="d-flex align-items-start gap-2">
                                        <span class="badge bg-navy rounded-pill mt-1">#{{ $lesson->position }}</span>
                                        <div>
                                            <div class="fw-semibold">{{ $lesson->title }}</div>
                                            @if ($lesson->description)
                                                <div class="small text-muted">{{ $lesson->description }}</div>
                                            @endif
                                            <div class="small text-muted mt-1">
                                                @if ($lesson->hasVideo())
                                                    <span class="badge bg-navy-accent me-1"><i class="bi bi-film me-1" aria-hidden="true"></i>Video Uploaded</span>
                                                    <span>{{ $lesson->video_original_name }} ({{ $lesson->formattedFileSize() }})</span>
                                                @else
                                                    <span class="badge bg-secondary me-1">No Video Uploaded</span>
                                                @endif
                                                · Duration: {{ $lesson->formattedDuration() }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex align-items-center gap-1">
                                        {{-- Reorder Up/Down --}}
                                        <form method="POST" action="{{ route('admin.courses.lessons.move', [$course, $lesson]) }}">
                                            @csrf
                                            <input type="hidden" name="direction" value="up">
                                            <button type="submit" class="btn btn-ma-pref btn-sm" {{ $loop->first ? 'disabled' : '' }} title="Move up">
                                                <i class="bi bi-arrow-up" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.courses.lessons.move', [$course, $lesson]) }}">
                                            @csrf
                                            <input type="hidden" name="direction" value="down">
                                            <button type="submit" class="btn btn-ma-pref btn-sm" {{ $loop->last ? 'disabled' : '' }} title="Move down">
                                                <i class="bi bi-arrow-down" aria-hidden="true"></i>
                                            </button>
                                        </form>

                                        @if ($lesson->hasVideo())
                                            <button type="button" class="btn btn-ma-primary btn-sm" data-bs-toggle="modal" data-bs-target="#previewLessonModal{{ $lesson->id }}" title="Preview private video">
                                                <i class="bi bi-play-fill" aria-hidden="true"></i>
                                            </button>
                                        @endif

                                        <button type="button" class="btn btn-ma-outline btn-sm" data-bs-toggle="modal" data-bs-target="#editLessonModal{{ $lesson->id }}" title="Edit lesson">
                                            <i class="bi bi-pencil" aria-hidden="true"></i>
                                        </button>

                                        <form method="POST" action="{{ route('admin.courses.lessons.destroy', [$course, $lesson]) }}" onsubmit="return confirm('Delete lesson and its uploaded video file?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete lesson">
                                                <i class="bi bi-trash" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            {{-- Private Video Preview Modal --}}
                            @if ($lesson->hasVideo())
                                <div class="modal fade" id="previewLessonModal{{ $lesson->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h4 class="modal-title h6 fw-bold">Admin Video Preview — {{ $lesson->title }}</h4>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body p-0 bg-black">
                                                <video controls preload="metadata" class="w-100" style="max-height: 460px;">
                                                    <source src="{{ route('admin.courses.lessons.stream', [$course, $lesson]) }}" type="{{ $lesson->video_mime ?: 'video/mp4' }}">
                                                </video>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            {{-- Edit Lesson Modal --}}
                            <div class="modal fade" id="editLessonModal{{ $lesson->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="POST" action="{{ route('admin.courses.lessons.update', [$course, $lesson]) }}" enctype="multipart/form-data">
                                            @csrf
                                            @method('PUT')
                                            <div class="modal-header">
                                                <h4 class="modal-title h6 fw-bold">Edit Lesson #{{ $lesson->position }}</h4>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label small fw-semibold">Title</label>
                                                    <input type="text" name="title" value="{{ $lesson->title }}" class="form-control" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label small fw-semibold">Description</label>
                                                    <textarea name="description" rows="2" class="form-control">{{ $lesson->description }}</textarea>
                                                </div>
                                                <div class="row g-2 mb-3">
                                                    <div class="col-6">
                                                        <label class="form-label small fw-semibold">Duration (seconds)</label>
                                                        <input type="number" min="0" name="duration_seconds" value="{{ $lesson->duration_seconds }}" class="form-control">
                                                    </div>
                                                    <div class="col-6 d-flex align-items-end">
                                                        <div class="form-check form-switch mb-2">
                                                            <input class="form-check-input" type="checkbox" name="is_published" value="1" {{ $lesson->is_published ? 'checked' : '' }}>
                                                            <label class="form-check-label small fw-semibold">Published</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label small fw-semibold">Replace Video (optional MP4/WebM)</label>
                                                    <input type="file" name="video" accept="video/mp4,video/webm,.mp4,.webm" class="form-control">
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">{{ __('messages.cancel') }}</button>
                                                <button type="submit" class="btn btn-ma-primary btn-sm">{{ __('messages.save_changes') }}</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
