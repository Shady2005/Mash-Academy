@php
    $locale = $currentLocale ?? app()->getLocale();
    $rtl = $isRtl ?? ($locale === 'ar');
    $theme = $currentTheme ?? 'light';
    $academyName = \App\Models\AcademySetting::localized('academy_name', $locale, $academySettings ?? null) ?: 'Mash Academy';
    $academyTagline = \App\Models\AcademySetting::localized('tagline', $locale, $academySettings ?? null);
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}" data-bs-theme="{{ $theme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="{{ $theme }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $academyName) — {{ $academyName }}</title>

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
                            <a class="nav-link {{ request()->routeIs('home') ? 'active fw-semibold' : '' }}" href="{{ route('home') }}">{{ __('messages.home') }}</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('courses.*') ? 'active fw-semibold' : '' }}" href="{{ route('courses.index') }}">{{ __('messages.courses') }}</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('policy.show') ? 'active fw-semibold' : '' }}" href="{{ route('policy.show') }}">{{ __('messages.platform_policy') }}</a>
                        </li>
                        @if (($academySettings['enable_consultations'] ?? '1') === '1')
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('consultations.*') ? 'active fw-semibold' : '' }}" href="{{ route('consultations.index') }}">{{ __('messages.book_consultation') }}</a>
                            </li>
                        @endif
                        @if (($academySettings['enable_certificate_verification'] ?? '1') === '1')
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('certificates.verify', 'verify-certificate') ? 'active fw-semibold' : '' }}" href="{{ route('verify-certificate') }}">{{ __('messages.verify_certificate') }}</a>
                            </li>
                        @endif
                    </ul>

                    <div class="d-flex flex-wrap align-items-center gap-2">
                        @include('partials.preferences-controls')

                        @auth
                            @if (auth()->user()->isSuperAdmin())
                                <a href="{{ route('admin.dashboard') }}" class="btn btn-ma-primary btn-sm px-3">
                                    <i class="bi bi-speedometer2 me-1" aria-hidden="true"></i>
                                    {{ __('messages.admin_panel') }}
                                </a>
                                <a href="{{ route('student.dashboard') }}" class="btn btn-ma-dark btn-sm px-3">
                                    <i class="bi bi-mortarboard me-1" aria-hidden="true"></i>
                                    {{ __('messages.student_view') }}
                                </a>
                            @else
                                <a href="{{ auth()->user()->dashboardRoute() }}" class="btn btn-ma-primary btn-sm px-3">
                                    <i class="bi bi-speedometer2 me-1" aria-hidden="true"></i>
                                    {{ auth()->user()->isStaff() ? __('messages.admin_panel') : __('messages.student_portal') }}
                                </a>
                            @endif
                            <form method="POST" action="{{ route('logout') }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-ma-outline btn-sm px-3">{{ __('messages.sign_out') }}</button>
                            </form>
                        @else
                            <a href="{{ route('login') }}" class="btn btn-ma-outline btn-sm px-3">{{ __('messages.sign_in') }}</a>
                            <a href="{{ route('register') }}" class="btn btn-ma-primary btn-sm px-3">{{ __('messages.student_sign_up') }}</a>
                        @endauth
                    </div>
                </div>
            </div>
        </nav>
    </header>

    @include('partials.global-announcements-bar')

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
                    <p class="mb-0 small">{{ $academyTagline }}</p>
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
                        <a href="{{ route('courses.index') }}" class="text-decoration-none text-muted me-3">{{ __('messages.courses') }}</a>
                        @if (($academySettings['enable_certificate_verification'] ?? '1') === '1')
                            <a href="{{ route('verify-certificate') }}" class="text-decoration-none text-muted me-3">{{ __('messages.verify_certificate') }}</a>
                        @endif
                        @if (($academySettings['enable_consultations'] ?? '1') === '1')
                            <a href="{{ route('consultations.index') }}" class="text-decoration-none text-muted me-3">{{ __('messages.book_consultation') }}</a>
                        @endif
                        <a href="{{ route('policy.show') }}" class="text-decoration-none text-muted me-3">{{ __('messages.platform_policy') }}</a>
                        <span>&copy; {{ date('Y') }} {{ $academyName }}. {{ __('messages.all_rights_reserved') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    @include('partials.support-chat-widget')

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="{{ asset('js/mash-academy.js') }}"></script>
    @stack('scripts')
</body>
</html>
