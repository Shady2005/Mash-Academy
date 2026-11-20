@php
    $locale = $currentLocale ?? app()->getLocale();
    $rtl = $isRtl ?? ($locale === 'ar');
    $theme = $currentTheme ?? 'light';
    $academyName = \App\Models\AcademySetting::localized('academy_name', $locale, $academySettings ?? null) ?: 'Mash Academy';
    $currentUser = auth()->user();
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}" data-bs-theme="{{ $theme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="{{ $theme }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('messages.admin_panel')) — {{ $academyName }}</title>

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
<body>
    <div class="ma-admin-wrapper">
        {{-- Desktop Sidebar --}}
        <aside class="ma-sidebar d-none d-lg-flex" aria-label="Admin Sidebar">
            <div class="ma-sidebar-brand">
                <a href="{{ route('admin.dashboard') }}" class="text-decoration-none">
                    @include('partials.brand', ['nameClass' => 'fs-5'])
                </a>
                <div class="mt-2">
                    <span class="badge bg-navy-accent">{{ $currentUser->role->label() }}</span>
                </div>
            </div>

            <nav class="flex-grow-1 py-2">
                <div class="ma-sidebar-section">{{ __('messages.overview') }}</div>
                <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                    <i class="bi bi-speedometer2" aria-hidden="true"></i>
                    <span>{{ __('messages.dashboard') }}</span>
                </a>

                @can('view-analytics')
                    <div class="ma-sidebar-section">{{ __('messages.analytics_support') }}</div>
                    <a class="nav-link {{ request()->routeIs('admin.analytics.*') ? 'active' : '' }}" href="{{ route('admin.analytics.index') }}">
                        <i class="bi bi-graph-up-arrow" aria-hidden="true"></i>
                        <span>{{ __('messages.analytics_dashboard') }}</span>
                    </a>
                    <a class="nav-link {{ request()->routeIs('admin.students.*') ? 'active' : '' }}" href="{{ route('admin.students.index') }}">
                        <i class="bi bi-person-lines-fill" aria-hidden="true"></i>
                        <span>{{ __('messages.student_directory') }}</span>
                    </a>
                    <a class="nav-link {{ request()->routeIs('admin.certificates.*') ? 'active' : '' }}" href="{{ route('admin.certificates.index') }}">
                        <i class="bi bi-patch-check-fill" aria-hidden="true"></i>
                        <span>{{ __('messages.certificates_registry') }}</span>
                    </a>
                @endcan

                @can('manage-courses')
                    <div class="ma-sidebar-section">{{ __('messages.curriculum') }}</div>
                    <a class="nav-link {{ request()->routeIs('admin.courses.*') ? 'active' : '' }}" href="{{ route('admin.courses.index') }}">
                        <i class="bi bi-journal-bookmark-fill" aria-hidden="true"></i>
                        <span>{{ __('messages.manage_courses') }}</span>
                    </a>
                @endcan

                @can('manage-instructor-profile')
                    <a class="nav-link {{ request()->routeIs('admin.instructor-profile.*') ? 'active' : '' }}" href="{{ route('admin.instructor-profile.edit') }}">
                        <i class="bi bi-person-badge-fill" aria-hidden="true"></i>
                        <span>{{ __('messages.instructor_profile_qr') }}</span>
                    </a>
                @endcan

                @can('manage-payments')
                    <div class="ma-sidebar-section">{{ __('messages.payments_enrollments') }}</div>
                    <a class="nav-link {{ request()->routeIs('admin.payments.*') ? 'active' : '' }}" href="{{ route('admin.payments.index') }}">
                        <i class="bi bi-receipt-cutoff" aria-hidden="true"></i>
                        <span>{{ __('messages.payments_inbox') }}</span>
                    </a>
                    <a class="nav-link {{ request()->routeIs('admin.enrollments.*') ? 'active' : '' }}" href="{{ route('admin.enrollments.index') }}">
                        <i class="bi bi-calendar2-check" aria-hidden="true"></i>
                        <span>{{ __('messages.enrollments') }}</span>
                    </a>
                    <a class="nav-link {{ request()->routeIs('admin.promo-codes.*') ? 'active' : '' }}" href="{{ route('admin.promo-codes.index') }}">
                        <i class="bi bi-ticket-perforated" aria-hidden="true"></i>
                        <span>{{ __('messages.promo_codes') }}</span>
                    </a>
                @endcan

                @can('manage-settings')
                    <div class="ma-sidebar-section">{{ __('messages.academy_administration') }}</div>
                    <a class="nav-link {{ request()->routeIs('admin.settings.edit') ? 'active' : '' }}" href="{{ route('admin.settings.edit') }}">
                        <i class="bi bi-sliders" aria-hidden="true"></i>
                        <span>{{ __('messages.academy_branding') }}</span>
                    </a>
                    <a class="nav-link {{ request()->routeIs('admin.settings.policy.*') ? 'active' : '' }}" href="{{ route('admin.settings.policy.edit') }}">
                        <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
                        <span>{{ __('messages.platform_policy') }}</span>
                    </a>
                @endcan

                @can('manage-staff')
                    <a class="nav-link {{ request()->routeIs('admin.staff.*') ? 'active' : '' }}" href="{{ route('admin.staff.index') }}">
                        <i class="bi bi-people" aria-hidden="true"></i>
                        <span>{{ __('messages.staff_management') }}</span>
                    </a>
                @endcan

                @can('view-activity-logs')
                    <a class="nav-link {{ request()->routeIs('admin.activity-logs.*') ? 'active' : '' }}" href="{{ route('admin.activity-logs.index') }}">
                        <i class="bi bi-journal-check" aria-hidden="true"></i>
                        <span>{{ __('messages.activity_logs') }}</span>
                    </a>
                @endcan

                <div class="ma-sidebar-section">{{ __('messages.account') }}</div>
                @if ($currentUser->isSuperAdmin())
                    <a class="nav-link" href="{{ route('student.dashboard') }}">
                        <i class="bi bi-mortarboard" aria-hidden="true"></i>
                        <span>{{ __('messages.student_view') }} / {{ __('messages.my_learning') }}</span>
                    </a>
                @endif
                <a class="nav-link {{ request()->routeIs('account.password.*') ? 'active' : '' }}" href="{{ route('account.password.edit') }}">
                    <i class="bi bi-key" aria-hidden="true"></i>
                    <span>{{ __('messages.change_password') }}</span>
                </a>
                <a class="nav-link" href="{{ route('home') }}">
                    <i class="bi bi-globe" aria-hidden="true"></i>
                    <span>{{ __('messages.view_public_site') }}</span>
                </a>
            </nav>

            <div class="p-3 border-top border-secondary border-opacity-25">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-light btn-sm w-100">
                        <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>{{ __('messages.sign_out') }}
                    </button>
                </form>
            </div>
        </aside>

        {{-- Mobile Sidebar Offcanvas --}}
        <div class="offcanvas offcanvas-start text-white d-lg-none" style="background-color: #0a192f;" tabindex="-1" id="adminMobileSidebar" aria-labelledby="adminMobileSidebarLabel">
            <div class="offcanvas-header border-bottom border-secondary border-opacity-25">
                <div id="adminMobileSidebarLabel">
                    @include('partials.brand', ['nameClass' => 'fs-5 text-white'])
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body d-flex flex-column p-0">
                <div class="px-3 pt-3">
                    <span class="badge bg-navy-accent">{{ $currentUser->role->label() }}</span>
                </div>
                <nav class="nav flex-column p-2 gap-1 flex-grow-1">
                    <a class="nav-link text-white rounded {{ request()->routeIs('admin.dashboard') ? 'bg-navy-accent fw-semibold' : '' }}" href="{{ route('admin.dashboard') }}">
                        <i class="bi bi-speedometer2 me-2" aria-hidden="true"></i>{{ __('messages.dashboard') }}
                    </a>
                    @can('view-analytics')
                        <a class="nav-link text-white rounded {{ request()->routeIs('admin.analytics.*') ? 'bg-navy-accent fw-semibold' : '' }}" href="{{ route('admin.analytics.index') }}">
                            <i class="bi bi-graph-up-arrow me-2" aria-hidden="true"></i>{{ __('messages.analytics_dashboard') }}
                        </a>
                        <a class="nav-link text-white rounded {{ request()->routeIs('admin.students.*') ? 'bg-navy-accent fw-semibold' : '' }}" href="{{ route('admin.students.index') }}">
                            <i class="bi bi-person-lines-fill me-2" aria-hidden="true"></i>{{ __('messages.student_directory') }}
                        </a>
                        <a class="nav-link text-white rounded {{ request()->routeIs('admin.certificates.*') ? 'bg-navy-accent fw-semibold' : '' }}" href="{{ route('admin.certificates.index') }}">
                            <i class="bi bi-patch-check-fill me-2" aria-hidden="true"></i>{{ __('messages.certificates_registry') }}
                        </a>
                    @endcan
                    @can('manage-courses')
                        <a class="nav-link text-white rounded {{ request()->routeIs('admin.courses.*') ? 'bg-navy-accent fw-semibold' : '' }}" href="{{ route('admin.courses.index') }}">
                            <i class="bi bi-journal-bookmark-fill me-2" aria-hidden="true"></i>{{ __('messages.manage_courses') }}
                        </a>
                    @endcan
                    @can('manage-instructor-profile')
                        <a class="nav-link text-white rounded {{ request()->routeIs('admin.instructor-profile.*') ? 'bg-navy-accent fw-semibold' : '' }}" href="{{ route('admin.instructor-profile.edit') }}">
                            <i class="bi bi-person-badge-fill me-2" aria-hidden="true"></i>{{ __('messages.instructor_profile_qr') }}
                        </a>
                    @endcan
                    @can('manage-payments')
                        <a class="nav-link text-white rounded {{ request()->routeIs('admin.payments.*') ? 'bg-navy-accent fw-semibold' : '' }}" href="{{ route('admin.payments.index') }}">
                            <i class="bi bi-receipt-cutoff me-2" aria-hidden="true"></i>{{ __('messages.payments_inbox') }}
                        </a>
                        <a class="nav-link text-white rounded {{ request()->routeIs('admin.enrollments.*') ? 'bg-navy-accent fw-semibold' : '' }}" href="{{ route('admin.enrollments.index') }}">
                            <i class="bi bi-calendar2-check me-2" aria-hidden="true"></i>{{ __('messages.enrollments') }}
                        </a>
                        <a class="nav-link text-white rounded {{ request()->routeIs('admin.promo-codes.*') ? 'bg-navy-accent fw-semibold' : '' }}" href="{{ route('admin.promo-codes.index') }}">
                            <i class="bi bi-ticket-perforated me-2" aria-hidden="true"></i>{{ __('messages.promo_codes') }}
                        </a>
                    @endcan
                    @can('manage-settings')
                        <a class="nav-link text-white rounded {{ request()->routeIs('admin.settings.edit') ? 'bg-navy-accent fw-semibold' : '' }}" href="{{ route('admin.settings.edit') }}">
                            <i class="bi bi-sliders me-2" aria-hidden="true"></i>{{ __('messages.academy_branding') }}
                        </a>
                        <a class="nav-link text-white rounded {{ request()->routeIs('admin.settings.policy.*') ? 'bg-navy-accent fw-semibold' : '' }}" href="{{ route('admin.settings.policy.edit') }}">
                            <i class="bi bi-file-earmark-text me-2" aria-hidden="true"></i>{{ __('messages.platform_policy') }}
                        </a>
                    @endcan
                    @can('manage-staff')
                        <a class="nav-link text-white rounded {{ request()->routeIs('admin.staff.*') ? 'bg-navy-accent fw-semibold' : '' }}" href="{{ route('admin.staff.index') }}">
                            <i class="bi bi-people me-2" aria-hidden="true"></i>{{ __('messages.staff_management') }}
                        </a>
                    @endcan
                    @can('view-activity-logs')
                        <a class="nav-link text-white rounded {{ request()->routeIs('admin.activity-logs.*') ? 'bg-navy-accent fw-semibold' : '' }}" href="{{ route('admin.activity-logs.index') }}">
                            <i class="bi bi-journal-check me-2" aria-hidden="true"></i>{{ __('messages.activity_logs') }}
                        </a>
                    @endcan
                    <hr class="border-secondary opacity-25 my-2">
                    @if ($currentUser->isSuperAdmin())
                        <a class="nav-link text-white rounded" href="{{ route('student.dashboard') }}">
                            <i class="bi bi-mortarboard me-2" aria-hidden="true"></i>{{ __('messages.student_view') }} / {{ __('messages.my_learning') }}
                        </a>
                    @endif
                    <a class="nav-link text-white rounded {{ request()->routeIs('account.password.*') ? 'bg-navy-accent fw-semibold' : '' }}" href="{{ route('account.password.edit') }}">
                        <i class="bi bi-key me-2" aria-hidden="true"></i>{{ __('messages.change_password') }}
                    </a>
                    <a class="nav-link text-white rounded" href="{{ route('home') }}">
                        <i class="bi bi-globe me-2" aria-hidden="true"></i>{{ __('messages.view_public_site') }}
                    </a>
                </nav>
                <div class="p-3 border-top border-secondary border-opacity-25">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-light btn-sm w-100">
                            <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>{{ __('messages.sign_out') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Main Content Area --}}
        <div class="ma-admin-main">
            <header class="ma-topbar d-flex align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-ma-pref btn-sm d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMobileSidebar" aria-controls="adminMobileSidebar" aria-label="Open menu">
                        <i class="bi bi-list fs-5" aria-hidden="true"></i>
                    </button>
                    <h1 class="h5 mb-0 fw-bold">@yield('page-title', __('messages.dashboard'))</h1>
                </div>

                <div class="d-flex align-items-center gap-2 gap-md-3">
                    @if ($currentUser->isSuperAdmin())
                        <a href="{{ route('student.dashboard') }}" class="btn btn-ma-dark btn-sm px-2 px-md-3">
                            <i class="bi bi-mortarboard me-1" aria-hidden="true"></i>
                            <span class="d-none d-sm-inline">{{ __('messages.student_view') }}</span>
                        </a>
                    @endif

                    @include('partials.preferences-controls')

                    <div class="text-end d-none d-md-block">
                        <div class="fw-semibold small lh-1">{{ $currentUser->name }}</div>
                        <div class="text-muted" style="font-size: 0.78rem;">{{ $currentUser->email }}</div>
                    </div>
                    <span class="badge bg-navy d-none d-sm-inline-block">{{ $currentUser->role->shortLabel() }}</span>
                </div>
            </header>

            <main class="p-3 p-md-4 flex-grow-1">
                @if ($currentUser->must_change_password && ! request()->routeIs('account.password.*'))
                    <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2 shadow-sm" role="alert">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-shield-exclamation fs-5" aria-hidden="true"></i>
                            <span><strong>{{ __('messages.security_reminder') }}</strong> {{ __('messages.temp_password_warning') }}</span>
                        </div>
                        <a href="{{ route('account.password.edit') }}" class="btn btn-warning btn-sm fw-semibold">{{ __('messages.update_password') }}</a>
                    </div>
                @endif

                @include('partials.flash')
                @yield('content')
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="{{ asset('js/mash-academy.js') }}"></script>
    @stack('scripts')
</body>
</html>
