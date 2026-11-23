@extends('layouts.admin')

@section('title', __('messages.edit_package'))
@section('page-title', __('messages.edit_package'))

@section('content')
    <div class="mb-3">
        <a href="{{ route('admin.packages.index') }}" class="btn btn-ma-outline btn-sm">
            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back to Packages
        </a>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="ma-card p-4">
                <h2 class="h6 fw-bold mb-3">Package Details &amp; Included Courses</h2>

                <form method="POST" action="{{ route('admin.packages.update', $package) }}" enctype="multipart/form-data" class="ma-form">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <div class="col-md-6 ma-field">
                            <label for="title" class="form-label fw-semibold">Package Title (English) <span class="text-danger">*</span></label>
                            <input type="text" id="title" name="title" value="{{ old('title', $package->title) }}" class="form-control @error('title') is-invalid @enderror" required maxlength="255">
                            @error('title')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6 ma-field">
                            <label for="title_ar" class="form-label fw-semibold">Package Title (Arabic)</label>
                            <input type="text" id="title_ar" name="title_ar" dir="rtl" value="{{ old('title_ar', $package->title_ar) }}" class="form-control" maxlength="255">
                        </div>

                        <div class="col-md-6 ma-field">
                            <label for="short_description" class="form-label fw-semibold">Short Summary (English)</label>
                            <textarea id="short_description" name="short_description" rows="2" class="form-control" maxlength="500">{{ old('short_description', $package->short_description) }}</textarea>
                        </div>

                        <div class="col-md-6 ma-field">
                            <label for="short_description_ar" class="form-label fw-semibold">Short Summary (Arabic)</label>
                            <textarea id="short_description_ar" name="short_description_ar" dir="rtl" rows="2" class="form-control" maxlength="500">{{ old('short_description_ar', $package->short_description_ar) }}</textarea>
                        </div>

                        <div class="col-md-6 ma-field">
                            <label for="description" class="form-label fw-semibold">Full Description (English) <span class="text-danger">*</span></label>
                            <textarea id="description" name="description" rows="4" class="form-control @error('description') is-invalid @enderror" required minlength="10">{{ old('description', $package->description) }}</textarea>
                            @error('description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6 ma-field">
                            <label for="description_ar" class="form-label fw-semibold">Full Description (Arabic)</label>
                            <textarea id="description_ar" name="description_ar" dir="rtl" rows="4" class="form-control">{{ old('description_ar', $package->description_ar) }}</textarea>
                        </div>

                        <div class="col-md-4 ma-field">
                            <label for="price" class="form-label fw-semibold">Bundle Price <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" id="price" name="price" value="{{ old('price', $package->price) }}" class="form-control @error('price') is-invalid @enderror" required>
                        </div>

                        <div class="col-md-4 ma-field">
                            <label for="currency" class="form-label fw-semibold">Currency</label>
                            <input type="text" id="currency" name="currency" value="{{ old('currency', $package->currency) }}" class="form-control" maxlength="10">
                        </div>

                        <div class="col-md-4 ma-field">
                            <label for="thumbnail" class="form-label fw-semibold">Replace Thumbnail</label>
                            <input type="file" id="thumbnail" name="thumbnail" accept=".png,.jpg,.jpeg,.webp" class="form-control @error('thumbnail') is-invalid @enderror">
                        </div>

                        <div class="col-12"><hr class="my-1"></div>

                        <div class="col-12">
                            <label class="form-label fw-bold mb-2">
                                <i class="bi bi-journal-check me-1" aria-hidden="true"></i>{{ __('messages.included_courses') }} <span class="text-danger">*</span>
                            </label>
                            @php $checkedIds = array_map('intval', (array) old('course_ids', $selectedCourseIds)); @endphp
                            <div class="row g-2">
                                @foreach ($courses as $course)
                                    <div class="col-md-6">
                                        <label class="border rounded-3 p-3 d-flex align-items-start gap-2 h-100" style="cursor: pointer;">
                                            <input
                                                type="checkbox"
                                                name="course_ids[]"
                                                value="{{ $course->id }}"
                                                class="form-check-input mt-1"
                                                {{ in_array($course->id, $checkedIds, true) ? 'checked' : '' }}
                                            >
                                            <span class="small">
                                                <strong class="d-block">{{ $course->title }}</strong>
                                                <span class="text-muted">{{ number_format((float) $course->price, 0) }} {{ $course->currency }}</span>
                                            </span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            @error('course_ids')<div class="invalid-feedback d-block mt-2">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12"><hr class="my-1"></div>

                        <div class="col-md-6 ma-field">
                            <label for="instapay_qr" class="form-label fw-semibold">Upload / Replace Package InstaPay QR</label>
                            <input type="file" id="instapay_qr" name="instapay_qr" accept=".png,.jpg,.jpeg,.webp" class="form-control @error('instapay_qr') is-invalid @enderror">
                        </div>

                        <div class="col-md-6 ma-field">
                            <label for="instapay_note" class="form-label fw-semibold">Package InstaPay Handle / Note</label>
                            <input type="text" id="instapay_note" name="instapay_note" value="{{ old('instapay_note', $package->instapay_note) }}" class="form-control" maxlength="255">
                        </div>

                        <div class="col-12 pt-2">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_published" name="is_published" value="1" {{ old('is_published', $package->is_published) ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="is_published">Publish Publicly</label>
                            </div>
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
                @if ($package->thumbnailUrl())
                    <img src="{{ $package->thumbnailUrl() }}" alt="{{ $package->title }}" class="img-fluid rounded border w-100" style="aspect-ratio: 16 / 9; object-fit: cover;">
                @else
                    <div class="ma-course-thumb rounded p-4 text-center">
                        <span class="small opacity-75">No thumbnail uploaded</span>
                    </div>
                @endif
            </div>

            <div class="ma-card p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="h6 fw-bold mb-0"><i class="bi bi-qr-code-scan me-1" aria-hidden="true"></i>Active Checkout QR</h3>
                    @php $qrSource = $resolvedInstaPay['source'] ?? 'none'; @endphp
                    <span class="badge {{ $qrSource === 'package' ? 'bg-navy-accent' : ($qrSource === 'instructor' ? 'bg-navy' : 'bg-secondary') }}">
                        {{ ucfirst($qrSource) }}
                    </span>
                </div>
                @if (! empty($resolvedInstaPay['url']))
                    <div class="p-3 bg-white rounded border text-center mb-3">
                        <img src="{{ $resolvedInstaPay['url'] }}" alt="Package InstaPay QR" class="img-fluid" style="max-height: 190px;">
                    </div>
                @else
                    <div class="alert alert-warning small mb-3">No InstaPay QR configured yet.</div>
                @endif
                @if ($package->instapay_qr_path)
                    <form method="POST" action="{{ route('admin.packages.instapay-qr.destroy', $package) }}" onsubmit="return confirm('Remove package-specific QR override?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                            <i class="bi bi-trash me-1" aria-hidden="true"></i>Remove Package QR Override
                        </button>
                    </form>
                @endif
            </div>

            <div class="ma-card p-4 border-danger">
                <h3 class="h6 fw-bold text-danger mb-2">Danger Zone</h3>
                <p class="small text-muted mb-3">Deleting a package hides it from checkout without revoking any already-granted student enrollments.</p>
                <form method="POST" action="{{ route('admin.packages.destroy', $package) }}" onsubmit="return confirm('Delete this package?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm w-100">
                        <i class="bi bi-trash me-1" aria-hidden="true"></i>Delete Package
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection
