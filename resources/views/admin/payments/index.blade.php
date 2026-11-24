@extends('layouts.admin')

@section('title', __('messages.payments_inbox'))
@section('page-title', __('messages.payments_inbox'))

@section('content')
    {{-- Summary + QR shortcut --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-4 col-lg-3">
            <div class="ma-card p-3">
                <div class="small text-muted text-uppercase fw-semibold">Pending Review</div>
                <div class="h3 fw-bold mb-0 mt-1 text-navy">{{ $counts['pending'] }}</div>
            </div>
        </div>
        <div class="col-sm-4 col-lg-3">
            <div class="ma-card p-3">
                <div class="small text-muted text-uppercase fw-semibold">Approved</div>
                <div class="h3 fw-bold mb-0 mt-1">{{ $counts['approved'] }}</div>
            </div>
        </div>
        <div class="col-sm-4 col-lg-3">
            <div class="ma-card p-3">
                <div class="small text-muted text-uppercase fw-semibold">Rejected</div>
                <div class="h3 fw-bold mb-0 mt-1 text-danger">{{ $counts['rejected'] }}</div>
            </div>
        </div>
        <div class="col-lg-3">
            <div class="ma-card p-3 d-flex flex-column justify-content-between h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="small fw-semibold">InstaPay QR</span>
                    <span class="badge {{ $instapayQrUrl ? 'bg-navy-accent' : 'bg-danger' }}">
                        {{ $instapayQrUrl ? 'Configured' : 'Missing' }}
                    </span>
                </div>
                <a href="{{ route('admin.settings.edit') }}#instapay-qr" class="btn btn-ma-outline btn-sm mt-2">
                    <i class="bi bi-qr-code me-1" aria-hidden="true"></i>Manage InstaPay QR
                </a>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="ma-card p-3 mb-4">
        <form method="GET" action="{{ route('admin.payments.index') }}" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label for="status" class="form-label small fw-semibold">Status</label>
                <select id="status" name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="pending" {{ $statusFilter === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="approved" {{ $statusFilter === 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="rejected" {{ $statusFilter === 'rejected' ? 'selected' : '' }}>Rejected</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="course_id" class="form-label small fw-semibold">Course</label>
                <select id="course_id" name="course_id" class="form-select form-select-sm">
                    <option value="">All Courses</option>
                    @foreach ($courses as $c)
                        <option value="{{ $c->id }}" {{ (string) $courseFilter === (string) $c->id ? 'selected' : '' }}>{{ $c->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-ma-primary btn-sm px-3">Filter</button>
                @if ($statusFilter !== '' || $courseFilter)
                    <a href="{{ route('admin.payments.index') }}" class="btn btn-ma-outline btn-sm">Clear</a>
                @endif
            </div>
        </form>
    </div>

    {{-- Payments Table --}}
    <div class="ma-card overflow-hidden">
        @if ($payments->isEmpty())
            <div class="p-5 text-center text-muted small">
                <i class="bi bi-inbox fs-1 d-block mb-2" aria-hidden="true"></i>
                No payment requests match your filter.
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0 small">
                    <thead>
                        <tr>
                            <th># / Date</th>
                            <th>Student</th>
                            <th>Course</th>
                            <th>Pricing &amp; Promo</th>
                            <th>Proof</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments as $payment)
                            <tr>
                                <td class="text-nowrap">
                                    <div class="fw-bold">#{{ $payment->id }}</div>
                                    <div class="text-muted">{{ $payment->created_at->format('M d, Y H:i') }}</div>
                                </td>
                                <td>
                                    @php $waPayUrl = \App\Support\WhatsAppHelper::forPayment($payment, $currentLocale ?? app()->getLocale()); @endphp
                                    <div class="fw-semibold">{{ $payment->name }}</div>
                                    <div class="text-muted">{{ $payment->email }}</div>
                                    <div class="d-flex align-items-center gap-2 flex-wrap mt-1">
                                        <span class="text-muted"><i class="bi bi-telephone me-1" aria-hidden="true"></i>{{ $payment->phone }}</span>
                                        @if ($waPayUrl)
                                            <a href="{{ $waPayUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-success btn-sm py-0 px-2" title="Send WhatsApp message">
                                                <i class="bi bi-whatsapp me-1" aria-hidden="true"></i>{{ __('messages.whatsapp_message') }}
                                            </a>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    @if ($payment->isConsultationPayment())
                                        <span class="badge bg-navy mb-1"><i class="bi bi-calendar-heart me-1" aria-hidden="true"></i>Consultation</span>
                                        <div class="fw-semibold">{{ $payment->consultation?->topic ?? '1-on-1 Consultation' }}</div>
                                    @elseif ($payment->isPackagePayment())
                                        <span class="badge bg-black-badge mb-1"><i class="bi bi-box-seam me-1" aria-hidden="true"></i>Package</span>
                                        <div class="fw-semibold">{{ $payment->coursePackage?->title ?? 'Deleted Package' }}</div>
                                        @if ($payment->coursePackage)
                                            <div class="text-muted" style="font-size: 0.75rem;">{{ $payment->coursePackage->courses->count() }} courses included</div>
                                        @endif
                                    @else
                                        <div class="fw-semibold">{{ $payment->course?->title ?? 'Deleted Course' }}</div>
                                    @endif
                                </td>
                                <td class="text-nowrap">
                                    <div class="fw-bold text-navy">{{ number_format((float) $payment->final_price, 2) }} {{ $payment->currency }}</div>
                                    @if ($payment->promo_code_snapshot)
                                        <div class="text-muted">
                                            <span class="text-decoration-line-through">{{ number_format((float) $payment->original_price, 0) }}</span>
                                            <span class="badge bg-black-badge ms-1">{{ $payment->promo_code_snapshot }} (-{{ number_format((float) $payment->discount_amount, 0) }})</span>
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <button type="button" class="btn btn-ma-outline btn-sm" data-bs-toggle="modal" data-bs-target="#proofModal{{ $payment->id }}">
                                        <i class="bi bi-image me-1" aria-hidden="true"></i>View
                                    </button>
                                </td>
                                <td>
                                    @if ($payment->isPending())
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    @elseif ($payment->isApproved())
                                        <span class="badge bg-navy-accent">Approved</span>
                                        @if ($payment->enrollments->isNotEmpty())
                                            <div class="text-muted mt-1" style="font-size: 0.72rem;">
                                                Enrolled ({{ $payment->enrollments->count() }} {{ $payment->enrollments->count() === 1 ? 'course' : 'courses' }})
                                            </div>
                                        @endif
                                    @else
                                        <span class="badge bg-danger">Rejected</span>
                                    @endif
                                    @if ($payment->admin_note)
                                        <div class="text-muted mt-1" style="max-width: 180px;">{{ $payment->admin_note }}</div>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    @if ($payment->isPending())
                                        <div class="d-inline-flex gap-1">
                                            <button type="button" class="btn btn-ma-primary btn-sm" data-bs-toggle="modal" data-bs-target="#approveModal{{ $payment->id }}">
                                                <i class="bi bi-check-lg me-1" aria-hidden="true"></i>Approve
                                            </button>
                                            <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $payment->id }}">
                                                <i class="bi bi-x-lg me-1" aria-hidden="true"></i>Reject
                                            </button>
                                        </div>
                                    @elseif ($payment->isApproved() && $payment->enrollments->isEmpty())
                                        <a
                                            href="{{ route('admin.enrollments.index', array_filter(['user_id' => $payment->user_id, 'course_id_prefill' => $payment->course_id, 'package_id_prefill' => $payment->course_package_id, 'payment_request_id' => $payment->id])) }}"
                                            class="btn btn-ma-primary btn-sm"
                                        >
                                            <i class="bi bi-calendar-plus me-1" aria-hidden="true"></i>Enroll Now
                                        </a>
                                    @else
                                        <span class="text-muted">Reviewed by {{ $payment->reviewer?->name ?? 'Admin' }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($payments->hasPages())
                <div class="p-3 border-top">{{ $payments->links() }}</div>
            @endif
        @endif
    </div>

    {{-- Modals: Screenshot Viewer, Approve (+ Optional Immediate Enroll), Reject --}}
    @foreach ($payments as $payment)
        {{-- Screenshot Modal --}}
        <div class="modal fade" id="proofModal{{ $payment->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title h6 fw-bold">Transfer Screenshot — Payment #{{ $payment->id }} ({{ $payment->name }})</h3>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center bg-dark p-3">
                        <img src="{{ route('admin.payments.screenshot', $payment) }}" alt="Transfer screenshot" class="img-fluid rounded" style="max-height: 70vh;">
                    </div>
                    <div class="modal-footer justify-content-between small">
                        <span>Expected Transfer: <strong>{{ number_format((float) $payment->final_price, 2) }} {{ $payment->currency }}</strong></span>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        @if ($payment->isPending())
            {{-- Approve Modal --}}
            <div class="modal fade" id="approveModal{{ $payment->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('admin.payments.approve', $payment) }}">
                            @csrf
                            <div class="modal-header">
                                <h3 class="modal-title h6 fw-bold">Approve Payment #{{ $payment->id }}</h3>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p class="small mb-3">
                                    Student: <strong>{{ $payment->name }}</strong> ({{ $payment->email }})<br>
                                    @if ($payment->isPackagePayment())
                                        Package: <strong>{{ $payment->coursePackage?->title }}</strong> ({{ $payment->coursePackage?->courses->count() ?? 0 }} courses will unlock together)<br>
                                    @else
                                        Course: <strong>{{ $payment->course?->title }}</strong><br>
                                    @endif
                                    Final Amount: <strong>{{ number_format((float) $payment->final_price, 2) }} {{ $payment->currency }}</strong>
                                </p>

                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="enroll_now_{{ $payment->id }}" name="enroll_now" value="1" checked>
                                    <label class="form-check-label small fw-semibold" for="enroll_now_{{ $payment->id }}">
                                        Activate student enrollment immediately upon approval
                                    </label>
                                </div>

                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Duration</label>
                                        <input type="number" min="1" max="3650" name="duration_value" value="3" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Unit</label>
                                        <select name="duration_unit" class="form-select form-select-sm">
                                            <option value="months" selected>Months</option>
                                            <option value="days">Days</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label small fw-semibold">Admin Note (Optional)</label>
                                    <textarea name="admin_note" rows="2" class="form-control form-control-sm" placeholder="e.g. Verified InstaPay transfer"></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-ma-primary btn-sm">Approve Payment</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Reject Modal --}}
            <div class="modal fade" id="rejectModal{{ $payment->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('admin.payments.reject', $payment) }}">
                            @csrf
                            <div class="modal-header">
                                <h3 class="modal-title h6 fw-bold text-danger">Reject Payment #{{ $payment->id }}</h3>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p class="small text-muted">
                                    The student will see your note on their "My Payments" page. Any reserved promo code usage will be released.
                                </p>
                                <label class="form-label small fw-semibold">Rejection Reason <span class="text-danger">*</span></label>
                                <textarea name="admin_note" rows="3" class="form-control" required minlength="3" placeholder="e.g. Unclear transfer screenshot or incorrect transfer amount."></textarea>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-danger btn-sm">Confirm Rejection</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    @endforeach
@endsection
