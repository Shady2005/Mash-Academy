@extends('layouts.public')

@section('title', 'Welcome')

@section('content')
    <section class="ma-hero py-5">
        <div class="container py-lg-4 position-relative" style="z-index: 1;">
            <div class="row align-items-center gy-4">
                <div class="col-lg-8">
                    <span class="badge bg-teal bg-opacity-75 px-3 py-2 mb-3 fw-semibold">
                        <i class="bi bi-mortarboard-fill me-1" aria-hidden="true"></i>
                        Online Learning Platform
                    </span>
                    <h1 class="display-5 fw-bold mb-3">
                        {{ $academySettings['academy_name'] ?? 'Mash Academy' }}
                    </h1>
                    <p class="lead text-white-50 mb-4" style="max-width: 640px;">
                        {{ $academySettings['tagline'] ?? 'Structured, practical online learning built for measurable student progress.' }}
                    </p>
                    <div class="d-flex flex-wrap gap-3">
                        @auth
                            <a href="{{ auth()->user()->dashboardRoute() }}" class="btn btn-ma-accent btn-lg px-4">
                                Go to My Dashboard <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
                            </a>
                        @else
                            <a href="{{ route('register') }}" class="btn btn-ma-accent btn-lg px-4">
                                Create Student Account <i class="bi bi-person-plus ms-1" aria-hidden="true"></i>
                            </a>
                            <a href="{{ route('login') }}" class="btn btn-outline-light btn-lg px-4">
                                Sign In
                            </a>
                        @endauth
                    </div>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <div class="p-4 rounded-4 bg-white bg-opacity-10 border border-white border-opacity-10 text-start">
                        <div class="small text-uppercase fw-semibold text-white-50 mb-2">Academy Standards</div>
                        <ul class="list-unstyled mb-0 small d-flex flex-column gap-2">
                            <li><i class="bi bi-check-circle-fill text-info me-2" aria-hidden="true"></i>Structured instructor-led curriculum</li>
                            <li><i class="bi bi-check-circle-fill text-info me-2" aria-hidden="true"></i>Verified student enrollments</li>
                            <li><i class="bi bi-check-circle-fill text-info me-2" aria-hidden="true"></i>Clear Platform Policy &amp; student consent</li>
                            <li><i class="bi bi-check-circle-fill text-info me-2" aria-hidden="true"></i>Dedicated academic &amp; support roles</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            @include('partials.flash')

            <div class="row g-4">
                <div class="col-md-6">
                    <div class="ma-card p-4 h-100">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="ma-icon-box ma-icon-navy">
                                <i class="bi bi-building" aria-hidden="true"></i>
                            </span>
                            <h2 class="h5 fw-bold mb-0">About {{ $academySettings['academy_name'] ?? 'Mash Academy' }}</h2>
                        </div>
                        <p class="text-muted mb-0" style="white-space: pre-line;">
                            {{ $academySettings['about'] ?? 'Mash Academy delivers structured online courses designed for measurable student mastery.' }}
                        </p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ma-card p-4 h-100">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="ma-icon-box ma-icon-teal">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </span>
                            <h2 class="h5 fw-bold mb-0">Our Vision</h2>
                        </div>
                        <p class="text-muted mb-0" style="white-space: pre-line;">
                            {{ $academySettings['vision'] ?? 'Empowering learners with practical, disciplined online education.' }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="ma-card p-4 mt-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <h3 class="h6 fw-bold mb-1">Platform Policy &amp; Student Agreement</h3>
                    <p class="text-muted small mb-0">
                        All students review and agree to our active Platform Policy
                        @if ($activePolicy)
                            (Version {{ $activePolicy->version }})
                        @endif
                        during registration.
                    </p>
                </div>
                <a href="{{ route('policy.show') }}" class="btn btn-outline-primary btn-sm px-3">
                    Read Active Policy <i class="bi bi-file-earmark-text ms-1" aria-hidden="true"></i>
                </a>
            </div>
        </div>
    </section>
@endsection
