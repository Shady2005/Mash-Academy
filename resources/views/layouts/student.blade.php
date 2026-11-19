<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Student Portal') — {{ $academySettings['academy_name'] ?? 'Mash Academy' }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="{{ asset('css/mash-academy.css') }}" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">
    <header class="ma-navbar sticky-top">
        <nav class="navbar navbar-expand-lg py-2" aria-label="Student Portal Navigation">
            <div class="container">
                <a class="navbar-brand py-1" href="{{ route('student.dashboard') }}">
                    @include('partials.brand', ['nameClass' => 'fs-5'])
                </a>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#studentNavbar" aria-controls="studentNavbar" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="studentNavbar">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-3">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('student.dashboard') ? 'active fw-semibold text-primary' : '' }}" href="{{ route('student.dashboard') }}">
                                <i class="bi bi-grid me-1" aria-hidden="true"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('account.password.*') ? 'active fw-semibold text-primary' : '' }}" href="{{ route('account.password.edit') }}">
                                <i class="bi bi-key me-1" aria-hidden="true"></i>Change Password
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('home') }}">
                                <i class="bi bi-house me-1" aria-hidden="true"></i>Public Site
                            </a>
                        </li>
                    </ul>

                    <div class="d-flex align-items-center gap-3">
                        <div class="text-end d-none d-lg-block">
                            <div class="fw-semibold small lh-1">{{ auth()->user()->name }}</div>
                            <span class="badge bg-secondary" style="font-size: 0.7rem;">Student</span>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Sign Out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <main class="flex-grow-1 py-4">
        <div class="container">
            @include('partials.flash')
            @yield('content')
        </div>
    </main>

    <footer class="ma-footer py-3 mt-auto">
        <div class="container d-flex flex-wrap justify-content-between align-items-center gap-2 small">
            <span>&copy; {{ date('Y') }} {{ $academySettings['academy_name'] ?? 'Mash Academy' }} — Student Portal</span>
            <a href="{{ route('policy.show') }}" class="text-decoration-none text-muted">Platform Policy</a>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="{{ asset('js/mash-academy.js') }}"></script>
    @stack('scripts')
</body>
</html>
