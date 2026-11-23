@extends('layouts.student')

@section('title', __('messages.subscribe_to_package'))

@section('content')
    @php
        $locale = $currentLocale ?? app()->getLocale();
        $packageTitle = $package->localizedTitle($locale);
        $currency = $package->currency ?: 'EGP';
        $originalPrice = (float) $package->price;
    @endphp

    <div class="mb-3">
        <a href="{{ route('student.courses.index') }}" class="btn btn-ma-outline btn-sm">
            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('messages.back_to_courses') }}
        </a>
    </div>

    @if ($pendingRequest)
        <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>
                <strong>Package Payment Request #{{ $pendingRequest->id }} is Pending Review.</strong>
                Submitted on {{ $pendingRequest->created_at->format('M d, Y H:i') }} for <strong>{{ number_format((float) $pendingRequest->final_price, 2) }} {{ $pendingRequest->currency }}</strong>.
            </div>
            <a href="{{ route('student.payments.index') }}" class="btn btn-ma-dark btn-sm">{{ __('messages.my_payments') }}</a>
        </div>
    @endif

    <div class="row g-4">
        {{-- Step 1: InstaPay QR Code, Included Courses & Dynamic Price Breakdown --}}
        <div class="col-lg-5">
            <div class="ma-card p-4 h-100">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <span class="badge bg-navy-accent">Step 1 · Package InstaPay Transfer</span>
                    @if (($instapaySource ?? 'none') === 'package')
                        <span class="badge bg-navy">{{ __('messages.package_instapay_qr') }}</span>
                    @elseif (($instapaySource ?? 'none') === 'instructor')
                        <span class="badge bg-navy">{{ __('messages.instructor_instapay_qr') }}</span>
                    @elseif (($instapaySource ?? 'none') === 'global')
                        <span class="badge bg-secondary">{{ __('messages.global_instapay_qr') }}</span>
                    @endif
                </div>

                <h1 class="h5 fw-bold mb-1">{{ $packageTitle }}</h1>
                <p class="small text-muted mb-3">
                    {{ $instapayNote ?: 'Scan the InstaPay QR code below, transfer the exact final bundle amount, and upload your transfer screenshot on the right.' }}
                </p>

                <div class="border rounded-4 p-4 text-center mb-3" style="background-color: var(--ma-surface-subtle);">
                    @if ($instapayQrUrl)
                        <img src="{{ $instapayQrUrl }}" alt="Mash Academy Package InstaPay QR" class="img-fluid rounded shadow-sm" style="max-height: 240px; object-fit: contain;">
                    @else
                        <div class="py-4 text-muted small">
                            <i class="bi bi-qr-code fs-1 d-block mb-2" aria-hidden="true"></i>
                            InstaPay QR code has not been uploaded yet.
                        </div>
                    @endif
                </div>

                {{-- Included Courses --}}
                <div class="mb-3">
                    <div class="small fw-bold text-uppercase text-muted mb-2">
                        {{ __('messages.included_courses') }} ({{ $package->courses->count() }})
                    </div>
                    <ul class="list-unstyled small mb-0 d-flex flex-column gap-1">
                        @foreach ($package->courses as $c)
                            <li>
                                <i class="bi bi-check-circle-fill text-navy me-1" aria-hidden="true"></i>
                                {{ $c->localizedTitle($locale) }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{-- Dynamic Price Summary (Updated live when Promo Code is applied) --}}
                <div class="border rounded-3 p-3" id="price-summary-card" style="background-color: var(--ma-surface-subtle);">
                    <div class="d-flex justify-content-between small mb-2">
                        <span class="text-muted">{{ __('messages.original_price') }}</span>
                        <span class="fw-semibold" id="summary-original-price">{{ number_format($originalPrice, 2) }} {{ $currency }}</span>
                    </div>
                    <div class="d-flex justify-content-between small mb-2 d-none" id="summary-discount-row">
                        <span class="text-success fw-semibold">
                            {{ __('messages.discount') }} <span id="summary-promo-badge" class="badge bg-navy-accent ms-1"></span>
                        </span>
                        <span class="text-success fw-semibold" id="summary-discount-amount">-0.00 {{ $currency }}</span>
                    </div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-bold">{{ __('messages.final_amount') }}</span>
                        <span class="h4 fw-bold text-navy mb-0" id="summary-final-price">{{ number_format($originalPrice, 2) }} {{ $currency }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Step 2: Promo Code & Payment Confirmation Form --}}
        <div class="col-lg-7">
            <div class="ma-card p-4">
                <span class="badge bg-black-badge mb-2">Step 2 · Payment Confirmation</span>
                <h2 class="h5 fw-bold mb-1">{{ __('messages.submit_payment_proof') }}</h2>
                <p class="small text-muted mb-4">
                    Once your transfer is approved, all {{ $package->courses->count() }} courses in this package will unlock automatically in your <strong>My Courses</strong> view.
                </p>

                <form
                    method="POST"
                    action="{{ route('student.packages.subscribe.store', $package) }}"
                    enctype="multipart/form-data"
                    class="ma-form"
                    data-promo-check-url="{{ route('student.packages.promo-check', $package) }}"
                >
                    @csrf

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="student_name" class="form-label small fw-semibold">Student Name (Auto-filled)</label>
                            <input type="text" id="student_name" value="{{ $student->name }}" class="form-control" readonly disabled>
                        </div>

                        <div class="col-md-6">
                            <label for="student_email" class="form-label small fw-semibold">Account Email (Auto-filled)</label>
                            <input type="email" id="student_email" value="{{ $student->email }}" class="form-control" readonly disabled>
                        </div>

                        <div class="col-12 ma-field">
                            <label for="phone" class="form-label fw-semibold">Phone Number <span class="text-danger">*</span></label>
                            <input
                                type="tel"
                                id="phone"
                                name="phone"
                                value="{{ old('phone', $student->phone) }}"
                                class="form-control @error('phone') is-invalid @enderror"
                                required
                                autocomplete="tel"
                                placeholder="+20 100 123 4567"
                            >
                            @error('phone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label for="promo_code" class="form-label fw-semibold">Promo Code (Optional)</label>
                            <div class="input-group">
                                <input
                                    type="text"
                                    id="promo_code"
                                    name="promo_code"
                                    value="{{ old('promo_code') }}"
                                    class="form-control text-uppercase @error('promo_code') is-invalid @enderror"
                                    placeholder="Enter promo code (e.g. MASH20)"
                                    maxlength="64"
                                >
                                <button type="button" id="apply-promo-btn" class="btn btn-ma-dark px-3">
                                    {{ __('messages.apply_promo') }}
                                </button>
                            </div>
                            <div id="promo-feedback" class="small mt-2 d-none" role="status" aria-live="polite"></div>
                            @error('promo_code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 ma-field">
                            <label for="screenshot" class="form-label fw-semibold">{{ __('messages.transfer_screenshot') }} <span class="text-danger">*</span></label>
                            <input
                                type="file"
                                id="screenshot"
                                name="screenshot"
                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                class="form-control @error('screenshot') is-invalid @enderror"
                                required
                            >
                            @error('screenshot')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-ma-primary btn-lg w-100" {{ $pendingRequest ? 'disabled' : '' }}>
                            <i class="bi bi-send-check me-1" aria-hidden="true"></i>{{ __('messages.submit_payment_proof') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
