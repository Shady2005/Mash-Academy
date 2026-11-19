@extends('layouts.public')

@section('title', '403 — Access Denied')

@section('content')
    <section class="py-5">
        <div class="container text-center" style="max-width: 540px;">
            <div class="ma-card-elevated p-5">
                <div class="ma-icon-box bg-danger bg-opacity-10 text-danger mx-auto mb-3" style="width: 64px; height: 64px; font-size: 1.75rem;">
                    <i class="bi bi-shield-lock-fill" aria-hidden="true"></i>
                </div>
                <div class="text-uppercase small fw-bold text-danger mb-1">Error 403</div>
                <h1 class="h3 fw-bold mb-2">Access Denied</h1>
                <p class="text-muted mb-4">
                    {{ $exception->getMessage() ?: 'You do not have permission to view this page or perform this action.' }}
                </p>
                <div class="d-flex justify-content-center gap-2">
                    @auth
                        <a href="{{ auth()->user()->dashboardRoute() }}" class="btn btn-ma-primary">Return to Dashboard</a>
                    @endauth
                    <a href="{{ route('home') }}" class="btn btn-outline-secondary">Back to Home</a>
                </div>
            </div>
        </div>
    </section>
@endsection
