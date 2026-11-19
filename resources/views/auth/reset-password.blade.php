@extends('layouts.public')

@section('title', 'Choose New Password')

@section('content')
    <section class="py-5">
        <div class="container" style="max-width: 460px;">
            <div class="ma-card-elevated p-4 p-md-5">
                <div class="text-center mb-4">
                    <h1 class="h4 fw-bold mb-1">Choose a New Password</h1>
                    <p class="text-muted small mb-0">Set a strong password for your Mash Academy account.</p>
                </div>

                @include('partials.flash')

                <form method="POST" action="{{ route('password.update') }}" class="ma-form">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">

                    <div class="mb-3 ma-field">
                        <label for="email" class="form-label fw-semibold">Email Address</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email', $email) }}"
                            class="form-control @error('email') is-invalid @enderror"
                            required
                            autocomplete="username"
                            spellcheck="false"
                        >
                        @error('email')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3 ma-field">
                        <label for="password" class="form-label fw-semibold">New Password</label>
                        <span class="ma-field-hint">Min 8 characters with uppercase, lowercase, and a number.</span>
                        <div class="input-group">
                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="form-control @error('password') is-invalid @enderror"
                                required
                                minlength="8"
                                autocomplete="new-password"
                            >
                            <button type="button" class="btn btn-outline-secondary" data-toggle-password="password" aria-label="Show password" aria-pressed="false">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                        @error('password')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4 ma-field">
                        <label for="password_confirmation" class="form-label fw-semibold">Confirm New Password</label>
                        <div class="input-group">
                            <input
                                type="password"
                                id="password_confirmation"
                                name="password_confirmation"
                                class="form-control"
                                required
                                minlength="8"
                                autocomplete="new-password"
                            >
                            <button type="button" class="btn btn-outline-secondary" data-toggle-password="password_confirmation" aria-label="Show password" aria-pressed="false">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-ma-primary w-100 py-2">
                        Update Password
                    </button>
                </form>
            </div>
        </div>
    </section>
@endsection
