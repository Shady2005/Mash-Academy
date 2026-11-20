@extends('layouts.student')

@section('title', __('messages.my_certificates'))

@section('content')
    @php
        $locale = $currentLocale ?? app()->getLocale();
    @endphp

    <div class="ma-card p-4 mb-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <span class="badge bg-navy-accent mb-2">{{ __('messages.student_portal') }}</span>
            <h1 class="h4 fw-bold mb-1">{{ __('messages.my_certificates') }}</h1>
            <p class="text-muted small mb-0">
                Course completion certificates remain 100% locked until you reach ≥ 95% completion AND submit your Final Project.
            </p>
        </div>
        <a href="{{ route('student.my-courses.index') }}" class="btn btn-ma-outline btn-sm">
            <i class="bi bi-play-btn me-1" aria-hidden="true"></i>{{ __('messages.my_courses') }}
        </a>
    </div>

    {{-- Earned Certificates --}}
    <h2 class="h6 fw-bold text-uppercase text-muted mb-3">Earned Certificates ({{ $earnedItems->count() }})</h2>
    @if ($earnedItems->isEmpty())
        <div class="ma-card p-4 text-center mb-4">
            <i class="bi bi-award fs-1 text-muted d-block mb-2" aria-hidden="true"></i>
            <p class="text-muted small mb-0">
                You have not unlocked a certificate yet. Complete a course (≥ 95%) and submit its Final Project to earn your verified Mash Academy certificate.
            </p>
        </div>
    @else
        <div class="row g-4 mb-5">
            @foreach ($earnedItems as $item)
                @php
                    $course = $item['course'];
                    $cert = $item['certificate'];
                @endphp
                <div class="col-md-6">
                    <div class="ma-card p-4 h-100 border-start border-4 d-flex flex-column" style="border-inline-start-color: #1e3a8a !important;">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <span class="badge bg-success">
                                <i class="bi bi-patch-check-fill me-1" aria-hidden="true"></i>Earned &amp; Verified
                            </span>
                            <code class="small fw-bold text-navy">{{ $cert->certificate_code }}</code>
                        </div>
                        <h3 class="h5 fw-bold mb-1">{{ $course->localizedTitle($locale) }}</h3>
                        <p class="small text-muted mb-4 flex-grow-1">
                            Issued on {{ $cert->issued_at->format('F d, Y') }}
                        </p>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('student.certificates.show', $course) }}" class="btn btn-ma-primary btn-sm flex-grow-1">
                                <i class="bi bi-eye me-1" aria-hidden="true"></i>View &amp; Print
                            </a>
                            <a href="{{ route('student.certificates.download', $course) }}" class="btn btn-ma-outline btn-sm">
                                <i class="bi bi-download me-1" aria-hidden="true"></i>{{ __('messages.download_certificate') }}
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Locked Certificates --}}
    @if ($lockedItems->isNotEmpty())
        <h2 class="h6 fw-bold text-uppercase text-muted mb-3">Locked Certificates ({{ $lockedItems->count() }})</h2>
        <div class="row g-4">
            @foreach ($lockedItems as $item)
                @php
                    $course = $item['course'];
                @endphp
                <div class="col-md-6">
                    <div class="ma-card p-4 h-100 d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge bg-black-badge">
                                <i class="bi bi-lock-fill me-1" aria-hidden="true"></i>100% Locked
                            </span>
                            <span class="small fw-semibold text-navy">{{ $item['completion_percentage'] }}% / {{ $item['required_percentage'] }}%</span>
                        </div>
                        <h3 class="h6 fw-bold mb-2">{{ $course->localizedTitle($locale) }}</h3>
                        <p class="small text-muted mb-3 flex-grow-1">{{ $item['reason'] }}</p>
                        <a href="{{ route('student.courses.learn', $course) }}" class="btn btn-ma-outline btn-sm">
                            {{ __('messages.continue_learning') }}
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
