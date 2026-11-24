@extends('layouts.admin')

@section('title', __('messages.edit_course'))
@section('page-title', __('messages.edit_course'))

@section('content')
    @include('admin.courses.partials.tabs', ['course' => $course])

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="ma-card p-4">
                <h3 class="h6 fw-bold mb-3">Course Details &amp; Visibility</h3>

                <form method="POST" action="{{ route('admin.courses.update', $course) }}" enctype="multipart/form-data" class="ma-form">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <div class="col-md-6 ma-field">
                            <label for="title" class="form-label fw-semibold">Course Title (English) <span class="text-danger">*</span></label>
                            <input type="text" id="title" name="title" value="{{ old('title', $course->title) }}" class="form-control @error('title') is-invalid @enderror" required maxlength="255">
                            @error('title')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6 ma-field">
                            <label for="title_ar" class="form-label fw-semibold">Course Title (Arabic)</label>
                            <input type="text" id="title_ar" name="title_ar" dir="rtl" value="{{ old('title_ar', $course->title_ar) }}" class="form-control" maxlength="255">
                        </div>

                        @if (\App\Models\AcademySetting::isAiAdminToolsEnabled())
                            <div class="col-12">
                                <div class="border rounded-3 p-3 d-flex flex-wrap align-items-center justify-content-between gap-2" style="background-color: var(--ma-surface-subtle);">
                                    <div class="small">
                                        <div class="fw-semibold text-navy">
                                            <i class="bi bi-stars me-1" aria-hidden="true"></i>AI Bilingual Description Generator
                                        </div>
                                        <div class="text-muted">Generate polished English &amp; Arabic short and full descriptions from the Course Title.</div>
                                    </div>
                                    <button
                                        type="button"
                                        class="btn btn-ma-dark btn-sm text-nowrap"
                                        data-ai-course-description-btn
                                        data-ai-url="{{ route('admin.ai.course-description') }}"
                                    >
                                        <i class="bi bi-stars me-1" aria-hidden="true"></i>{{ __('messages.ai_generate_description') }}
                                    </button>
                                    <div id="ai-course-desc-feedback" class="alert small py-2 px-3 mb-0 w-100 d-none" role="status"></div>
                                </div>
                            </div>
                        @endif

                        <div class="col-md-6 ma-field">
                            <label for="short_description" class="form-label fw-semibold">Short Summary (English)</label>
                            <textarea id="short_description" name="short_description" rows="2" class="form-control" maxlength="500">{{ old('short_description', $course->short_description) }}</textarea>
                        </div>

                        <div class="col-md-6 ma-field">
                            <label for="short_description_ar" class="form-label fw-semibold">Short Summary (Arabic)</label>
                            <textarea id="short_description_ar" name="short_description_ar" dir="rtl" rows="2" class="form-control" maxlength="500">{{ old('short_description_ar', $course->short_description_ar) }}</textarea>
                        </div>

                        <div class="col-md-6 ma-field">
                            <label for="description" class="form-label fw-semibold">Full Description (English) <span class="text-danger">*</span></label>
                            <textarea id="description" name="description" rows="5" class="form-control @error('description') is-invalid @enderror" required minlength="10">{{ old('description', $course->description) }}</textarea>
                            @error('description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6 ma-field">
                            <label for="description_ar" class="form-label fw-semibold">Full Description (Arabic)</label>
                            <textarea id="description_ar" name="description_ar" dir="rtl" rows="5" class="form-control">{{ old('description_ar', $course->description_ar) }}</textarea>
                        </div>

                        <div class="col-md-4 ma-field">
                            <label for="price" class="form-label fw-semibold">Price <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" id="price" name="price" value="{{ old('price', $course->price) }}" class="form-control @error('price') is-invalid @enderror" required>
                        </div>

                        <div class="col-md-4 ma-field">
                            <label for="currency" class="form-label fw-semibold">Currency</label>
                            <input type="text" id="currency" name="currency" value="{{ old('currency', $course->currency) }}" class="form-control" maxlength="10">
                        </div>

                        <div class="col-md-4 ma-field">
                            <label for="thumbnail" class="form-label fw-semibold">Replace Thumbnail</label>
                            <input type="file" id="thumbnail" name="thumbnail" accept=".png,.jpg,.jpeg,.webp" class="form-control @error('thumbnail') is-invalid @enderror">
                            @error('thumbnail')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12"><hr class="my-1"></div>

                        <div class="col-12">
                            <h3 class="h6 fw-bold mb-1">
                                <i class="bi bi-qr-code-scan me-1" aria-hidden="true"></i>{{ __('messages.course_instapay_qr') }} (Optional Override)
                            </h3>
                            <p class="small text-muted mb-2">
                                Upload a course-specific InstaPay QR code. Leave blank to fall back to the instructor's personal default QR or the Academy Global QR.
                            </p>
                        </div>

                        <div class="col-md-6 ma-field">
                            <label for="instapay_qr" class="form-label fw-semibold">Upload / Replace Course InstaPay QR</label>
                            <input type="file" id="instapay_qr" name="instapay_qr" accept=".png,.jpg,.jpeg,.webp" class="form-control @error('instapay_qr') is-invalid @enderror">
                            <div class="form-text">PNG, JPG, WebP · Max 4 MB</div>
                            @error('instapay_qr')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6 ma-field">
                            <label for="instapay_note" class="form-label fw-semibold">Course InstaPay Handle / Note</label>
                            <input type="text" id="instapay_note" name="instapay_note" value="{{ old('instapay_note', $course->instapay_note) }}"
                                   placeholder="e.g. instructor@instapay — Transfer exact course fee"
                                   class="form-control @error('instapay_note') is-invalid @enderror" maxlength="255">
                            @error('instapay_note')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12"><hr class="my-1"></div>

                        <div class="col-12">
                            <h3 class="h6 fw-bold mb-1">
                                <i class="bi bi-play-btn-fill me-1 text-navy" aria-hidden="true"></i>Optional Free Demo / Preview Video
                            </h3>
                            <p class="small text-muted mb-2">Configure a free preview clip shown on the public Course Details page when Demo Videos are enabled.</p>
                        </div>

                        <div class="col-md-4">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" id="has_demo_video" name="has_demo_video" value="1" {{ old('has_demo_video', $course->has_demo_video) ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="has_demo_video">Enable Demo Video</label>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label for="demo_video_type" class="form-label small fw-semibold">Demo Video Source</label>
                            <select id="demo_video_type" name="demo_video_type" class="form-select form-select-sm">
                                <option value="youtube" {{ old('demo_video_type', $course->demo_video_type ?: 'youtube') === 'youtube' ? 'selected' : '' }}>YouTube URL / ID</option>
                                <option value="bunny" {{ old('demo_video_type', $course->demo_video_type) === 'bunny' ? 'selected' : '' }}>Bunny.net Stream ID / URL</option>
                                <option value="upload" {{ old('demo_video_type', $course->demo_video_type) === 'upload' ? 'selected' : '' }}>Direct Upload (MP4 / WebM)</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="demo_video_value" class="form-label small fw-semibold">YouTube / Bunny URL or Video ID</label>
                            <input type="text" id="demo_video_value" name="demo_video_value" value="{{ old('demo_video_value', $course->demo_video_type !== 'upload' ? $course->demo_video_value : '') }}" class="form-control form-control-sm" placeholder="https://youtu.be/... or Bunny ID">
                        </div>

                        <div class="col-md-6">
                            <label for="demo_video_file" class="form-label small fw-semibold">Upload / Replace Demo Video File</label>
                            <input type="file" id="demo_video_file" name="demo_video_file" accept="video/mp4,video/webm,.mp4,.webm,.mov,.m4v" class="form-control form-control-sm">
                            @if ($course->demo_video_type === 'upload' && $course->demo_video_value)
                                <div class="form-text text-success">Uploaded demo file active: <code>{{ basename($course->demo_video_value) }}</code></div>
                            @endif
                        </div>

                        <div class="col-12"><hr class="my-1"></div>

                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_published" name="is_published" value="1" {{ old('is_published', $course->is_published) ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="is_published">Publish Publicly</label>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured" value="1" {{ old('is_featured', $course->is_featured) ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="is_featured">Feature on Home Page</label>
                            </div>
                            <div class="form-text">Other featured courses: <strong>{{ $featuredCount }} / 3</strong>.</div>
                            @error('is_featured')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4">
                            <label for="featured_order" class="form-label fw-semibold">Featured Order (1–3)</label>
                            <select id="featured_order" name="featured_order" class="form-select">
                                <option value="">Auto</option>
                                <option value="1" {{ old('featured_order', $course->featured_order) == 1 ? 'selected' : '' }}>Position 1</option>
                                <option value="2" {{ old('featured_order', $course->featured_order) == 2 ? 'selected' : '' }}>Position 2</option>
                                <option value="3" {{ old('featured_order', $course->featured_order) == 3 ? 'selected' : '' }}>Position 3</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-ma-primary px-4">
                            <i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ __('messages.save_changes') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="ma-card p-4 mb-4">
                <h3 class="h6 fw-bold mb-3">Thumbnail Preview</h3>
                @if ($course->thumbnailUrl())
                    <img src="{{ $course->thumbnailUrl() }}" alt="{{ $course->title }}" class="img-fluid rounded border w-100" style="aspect-ratio: 16 / 9; object-fit: cover;">
                @else
                    <div class="ma-course-thumb rounded p-4 text-center">
                        <span class="small opacity-75">No thumbnail uploaded</span>
                    </div>
                @endif
            </div>

            <div class="ma-card p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="h6 fw-bold mb-0">
                        <i class="bi bi-qr-code-scan me-1" aria-hidden="true"></i>Active Checkout QR
                    </h3>
                    @php $qrSource = $resolvedInstaPay['source'] ?? 'none'; @endphp
                    @if ($qrSource === 'course')
                        <span class="badge bg-navy-accent">Course QR</span>
                    @elseif ($qrSource === 'instructor')
                        <span class="badge bg-navy">Instructor QR</span>
                    @elseif ($qrSource === 'global')
                        <span class="badge bg-secondary">Academy QR</span>
                    @else
                        <span class="badge bg-warning text-dark">Not Set</span>
                    @endif
                </div>

                @if (! empty($resolvedInstaPay['url']))
                    <div class="p-3 bg-white rounded border text-center mb-3">
                        <img src="{{ $resolvedInstaPay['url'] }}" alt="Active InstaPay QR" class="img-fluid" style="max-height: 190px;">
                    </div>
                    @if (! empty($resolvedInstaPay['note']))
                        <div class="small text-muted text-center mb-3">{{ $resolvedInstaPay['note'] }}</div>
                    @endif
                @else
                    <div class="alert alert-warning small mb-3">
                        No InstaPay QR configured yet for this course, its instructor, or the global academy settings.
                    </div>
                @endif

                @if ($course->instapay_qr_path)
                    <form method="POST" action="{{ route('admin.courses.instapay-qr.destroy', $course) }}"
                          onsubmit="return confirm('Remove this course-specific InstaPay QR and revert to instructor/global default?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                            <i class="bi bi-trash me-1" aria-hidden="true"></i>Remove Course QR Override
                        </button>
                    </form>
                @else
                    <a href="{{ route('admin.instructor-profile.edit') }}" class="btn btn-ma-outline btn-sm w-100">
                        <i class="bi bi-person-badge me-1" aria-hidden="true"></i>Manage Instructor Default QR
                    </a>
                @endif
            </div>

            @can('delete', $course)
                <div class="ma-card p-4 border-danger">
                    <h3 class="h6 fw-bold text-danger mb-2">Danger Zone</h3>
                    <p class="small text-muted mb-3">Deleting this course removes all its lessons, uploaded videos, quizzes, assignments, and Final Project.</p>
                    <form method="POST" action="{{ route('admin.courses.destroy', $course) }}" onsubmit="return confirm('Delete this course and all of its content permanently?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm w-100">
                            <i class="bi bi-trash me-1" aria-hidden="true"></i>{{ __('messages.delete_course') }}
                        </button>
                    </form>
                </div>
            @endcan
        </div>
    </div>
@endsection
