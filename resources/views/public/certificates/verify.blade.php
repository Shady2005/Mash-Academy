@extends('layouts.public')

@section('title', __('messages.verify_certificate'))

@section('content')
    <section class="py-5">
        <div class="container" style="max-width: 680px;">
            <div class="ma-card-elevated p-4 p-md-5">
                <div class="text-center mb-4">
                    <span class="badge bg-navy-accent mb-2">
                        <i class="bi bi-patch-check-fill me-1" aria-hidden="true"></i>Official Verification
                    </span>
                    <h1 class="h3 fw-bold mb-1">{{ __('messages.verify_certificate') }}</h1>
                    <p class="text-muted small mb-0">
                        Enter a Mash Academy certificate verification ID (e.g. <code>MA-2026-XXXXXXXX</code>) to verify authenticity.
                    </p>
                </div>

                <form method="GET" action="{{ route('certificates.verify') }}" class="mb-4">
                    <div class="input-group">
                        <input
                            type="text"
                            name="code"
                            value="{{ $code }}"
                            class="form-control text-uppercase"
                            placeholder="MA-2026-XXXXXXXX"
                            required
                            aria-label="Certificate Verification Code"
                        >
                        <button type="submit" class="btn btn-ma-primary px-4">Verify</button>
                    </div>
                </form>

                @if ($code !== '')
                    @if ($certificate && $certificate->isValid())
                        <div class="border border-success rounded-4 p-4" style="background-color: var(--ma-surface-subtle);">
                            <div class="d-flex align-items-center gap-2 text-success fw-bold mb-3">
                                <i class="bi bi-patch-check-fill fs-4" aria-hidden="true"></i>
                                <span>Verified Authentic Mash Academy Certificate</span>
                            </div>
                            <dl class="row small mb-0">
                                <dt class="col-sm-4 text-muted">Verification ID</dt>
                                <dd class="col-sm-8 fw-bold font-monospace text-navy">{{ $certificate->certificate_code }}</dd>

                                <dt class="col-sm-4 text-muted">Student Name</dt>
                                <dd class="col-sm-8 fw-semibold">{{ $certificate->user?->name ?? 'Student' }}</dd>

                                <dt class="col-sm-4 text-muted">Course Completed</dt>
                                <dd class="col-sm-8 fw-semibold">{{ $certificate->course?->title ?? 'Course' }}</dd>

                                <dt class="col-sm-4 text-muted">Issued Date</dt>
                                <dd class="col-sm-8">{{ $certificate->issued_at->format('F d, Y') }}</dd>
                            </dl>
                        </div>
                    @elseif ($certificate && $certificate->isRevoked())
                        <div class="alert alert-danger mb-0">
                            <strong>Certificate Revoked:</strong> Certificate <code>{{ $certificate->certificate_code }}</code> was revoked on {{ $certificate->revoked_at->format('M d, Y') }}.
                            @if ($certificate->revocation_reason)
                                <div class="small mt-1">Reason: {{ $certificate->revocation_reason }}</div>
                            @endif
                        </div>
                    @else
                        <div class="alert alert-warning mb-0">
                            <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>
                            No certificate was found matching code <code>{{ $code }}</code>.
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </section>
@endsection
