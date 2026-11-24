@extends('layouts.admin')

@section('title', __('messages.student_directory'))
@section('page-title', __('messages.student_directory'))

@section('content')
    <div class="ma-card p-4 mb-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h2 class="h5 fw-bold mb-1">Student Accounts Directory</h2>
            <p class="small text-muted mb-0">
                Search students by name, email, or phone to inspect contact details, enrollment statuses, progress, and certificates.
            </p>
        </div>
        @if (auth()->user()->isSupport())
            <span class="badge bg-black-badge px-3 py-2">
                <i class="bi bi-eye me-1" aria-hidden="true"></i>{{ __('messages.read_only_badge') }}
            </span>
        @endif
    </div>

    <div class="ma-card p-3 mb-3">
        <form method="GET" action="{{ route('admin.students.index') }}" class="row g-2 align-items-center">
            <div class="col-md-5">
                <input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="Search by student name, email, phone, or ID...">
            </div>
            <div class="col-md-4">
                <select name="filter" class="form-select form-select-sm" aria-label="Filter by enrollment status">
                    <option value="" {{ ($filter ?? '') === '' ? 'selected' : '' }}>All Registered Students</option>
                    <option value="active" {{ ($filter ?? '') === 'active' ? 'selected' : '' }}>Active Enrollments</option>
                    <option value="expired" {{ ($filter ?? '') === 'expired' ? 'selected' : '' }}>Expired / Revoked Enrollments</option>
                    <option value="pending_payment" {{ ($filter ?? '') === 'pending_payment' ? 'selected' : '' }}>Pending Payment Requests</option>
                    <option value="none" {{ ($filter ?? '') === 'none' ? 'selected' : '' }}>No Enrollments Yet</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-ma-primary btn-sm px-3">Filter</button>
                @if ($search !== '' || ($filter ?? '') !== '')
                    <a href="{{ route('admin.students.index') }}" class="btn btn-ma-outline btn-sm">Clear</a>
                @endif
            </div>
        </form>
    </div>

    <div class="ma-card overflow-hidden">
        @if ($students->isEmpty())
            <div class="p-5 text-center text-muted small">No students match your search or filter.</div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0 small">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Phone</th>
                            <th>Enrollment &amp; Payment Status</th>
                            <th>Certificates</th>
                            <th>Joined / Last Login</th>
                            <th class="text-end">View</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($students as $student)
                            @php
                                $activeCount = $student->enrollments->filter(fn ($e) => $e->isCurrentlyActive())->count();
                                $expiredCount = $student->enrollments->filter(fn ($e) => ! $e->isCurrentlyActive())->count();
                                $pendingPayments = $student->paymentRequests->where('status', \App\Models\PaymentRequest::STATUS_PENDING)->count();
                            @endphp
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <span class="fw-semibold">{{ $student->name }}</span>
                                        @if ($student->trashed())
                                            <span class="badge bg-danger">Archived</span>
                                        @elseif (! $student->is_active)
                                            <span class="badge bg-secondary">Inactive</span>
                                        @endif
                                    </div>
                                    <div class="text-muted">{{ $student->email }}</div>
                                </td>
                                <td>
                                    @php $waStudentUrl = \App\Support\WhatsAppHelper::forStudent($student, $currentLocale ?? app()->getLocale()); @endphp
                                    <div>{{ $student->phone ?: '—' }}</div>
                                    @if ($waStudentUrl)
                                        <a href="{{ $waStudentUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-success btn-sm py-0 px-2 mt-1">
                                            <i class="bi bi-whatsapp me-1" aria-hidden="true"></i>{{ __('messages.whatsapp_message') }}
                                        </a>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1 mb-1">
                                        @if ($activeCount > 0)
                                            <span class="badge bg-navy-accent">{{ $activeCount }} Active</span>
                                        @endif
                                        @if ($expiredCount > 0)
                                            <span class="badge bg-secondary">{{ $expiredCount }} Expired/Revoked</span>
                                        @endif
                                        @if ($pendingPayments > 0)
                                            <span class="badge bg-warning text-dark">{{ $pendingPayments }} Pending Payment</span>
                                        @endif
                                        @if ($activeCount === 0 && $expiredCount === 0 && $pendingPayments === 0)
                                            <span class="badge bg-light text-dark border">Not Enrolled</span>
                                        @endif
                                    </div>
                                    @if ($student->enrollments->isNotEmpty())
                                        <div class="text-muted" style="font-size: 0.78rem;">
                                            {{ $student->enrollments->take(2)->map(fn ($e) => $e->course?->title ?? 'Course')->implode(', ') }}
                                            @if ($student->enrollments->count() > 2)
                                                +{{ $student->enrollments->count() - 2 }} more
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td><span class="badge bg-success">{{ $student->certificates_count }}</span></td>
                                <td class="text-muted text-nowrap">
                                    <div>Joined: {{ $student->created_at->format('M d, Y') }}</div>
                                    <div>Login: {{ $student->last_login_at ? $student->last_login_at->format('M d, Y H:i') : 'Never' }}</div>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('admin.students.show', $student->id) }}" class="btn btn-ma-outline btn-sm">
                                        <i class="bi bi-eye me-1" aria-hidden="true"></i>Inspect Profile
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($students->hasPages())
                <div class="p-3 border-top">{{ $students->links() }}</div>
            @endif
        @endif
    </div>
@endsection
