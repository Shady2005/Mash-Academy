@extends('layouts.admin')

@section('title', 'Staff Management')
@section('page-title', 'Staff Management')

@section('content')
    <div class="ma-card p-4 mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <h2 class="h5 fw-bold mb-1">Mash Academy Staff Directory (Fixed 4-Account Architecture)</h2>
                <p class="text-muted small mb-0">
                    Exactly 4 staff accounts exist in the system. Neither Super Admin can delete the other or themselves.
                    Super Admins may reset passwords or delete the Teacher and Customer Support accounts.
                </p>
            </div>
            <span class="badge bg-light text-dark border px-3 py-2">
                <i class="bi bi-lock-fill me-1 text-primary" aria-hidden="true"></i>
                No additional staff accounts allowed
            </span>
        </div>
    </div>

    <div class="ma-card overflow-hidden">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Staff Member</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($staffMembers as $member)
                        <tr class="{{ $member->trashed() ? 'table-light text-muted' : '' }}">
                            <td>
                                <div class="fw-semibold">{{ $member->name }}</div>
                                <div class="small text-muted">{{ $member->email }}</div>
                                @if ($member->phone)
                                    <div class="small text-muted">{{ $member->phone }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $member->role->badgeClass() }}">{{ $member->role->label() }}</span>
                            </td>
                            <td>
                                @if ($member->trashed())
                                    <span class="badge bg-danger">Deleted</span>
                                @elseif (! $member->is_active)
                                    <span class="badge bg-warning text-dark">Inactive</span>
                                @else
                                    <span class="badge bg-success">Active</span>
                                @endif
                            </td>
                            <td class="small text-muted">
                                {{ $member->last_login_at ? $member->last_login_at->format('M d, Y H:i') : 'Never' }}
                            </td>
                            <td class="text-end">
                                @if ($member->isSuperAdmin())
                                    <span class="badge bg-light text-secondary border">
                                        <i class="bi bi-shield-lock me-1" aria-hidden="true"></i>Protected Account
                                    </span>
                                @elseif ($member->trashed())
                                    <span class="small text-muted">Removed {{ $member->deleted_at->format('M d, Y') }}</span>
                                @else
                                    <div class="d-inline-flex gap-2">
                                        @can('resetPassword', $member)
                                            <button
                                                type="button"
                                                class="btn btn-outline-primary btn-sm"
                                                data-bs-toggle="modal"
                                                data-bs-target="#resetPasswordModal{{ $member->id }}"
                                            >
                                                <i class="bi bi-key me-1" aria-hidden="true"></i>Reset Password
                                            </button>
                                        @endcan

                                        @can('delete', $member)
                                            <button
                                                type="button"
                                                class="btn btn-outline-danger btn-sm"
                                                data-bs-toggle="modal"
                                                data-bs-target="#deleteStaffModal{{ $member->id }}"
                                            >
                                                <i class="bi bi-trash me-1" aria-hidden="true"></i>Delete
                                            </button>
                                        @endcan
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modals for Teacher / Support accounts --}}
    @foreach ($staffMembers as $member)
        @if (! $member->isSuperAdmin() && ! $member->trashed())
            {{-- Reset Password Modal --}}
            <div class="modal fade" id="resetPasswordModal{{ $member->id }}" tabindex="-1" aria-labelledby="resetPasswordLabel{{ $member->id }}" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('admin.staff.reset-password', $member->id) }}">
                            @csrf
                            @method('PUT')
                            <div class="modal-header">
                                <h3 class="modal-title h6 fw-bold" id="resetPasswordLabel{{ $member->id }}">
                                    Reset Password — {{ $member->name }}
                                </h3>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p class="small text-muted">
                                    Set a new temporary password for <strong>{{ $member->email }}</strong>. They will be prompted to update it upon signing in.
                                </p>
                                <div class="mb-3">
                                    <label for="new_password_{{ $member->id }}" class="form-label small fw-semibold">New Password</label>
                                    <input type="password" id="new_password_{{ $member->id }}" name="password" class="form-control" required minlength="8" autocomplete="new-password">
                                    <div class="form-text">Min 8 characters with uppercase, lowercase, and a number.</div>
                                </div>
                                <div class="mb-2">
                                    <label for="new_password_confirmation_{{ $member->id }}" class="form-label small fw-semibold">Confirm New Password</label>
                                    <input type="password" id="new_password_confirmation_{{ $member->id }}" name="password_confirmation" class="form-control" required minlength="8" autocomplete="new-password">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-ma-primary btn-sm">Confirm Reset</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Delete Staff Modal --}}
            <div class="modal fade" id="deleteStaffModal{{ $member->id }}" tabindex="-1" aria-labelledby="deleteStaffLabel{{ $member->id }}" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('admin.staff.destroy', $member->id) }}">
                            @csrf
                            @method('DELETE')
                            <div class="modal-header">
                                <h3 class="modal-title h6 fw-bold text-danger" id="deleteStaffLabel{{ $member->id }}">
                                    Delete Staff Account
                                </h3>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p class="mb-2">
                                    Are you sure you want to delete <strong>{{ $member->name }}</strong> ({{ $member->role->label() }})?
                                </p>
                                <p class="small text-muted mb-0">
                                    This immediately deactivates the account, revokes active sessions, and logs the deletion in the audit trail.
                                </p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-danger btn-sm">Yes, Delete Account</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    @endforeach
@endsection
