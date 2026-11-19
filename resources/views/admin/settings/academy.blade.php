@extends('layouts.admin')

@section('title', 'Academy & Branding Settings')
@section('page-title', 'Academy & Branding Settings')

@section('content')
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="ma-card p-4">
                <h2 class="h5 fw-bold mb-1">Academy Profile &amp; Branding</h2>
                <p class="text-muted small mb-4">Updates to the Academy Name and Logo appear immediately in the site-wide header.</p>

                <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="ma-form">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <div class="col-md-6 ma-field">
                            <label for="academy_name" class="form-label fw-semibold">Academy Name <span class="text-danger">*</span></label>
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
                            <label for="tagline" class="form-label fw-semibold">Tagline</label>
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

                        <div class="col-12 ma-field">
                            <label for="about" class="form-label fw-semibold">About the Academy</label>
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

                        <div class="col-12 ma-field">
                            <label for="vision" class="form-label fw-semibold">Academy Vision</label>
                            <textarea
                                id="vision"
                                name="vision"
                                rows="3"
                                class="form-control @error('vision') is-invalid @enderror"
                            >{{ old('vision', $settings['vision'] ?? '') }}</textarea>
                            @error('vision')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

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
                            <hr class="my-2">
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
                            <hr class="my-2">
                            <label for="logo" class="form-label fw-semibold">Upload / Replace Academy Logo</label>
                            <span class="ma-field-hint">Accepted formats: PNG, JPG, JPEG, WebP · Maximum size: 2 MB.</span>
                            <input
                                type="file"
                                id="logo"
                                name="logo"
                                accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp"
                                class="form-control @error('logo') is-invalid @enderror"
                            >
                            @error('logo')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-ma-primary px-4">
                            <i class="bi bi-check-lg me-1" aria-hidden="true"></i>Save Academy Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="ma-card p-4">
                <h3 class="h6 fw-bold mb-3">Current Logo Preview</h3>
                <div class="border rounded-3 p-4 bg-light text-center mb-3">
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
        </div>
    </div>
@endsection
