<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin Panel') — {{ $academySettings['academy_name'] ?? 'Mash Academy' }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="{{ asset('css/mash-academy.css') }}" rel="stylesheet">
</head>
<body>
    @php
        $currentUser = auth()->user();
    @endphp

    <div class="ma-admin-wrapper">
        {{-- Desktop Sidebar --}}
        <aside class="ma-sidebar d-none d-lg-flex" aria-label="Admin Sidebar">
            <div class="ma-sidebar-brand">
                <a href="{{ route('admin.dashboard') }}" class="text-decoration-none">
                    @include('partials.brand', ['nameClass' => 'fs-5'])
                </a>
                <div class="mt-2">
                    <span class="badge {{ $currentUser->role->badgeClass() }}">{{ $currentUser->role->label() }}</span>
                </div>
            </div>

            <nav class="flex-grow-1 py-2">
                <div class="ma-sidebar-section">Overview</div>
                <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                    <i class="bi bi-speedometer2" aria-hidden="true"></i>
                    <span>Dashboard</span>
                </a>

                @can('manage-settings')
                    <div class="ma-sidebar-section">Academy Administration</div>
                    <a class="nav-link {{ request()->routeIs('admin.settings.edit') ? 'active' : '' }}" href="{{ route('admin.settings.edit') }}">
                        <i class="bi bi-sliders" aria-hidden="true"></i>
                        <span>Academy &amp; Branding</span>
                    </a>
                    <a class="nav-link {{ request()->routeIs('admin.settings.policy.*') ? 'active' : '' }}" href="{{ route('admin.settings.policy.edit') }}">
                        <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
                        <span>Platform Policy</span>
                    </a>
                @endcan

                @can('manage-staff')
                    <a class="nav-link {{ request()->routeIs('admin.staff.*') ? 'active' : '' }}" href="{{ route('admin.staff.index') }}">
                        <i class="bi bi-people" aria-hidden="true"></i>
                        <span>Staff Management</span>
                    </a>
                @endcan

                @can('view-activity-logs')
                    <a class="nav-link {{ request()->routeIs('admin.activity-logs.*') ? 'active' : '' }}" href="{{ route('admin.activity-logs.index') }}">
                        <i class="bi bi-journal-check" aria-hidden="true"></i>
                        <span>Activity Logs</span>
                    </a>
                @endcan

                <div class="ma-sidebar-section">Account</div>
                <a class="nav-link {{ request()->routeIs('account.password.*') ? 'active' : '' }}" href="{{ route('account.password.edit') }}">
                    <i class="bi bi-key" aria-hidden="true"></i>
                    <span>Change Password</span>
                </a>
                <a class="nav-link" href="{{ route('home') }}">
                    <i class="bi bi-globe" aria-hidden="true"></i>
                    <span>View Public Site</span>
                </a>
            </nav>

            <div class="p-3 border-top border-secondary border-opacity-25">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-light btn-sm w-100">
                        <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Sign Out
                    </button>
                </form>
            </div>
        </aside>

        {{-- Mobile Sidebar Offcanvas --}}
        <div class="offcanvas offcanvas-start bg-dark text-white d-lg-none" tabindex="-1" id="adminMobileSidebar" aria-labelledby="adminMobileSidebarLabel">
            <div class="offcanvas-header border-bottom border-secondary border-opacity-25">
                <div id="adminMobileSidebarLabel">
                    @include('partials.brand', ['nameClass' => 'fs-5 text-white'])
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body d-flex flex-column p-0">
                <div class="px-3 pt-3">
                    <span class="badge {{ $currentUser->role->badgeClass() }}">{{ $currentUser->role->label() }}</span>
                </div>
                <nav class="nav flex-column p-2 gap-1 flex-grow-1">
                    <a class="nav-link text-white rounded {{ request()->routeIs('admin.dashboard') ? 'bg-teal fw-semibold' : '' }}" href="{{ route('admin.dashboard') }}">
                        <i class="bi bi-speedometer2 me-2" aria-hidden="true"></i>Dashboard
                    </a>
                    @can('manage-settings')
                        <a class="nav-link text-white rounded {{ request()->routeIs('admin.settings.edit') ? 'bg-teal fw-semibold' : '' }}" href="{{ route('admin.settings.edit') }}">
                            <i class="bi bi-sliders me-2" aria-hidden="true"></i>Academy &amp; Branding
                        </a>
                        <a class="nav-link text-white rounded {{ request()->routeIs('admin.settings.policy.*') ? 'bg-teal fw-semibold' : '' }}" href="{{ route('admin.settings.policy.edit') }}">
                            <i class="bi bi-file-earmark-text me-2" aria-hidden="true"></i>Platform Policy
                        </a>
                    @endcan
                    @can('manage-staff')
                        <a class="nav-link text-white rounded {{ request()->routeIs('admin.staff.*') ? 'bg-teal fw-semibold' : '' }}" href="{{ route('admin.staff.index') }}">
                            <i class="bi bi-people me-2" aria-hidden="true"></i>Staff Management
                        </a>
                    @endcan
                    @can('view-activity-logs')
                        <a class="nav-link text-white rounded {{ request()->routeIs('admin.activity-logs.*') ? 'bg-teal fw-semibold' : '' }}" href="{{ route('admin.activity-logs.index') }}">
                            <i class="bi bi-journal-check me-2" aria-hidden="true"></i>Activity Logs
                        </a>
                    @endcan
                    <hr class="border-secondary opacity-25 my-2">
                    <a class="nav-link text-white rounded {{ request()->routeIs('account.password.*') ? 'bg-teal fw-semibold' : '' }}" href="{{ route('account.password.edit') }}">
                        <i class="bi bi-key me-2" aria-hidden="true"></i>Change Password
                    </a>
                    <a class="nav-link text-white rounded" href="{{ route('home') }}">
                        <i class="bi bi-globe me-2" aria-hidden="true"></i>View Public Site
                    </a>
                </nav>
                <div class="p-3 border-top border-secondary border-opacity-25">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-light btn-sm w-100">
                            <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Sign Out
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Main Content Area --}}
        <div class="ma-admin-main">
            <header class="ma-topbar d-flex align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-outline-secondary btn-sm d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMobileSidebar" aria-controls="adminMobileSidebar" aria-label="Open menu">
                        <i class="bi bi-list fs-5" aria-hidden="true"></i>
                    </button>
                    <h1 class="h5 mb-0 fw-bold">@yield('page-title', 'Dashboard')</h1>
                </div>

                <div class="d-flex align-items-center gap-3">
                    <div class="text-end d-none sm-block d-md-block">
                        <div class="fw-semibold small lh-1">{{ $currentUser->name }}</div>
                        <div class="text-muted" style="font-size: 0.78rem;">{{ $currentUser->email }}</div>
                    </div>
                    <span class="badge {{ $currentUser->role->badgeClass() }} d-none d-sm-inline-block">{{ $currentUser->role->shortLabel() }}</span>
                </div>
            </header>

            <main class="p-3 p-md-4 flex-grow-1">
                @if ($currentUser->must_change_password && ! request()->routeIs('account.password.*'))
                    <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2 shadow-sm" role="alert">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-shield-exclamation fs-5" aria-hidden="true"></i>
                            <span><strong>Security reminder:</strong> You are currently using a temporary password. Please update your password.</span>
                        </div>
                        <a href="{{ route('account.password.edit') }}" class="btn btn-warning btn-sm fw-semibold">Update Password</a>
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
