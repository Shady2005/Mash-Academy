@extends('layouts.admin')

@section('title', __('messages.create_course'))
@section('page-title', __('messages.create_course'))

@section('content')
    <div class="ma-card p-4" style="max-width: 920px;">
        <div class="mb-4 pb-3 border-bottom d-flex justify-content-between align-items-center">
            <div>
                <h2 class="h5 fw-bold mb-1">New Course</h2>
                <p class="text-muted small mb-0">After creating the course, you can upload videos, build quizzes &amp; flexible assignments, and configure the Final Project.</p>
            </div>
            <a href="{{ route('admin.courses.index') }}" class="btn btn-ma-outline btn-sm">{{ __('messages.back_to_courses') }}</a>
        </div>

        <form method="POST" action="{{ route('admin.courses.store') }}" enctype="multipart/form-data" class="ma-form">
            @csrf

            <div class="row g-3">
                <div class="col-md-6 ma-field">
                    <label for="title" class="form-label fw-semibold">Course Title (English) <span class="text-danger">*</span></label>
                    <input type="text" id="title" name="title" value="{{ old('title') }}" class="form-control @error('title') is-invalid @enderror" required maxlength="255">
                    @error('title')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6 ma-field">
                    <label for="title_ar" class="form-label fw-semibold">Course Title (Arabic / العنوان بالعربية)</label>
                    <input type="text" id="title_ar" name="title_ar" dir="rtl" value="{{ old('title_ar') }}" class="form-control @error('title_ar') is-invalid @enderror" maxlength="255">
                    @error('title_ar')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6 ma-field">
                    <label for="short_description" class="form-label fw-semibold">Short Summary (English)</label>
                    <textarea id="short_description" name="short_description" rows="2" class="form-control @error('short_description') is-invalid @enderror" maxlength="500">{{ old('short_description') }}</textarea>
                </div>

                <div class="col-md-6 ma-field">
                    <label for="short_description_ar" class="form-label fw-semibold">Short Summary (Arabic / ملخص قصير)</label>
                    <textarea id="short_description_ar" name="short_description_ar" dir="rtl" rows="2" class="form-control @error('short_description_ar') is-invalid @enderror" maxlength="500">{{ old('short_description_ar') }}</textarea>
                </div>

                <div class="col-md-6 ma-field">
                    <label for="description" class="form-label fw-semibold">Full Description (English) <span class="text-danger">*</span></label>
                    <textarea id="description" name="description" rows="5" class="form-control @error('description') is-invalid @enderror" required minlength="10">{{ old('description') }}</textarea>
                    @error('description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6 ma-field">
                    <label for="description_ar" class="form-label fw-semibold">Full Description (Arabic / الوصف الكامل)</label>
                    <textarea id="description_ar" name="description_ar" dir="rtl" rows="5" class="form-control @error('description_ar') is-invalid @enderror">{{ old('description_ar') }}</textarea>
                </div>

                <div class="col-md-4 ma-field">
                    <label for="price" class="form-label fw-semibold">Price (EGP) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0" id="price" name="price" value="{{ old('price', '1000') }}" class="form-control @error('price') is-invalid @enderror" required>
                    @error('price')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4 ma-field">
                    <label for="currency" class="form-label fw-semibold">Currency</label>
                    <input type="text" id="currency" name="currency" value="{{ old('currency', 'EGP') }}" class="form-control" maxlength="10">
                </div>

                <div class="col-md-4 ma-field">
                    <label for="thumbnail" class="form-label fw-semibold">Course Thumbnail</label>
                    <input type="file" id="thumbnail" name="thumbnail" accept=".png,.jpg,.jpeg,.webp" class="form-control @error('thumbnail') is-invalid @enderror">
                    <div class="form-text">PNG, JPG, WebP · Max 2 MB</div>
                    @error('thumbnail')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-12"><hr class="my-1"></div>

                <div class="col-12">
                    <h3 class="h6 fw-bold mb-1">
                        <i class="bi bi-qr-code-scan me-1" aria-hidden="true"></i>{{ __('messages.course_instapay_qr') }} (Optional Override)
                    </h3>
                    <p class="small text-muted mb-2">
                        Upload a dedicated InstaPay QR for this course. If left blank, students will automatically see your
                        <a href="{{ route('admin.instructor-profile.edit') }}">personal default InstaPay QR</a> (or the Academy Global QR).
                    </p>
                </div>

                <div class="col-md-6 ma-field">
                    <label for="instapay_qr" class="form-label fw-semibold">Course InstaPay QR Image</label>
                    <input type="file" id="instapay_qr" name="instapay_qr" accept=".png,.jpg,.jpeg,.webp" class="form-control @error('instapay_qr') is-invalid @enderror">
                    <div class="form-text">PNG, JPG, WebP · Max 4 MB</div>
                    @error('instapay_qr')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6 ma-field">
                    <label for="instapay_note" class="form-label fw-semibold">Course InstaPay Handle / Transfer Note</label>
                    <input type="text" id="instapay_note" name="instapay_note" value="{{ old('instapay_note') }}"
                           placeholder="e.g. instructor@instapay — Include course title in reference"
                           class="form-control @error('instapay_note') is-invalid @enderror" maxlength="255">
                    @error('instapay_note')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-12"><hr class="my-1"></div>

                <div class="col-12">
                    <h3 class="h6 fw-bold mb-1">
                        <i class="bi bi-play-btn-fill me-1 text-navy" aria-hidden="true"></i>Optional Free Demo / Preview Video
                    </h3>
                    <p class="small text-muted mb-2">Let visitors preview your teaching style before subscribing (respects the global Demo Videos toggle in Academy Settings).</p>
                </div>

                <div class="col-md-4">
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" id="has_demo_video" name="has_demo_video" value="1" {{ old('has_demo_video') ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="has_demo_video">Enable Demo Video for this Course</label>
                    </div>
                </div>

                <div class="col-md-4">
                    <label for="demo_video_type" class="form-label small fw-semibold">Demo Video Source</label>
                    <select id="demo_video_type" name="demo_video_type" class="form-select form-select-sm">
                        <option value="youtube" {{ old('demo_video_type', 'youtube') === 'youtube' ? 'selected' : '' }}>YouTube URL / Video ID</option>
                        <option value="bunny" {{ old('demo_video_type') === 'bunny' ? 'selected' : '' }}>Bunny.net Stream Video ID / Embed URL</option>
                        <option value="upload" {{ old('demo_video_type') === 'upload' ? 'selected' : '' }}>Direct Upload (MP4 / WebM)</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="demo_video_value" class="form-label small fw-semibold">YouTube / Bunny URL or Video ID</label>
                    <input type="text" id="demo_video_value" name="demo_video_value" value="{{ old('demo_video_value') }}" class="form-control form-control-sm" placeholder="https://youtu.be/... or Bunny Video ID">
                </div>

                <div class="col-md-6">
                    <label for="demo_video_file" class="form-label small fw-semibold">Or Upload Demo Video File (when source is Upload)</label>
                    <input type="file" id="demo_video_file" name="demo_video_file" accept="video/mp4,video/webm,.mp4,.webm,.mov,.m4v" class="form-control form-control-sm">
                </div>

                <div class="col-12"><hr class="my-1"></div>

                <div class="col-md-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="is_published" name="is_published" value="1" {{ old('is_published', '1') ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="is_published">Publish Course Publicly</label>
                    </div>
                    <div class="form-text">Unpublished courses stay hidden from public &amp; student catalogs.</div>
                </div>

                <div class="col-md-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured" value="1" {{ old('is_featured') ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="is_featured">Feature on Home Page</label>
                    </div>
                    <div class="form-text">Currently featured: <strong>{{ $featuredCount }} / 3</strong> (max 3).</div>
                    @error('is_featured')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label for="featured_order" class="form-label fw-semibold">Featured Display Order (1–3)</label>
                    <select id="featured_order" name="featured_order" class="form-select">
                        <option value="">Auto</option>
                        <option value="1" {{ old('featured_order') == '1' ? 'selected' : '' }}>Position 1</option>
                        <option value="2" {{ old('featured_order') == '2' ? 'selected' : '' }}>Position 2</option>
                        <option value="3" {{ old('featured_order') == '3' ? 'selected' : '' }}>Position 3</option>
                    </select>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-ma-primary px-4">
                    <i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ __('messages.create_course') }}
                </button>
                <a href="{{ route('admin.courses.index') }}" class="btn btn-ma-outline">{{ __('messages.cancel') }}</a>
            </div>
        </form>
    </div>
@endsection
