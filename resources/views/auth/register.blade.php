@extends('layouts.public')

@section('title', 'Student Registration')

@section('content')
    <section class="py-5">
        <div class="container" style="max-width: 680px;">
            <div class="ma-card-elevated p-4 p-md-5">
                <div class="text-center mb-4">
                    <span class="badge bg-teal mb-2">Student Registration</span>
                    <h1 class="h3 fw-bold mb-1">Create Your Student Account</h1>
                    <p class="text-muted small mb-0">
                        Review the active Academy Policy below and accept it to complete your registration.
                    </p>
                </div>

                @include('partials.flash')

                <form id="student-register-form" method="POST" action="{{ route('register.store') }}" class="ma-form">
                    @csrf

                    <div class="row g-3">
                        <div class="col-md-6 ma-field">
                            <label for="name" class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                id="name"
                                name="name"
                                value="{{ old('name') }}"
                                class="form-control @error('name') is-invalid @enderror"
                                required
                                autofocus
                                autocomplete="name"
                                enterkeyhint="next"
                                data-error="reg-name-error"
                            >
                            <div id="reg-name-error" class="ma-inline-error">Please enter your full name.</div>
                            @error('name')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 ma-field">
                            <label for="phone" class="form-label fw-semibold">Phone Number</label>
                            <span id="phone-hint" class="ma-field-hint">Example: +20 100 123 4567</span>
                            <input
                                type="tel"
                                id="phone"
                                name="phone"
                                value="{{ old('phone') }}"
                                class="form-control @error('phone') is-invalid @enderror"
                                autocomplete="tel"
                                enterkeyhint="next"
                                aria-describedby="phone-hint"
                                data-error="reg-phone-error"
                            >
                            <div id="reg-phone-error" class="ma-inline-error">Please enter a valid phone number.</div>
                            @error('phone')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 ma-field">
                            <label for="email" class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                            <span id="email-hint" class="ma-field-hint">Format: student@example.com</span>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                value="{{ old('email') }}"
                                class="form-control @error('email') is-invalid @enderror"
                                required
                                autocomplete="username"
                                spellcheck="false"
                                enterkeyhint="next"
                                aria-describedby="email-hint"
                                data-error="reg-email-error"
                            >
                            <div id="reg-email-error" class="ma-inline-error">Enter an email address in the format student@example.com.</div>
                            @error('email')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 ma-field">
                            <label for="password" class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                            <span id="password-hint" class="ma-field-hint">Min 8 characters with uppercase, lowercase, and a number.</span>
                            <div class="input-group">
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    class="form-control @error('password') is-invalid @enderror"
                                    required
                                    minlength="8"
                                    autocomplete="new-password"
                                    enterkeyhint="next"
                                    aria-describedby="password-hint"
                                    data-error="reg-password-error"
                                >
                                <button type="button" class="btn btn-outline-secondary" data-toggle-password="password" aria-label="Show password" aria-pressed="false">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                            <div id="reg-password-error" class="ma-inline-error">Password must be at least 8 characters.</div>
                            @error('password')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 ma-field">
                            <label for="password_confirmation" class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                            <span class="ma-field-hint">Re-enter your chosen password.</span>
                            <div class="input-group">
                                <input
                                    type="password"
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    class="form-control"
                                    required
                                    minlength="8"
                                    autocomplete="new-password"
                                    enterkeyhint="done"
                                    data-error="reg-confirm-error"
                                >
                                <button type="button" class="btn btn-outline-secondary" data-toggle-password="password_confirmation" aria-label="Show password" aria-pressed="false">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                            <div id="reg-confirm-error" class="ma-inline-error">Please confirm your password.</div>
                        </div>
                    </div>

                    {{-- Mandatory Policy Gate --}}
                    <div class="mt-4">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                            <label class="form-label fw-bold mb-0">
                                <i class="bi bi-shield-check text-primary me-1" aria-hidden="true"></i>
                                {{ $policy?->title ?? 'Academy Platform Policy' }}
                            </label>
                            @if ($policy)
                                <span class="badge bg-light text-dark border">Version {{ $policy->version }}</span>
                            @endif
                        </div>

                        <div class="ma-policy-box mb-3" tabindex="0" aria-label="Academy Platform Policy Text">
                            {{ $policy?->body ?? 'No active Academy Policy is currently configured.' }}
                        </div>

                        <div id="policy-consent-box" class="ma-policy-consent @error('agree_policy') has-error @enderror">
                            <div class="form-check">
                                <input
                                    class="form-check-input @error('agree_policy') is-invalid @enderror"
                                    type="checkbox"
                                    id="agree_policy"
                                    name="agree_policy"
                                    value="1"
                                    {{ old('agree_policy') ? 'checked' : '' }}
                                    required
                                >
                                <label class="form-check-label fw-semibold" for="agree_policy">
                                    I have read and agree to the {{ $academySettings['academy_name'] ?? 'Mash Academy' }} Policy
                                    @if ($policy)
                                        (Version {{ $policy->version }})
                                    @endif
                                    <span class="text-danger">*</span>
                                </label>
                            </div>
                            <div id="policy-client-error" class="text-danger small mt-2 d-none">
                                <i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>
                                You must tick the Policy agreement checkbox before creating your account.
                            </div>
                            @error('agree_policy')
                                <div class="text-danger small mt-2 fw-semibold">
                                    <i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ $message }}
                                </div>
                            @enderror
                        </div>
                    </div>

                    <button type="submit" class="btn btn-ma-accent w-100 py-2 mt-4">
                        Create Student Account
                    </button>
                </form>

                <hr class="my-4">
                <p class="text-center small text-muted mb-0">
                    Already have an account?
                    <a href="{{ route('login') }}" class="fw-semibold text-decoration-none">Sign In</a>
                </p>
            </div>
        </div>
    </section>
@endsection
