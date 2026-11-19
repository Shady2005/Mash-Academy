@extends('layouts.public')

@section('title', '419 — Session Expired')

@section('content')
    <section class="py-5">
        <div class="container text-center" style="max-width: 540px;">
            <div class="ma-card-elevated p-5">
                <div class="ma-icon-box ma-icon-amber mx-auto mb-3" style="width: 64px; height: 64px; font-size: 1.75rem;">
                    <i class="bi bi-clock-history" aria-hidden="true"></i>
                </div>
                <div class="text-uppercase small fw-bold text-warning mb-1">Error 419</div>
                <h1 class="h3 fw-bold mb-2">Page Expired</h1>
                <p class="text-muted mb-4">
                    Your security token or session has expired. Please refresh the page and submit the form again.
                </p>
                <div class="d-flex justify-content-center gap-2">
                    <a href="{{ url()->previous() }}" class="btn btn-ma-primary">Go Back &amp; Retry</a>
                    <a href="{{ route('home') }}" class="btn btn-outline-secondary">Home</a>
                </div>
            </div>
        </div>
    </section>
@endsection
