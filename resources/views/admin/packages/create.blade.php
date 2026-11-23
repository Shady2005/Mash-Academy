@extends('layouts.admin')

@section('title', __('messages.create_package'))
@section('page-title', __('messages.create_package'))

@section('content')
    <div class="ma-card p-4" style="max-width: 960px;">
        <div class="mb-4 pb-3 border-bottom d-flex justify-content-between align-items-center gap-2">
            <div>
                <h2 class="h5 fw-bold mb-1">New Course Package / Bundle</h2>
                <p class="text-muted small mb-0">Select multiple courses to bundle together under a single price and InstaPay checkout flow.</p>
            </div>
            <a href="{{ route('admin.packages.index') }}" class="btn btn-ma-outline btn-sm">Back to Packages</a>
        </div>

        <form method="POST" action="{{ route('admin.packages.store') }}" enctype="multipart/form-data" class="ma-form">
            @csrf

            <div class="row g-3">
                <div class="col-md-6 ma-field">
                    <label for="title" class="form-label fw-semibold">Package Title (English) <span class="text-danger">*</span></label>
                    <input type="text" id="title" name="title" value="{{ old('title') }}" class="form-control @error('title') is-invalid @enderror" required maxlength="255">
                    @error('title')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6 ma-field">
                    <label for="title_ar" class="form-label fw-semibold">Package Title (Arabic / اسم الباكيدج)</label>
                    <input type="text" id="title_ar" name="title_ar" dir="rtl" value="{{ old('title_ar') }}" class="form-control @error('title_ar') is-invalid @enderror" maxlength="255">
                    @error('title_ar')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6 ma-field">
                    <label for="short_description" class="form-label fw-semibold">Short Summary (English)</label>
                    <textarea id="short_description" name="short_description" rows="2" class="form-control" maxlength="500">{{ old('short_description') }}</textarea>
                </div>

                <div class="col-md-6 ma-field">
                    <label for="short_description_ar" class="form-label fw-semibold">Short Summary (Arabic / ملخص قصير)</label>
                    <textarea id="short_description_ar" name="short_description_ar" dir="rtl" rows="2" class="form-control" maxlength="500">{{ old('short_description_ar') }}</textarea>
                </div>

                <div class="col-md-6 ma-field">
                    <label for="description" class="form-label fw-semibold">Full Description (English) <span class="text-danger">*</span></label>
                    <textarea id="description" name="description" rows="4" class="form-control @error('description') is-invalid @enderror" required minlength="10">{{ old('description') }}</textarea>
                    @error('description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6 ma-field">
                    <label for="description_ar" class="form-label fw-semibold">Full Description (Arabic / الوصف الكامل)</label>
                    <textarea id="description_ar" name="description_ar" dir="rtl" rows="4" class="form-control">{{ old('description_ar') }}</textarea>
                </div>

                <div class="col-md-4 ma-field">
                    <label for="price" class="form-label fw-semibold">Bundle Price (EGP) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0" id="price" name="price" value="{{ old('price', '1500') }}" class="form-control @error('price') is-invalid @enderror" required>
                    @error('price')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4 ma-field">
                    <label for="currency" class="form-label fw-semibold">Currency</label>
                    <input type="text" id="currency" name="currency" value="{{ old('currency', 'EGP') }}" class="form-control" maxlength="10">
                </div>

                <div class="col-md-4 ma-field">
                    <label for="thumbnail" class="form-label fw-semibold">Package Thumbnail</label>
                    <input type="file" id="thumbnail" name="thumbnail" accept=".png,.jpg,.jpeg,.webp" class="form-control @error('thumbnail') is-invalid @enderror">
                    <div class="form-text">PNG, JPG, WebP · Max 2 MB</div>
                    @error('thumbnail')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-12"><hr class="my-1"></div>

                {{-- Multi-Select Course Checklist --}}
                <div class="col-12">
                    <label class="form-label fw-bold mb-1">
                        <i class="bi bi-journal-check me-1" aria-hidden="true"></i>{{ __('messages.included_courses') }} <span class="text-danger">*</span>
                    </label>
                    <p class="small text-muted mb-2">Check all existing courses that unlock automatically when a student enrolls in this package.</p>

                    @if ($courses->isEmpty())
                        <div class="alert alert-warning small">No courses exist yet. Create courses first before creating a package.</div>
                    @else
                        <div class="row g-2">
                            @php $oldCourseIds = array_map('intval', (array) old('course_ids', [])); @endphp
                            @foreach ($courses as $course)
                                <div class="col-md-6">
                                    <label class="border rounded-3 p-3 d-flex align-items-start gap-2 h-100" style="cursor: pointer;">
                                        <input
                                            type="checkbox"
                                            name="course_ids[]"
                                            value="{{ $course->id }}"
                                            class="form-check-input mt-1"
                                            {{ in_array($course->id, $oldCourseIds, true) ? 'checked' : '' }}
                                        >
                                        <span class="small">
                                            <strong class="d-block">{{ $course->title }}</strong>
                                            <span class="text-muted">
                                                {{ number_format((float) $course->price, 0) }} {{ $course->currency }}
                                                · {{ $course->is_published ? 'Published' : 'Draft' }}
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    @error('course_ids')<div class="invalid-feedback d-block mt-2">{{ $message }}</div>@enderror
                </div>

                <div class="col-12"><hr class="my-1"></div>

                <div class="col-12">
                    <h3 class="h6 fw-bold mb-1">
                        <i class="bi bi-qr-code-scan me-1" aria-hidden="true"></i>{{ __('messages.package_instapay_qr') }} (Optional Override)
                    </h3>
                    <p class="small text-muted mb-2">If left blank, checkout will fall back to your personal instructor QR or the Academy Global QR.</p>
                </div>

                <div class="col-md-6 ma-field">
                    <label for="instapay_qr" class="form-label fw-semibold">Package InstaPay QR Image</label>
                    <input type="file" id="instapay_qr" name="instapay_qr" accept=".png,.jpg,.jpeg,.webp" class="form-control @error('instapay_qr') is-invalid @enderror">
                    <div class="form-text">PNG, JPG, WebP · Max 4 MB</div>
                    @error('instapay_qr')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6 ma-field">
                    <label for="instapay_note" class="form-label fw-semibold">Package InstaPay Handle / Note</label>
                    <input type="text" id="instapay_note" name="instapay_note" value="{{ old('instapay_note') }}" class="form-control" maxlength="255" placeholder="e.g. academy@instapay — Include package title">
                </div>

                <div class="col-12 pt-2">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="is_published" name="is_published" value="1" {{ old('is_published', '1') ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="is_published">Publish Package Publicly</label>
                    </div>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-ma-primary px-4">
                    <i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ __('messages.create_package') }}
                </button>
                <a href="{{ route('admin.packages.index') }}" class="btn btn-ma-outline">{{ __('messages.cancel') }}</a>
            </div>
        </form>
    </div>
@endsection
