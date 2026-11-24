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
                            <h3 class="h6 fw-bold mb-1 text-navy">Platform Feature Toggles &amp; Bunny.net Stream</h3>
                            <p class="small text-muted mb-3">Enable or disable optional platform features globally with one click.</p>
                            <input type="hidden" name="toggles_present" value="1">
                        </div>

                        <div class="col-md-6">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="enable_demo_videos" name="enable_demo_videos" value="1" {{ old('enable_demo_videos', $settings['enable_demo_videos'] ?? '1') === '1' ? 'checked' : '' }}>
                                <label class="form-check-label small fw-semibold" for="enable_demo_videos">Enable Free Demo / Preview Videos</label>
                            </div>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="enable_certificate_verification" name="enable_certificate_verification" value="1" {{ old('enable_certificate_verification', $settings['enable_certificate_verification'] ?? '1') === '1' ? 'checked' : '' }}>
                                <label class="form-check-label small fw-semibold" for="enable_certificate_verification">Enable Public Certificate Verification (/verify-certificate)</label>
                            </div>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="enable_course_qa" name="enable_course_qa" value="1" {{ old('enable_course_qa', $settings['enable_course_qa'] ?? '1') === '1' ? 'checked' : '' }}>
                                <label class="form-check-label small fw-semibold" for="enable_course_qa">Enable Course Q&amp;A Discussions</label>
                            </div>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="enable_course_reviews" name="enable_course_reviews" value="1" {{ old('enable_course_reviews', $settings['enable_course_reviews'] ?? '1') === '1' ? 'checked' : '' }}>
                                <label class="form-check-label small fw-semibold" for="enable_course_reviews">Enable Student Star Reviews (≥ 50% Progress)</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="enable_consultations" name="enable_consultations" value="1" {{ old('enable_consultations', $settings['enable_consultations'] ?? '1') === '1' ? 'checked' : '' }}>
                                <label class="form-check-label small fw-semibold" for="enable_consultations">Enable 1-on-1 Consultations Booking</label>
                            </div>
                        </div>

                        <div class="col-md-6 ma-field">
                            <label for="bunny_library_id" class="form-label small fw-semibold">Default Bunny.net Stream Library ID (Optional)</label>
                            <input type="text" id="bunny_library_id" name="bunny_library_id" value="{{ old('bunny_library_id', $settings['bunny_library_id'] ?? '') }}" class="form-control" maxlength="64" placeholder="e.g. 284910">
                            <div class="form-text">Configured once here so instructors only need to paste a Bunny.net Video ID on lessons or demo videos.</div>
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

            {{-- AI Integration & Independent Visibility Toggles Card --}}
            <div class="ma-card p-4 mt-4" id="ai-integration">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                    <h2 class="h5 fw-bold mb-0">
                        <i class="bi bi-stars text-navy me-1" aria-hidden="true"></i>{{ __('messages.ai_integration') }}
                    </h2>
                    <div class="d-flex flex-wrap gap-1">
                        <span class="badge {{ ($hasStoredAiApiKey || $hasEnvAiApiKey) ? 'bg-success' : 'bg-secondary' }}">
                            {{ $hasStoredAiApiKey ? 'Encrypted Key Saved' : ($hasEnvAiApiKey ? 'Using .env Key' : 'API Key Missing') }}
                        </span>
                        <span class="badge {{ ($settings['enable_ai_student_tutor'] ?? '0') === '1' ? 'bg-navy-accent' : 'bg-black-badge' }}">
                            Student Tutor: {{ ($settings['enable_ai_student_tutor'] ?? '0') === '1' ? 'ON' : 'OFF (Hidden)' }}
                        </span>
                    </div>
                </div>
                <p class="text-muted small mb-4">
                    Configure Google Gemini or OpenAI to power Dashboard AI content tools and the optional Student AI Course Tutor. Your API key is encrypted at rest in the database (with automatic <code>.env</code> fallback).
                </p>

                <form method="POST" action="{{ route('admin.settings.ai.update') }}" class="ma-form">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <div class="col-md-4 ma-field">
                            <label for="ai_provider" class="form-label fw-semibold small">AI Provider</label>
                            <select id="ai_provider" name="ai_provider" class="form-select @error('ai_provider') is-invalid @enderror">
                                <option value="gemini" @selected(old('ai_provider', $settings['ai_provider'] ?? 'gemini') === 'gemini')>Google Gemini (Default)</option>
                                <option value="openai" @selected(old('ai_provider', $settings['ai_provider'] ?? 'gemini') === 'openai')>OpenAI</option>
                            </select>
                            @error('ai_provider')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4 ma-field">
                            <label for="ai_model" class="form-label fw-semibold small">Model Name</label>
                            <input
                                type="text"
                                id="ai_model"
                                name="ai_model"
                                value="{{ old('ai_model', $settings['ai_model'] ?? 'gemini-2.5-flash') }}"
                                class="form-control @error('ai_model') is-invalid @enderror"
                                placeholder="gemini-2.5-flash or gpt-4o-mini"
                                maxlength="80"
                            >
                            <div class="form-text">Recommended: <code>gemini-2.5-flash</code> or <code>gpt-4o-mini</code>.</div>
                            @error('ai_model')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4 ma-field">
                            <label for="ai_student_daily_limit" class="form-label fw-semibold small">Student Daily Tutor Quota</label>
                            <input
                                type="number"
                                id="ai_student_daily_limit"
                                name="ai_student_daily_limit"
                                min="1"
                                max="200"
                                value="{{ old('ai_student_daily_limit', $settings['ai_student_daily_limit'] ?? '20') }}"
                                class="form-control @error('ai_student_daily_limit') is-invalid @enderror"
                            >
                            <div class="form-text">Max AI Tutor questions per student per day.</div>
                        </div>

                        <div class="col-12 ma-field">
                            <label for="ai_api_key" class="form-label fw-semibold small">
                                API Key
                                @if ($maskedAiApiKey)
                                    <span class="text-muted fw-normal">(Active: <code>{{ $maskedAiApiKey }}</code>)</span>
                                @endif
                            </label>
                            <div class="input-group">
                                <input
                                    type="password"
                                    id="ai_api_key"
                                    name="ai_api_key"
                                    value=""
                                    autocomplete="new-password"
                                    class="form-control @error('ai_api_key') is-invalid @enderror"
                                    placeholder="{{ $hasStoredAiApiKey ? 'Leave blank to keep existing encrypted key, or paste new key to replace' : 'Paste your Google Gemini or OpenAI API key...' }}"
                                    maxlength="255"
                                >
                                <button type="button" class="btn btn-outline-secondary" data-toggle-password="ai_api_key" aria-label="Show API key">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                            <div class="form-text">
                                Stored encrypted in <code>academy_settings</code>. If empty, falls back to <code>GEMINI_API_KEY</code> or <code>OPENAI_API_KEY</code> in <code>.env</code>.
                            </div>
                            @error('ai_api_key')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12"><hr class="my-1"></div>

                        <div class="col-md-6">
                            <div class="border rounded-3 p-3 h-100" style="background-color: var(--ma-surface-subtle);">
                                <div class="form-check form-switch mb-1">
                                    <input class="form-check-input" type="checkbox" id="enable_ai_admin_tools" name="enable_ai_admin_tools" value="1" {{ old('enable_ai_admin_tools', $settings['enable_ai_admin_tools'] ?? '1') === '1' ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold small" for="enable_ai_admin_tools">
                                        Enable Dashboard AI Tools
                                    </label>
                                </div>
                                <div class="small text-muted">
                                    Enables AI Bilingual Course Description Generator, AI Quiz Questions Generator, and Super Admin AI Sandbox. (Default: ON)
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="border rounded-3 p-3 h-100" style="background-color: var(--ma-surface-subtle);">
                                <div class="form-check form-switch mb-1">
                                    <input class="form-check-input" type="checkbox" id="enable_ai_student_tutor" name="enable_ai_student_tutor" value="1" {{ old('enable_ai_student_tutor', $settings['enable_ai_student_tutor'] ?? '0') === '1' ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold small" for="enable_ai_student_tutor">
                                        Enable Student-Facing AI Course Tutor
                                    </label>
                                </div>
                                <div class="small text-muted">
                                    When <strong>OFF</strong> (default), all AI buttons, drawers, and endpoints are completely hidden from Students and the Public Website.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-ma-primary px-4">
                                <i class="bi bi-check-lg me-1" aria-hidden="true"></i>Save AI Settings
                            </button>
                            <a href="{{ route('admin.ai-assistant.index') }}" class="btn btn-ma-outline">
                                <i class="bi bi-stars me-1" aria-hidden="true"></i>Open AI Sandbox
                            </a>
                        </div>
                    </div>
                </form>

                @if ($hasStoredAiApiKey)
                    <form method="POST" action="{{ route('admin.settings.ai-key.destroy') }}" class="mt-3 pt-3 border-top" onsubmit="return confirm('Remove the stored AI API key from the database?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm">
                            <i class="bi bi-trash me-1" aria-hidden="true"></i>Clear Stored Database API Key
                        </button>
                    </form>
                @endif
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
