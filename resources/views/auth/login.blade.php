@extends('layouts.public')

@section('title', 'Sign In')

@section('content')
    <section class="py-5">
        <div class="container" style="max-width: 460px;">
            <div class="ma-card-elevated p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="mb-3">
                        @include('partials.brand', ['nameClass' => 'fs-5'])
                    </div>
                    <h1 class="h4 fw-bold mb-1">Sign in to {{ $academySettings['academy_name'] ?? 'Mash Academy' }}</h1>
                    <p class="text-muted small mb-0">Students and staff sign in with their registered email address.</p>
                </div>

                @include('partials.flash')

                <form method="POST" action="{{ route('login.store') }}" class="ma-form" novalidate>
                    @csrf

                    <div class="mb-3 ma-field">
                        <label for="email" class="form-label fw-semibold">Email Address</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email') }}"
                            class="form-control @error('email') is-invalid @enderror"
                            required
                            autofocus
                            autocomplete="username"
                            spellcheck="false"
                            enterkeyhint="next"
                            data-error="login-email-error"
                        >
                        <div id="login-email-error" class="ma-inline-error">
                            Please enter a valid email address.
                        </div>
                        @error('email')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3 ma-field">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label for="password" class="form-label fw-semibold mb-0">Password</label>
                            <a href="{{ route('password.request') }}" class="small text-decoration-none">Forgot password?</a>
                        </div>
                        <div class="input-group">
                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="form-control @error('password') is-invalid @enderror"
                                required
                                autocomplete="current-password"
                                enterkeyhint="done"
                                data-error="login-password-error"
                            >
                            <button type="button" class="btn btn-outline-secondary" data-toggle-password="password" aria-label="Show password" aria-pressed="false">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div id="login-password-error" class="ma-inline-error">
                            Please enter your password.
                        </div>
                        @error('password')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                        <label class="form-check-label small" for="remember">Keep me signed in on this device</label>
                    </div>

                    <button type="submit" class="btn btn-ma-primary w-100 py-2">
                        Sign In
                    </button>
                </form>

                <hr class="my-4">
                <p class="text-center small text-muted mb-0">
                    New student at {{ $academySettings['academy_name'] ?? 'Mash Academy' }}?
                    <a href="{{ route('register') }}" class="fw-semibold text-decoration-none">Create a Student Account</a>
                </p>
            </div>
        </div>
    </section>
@endsection
