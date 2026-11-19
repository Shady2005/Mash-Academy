<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $academySettings['academy_name'] ?? 'Mash Academy') — {{ $academySettings['academy_name'] ?? 'Mash Academy' }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="{{ asset('css/mash-academy.css') }}" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">
    <header class="ma-navbar sticky-top">
        <nav class="navbar navbar-expand-lg py-2" aria-label="Primary Navigation">
            <div class="container">
                <a class="navbar-brand py-1" href="{{ route('home') }}">
                    @include('partials.brand', ['nameClass' => 'fs-5'])
                </a>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNavbar" aria-controls="publicNavbar" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="publicNavbar">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-3">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('home') ? 'active fw-semibold text-primary' : '' }}" href="{{ route('home') }}">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('policy.show') ? 'active fw-semibold text-primary' : '' }}" href="{{ route('policy.show') }}">Platform Policy</a>
                        </li>
                    </ul>

                    <div class="d-flex align-items-center gap-2">
                        @auth
                            <a href="{{ auth()->user()->dashboardRoute() }}" class="btn btn-ma-primary btn-sm px-3">
                                <i class="bi bi-speedometer2 me-1" aria-hidden="true"></i>
                                {{ auth()->user()->isStaff() ? 'Admin Panel' : 'Student Portal' }}
                            </a>
                            <form method="POST" action="{{ route('logout') }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-outline-secondary btn-sm px-3">Sign Out</button>
                            </form>
                        @else
                            <a href="{{ route('login') }}" class="btn btn-outline-secondary btn-sm px-3">Sign In</a>
                            <a href="{{ route('register') }}" class="btn btn-ma-accent btn-sm px-3">Student Sign Up</a>
                        @endauth
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <main class="flex-grow-1">
        @yield('content')
    </main>

    <footer class="ma-footer py-4 mt-auto">
        <div class="container">
            <div class="row gy-3 align-items-center">
                <div class="col-md-6">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        @include('partials.brand', ['nameClass' => 'fs-6'])
                    </div>
                    <p class="mb-0 small">{{ $academySettings['tagline'] ?? '' }}</p>
                </div>
                <div class="col-md-6 text-md-end small">
                    @if (! empty($academySettings['contact_email']))
                        <span class="me-3">
                            <i class="bi bi-envelope me-1" aria-hidden="true"></i>
                            <a href="mailto:{{ $academySettings['contact_email'] }}" class="text-decoration-none text-muted">{{ $academySettings['contact_email'] }}</a>
                        </span>
                    @endif
                    @if (! empty($academySettings['contact_phone']))
                        <span>
                            <i class="bi bi-telephone me-1" aria-hidden="true"></i>
                            {{ $academySettings['contact_phone'] }}
                        </span>
                    @endif
                    <div class="mt-2">
                        <a href="{{ route('policy.show') }}" class="text-decoration-none text-muted me-3">Platform Policy</a>
                        <span>&copy; {{ date('Y') }} {{ $academySettings['academy_name'] ?? 'Mash Academy' }}. All rights reserved.</span>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="{{ asset('js/mash-academy.js') }}"></script>
    @stack('scripts')
</body>
</html>
