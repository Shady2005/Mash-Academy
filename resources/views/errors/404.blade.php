@extends('layouts.public')

@section('title', '404 — Page Not Found')

@section('content')
    <section class="py-5">
        <div class="container text-center" style="max-width: 540px;">
            <div class="ma-card-elevated p-5">
                <div class="ma-icon-box ma-icon-navy mx-auto mb-3" style="width: 64px; height: 64px; font-size: 1.75rem;">
                    <i class="bi bi-compass" aria-hidden="true"></i>
                </div>
                <div class="text-uppercase small fw-bold text-muted mb-1">Error 404</div>
                <h1 class="h3 fw-bold mb-2">Page Not Found</h1>
                <p class="text-muted mb-4">
                    The page you requested could not be found or may have been moved.
                </p>
                <a href="{{ route('home') }}" class="btn btn-ma-primary">Back to Home</a>
            </div>
        </div>
    </section>
@endsection
