@extends('layouts.admin')

@section('title', __('messages.users_management'))
@section('page-title', __('messages.users_management'))

@section('content')
    @php
        $actor = auth()->user();
        $isSuperAdmin = $actor->isSuperAdmin();
    @endphp

    {{-- KPI Strip + Add User Trigger --}}
    <div class="row g-3 mb-4 align-items-stretch">
        <div class="col-sm-4 col-lg-3">
            <div class="ma-card p-3 h-100">
                <div class="small text-muted text-uppercase fw-semibold">Total Users</div>
                <div class="h3 fw-bold mb-0 mt-1">{{ $counts['total'] }}</div>
            </div>
        </div>
        <div class="col-sm-4 col-lg-3">
            <div class="ma-card p-3 h-100">
                <div class="small text-muted text-uppercase fw-semibold">Students</div>
                <div class="h3 fw-bold mb-0 mt-1 text-navy">{{ $counts['students'] }}</div>
            </div>
        </div>
        <div class="col-sm-4 col-lg-3">
            <div class="ma-card p-3 h-100">
                <div class="small text-muted text-uppercase fw-semibold">Blocked Accounts</div>
                <div class="h3 fw-bold mb-0 mt-1 text-danger">{{ $counts['blocked'] }}</div>
            </div>
        </div>
        <div class="col-lg-3">
            <div class="ma-card p-3 h-100 d-flex flex-column justify-content-between">
                <div class="small text-muted">
                    {{ $isSuperAdmin ? 'Create Student or available Staff accounts.' : 'Teacher & Support can create Student accounts.' }}
                </div>
                <button type="button" class="btn btn-ma-primary btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="bi bi-person-plus-fill me-1" aria-hidden="true"></i>{{ __('messages.add_user') }}
                </button>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="ma-card p-3 mb-3">
        <form method="GET" action="{{ route('admin.users.index') }}" class="row g-2 align-items-center">
            <div class="col-md-5">
                <input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="Search by name, email, or phone...">
            </div>
            <div class="col-md-3">
                <select name="role" class="form-select form-select-sm">
                    <option value="">All Roles</option>
                    <option value="student" {{ $roleFilter === 'student' ? 'selected' : '' }}>Students Only</option>
                    <option value="staff" {{ $roleFilter === 'staff' ? 'selected' : '' }}>Staff Accounts</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="active" {{ $statusFilter === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="blocked" {{ $statusFilter === 'blocked' ? 'selected' : '' }}>Blocked</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-ma-primary btn-sm px-3">Filter</button>
                @if ($search !== '' || $roleFilter !== '' || $statusFilter !== '')
                    <a href="{{ route('admin.users.index') }}" class="btn btn-ma-outline btn-sm">Clear</a>
                @endif
            </div>
        </form>
    </div>

    {{-- Users Table --}}
    <div class="ma-card overflow-hidden">
        @if ($users->isEmpty())
            <div class="p-5 text-center text-muted small">No user accounts match your filter.</div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0 small">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Enrollments</th>
                            <th>Joined</th>
                            <th class="text-end">{{ __('messages.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $u)
                            @php
                                $canBlock = $u->canBeBlockedBy($actor);
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $u->name }}</div>
                                    <div class="text-muted">{{ $u->email }}</div>
                                </td>
                                <td>
                                    <span class="badge {{ $u->isSuperAdmin() ? 'bg-black-badge' : ($u->isStaff() ? 'bg-navy' : 'bg-navy-accent') }}">
                                        {{ $u->role->shortLabel() }}
                                    </span>
                                </td>
                                <td>{{ $u->phone ?: '—' }}</td>
                                <td>
                                    @if ($u->isBlocked())
                                        <span class="badge bg-danger">
                                            <i class="bi bi-slash-circle me-1" aria-hidden="true"></i>{{ __('messages.blocked_badge') }}
                                        </span>
                                        @if ($u->blocked_reason)
                                            <div class="text-danger mt-1" style="max-width: 220px; font-size: 0.76rem;">{{ $u->blocked_reason }}</div>
                                        @endif
                                    @elseif (! $u->is_active)
                                        <span class="badge bg-secondary">Inactive</span>
                                    @else
                                        <span class="badge bg-success">Active</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($u->isStudent())
                                        <span class="badge bg-light text-dark border">{{ $u->enrollments_count }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-muted text-nowrap">{{ $u->created_at->format('M d, Y') }}</td>
                                <td class="text-end text-nowrap">
                                    @if ($canBlock)
                                        @if ($u->isBlocked())
                                            <form method="POST" action="{{ route('admin.users.unblock', $u) }}" class="d-inline" onsubmit="return confirm('Unblock {{ $u->name }} and allow them to sign in again?');">
                                                @csrf
                                                <button type="submit" class="btn btn-ma-primary btn-sm">
                                                    <i class="bi bi-unlock me-1" aria-hidden="true"></i>{{ __('messages.unblock_account') }}
                                                </button>
                                            </form>
                                        @else
                                            <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#blockUserModal{{ $u->id }}">
                                                <i class="bi bi-slash-circle me-1" aria-hidden="true"></i>{{ __('messages.block_account') }}
                                            </button>
                                        @endif
                                    @else
                                        <span class="badge bg-light text-muted border">Protected</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($users->hasPages())
                <div class="p-3 border-top">{{ $users->links() }}</div>
            @endif
        @endif
    </div>

    {{-- Add User Modal --}}
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.users.store') }}" class="ma-form">
                    @csrf
                    <div class="modal-header">
                        <h2 class="modal-title h6 fw-bold" id="addUserModalLabel">
                            <i class="bi bi-person-plus-fill me-1" aria-hidden="true"></i>{{ __('messages.add_user') }}
                        </h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="new_user_name" class="form-label small fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" id="new_user_name" name="name" value="{{ old('name') }}" class="form-control" required maxlength="120">
                        </div>
                        <div class="mb-3">
                            <label for="new_user_email" class="form-label small fw-semibold">Email Address <span class="text-danger">*</span></label>
                            <input type="email" id="new_user_email" name="email" value="{{ old('email') }}" class="form-control" required maxlength="190">
                        </div>
                        <div class="mb-3">
                            <label for="new_user_phone" class="form-label small fw-semibold">Phone Number</label>
                            <input type="tel" id="new_user_phone" name="phone" value="{{ old('phone') }}" class="form-control" maxlength="30" placeholder="+20 100 000 0000">
                        </div>
                        <div class="mb-3">
                            <label for="new_user_password" class="form-label small fw-semibold">Password <span class="text-danger">*</span></label>
                            <input type="password" id="new_user_password" name="password" class="form-control" required minlength="8" autocomplete="new-password">
                            <div class="form-text">Min 8 characters with letters and numbers.</div>
                        </div>
                        <div class="mb-2">
                            <label for="new_user_role" class="form-label small fw-semibold">Account Role <span class="text-danger">*</span></label>
                            @if ($isSuperAdmin)
                                <select id="new_user_role" name="role" class="form-select">
                                    <option value="student" selected>Student</option>
                                    @foreach ($availableStaffRoles as $staffRole)
                                        <option value="{{ $staffRole->value }}">{{ $staffRole->label() }}</option>
                                    @endforeach
                                </select>
                                @if (empty($availableStaffRoles))
                                    <div class="form-text">All 4 fixed staff roles are currently filled; new accounts will be created as Student.</div>
                                @endif
                            @else
                                <input type="hidden" name="role" value="student">
                                <input type="text" id="new_user_role" class="form-control" value="Student (Locked)" readonly disabled>
                                <div class="form-text">Teacher and Customer Support roles are restricted to creating Student accounts only.</div>
                            @endif
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">{{ __('messages.cancel') }}</button>
                        <button type="submit" class="btn btn-ma-primary btn-sm">
                            <i class="bi bi-check-lg me-1" aria-hidden="true"></i>Create User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Block User Modals --}}
    @foreach ($users as $u)
        @if ($u->canBeBlockedBy($actor) && ! $u->isBlocked())
            <div class="modal fade" id="blockUserModal{{ $u->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('admin.users.block', $u) }}">
                            @csrf
                            <div class="modal-header">
                                <h3 class="modal-title h6 fw-bold text-danger">
                                    <i class="bi bi-slash-circle me-1" aria-hidden="true"></i>Block {{ $u->name }}
                                </h3>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p class="small text-muted mb-3">
                                    Blocking <strong>{{ $u->email }}</strong> will immediately log them out of any active session and prevent login due to violating Mash Academy's Platform Policy.
                                </p>
                                <label class="form-label small fw-semibold">Reason (Optional internal log)</label>
                                <textarea name="blocked_reason" rows="3" class="form-control" placeholder="e.g. Shared account credentials / violated platform policy."></textarea>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">{{ __('messages.cancel') }}</button>
                                <button type="submit" class="btn btn-danger btn-sm">Confirm Block</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    @endforeach
@endsection
