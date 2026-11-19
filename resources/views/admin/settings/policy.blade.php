@extends('layouts.admin')

@section('title', 'Platform Policy Editor')
@section('page-title', 'Platform Policy Editor')

@section('content')
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="ma-card p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div>
                        <h2 class="h5 fw-bold mb-1">Publish New Policy Version</h2>
                        <p class="text-muted small mb-0">
                            Saving publishes a new version (v{{ ($activePolicy?->version ?? 0) + 1 }}), activates it on Student Registration, and archives all previous versions.
                        </p>
                    </div>
                    @if ($activePolicy)
                        <span class="badge bg-teal">Current: v{{ $activePolicy->version }}</span>
                    @endif
                </div>

                <form method="POST" action="{{ route('admin.settings.policy.store') }}" class="ma-form">
                    @csrf

                    <div class="mb-3 ma-field">
                        <label for="title" class="form-label fw-semibold">Policy Title <span class="text-danger">*</span></label>
                        <input
                            type="text"
                            id="title"
                            name="title"
                            value="{{ old('title', $activePolicy?->title ?? 'Mash Academy Student Terms & Platform Policy') }}"
                            class="form-control @error('title') is-invalid @enderror"
                            required
                            maxlength="255"
                        >
                        @error('title')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4 ma-field">
                        <label for="body" class="form-label fw-semibold">Policy Text <span class="text-danger">*</span></label>
                        <span class="ma-field-hint">This exact text is displayed to students on the Registration page before they tick the mandatory agreement checkbox.</span>
                        <textarea
                            id="body"
                            name="body"
                            rows="14"
                            class="form-control @error('body') is-invalid @enderror"
                            required
                            minlength="20"
                        >{{ old('body', $activePolicy?->body ?? '') }}</textarea>
                        @error('body')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-ma-primary px-4">
                            <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>
                            Publish Version {{ ($activePolicy?->version ?? 0) + 1 }}
                        </button>
                        <a href="{{ route('policy.show') }}" target="_blank" rel="noopener" class="btn btn-outline-secondary">
                            Preview Public Policy <i class="bi bi-box-arrow-up-right ms-1" aria-hidden="true"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="ma-card p-4">
                <h3 class="h6 fw-bold mb-3">Policy Version History</h3>

                @if ($history->isEmpty())
                    <p class="text-muted small mb-0">No policy versions have been published yet.</p>
                @else
                    <div class="accordion accordion-flush" id="policyHistoryAccordion">
                        @foreach ($history as $item)
                            <div class="accordion-item border rounded mb-2">
                                <h4 class="accordion-header" id="policyHeading{{ $item->id }}">
                                    <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }} py-2 px-3 small" type="button" data-bs-toggle="collapse" data-bs-target="#policyCollapse{{ $item->id }}" aria-expanded="{{ $loop->first ? 'true' : 'false' }}" aria-controls="policyCollapse{{ $item->id }}">
                                        <div class="d-flex align-items-center gap-2 flex-wrap w-100 me-2">
                                            <span class="badge {{ $item->is_active ? 'bg-teal' : 'bg-secondary' }}">v{{ $item->version }}</span>
                                            <span class="fw-semibold text-truncate" style="max-width: 190px;">{{ $item->title }}</span>
                                            <span class="ms-auto text-muted" style="font-size: 0.75rem;">
                                                {{ $item->acceptances_count }} accepted
                                            </span>
                                        </div>
                                    </button>
                                </h4>
                                <div id="policyCollapse{{ $item->id }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" aria-labelledby="policyHeading{{ $item->id }}" data-bs-parent="#policyHistoryAccordion">
                                    <div class="accordion-body small">
                                        <div class="text-muted mb-2">
                                            Published {{ $item->created_at->format('M d, Y H:i') }}
                                            @if ($item->updatedBy)
                                                by <strong>{{ $item->updatedBy->name }}</strong>
                                            @endif
                                        </div>
                                        <div class="p-2 bg-light rounded border" style="max-height: 180px; overflow-y: auto; white-space: pre-line;">{{ $item->body }}</div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
