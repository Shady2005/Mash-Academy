@extends('layouts.public')

@section('title', 'Platform Policy')

@section('content')
    <section class="py-5">
        <div class="container" style="max-width: 820px;">
            <div class="ma-card-elevated p-4 p-md-5">
                @if ($policy)
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 pb-3 border-bottom">
                        <div>
                            <span class="badge bg-teal mb-2">Active Policy · Version {{ $policy->version }}</span>
                            <h1 class="h3 fw-bold mb-0">{{ $policy->title }}</h1>
                        </div>
                        <div class="text-muted small">
                            Updated {{ $policy->updated_at->format('M d, Y') }}
                        </div>
                    </div>

                    <div class="text-body" style="white-space: pre-line; line-height: 1.75;">{{ $policy->body }}</div>

                    <div class="mt-4 pt-3 border-top d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <a href="{{ route('home') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back to Home
                        </a>
                        @guest
                            <a href="{{ route('register') }}" class="btn btn-ma-accent btn-sm px-3">
                                Proceed to Student Registration
                            </a>
                        @endguest
                    </div>
                @else
                    <div class="text-center py-4">
                        <h1 class="h4 fw-bold">Platform Policy</h1>
                        <p class="text-muted mb-0">No active platform policy has been published yet.</p>
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
