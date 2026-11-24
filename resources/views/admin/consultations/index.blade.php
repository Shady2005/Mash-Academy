@extends('layouts.admin')

@section('title', __('messages.consultations'))
@section('page-title', __('messages.consultations'))

@section('content')
    <div class="row g-4">
        {{-- Consultation Settings (Super Admins 1 & 2) --}}
        @if ($canEditSettings)
            <div class="col-lg-4">
                <div class="ma-card p-4">
                    <h2 class="h6 fw-bold mb-1">
                        <i class="bi bi-sliders me-1 text-navy" aria-hidden="true"></i>1-on-1 Consultation Settings
                    </h2>
                    <p class="small text-muted mb-3">
                        Enable or disable public consultation booking, set session length, and configure Free or Paid (InstaPay) pricing.
                    </p>

                    <form method="POST" action="{{ route('admin.consultations.settings.update') }}" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="form-check form-switch mb-3">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                id="enable_consultations"
                                name="enable_consultations"
                                value="1"
                                {{ ($settings['enable_consultations'] ?? '1') === '1' ? 'checked' : '' }}
                            >
                            <label class="form-check-label small fw-semibold" for="enable_consultations">
                                Enable Public "Book a Consultation" Page
                            </label>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Session Duration</label>
                            <select name="consultation_duration_minutes" class="form-select form-select-sm">
                                @foreach ([15, 30, 45, 60, 90] as $mins)
                                    <option value="{{ $mins }}" {{ (int) ($settings['consultation_duration_minutes'] ?? 30) === $mins ? 'selected' : '' }}>
                                        {{ $mins }} Minutes
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Pricing Mode</label>
                            <select name="consultation_pricing_mode" class="form-select form-select-sm">
                                <option value="free" {{ ($settings['consultation_pricing_mode'] ?? 'free') === 'free' ? 'selected' : '' }}>Free Consultation</option>
                                <option value="paid" {{ ($settings['consultation_pricing_mode'] ?? 'free') === 'paid' ? 'selected' : '' }}>Paid Consultation (InstaPay)</option>
                            </select>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-7">
                                <label class="form-label small fw-semibold">Price (if Paid)</label>
                                <input type="number" step="0.01" min="0" name="consultation_price" value="{{ $settings['consultation_price'] ?? 0 }}" class="form-control form-control-sm">
                            </div>
                            <div class="col-5">
                                <label class="form-label small fw-semibold">Currency</label>
                                <input type="text" name="consultation_currency" value="{{ $settings['consultation_currency'] ?? 'EGP' }}" class="form-control form-control-sm" maxlength="10">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Available Days / Time Notes</label>
                            <textarea name="consultation_availability_note" rows="2" class="form-control form-control-sm">{{ $settings['consultation_availability_note'] ?? '' }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Consultation InstaPay QR (Optional)</label>
                            <input type="file" name="consultation_instapay_qr" accept=".png,.jpg,.jpeg,.webp" class="form-control form-control-sm">
                            <div class="form-text">Falls back to Academy Global QR if blank.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Consultation InstaPay Note</label>
                            <input type="text" name="consultation_instapay_note" value="{{ $settings['consultation_instapay_note'] ?? '' }}" class="form-control form-control-sm" maxlength="255">
                        </div>

                        @if ($consultationQrUrl)
                            <div class="border rounded p-2 text-center bg-white mb-3">
                                <img src="{{ $consultationQrUrl }}" alt="Consultation InstaPay QR" class="img-fluid" style="max-height: 150px;">
                            </div>
                        @endif

                        <button type="submit" class="btn btn-ma-primary btn-sm w-100">
                            <i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ __('messages.save_changes') }}
                        </button>
                    </form>

                    @if ($hasDedicatedConsultationQr)
                        <form method="POST" action="{{ route('admin.consultations.instapay-qr.destroy') }}" class="mt-2" onsubmit="return confirm('Remove dedicated consultation InstaPay QR?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger btn-sm w-100">Remove Consultation QR</button>
                        </form>
                    @endif
                </div>
            </div>
        @endif

        {{-- Bookings Management Table --}}
        <div class="{{ $canEditSettings ? 'col-lg-8' : 'col-12' }}">
            <div class="ma-card p-3 mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <form method="GET" action="{{ route('admin.consultations.index') }}" class="d-flex gap-2 align-items-center">
                    <select name="status" class="form-select form-select-sm" style="width: 180px;">
                        <option value="">All Statuses</option>
                        <option value="pending" {{ $statusFilter === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="confirmed" {{ $statusFilter === 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                        <option value="completed" {{ $statusFilter === 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="cancelled" {{ $statusFilter === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                    <button type="submit" class="btn btn-ma-primary btn-sm">Filter</button>
                </form>
                <a href="{{ route('consultations.index') }}" target="_blank" rel="noopener noreferrer" class="btn btn-ma-outline btn-sm">
                    <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>Open Booking Page
                </a>
            </div>

            <div class="ma-card overflow-hidden">
                @if ($consultations->isEmpty())
                    <div class="p-5 text-center text-muted small">
                        <i class="bi bi-calendar-event fs-1 d-block mb-2" aria-hidden="true"></i>
                        No consultation requests found.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 small">
                            <thead>
                                <tr>
                                    <th>Student &amp; Slot</th>
                                    <th>Topic &amp; Type</th>
                                    <th>Status</th>
                                    <th class="text-end">{{ __('messages.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($consultations as $booking)
                                    @php
                                        $waUrl = \App\Support\WhatsAppHelper::buildUrl(
                                            $booking->user?->phone,
                                            "Hi {$booking->user?->name}, regarding your 1-on-1 consultation at Mash Academy ({$booking->preferred_date->format('M d, Y')} at {$booking->preferred_time}): {$booking->topic}"
                                        );
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $booking->user?->name }}</div>
                                            <div class="text-muted">{{ $booking->user?->email }}</div>
                                            <div class="fw-semibold text-navy mt-1">
                                                {{ $booking->preferred_date->format('M d, Y') }} · {{ $booking->preferred_time }} ({{ $booking->duration_minutes }}m)
                                            </div>
                                            @if ($waUrl)
                                                <a href="{{ $waUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-success btn-sm py-0 px-2 mt-1">
                                                    <i class="bi bi-whatsapp me-1" aria-hidden="true"></i>{{ __('messages.whatsapp_message') }}
                                                </a>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="fw-semibold">{{ $booking->topic }}</div>
                                            @if ($booking->notes)
                                                <div class="text-muted" style="max-width: 240px;">{{ $booking->notes }}</div>
                                            @endif
                                            <div class="mt-1">
                                                @if ($booking->isPaid())
                                                    <span class="badge bg-navy-accent">Paid · {{ number_format((float) $booking->price, 0) }} {{ $booking->currency }}</span>
                                                    @if ($booking->paymentRequest)
                                                        <span class="badge bg-secondary">Payment: {{ ucfirst($booking->paymentRequest->status) }}</span>
                                                    @endif
                                                @else
                                                    <span class="badge bg-success">Free</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge {{ $booking->status === 'confirmed' ? 'bg-navy-accent' : ($booking->status === 'completed' ? 'bg-success' : ($booking->status === 'cancelled' ? 'bg-danger' : 'bg-warning text-dark')) }}">
                                                {{ ucfirst($booking->status) }}
                                            </span>
                                            @if ($booking->meeting_link)
                                                <div class="mt-1">
                                                    <a href="{{ $booking->meeting_link }}" target="_blank" rel="noopener noreferrer" class="small">Meeting Link</a>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <button type="button" class="btn btn-ma-outline btn-sm" data-bs-toggle="modal" data-bs-target="#manageConsultModal{{ $booking->id }}">
                                                Manage
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($consultations->hasPages())
                        <div class="p-3 border-top">{{ $consultations->links() }}</div>
                    @endif
                @endif
            </div>
        </div>
    </div>

    @foreach ($consultations as $booking)
        <div class="modal fade" id="manageConsultModal{{ $booking->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('admin.consultations.status', $booking) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h3 class="modal-title h6 fw-bold">Manage Consultation #{{ $booking->id }}</h3>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Status</label>
                                <select name="status" class="form-select" required>
                                    <option value="pending" {{ $booking->status === 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="confirmed" {{ $booking->status === 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                                    <option value="completed" {{ $booking->status === 'completed' ? 'selected' : '' }}>Completed</option>
                                    <option value="cancelled" {{ $booking->status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Meeting Link (Zoom / Google Meet)</label>
                                <input type="url" name="meeting_link" value="{{ $booking->meeting_link }}" class="form-control" placeholder="https://meet.google.com/...">
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-semibold">Admin / Instructor Note</label>
                                <textarea name="admin_note" rows="3" class="form-control">{{ $booking->admin_note }}</textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">{{ __('messages.cancel') }}</button>
                            <button type="submit" class="btn btn-ma-primary btn-sm">{{ __('messages.save_changes') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endsection
