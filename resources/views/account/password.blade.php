@extends($layout)

@section('title', 'Change Password')
@section('page-title', 'Change Password')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="ma-card p-4">
                <div class="mb-3 pb-3 border-bottom">
                    <h2 class="h5 fw-bold mb-1">Update Account Password</h2>
                    <p class="text-muted small mb-0">
                        Signed in as <strong>{{ $user->email }}</strong> ({{ $user->role->label() }}).
                    </p>
                </div>

                <form method="POST" action="{{ route('account.password.update') }}" class="ma-form">
                    @csrf
                    @method('PUT')

                    <div class="mb-3 ma-field">
                        <label for="current_password" class="form-label fw-semibold">Current Password</label>
                        <div class="input-group">
                            <input
                                type="password"
                                id="current_password"
                                name="current_password"
                                class="form-control @error('current_password') is-invalid @enderror"
                                required
                                autocomplete="current-password"
                            >
                            <button type="button" class="btn btn-outline-secondary" data-toggle-password="current_password" aria-label="Show password" aria-pressed="false">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                        @error('current_password')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3 ma-field">
                        <label for="password" class="form-label fw-semibold">New Password</label>
                        <span class="ma-field-hint">Min 8 characters with uppercase, lowercase, and a number.</span>
                        <div class="input-group">
                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="form-control @error('password') is-invalid @enderror"
                                required
                                minlength="8"
                                autocomplete="new-password"
                            >
                            <button type="button" class="btn btn-outline-secondary" data-toggle-password="password" aria-label="Show password" aria-pressed="false">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                        @error('password')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4 ma-field">
                        <label for="password_confirmation" class="form-label fw-semibold">Confirm New Password</label>
                        <div class="input-group">
                            <input
                                type="password"
                                id="password_confirmation"
                                name="password_confirmation"
                                class="form-control"
                                required
                                minlength="8"
                                autocomplete="new-password"
                            >
                            <button type="button" class="btn btn-outline-secondary" data-toggle-password="password_confirmation" aria-label="Show password" aria-pressed="false">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-ma-primary px-4">
                        <i class="bi bi-check-lg me-1" aria-hidden="true"></i>Save New Password
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection
