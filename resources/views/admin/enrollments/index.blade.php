@extends('layouts.admin')

@section('title', __('messages.enrollments'))
@section('page-title', __('messages.enrollments'))

@section('content')
    <div class="row g-4">
        {{-- Create Enrollment Form --}}
        <div class="col-lg-4">
            <div class="ma-card p-4">
                <h2 class="h6 fw-bold mb-1">Grant Time-Limited Enrollment</h2>
                <p class="small text-muted mb-3">
                    Select a student, choose a course, and assign an access duration. Course access unlocks immediately and auto-locks on expiry.
                </p>

                <form method="POST" action="{{ route('admin.enrollments.store') }}" id="create-enrollment-form">
                    @csrf
                    @if ($prefillPaymentId)
                        <input type="hidden" name="payment_request_id" value="{{ $prefillPaymentId }}">
                        <div class="alert alert-info small py-2">
                            Linking to approved Payment Request <strong>#{{ $prefillPaymentId }}</strong>.
                        </div>
                    @endif

                    <div class="mb-3">
                        <label for="enroll_user_id" class="form-label small fw-semibold">Student <span class="text-danger">*</span></label>
                        <select id="enroll_user_id" name="user_id" class="form-select @error('user_id') is-invalid @enderror" required>
                            <option value="">Choose a student...</option>
                            @foreach ($students as $st)
                                <option value="{{ $st->id }}" {{ (string) old('user_id', $prefillUserId) === (string) $st->id ? 'selected' : '' }}>
                                    {{ $st->name }} ({{ $st->email }})
                                </option>
                            @endforeach
                        </select>
                        @error('user_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="enroll_course_id" class="form-label small fw-semibold">Single Course</label>
                        <select id="enroll_course_id" name="course_id" class="form-select @error('course_id') is-invalid @enderror">
                            <option value="">Choose a single course (or select a package below)...</option>
                            @foreach ($courses as $c)
                                <option value="{{ $c->id }}" {{ (string) old('course_id', $prefillCourseId) === (string) $c->id ? 'selected' : '' }}>
                                    {{ $c->title }}
                                </option>
                            @endforeach
                        </select>
                        @error('course_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    @if (isset($packages) && $packages->isNotEmpty())
                        <div class="mb-3">
                            <label for="enroll_package_id" class="form-label small fw-semibold">Or Course Package (Unlocks All Included Courses)</label>
                            <select id="enroll_package_id" name="course_package_id" class="form-select @error('course_package_id') is-invalid @enderror">
                                <option value="">None (Enroll in single course above)</option>
                                @foreach ($packages as $pkg)
                                    <option value="{{ $pkg->id }}" {{ (string) old('course_package_id', $prefillPackageId ?? '') === (string) $pkg->id ? 'selected' : '' }}>
                                        {{ $pkg->title }} ({{ $pkg->courses_count }} courses)
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">Selecting a package automatically unlocks every course in that package with the same expiry date.</div>
                            @error('course_package_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    @endif

                    {{-- Quick Duration Presets --}}
                    <div class="mb-2">
                        <span class="form-label small fw-semibold d-block mb-1">Quick Duration Presets</span>
                        <div class="d-flex flex-wrap gap-1">
                            <button type="button" class="btn btn-ma-pref btn-sm" data-duration-preset="1" data-duration-unit="months">1 Month</button>
                            <button type="button" class="btn btn-ma-pref btn-sm" data-duration-preset="3" data-duration-unit="months">3 Months</button>
                            <button type="button" class="btn btn-ma-pref btn-sm" data-duration-preset="6" data-duration-unit="months">6 Months</button>
                            <button type="button" class="btn btn-ma-pref btn-sm" data-duration-preset="1" data-duration-unit="days">1 Day (Test)</button>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="duration_value" class="form-label small fw-semibold">Duration <span class="text-danger">*</span></label>
                            <input type="number" min="1" max="3650" id="duration_value" name="duration_value" value="{{ old('duration_value', 3) }}" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label for="duration_unit" class="form-label small fw-semibold">Unit <span class="text-danger">*</span></label>
                            <select id="duration_unit" name="duration_unit" class="form-select" required>
                                <option value="months" {{ old('duration_unit', 'months') === 'months' ? 'selected' : '' }}>Months</option>
                                <option value="days" {{ old('duration_unit') === 'days' ? 'selected' : '' }}>Days</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-ma-primary w-100">
                        <i class="bi bi-unlock-fill me-1" aria-hidden="true"></i>Activate Enrollment
                    </button>
                </form>
            </div>
        </div>

        {{-- Enrollments Directory & History --}}
        <div class="col-lg-8">
            <div class="ma-card p-3 mb-3">
                <form method="GET" action="{{ route('admin.enrollments.index') }}" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label for="q" class="form-label small fw-semibold">Search Student</label>
                        <input type="text" id="q" name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="Name or email...">
                    </div>
                    <div class="col-md-3">
                        <label for="filter_status" class="form-label small fw-semibold">Status</label>
                        <select id="filter_status" name="status" class="form-select form-select-sm">
                            <option value="">All</option>
                            <option value="active" {{ $statusFilter === 'active' ? 'selected' : '' }}>Active</option>
                            <option value="expired" {{ $statusFilter === 'expired' ? 'selected' : '' }}>Expired</option>
                            <option value="revoked" {{ $statusFilter === 'revoked' ? 'selected' : '' }}>Revoked</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="filter_course" class="form-label small fw-semibold">Course</label>
                        <select id="filter_course" name="course_id" class="form-select form-select-sm">
                            <option value="">All Courses</option>
                            @foreach ($courses as $c)
                                <option value="{{ $c->id }}" {{ (string) $courseFilter === (string) $c->id ? 'selected' : '' }}>{{ $c->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-ma-primary btn-sm">Filter</button>
                    </div>
                </form>
            </div>

            <div class="ma-card overflow-hidden">
                @if ($enrollments->isEmpty())
                    <div class="p-5 text-center text-muted small">
                        <i class="bi bi-calendar2-x fs-1 d-block mb-2" aria-hidden="true"></i>
                        No enrollments found.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 small">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Course</th>
                                    <th>Window</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($enrollments as $enrollment)
                                    @php
                                        $effStatus = $enrollment->effectiveStatus();
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $enrollment->user?->name ?? 'Deleted User' }}</div>
                                            <div class="text-muted">{{ $enrollment->user?->email }}</div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold">{{ $enrollment->course?->title ?? 'Deleted Course' }}</div>
                                            <div class="text-muted">{{ $enrollment->duration_value }} {{ $enrollment->duration_unit }}</div>
                                        </td>
                                        <td class="text-nowrap">
                                            <div>Starts: {{ $enrollment->starts_at->format('M d, Y') }}</div>
                                            <div class="fw-semibold">Expires: {{ $enrollment->expires_at->format('M d, Y H:i') }}</div>
                                        </td>
                                        <td>
                                            @if ($effStatus === 'active')
                                                <span class="badge bg-navy-accent">Active</span>
                                                <div class="text-muted mt-1" style="font-size: 0.72rem;">{{ $enrollment->remainingDays() }}d left</div>
                                            @elseif ($effStatus === 'expired')
                                                <span class="badge bg-secondary">Expired</span>
                                            @else
                                                <span class="badge bg-danger">Revoked</span>
                                            @endif
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <div class="d-inline-flex gap-1">
                                                <button type="button" class="btn btn-ma-outline btn-sm" data-bs-toggle="modal" data-bs-target="#extendModal{{ $enrollment->id }}" title="Extend access">
                                                    <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Extend
                                                </button>
                                                @if ($effStatus === 'active')
                                                    <form method="POST" action="{{ route('admin.enrollments.revoke', $enrollment) }}" onsubmit="return confirm('Revoke this enrollment immediately? The course will re-lock at once.');">
                                                        @csrf
                                                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Revoke access">
                                                            <i class="bi bi-slash-circle" aria-hidden="true"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($enrollments->hasPages())
                        <div class="p-3 border-top">{{ $enrollments->links() }}</div>
                    @endif
                @endif
            </div>
        </div>
    </div>

    {{-- Extend Enrollment Modals --}}
    @foreach ($enrollments as $enrollment)
        <div class="modal fade" id="extendModal{{ $enrollment->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('admin.enrollments.extend', $enrollment) }}">
                        @csrf
                        <div class="modal-header">
                            <h3 class="modal-title h6 fw-bold">Extend Enrollment #{{ $enrollment->id }}</h3>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="small text-muted">
                                Extending access for <strong>{{ $enrollment->user?->name }}</strong> in <strong>{{ $enrollment->course?->title }}</strong>.
                                Current expiry: <strong>{{ $enrollment->expires_at->format('M d, Y H:i') }}</strong>.
                            </p>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label small fw-semibold">Add Duration</label>
                                    <input type="number" min="1" max="3650" name="duration_value" value="1" class="form-control" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-semibold">Unit</label>
                                    <select name="duration_unit" class="form-select" required>
                                        <option value="months">Months</option>
                                        <option value="days">Days</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-ma-primary btn-sm">Confirm Extension</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endsection
