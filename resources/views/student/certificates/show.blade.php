@extends('layouts.student')

@section('title', __('messages.certificate_of_completion'))

@section('content')
    @php
        $locale = $currentLocale ?? app()->getLocale();
        $academyName = \App\Models\AcademySetting::localized('academy_name', $locale, $academySettings ?? null) ?: 'Mash Academy';
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4 d-print-none">
        <a href="{{ route('student.certificates.index') }}" class="btn btn-ma-outline btn-sm">
            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('messages.my_certificates') }}
        </a>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-ma-primary btn-sm" onclick="window.print()">
                <i class="bi bi-printer me-1" aria-hidden="true"></i>{{ __('messages.print_certificate') }}
            </button>
            <a href="{{ route('student.certificates.download', $course) }}" class="btn btn-ma-dark btn-sm">
                <i class="bi bi-download me-1" aria-hidden="true"></i>{{ __('messages.download_certificate') }}
            </a>
        </div>
    </div>

    {{-- Official Mash Academy Printable Certificate Frame (Strict White / Crisp Black / Navy Blue) --}}
    <div class="mx-auto bg-white text-dark p-4 p-md-5 rounded-4 shadow" style="max-width: 900px; border: 8px double #0a192f;">
        <div class="p-4 p-md-5 text-center" style="border: 2px solid #1e3a8a; border-radius: 0.75rem;">
            <div class="mb-4">
                @if (! empty($academyLogoUrl))
                    <img src="{{ $academyLogoUrl }}" alt="{{ $academyName }}" style="max-height: 64px; object-fit: contain;" class="mb-2">
                @else
                    <div class="ma-brand-emblem mx-auto mb-2" style="width: 56px; height: 56px; font-size: 1.4rem;">MA</div>
                @endif
                <div class="text-uppercase fw-bold" style="letter-spacing: 0.16em; color: #0a192f;">{{ $academyName }}</div>
            </div>

            <div class="text-uppercase small fw-bold mb-2" style="letter-spacing: 0.2em; color: #1e3a8a;">
                {{ __('messages.certificate_of_completion') }}
            </div>

            <p class="text-muted mb-2">This certifies that</p>

            <h1 class="display-6 fw-bold mb-3" style="color: #111111;">{{ $student->name }}</h1>

            <p class="text-muted mb-2">has successfully completed all curriculum requirements and the Final Project for</p>

            <h2 class="h3 fw-bold mb-4" style="color: #1e3a8a;">{{ $course->localizedTitle($locale) }}</h2>

            <hr class="my-4" style="border-color: #111111; opacity: 0.2;">

            <div class="row g-3 align-items-end text-start text-md-center small">
                <div class="col-md-4">
                    <div class="text-muted text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.08em;">Completion Date</div>
                    <div class="fw-bold mt-1">{{ $certificate->issued_at->format('F d, Y') }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.08em;">Verification ID</div>
                    <div class="fw-bold font-monospace mt-1" style="color: #1e3a8a;">{{ $certificate->certificate_code }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.08em;">Authorized By</div>
                    <div class="fw-bold mt-1">{{ $academyName }}</div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-top small text-muted">
                Verify authenticity at: <code>{{ route('certificates.verify', ['code' => $certificate->certificate_code]) }}</code>
            </div>
        </div>
    </div>
@endsection
