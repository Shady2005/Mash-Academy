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
                        <label for="lesson_video" class="form-label small fw-semibold">Video File (MP4 / WebM / MOV / M4V)</label>
                        <input type="file" id="lesson_video" name="video" accept="video/mp4,video/webm,video/quicktime,video/x-m4v,.mp4,.webm,.mov,.m4v" class="form-control @error('video') is-invalid @enderror">
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
                                            <div class="modal-body p-0">
                                                <div class="ma-video-shell">
                                                    <video
                                                        controls
                                                        playsinline
                                                        preload="metadata"
                                                        class="ma-video-player"
                                                        data-codec-check
                                                        data-codec-warning-target="#video-codec-warning-{{ $lesson->id }}"
                                                    >
                                                        <source src="{{ route('admin.courses.lessons.stream', [$course, $lesson]) }}" type="{{ $lesson->video_mime ?: 'video/mp4' }}">
                                                        Your browser does not support HTML5 video playback.
                                                    </video>
                                                </div>
                                                <div id="video-codec-warning-{{ $lesson->id }}" class="alert alert-warning small m-3 d-none" role="alert">
                                                    <div class="fw-semibold mb-1">
                                                        <i class="bi bi-exclamation-triangle-fill me-1" aria-hidden="true"></i>
                                                        Unable to decode the video track in this browser
                                                    </div>
                                                    <div data-codec-warning-detail>
                                                        This video file may be encoded with an unsupported codec such as <strong>HEVC (H.265)</strong>. Please re-encode or export it as a standard <strong>MP4 (H.264 / AVC video + AAC audio)</strong> and re-upload it so all browsers can play it smoothly.
                                                    </div>
                                                </div>
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
                                                    <label class="form-label small fw-semibold">Replace Video (optional MP4 / WebM / MOV / M4V)</label>
                                                    <input type="file" name="video" accept="video/mp4,video/webm,video/quicktime,video/x-m4v,.mp4,.webm,.mov,.m4v" class="form-control">
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

@push('scripts')
<script>
(() => {
    'use strict';

    document.querySelectorAll('[data-upload-progress-form]').forEach((form) => {
        const fileInput = form.querySelector('input[type="file"]');
        const progressContainer = form.querySelector('[data-upload-progress-container]');
        const progressBar = form.querySelector('[data-upload-progress-bar]');
        const progressLabel = form.querySelector('[data-upload-progress-label]');
        const errorBox = form.querySelector('[data-upload-error]');
        const submitBtn = form.querySelector('[data-upload-submit]');

        const showUploadError = (message) => {
            if (!errorBox) return;
            errorBox.textContent = message;
            errorBox.classList.remove('d-none');
        };

        form.addEventListener('submit', (event) => {
            if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                return;
            }

            event.preventDefault();
            errorBox?.classList.add('d-none');
            progressContainer?.classList.remove('d-none');
            if (progressBar) progressBar.style.width = '0%';
            if (progressLabel) progressLabel.textContent = '0%';
            if (submitBtn) submitBtn.disabled = true;

            const xhr = new XMLHttpRequest();
            xhr.open(form.method || 'POST', form.action, true);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

            xhr.upload.addEventListener('progress', (e) => {
                if (!e.lengthComputable) return;
                const pct = Math.min(100, Math.round((e.loaded / e.total) * 100));
                if (progressBar) progressBar.style.width = `${pct}%`;
                if (progressLabel) progressLabel.textContent = `${pct}%`;
            });

            xhr.addEventListener('load', () => {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        const data = JSON.parse(xhr.responseText);
                        window.location.href = data.redirect || window.location.href;
                    } catch {
                        window.location.reload();
                    }
                    return;
                }

                if (submitBtn) submitBtn.disabled = false;

                let msg = '';
                try {
                    const data = JSON.parse(xhr.responseText);
                    if (data && data.errors && typeof data.errors === 'object') {
                        const fieldErrors = Object.values(data.errors).flat().filter(Boolean);
                        if (fieldErrors.length > 0) {
                            msg = fieldErrors.join(' ');
                        }
                    }
                    if (!msg && data && typeof data.message === 'string' && data.message.trim() !== '') {
                        msg = data.message.trim();
                    }
                    if (!msg && data && typeof data.exception === 'string' && data.exception.trim() !== '') {
                        msg = `HTTP ${xhr.status}: ${data.exception}`;
                    }
                } catch {
                    const plain = (xhr.responseText || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                    msg = plain
                        ? `HTTP ${xhr.status} (${xhr.statusText || 'Error'}): ${plain.slice(0, 300)}`
                        : `HTTP ${xhr.status} (${xhr.statusText || 'Upload failed'}).`;
                }

                if (!msg) {
                    msg = `HTTP ${xhr.status} (${xhr.statusText || 'Error'})`;
                }

                showUploadError(msg);
            });

            xhr.addEventListener('error', () => {
                if (submitBtn) submitBtn.disabled = false;
                showUploadError('Network error during upload (connection interrupted or blocked by server limits).');
            });

            xhr.send(new FormData(form));
        });
    });
})();
</script>
@endpush
