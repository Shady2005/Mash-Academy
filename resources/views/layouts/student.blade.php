@php
    $locale = $currentLocale ?? app()->getLocale();
    $rtl = $isRtl ?? ($locale === 'ar');
    $theme = $currentTheme ?? 'light';
    $academyName = \App\Models\AcademySetting::localized('academy_name', $locale, $academySettings ?? null) ?: 'Mash Academy';
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}" data-bs-theme="{{ $theme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="{{ $theme }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('messages.student_portal')) — {{ $academyName }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @if ($rtl)
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet" crossorigin="anonymous">
    @else
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    @endif
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
                            <a class="nav-link {{ request()->routeIs('student.dashboard') ? 'active fw-semibold' : '' }}" href="{{ route('student.dashboard') }}">
                                <i class="bi bi-grid me-1" aria-hidden="true"></i>{{ __('messages.dashboard') }}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('student.my-courses.*') ? 'active fw-semibold' : '' }}" href="{{ route('student.my-courses.index') }}">
                                <i class="bi bi-play-btn me-1" aria-hidden="true"></i>{{ __('messages.my_courses') }}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('student.courses.*') ? 'active fw-semibold' : '' }}" href="{{ route('student.courses.index') }}">
                                <i class="bi bi-collection-play me-1" aria-hidden="true"></i>{{ __('messages.browse_courses') }}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('student.payments.*') ? 'active fw-semibold' : '' }}" href="{{ route('student.payments.index') }}">
                                <i class="bi bi-receipt me-1" aria-hidden="true"></i>{{ __('messages.my_payments') }}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('student.certificates.*') ? 'active fw-semibold' : '' }}" href="{{ route('student.certificates.index') }}">
                                <i class="bi bi-award me-1" aria-hidden="true"></i>{{ __('messages.my_certificates') }}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('account.password.*') ? 'active fw-semibold' : '' }}" href="{{ route('account.password.edit') }}">
                                <i class="bi bi-key me-1" aria-hidden="true"></i>{{ __('messages.change_password') }}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('home') }}">
                                <i class="bi bi-house me-1" aria-hidden="true"></i>{{ __('messages.public_site') }}
                            </a>
                        </li>
                    </ul>

                    <div class="d-flex flex-wrap align-items-center gap-2">
                        @include('partials.preferences-controls')

                        @if (auth()->user()->isSuperAdmin())
                            <a href="{{ route('admin.dashboard') }}" class="btn btn-ma-primary btn-sm px-3">
                                <i class="bi bi-speedometer2 me-1" aria-hidden="true"></i>{{ __('messages.admin_panel') }}
                            </a>
                        @endif

                        <div class="text-end d-none d-lg-block ms-2">
                            <div class="fw-semibold small lh-1">{{ auth()->user()->name }}</div>
                            <span class="badge {{ auth()->user()->isSuperAdmin() ? 'bg-navy-accent' : 'bg-navy' }}" style="font-size: 0.7rem;">
                                {{ auth()->user()->isSuperAdmin() ? auth()->user()->role->shortLabel() : 'Student' }}
                            </span>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>{{ __('messages.sign_out') }}
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
            <span>&copy; {{ date('Y') }} {{ $academyName }} — {{ __('messages.student_portal') }}</span>
            <a href="{{ route('policy.show') }}" class="text-decoration-none text-muted">{{ __('messages.platform_policy') }}</a>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="{{ asset('js/mash-academy.js') }}"></script>
    @stack('scripts')
</body>
</html>
