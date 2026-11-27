@extends('layouts.public')

@section('title', __('messages.book_consultation'))

@section('content')
    @php
        $isPaid = $pricingMode === \App\Models\Consultation::PRICING_PAID && $price > 0;
    @endphp

    <section class="ma-hero py-5">
        <div class="container position-relative" style="z-index: 1;">
            <span class="badge bg-navy-accent px-3 py-2 mb-3">
                <i class="bi bi-calendar-heart-fill me-1" aria-hidden="true"></i>1-on-1 {{ __('messages.consultations') }}
            </span>
            <h1 class="h2 fw-bold text-white mb-2">{{ __('messages.book_consultation') }}</h1>
            <p class="text-white-50 mb-3" style="max-width: 680px;">
                {{ __('messages.consultation_subtitle') }}
            </p>
            <div class="d-flex flex-wrap gap-3 small text-white">
                <span class="badge bg-dark border border-light border-opacity-25 px-3 py-2">
                    <i class="bi bi-clock me-1" aria-hidden="true"></i>{{ $duration }} Minutes
                </span>
                <span class="badge {{ $isPaid ? 'bg-navy-accent' : 'bg-success' }} px-3 py-2">
                    {{ $isPaid ? number_format($price, 0).' '.$currency : 'Free Session' }}
                </span>
                @if ($availabilityNote)
                    <span class="badge bg-dark border border-light border-opacity-25 px-3 py-2">
                        <i class="bi bi-calendar-week me-1" aria-hidden="true"></i>{{ $availabilityNote }}
                    </span>
                @endif
            </div>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            @include('partials.flash')

            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="ma-card p-4">
                        <h2 class="h5 fw-bold mb-1">Request Your 1-on-1 Session</h2>
                        <p class="small text-muted mb-4">
                            Choose your preferred date, time, and discussion topic below.
                        </p>

                        @guest
                            <div class="alert alert-secondary small">
                                Please sign in or create a student account to submit a consultation booking.
                            </div>
                            <div class="d-flex gap-2">
                                <a href="{{ route('login') }}" class="btn btn-ma-primary">{{ __('messages.sign_in') }}</a>
                                <a href="{{ route('register') }}" class="btn btn-ma-outline">{{ __('messages.create_student_account') }}</a>
                            </div>
                        @else
                            @if (auth()->user()->isStudent() || auth()->user()->isSuperAdmin())
                                <form method="POST" action="{{ route('consultations.store') }}" enctype="multipart/form-data" class="ma-form" @if ($isPaid) data-promo-check-url="{{ route('consultations.promo-check') }}" @endif>
                                    @csrf

                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="preferred_date" class="form-label fw-semibold">Preferred Date <span class="text-danger">*</span></label>
                                            <input type="date" id="preferred_date" name="preferred_date" min="{{ date('Y-m-d') }}" value="{{ old('preferred_date') }}" class="form-control @error('preferred_date') is-invalid @enderror" required>
                                            @error('preferred_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                        </div>

                                        <div class="col-md-6">
                                            <label for="preferred_time" class="form-label fw-semibold">Preferred Time <span class="text-danger">*</span></label>
                                            <input type="text" id="preferred_time" name="preferred_time" value="{{ old('preferred_time', '7:00 PM') }}" class="form-control @error('preferred_time') is-invalid @enderror" required placeholder="e.g. 7:00 PM Cairo Time">
                                            @error('preferred_time')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                        </div>

                                        @if ($instructors->isNotEmpty())
                                            <div class="col-12">
                                                <label for="instructor_id" class="form-label fw-semibold">Preferred Instructor (Optional)</label>
                                                <select id="instructor_id" name="instructor_id" class="form-select">
                                                    <option value="">Any Available Mash Academy Instructor</option>
                                                    @foreach ($instructors as $inst)
                                                        <option value="{{ $inst->id }}" {{ (string) old('instructor_id') === (string) $inst->id ? 'selected' : '' }}>
                                                            {{ $inst->name }} — {{ $inst->localizedInstructorTitle() }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        @endif

                                        <div class="col-12">
                                            <label for="topic" class="form-label fw-semibold">Consultation Topic <span class="text-danger">*</span></label>
                                            <input type="text" id="topic" name="topic" value="{{ old('topic') }}" class="form-control @error('topic') is-invalid @enderror" required maxlength="255" placeholder="e.g. Career roadmap, CV review, or project architecture">
                                            @error('topic')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                        </div>

                                        <div class="col-12">
                                            <label for="notes" class="form-label fw-semibold">Additional Details / Questions</label>
                                            <textarea id="notes" name="notes" rows="3" class="form-control">{{ old('notes') }}</textarea>
                                        </div>

                                        @if ($isPaid)
                                            <div class="col-12"><hr class="my-1"></div>
                                            <div class="col-12">
                                                <label for="promo_code" class="form-label fw-semibold">{{ __('messages.promo_code') }} (Optional — Select Instructor above first)</label>
                                                <div class="input-group">
                                                    <input
                                                        type="text"
                                                        id="promo_code"
                                                        name="promo_code"
                                                        value="{{ old('promo_code') }}"
                                                        class="form-control text-uppercase @error('promo_code') is-invalid @enderror"
                                                        placeholder="Enter instructor promo code..."
                                                        maxlength="64"
                                                    >
                                                    <button type="button" id="apply-promo-btn" class="btn btn-ma-dark px-3">{{ __('messages.apply') }}</button>
                                                </div>
                                                <div id="promo-feedback" class="small mt-1 d-none" role="status" aria-live="polite"></div>
                                                @error('promo_code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                                <div class="border rounded-3 p-2 px-3 mt-2 small" style="background-color: var(--ma-surface-subtle);">
                                                    <div class="d-flex justify-content-between">
                                                        <span class="text-muted">Original Fee:</span>
                                                        <span id="summary-original-price">{{ number_format($price, 2) }} {{ $currency }}</span>
                                                    </div>
                                                    <div id="summary-discount-row" class="d-flex justify-content-between text-success fw-semibold d-none">
                                                        <span>Discount <span id="summary-promo-badge" class="badge bg-success ms-1"></span></span>
                                                        <span id="summary-discount-amount">-0.00 {{ $currency }}</span>
                                                    </div>
                                                    <div class="d-flex justify-content-between fw-bold border-top pt-1 mt-1">
                                                        <span>Final Payable:</span>
                                                        <span id="summary-final-price" class="text-navy">{{ number_format($price, 2) }} {{ $currency }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <label for="phone" class="form-label fw-semibold">Phone / WhatsApp Number <span class="text-danger">*</span></label>
                                                <input type="tel" id="phone" name="phone" value="{{ old('phone', auth()->user()->phone) }}" class="form-control @error('phone') is-invalid @enderror" required placeholder="+20 100 123 4567">
                                                @error('phone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                            </div>
                                            <div class="col-12">
                                                <label for="screenshot" class="form-label fw-semibold">{{ __('messages.transfer_screenshot') }} <span class="text-danger">*</span></label>
                                                <input type="file" id="screenshot" name="screenshot" accept=".jpg,.jpeg,.png,.webp" class="form-control @error('screenshot') is-invalid @enderror" required>
                                                @error('screenshot')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                            </div>
                                        @endif
                                    </div>

                                    <button type="submit" class="btn btn-ma-primary btn-lg w-100 mt-4">
                                        <i class="bi bi-calendar-check me-1" aria-hidden="true"></i>
                                        {{ $isPaid ? 'Submit Paid Consultation & Receipt' : 'Submit Free Consultation Request' }}
                                    </button>
                                </form>
                            @else
                                <div class="alert alert-secondary small mb-0">
                                    Signed in as staff ({{ auth()->user()->role->shortLabel() }}). Manage bookings from the Admin Consultations dashboard.
                                </div>
                            @endif
                        @endguest
                    </div>
                </div>

                <div class="col-lg-5">
                    @if ($isPaid)
                        <div class="ma-card p-4 mb-4">
                            <span class="badge bg-navy-accent mb-2">Paid Session · {{ number_format($price, 0) }} {{ $currency }}</span>
                            <h3 class="h6 fw-bold mb-2">InstaPay QR for Consultations</h3>
                            <p class="small text-muted mb-3">
                                {{ $instapayNote ?: 'Transfer the exact session fee via InstaPay and upload your screenshot on the left.' }}
                            </p>
                            <div class="border rounded-3 p-3 text-center bg-white">
                                @if ($instapayQrUrl)
                                    <img src="{{ $instapayQrUrl }}" alt="Consultation InstaPay QR" class="img-fluid" style="max-height: 220px;">
                                @else
                                    <div class="py-4 text-muted small">InstaPay QR not configured yet.</div>
                                @endif
                            </div>
                        </div>
                    @endif

                    @auth
                        <div class="ma-card p-4">
                            <h3 class="h6 fw-bold mb-3">My Consultation Requests ({{ $myConsultations->count() }})</h3>
                            @if ($myConsultations->isEmpty())
                                <p class="small text-muted mb-0">You have not booked any consultations yet.</p>
                            @else
                                <div class="d-flex flex-column gap-3">
                                    @foreach ($myConsultations as $item)
                                        <div class="border rounded-3 p-3 small">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <strong>{{ $item->topic }}</strong>
                                                <span class="badge {{ $item->status === 'confirmed' ? 'bg-navy-accent' : ($item->status === 'completed' ? 'bg-success' : ($item->status === 'cancelled' ? 'bg-danger' : 'bg-warning text-dark')) }}">
                                                    {{ ucfirst($item->status) }}
                                                </span>
                                            </div>
                                            <div class="text-muted">
                                                {{ $item->preferred_date->format('M d, Y') }} · {{ $item->preferred_time }} ({{ $item->duration_minutes }} min)
                                            </div>
                                            @if ($item->meeting_link)
                                                <a href="{{ $item->meeting_link }}" target="_blank" rel="noopener noreferrer" class="btn btn-ma-primary btn-sm mt-2">
                                                    <i class="bi bi-camera-video me-1" aria-hidden="true"></i>Join Meeting
                                                </a>
                                            @endif
                                            @if ($item->admin_note)
                                                <div class="text-muted mt-2">{{ $item->admin_note }}</div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endauth
                </div>
            </div>
        </div>
    </section>
@endsection
