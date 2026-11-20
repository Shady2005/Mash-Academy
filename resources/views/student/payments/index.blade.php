@extends('layouts.student')

@section('title', __('messages.my_payments'))

@section('content')
    <div class="ma-card p-4 mb-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <span class="badge bg-navy-accent mb-2">{{ __('messages.student_portal') }}</span>
            <h1 class="h4 fw-bold mb-1">{{ __('messages.my_payments') }}</h1>
            <p class="text-muted small mb-0">Track the review status of your InstaPay payment confirmations and active enrollments.</p>
        </div>
        <a href="{{ route('student.courses.index') }}" class="btn btn-ma-primary btn-sm">
            <i class="bi bi-collection-play me-1" aria-hidden="true"></i>{{ __('messages.browse_courses') }}
        </a>
    </div>

    <div class="ma-card overflow-hidden">
        @if ($payments->isEmpty())
            <div class="p-5 text-center text-muted small">
                <i class="bi bi-receipt fs-1 d-block mb-2" aria-hidden="true"></i>
                You have not submitted any payment requests yet.
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0 small">
                    <thead>
                        <tr>
                            <th># / Date</th>
                            <th>Course</th>
                            <th>Amount &amp; Promo</th>
                            <th>Status</th>
                            <th>Admin Note</th>
                            <th class="text-end">Proof / Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments as $payment)
                            <tr>
                                <td class="text-nowrap">
                                    <div class="fw-bold">#{{ $payment->id }}</div>
                                    <div class="text-muted">{{ $payment->created_at->format('M d, Y H:i') }}</div>
                                </td>
                                <td class="fw-semibold">{{ $payment->course?->title ?? 'Course' }}</td>
                                <td class="text-nowrap">
                                    <div class="fw-bold text-navy">{{ number_format((float) $payment->final_price, 2) }} {{ $payment->currency }}</div>
                                    @if ($payment->promo_code_snapshot)
                                        <div class="text-muted">
                                            <span class="text-decoration-line-through">{{ number_format((float) $payment->original_price, 0) }}</span>
                                            <span class="badge bg-black-badge ms-1">{{ $payment->promo_code_snapshot }}</span>
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @if ($payment->isPending())
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    @elseif ($payment->isApproved())
                                        <span class="badge bg-navy-accent">Approved</span>
                                    @else
                                        <span class="badge bg-danger">Rejected</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($payment->admin_note)
                                        <div class="{{ $payment->isRejected() ? 'text-danger fw-semibold' : 'text-muted' }}">{{ $payment->admin_note }}</div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    <button type="button" class="btn btn-ma-outline btn-sm" data-bs-toggle="modal" data-bs-target="#myProofModal{{ $payment->id }}">
                                        <i class="bi bi-image me-1" aria-hidden="true"></i>Screenshot
                                    </button>
                                    @if ($payment->isRejected() && $payment->course)
                                        <a href="{{ route('student.courses.subscribe', $payment->course) }}" class="btn btn-ma-primary btn-sm ms-1">
                                            Resubmit
                                        </a>
                                    @elseif ($payment->enrollment && $payment->enrollment->isCurrentlyActive() && $payment->course)
                                        <a href="{{ route('student.courses.access', $payment->course) }}" class="btn btn-ma-primary btn-sm ms-1">
                                            {{ __('messages.open_course') }}
                                        </a>
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

    @foreach ($payments as $payment)
        <div class="modal fade" id="myProofModal{{ $payment->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title h6 fw-bold">Transfer Screenshot — Request #{{ $payment->id }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center bg-dark p-3">
                        <img src="{{ route('student.payments.screenshot', $payment) }}" alt="Uploaded transfer screenshot" class="img-fluid rounded" style="max-height: 70vh;">
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@endsection
