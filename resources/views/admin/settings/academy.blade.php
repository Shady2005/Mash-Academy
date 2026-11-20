@extends('layouts.admin')

@section('title', __('messages.academy_branding'))
@section('page-title', __('messages.academy_branding'))

@section('content')
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="ma-card p-4">
                <h2 class="h5 fw-bold mb-1">Academy Profile, Bilingual Vision (EN / AR) &amp; Branding</h2>
                <p class="text-muted small mb-4">Updates to the Academy Name, Logo, About, and Vision appear immediately across English and Arabic views.</p>

                <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="ma-form">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <div class="col-md-6 ma-field">
                            <label for="academy_name" class="form-label fw-semibold">Academy Name (English) <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                id="academy_name"
                                name="academy_name"
                                value="{{ old('academy_name', $settings['academy_name'] ?? 'Mash Academy') }}"
                                class="form-control @error('academy_name') is-invalid @enderror"
                                required
                                maxlength="120"
                            >
                            @error('academy_name')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 ma-field">
                            <label for="academy_name_ar" class="form-label fw-semibold">Academy Name (Arabic / الاسم بالعربية)</label>
                            <input
                                type="text"
                                id="academy_name_ar"
                                name="academy_name_ar"
                                dir="rtl"
                                value="{{ old('academy_name_ar', $settings['academy_name_ar'] ?? '') }}"
                                class="form-control @error('academy_name_ar') is-invalid @enderror"
                                maxlength="120"
                            >
                            @error('academy_name_ar')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 ma-field">
                            <label for="tagline" class="form-label fw-semibold">Tagline (English)</label>
                            <input
                                type="text"
                                id="tagline"
                                name="tagline"
                                value="{{ old('tagline', $settings['tagline'] ?? '') }}"
                                class="form-control @error('tagline') is-invalid @enderror"
                                maxlength="255"
                            >
                            @error('tagline')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 ma-field">
                            <label for="tagline_ar" class="form-label fw-semibold">Tagline (Arabic / الشعار بالعربية)</label>
                            <input
                                type="text"
                                id="tagline_ar"
                                name="tagline_ar"
                                dir="rtl"
                                value="{{ old('tagline_ar', $settings['tagline_ar'] ?? '') }}"
                                class="form-control @error('tagline_ar') is-invalid @enderror"
                                maxlength="255"
                            >
                            @error('tagline_ar')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <hr class="my-1">
                            <h3 class="h6 fw-bold mb-1 text-navy">Academy Vision (Dynamic on Home Page)</h3>
                            <p class="small text-muted mb-2">Edit the Academy Vision in both English and Arabic below.</p>
                        </div>

                        <div class="col-md-6 ma-field">
                            <label for="vision" class="form-label fw-semibold">Academy Vision (English)</label>
                            <textarea
                                id="vision"
                                name="vision"
                                rows="4"
                                class="form-control @error('vision') is-invalid @enderror"
                            >{{ old('vision', $settings['vision'] ?? '') }}</textarea>
                            @error('vision')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 ma-field">
                            <label for="vision_ar" class="form-label fw-semibold">Academy Vision (Arabic / الرؤية بالعربية)</label>
                            <textarea
                                id="vision_ar"
                                name="vision_ar"
                                dir="rtl"
                                rows="4"
                                class="form-control @error('vision_ar') is-invalid @enderror"
                            >{{ old('vision_ar', $settings['vision_ar'] ?? '') }}</textarea>
                            @error('vision_ar')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 ma-field">
                            <label for="about" class="form-label fw-semibold">About the Academy (English)</label>
                            <textarea
                                id="about"
                                name="about"
                                rows="4"
                                class="form-control @error('about') is-invalid @enderror"
                            >{{ old('about', $settings['about'] ?? '') }}</textarea>
                            @error('about')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 ma-field">
                            <label for="about_ar" class="form-label fw-semibold">About the Academy (Arabic / عن الأكاديمية)</label>
                            <textarea
                                id="about_ar"
                                name="about_ar"
                                dir="rtl"
                                rows="4"
                                class="form-control @error('about_ar') is-invalid @enderror"
                            >{{ old('about_ar', $settings['about_ar'] ?? '') }}</textarea>
                            @error('about_ar')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12"><hr class="my-1"></div>

                        <div class="col-md-6 ma-field">
                            <label for="contact_email" class="form-label fw-semibold">Contact Email</label>
                            <input
                                type="email"
                                id="contact_email"
                                name="contact_email"
                                value="{{ old('contact_email', $settings['contact_email'] ?? '') }}"
                                class="form-control @error('contact_email') is-invalid @enderror"
                            >
                            @error('contact_email')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 ma-field">
                            <label for="contact_phone" class="form-label fw-semibold">Contact Phone</label>
                            <input
                                type="tel"
                                id="contact_phone"
                                name="contact_phone"
                                value="{{ old('contact_phone', $settings['contact_phone'] ?? '') }}"
                                class="form-control @error('contact_phone') is-invalid @enderror"
                            >
                            @error('contact_phone')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <hr class="my-1">
                            <h3 class="h6 fw-bold mb-2">Social Links (Optional)</h3>
                        </div>

                        <div class="col-md-6 ma-field">
                            <label for="facebook_url" class="form-label small fw-semibold">Facebook URL</label>
                            <input type="url" id="facebook_url" name="facebook_url" value="{{ old('facebook_url', $settings['facebook_url'] ?? '') }}" class="form-control @error('facebook_url') is-invalid @enderror" placeholder="https://facebook.com/...">
                        </div>

                        <div class="col-md-6 ma-field">
                            <label for="instagram_url" class="form-label small fw-semibold">Instagram URL</label>
                            <input type="url" id="instagram_url" name="instagram_url" value="{{ old('instagram_url', $settings['instagram_url'] ?? '') }}" class="form-control @error('instagram_url') is-invalid @enderror" placeholder="https://instagram.com/...">
                        </div>

                        <div class="col-md-6 ma-field">
                            <label for="youtube_url" class="form-label small fw-semibold">YouTube URL</label>
                            <input type="url" id="youtube_url" name="youtube_url" value="{{ old('youtube_url', $settings['youtube_url'] ?? '') }}" class="form-control @error('youtube_url') is-invalid @enderror" placeholder="https://youtube.com/...">
                        </div>

                        <div class="col-md-6 ma-field">
                            <label for="whatsapp_number" class="form-label small fw-semibold">WhatsApp Number</label>
                            <input type="tel" id="whatsapp_number" name="whatsapp_number" value="{{ old('whatsapp_number', $settings['whatsapp_number'] ?? '') }}" class="form-control @error('whatsapp_number') is-invalid @enderror" placeholder="+20 100 000 0000">
                        </div>

                        <div class="col-12">
                            <hr class="my-1">
                            <label for="logo" class="form-label fw-semibold">Upload / Replace Academy Logo</label>
                            <span class="ma-field-hint">Accepted formats: PNG, JPG, JPEG, SVG, WebP · Maximum size: 2 MB. Replaces the default "MA" badge site-wide.</span>
                            <input
                                type="file"
                                id="logo"
                                name="logo"
                                accept=".png,.jpg,.jpeg,.svg,.webp,image/png,image/jpeg,image/svg+xml,image/webp"
                                class="form-control @error('logo') is-invalid @enderror"
                            >
                            @error('logo')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-ma-primary px-4">
                            <i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ __('messages.save_changes') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="ma-card p-4">
                <h3 class="h6 fw-bold mb-3">Current Logo Preview</h3>
                <div class="border rounded-3 p-4 text-center mb-3" style="background-color: var(--ma-surface-subtle);">
                    <img
                        id="logo-preview-img"
                        src="{{ $logoUrl ?? '' }}"
                        alt="Academy logo preview"
                        class="img-fluid {{ $logoUrl ? '' : 'd-none' }}"
                        style="max-height: 110px; object-fit: contain;"
                    >
                    <div id="logo-preview-placeholder" class="{{ $logoUrl ? 'd-none' : '' }} text-muted small py-3">
                        <div class="ma-brand-emblem mx-auto mb-2" style="width: 52px; height: 52px; font-size: 1.25rem;">MA</div>
                        <div>Default emblem active</div>
                    </div>
                </div>

                @if ($logoUrl)
                    <form method="POST" action="{{ route('admin.settings.logo.destroy') }}" onsubmit="return confirm('Remove the uploaded academy logo and revert to the default emblem?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                            <i class="bi bi-trash me-1" aria-hidden="true"></i>Remove Uploaded Logo
                        </button>
                    </form>
                @endif
            </div>

            {{-- InstaPay QR Code Upload Card (Phase 3) --}}
            <div class="ma-card p-4 mt-4" id="instapay-qr">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h3 class="h6 fw-bold mb-0">
                        <i class="bi bi-qr-code me-1 text-navy" aria-hidden="true"></i>{{ __('messages.instapay_qr') }}
                    </h3>
                    <span class="badge {{ $instapayQrUrl ? 'bg-navy-accent' : 'bg-secondary' }}">
                        {{ $instapayQrUrl ? 'Active' : 'Not Uploaded' }}
                    </span>
                </div>
                <p class="small text-muted mb-3">
                    Displayed to students on the "Subscribe to Course" page alongside the payment confirmation form.
                </p>

                <div class="border rounded-3 p-3 text-center mb-3" style="background-color: var(--ma-surface-subtle);">
                    @if ($instapayQrUrl)
                        <img src="{{ $instapayQrUrl }}" alt="InstaPay QR Code" class="img-fluid rounded" style="max-height: 200px; object-fit: contain;">
                    @else
                        <div class="py-4 text-muted small">
                            <i class="bi bi-qr-code fs-1 d-block mb-1" aria-hidden="true"></i>
                            No InstaPay QR uploaded yet
                        </div>
                    @endif
                </div>

                <form method="POST" action="{{ route('admin.settings.instapay-qr.update') }}" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label for="instapay_qr" class="form-label small fw-semibold">Upload / Replace InstaPay QR</label>
                        <input type="file" id="instapay_qr" name="instapay_qr" accept=".png,.jpg,.jpeg,.webp" class="form-control form-control-sm @error('instapay_qr') is-invalid @enderror">
                        <div class="form-text">PNG, JPG, WebP · Max 4 MB</div>
                        @error('instapay_qr')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="instapay_account_note" class="form-label small fw-semibold">Transfer Instructions / InstaPay Handle</label>
                        <textarea id="instapay_account_note" name="instapay_account_note" rows="3" class="form-control form-control-sm">{{ old('instapay_account_note', $settings['instapay_account_note'] ?? '') }}</textarea>
                    </div>

                    <button type="submit" class="btn btn-ma-primary btn-sm w-100">
                        <i class="bi bi-upload me-1" aria-hidden="true"></i>Save InstaPay QR Settings
                    </button>
                </form>

                @if ($instapayQrUrl)
                    <form method="POST" action="{{ route('admin.settings.instapay-qr.destroy') }}" class="mt-2" onsubmit="return confirm('Remove the InstaPay QR image?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                            <i class="bi bi-trash me-1" aria-hidden="true"></i>Remove QR Image
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
@endsection
