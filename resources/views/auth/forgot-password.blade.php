@extends('layouts.public')

@section('title', 'Forgot Password')

@section('content')
    <section class="py-5">
        <div class="container" style="max-width: 460px;">
            <div class="ma-card-elevated p-4 p-md-5">
                <div class="text-center mb-4">
                    <h1 class="h4 fw-bold mb-1">Reset Your Password</h1>
                    <p class="text-muted small mb-0">Enter your registered email address and we will send you a password reset link.</p>
                </div>

                @include('partials.flash')

                <form method="POST" action="{{ route('password.email') }}" class="ma-form">
                    @csrf

                    <div class="mb-4 ma-field">
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
                            data-error="forgot-email-error"
                        >
                        <div id="forgot-email-error" class="ma-inline-error">Please enter a valid email address.</div>
                        @error('email')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-ma-primary w-100 py-2">
                        Send Password Reset Link
                    </button>
                </form>

                <div class="text-center mt-4">
                    <a href="{{ route('login') }}" class="small text-decoration-none">
                        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back to Sign In
                    </a>
                </div>
            </div>
        </div>
    </section>
@endsection
