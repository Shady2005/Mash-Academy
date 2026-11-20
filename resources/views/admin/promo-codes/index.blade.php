@extends('layouts.admin')

@section('title', __('messages.promo_codes'))
@section('page-title', __('messages.promo_codes'))

@section('content')
    <div class="row g-4">
        {{-- Create Promo Code --}}
        <div class="col-lg-4">
            <div class="ma-card p-4">
                <h2 class="h6 fw-bold mb-1">Create Promo Code</h2>
                <p class="small text-muted mb-3">
                    Create percentage (%) or fixed EGP discounts with optional expiry date and usage limit.
                </p>

                <form method="POST" action="{{ route('admin.promo-codes.store') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="promo_code" class="form-label small fw-semibold">Promo Code <span class="text-danger">*</span></label>
                        <input
                            type="text"
                            id="promo_code"
                            name="code"
                            value="{{ old('code') }}"
                            class="form-control text-uppercase @error('code') is-invalid @enderror"
                            placeholder="e.g. MASH20"
                            required
                            maxlength="64"
                        >
                        <div class="form-text">Uppercase letters, numbers, hyphens, or underscores.</div>
                        @error('code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="discount_type" class="form-label small fw-semibold">Discount Type <span class="text-danger">*</span></label>
                            <select id="discount_type" name="discount_type" class="form-select" required>
                                <option value="percentage" {{ old('discount_type', 'percentage') === 'percentage' ? 'selected' : '' }}>Percentage (%)</option>
                                <option value="fixed" {{ old('discount_type') === 'fixed' ? 'selected' : '' }}>Fixed (EGP)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label for="discount_value" class="form-label small fw-semibold">Value <span class="text-danger">*</span></label>
                            <input
                                type="number"
                                step="0.01"
                                min="0.01"
                                id="discount_value"
                                name="discount_value"
                                value="{{ old('discount_value', '20') }}"
                                class="form-control @error('discount_value') is-invalid @enderror"
                                required
                            >
                            @error('discount_value')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="max_uses" class="form-label small fw-semibold">Max Usage Limit (Optional)</label>
                        <input type="number" min="1" id="max_uses" name="max_uses" value="{{ old('max_uses') }}" class="form-control" placeholder="Leave blank for unlimited">
                    </div>

                    <div class="mb-3">
                        <label for="expires_at" class="form-label small fw-semibold">Expiration Date &amp; Time (Optional)</label>
                        <input type="datetime-local" id="expires_at" name="expires_at" value="{{ old('expires_at') }}" class="form-control">
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="promo_active" name="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                        <label class="form-check-label small fw-semibold" for="promo_active">Active immediately</label>
                    </div>

                    <button type="submit" class="btn btn-ma-primary w-100">
                        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Create Promo Code
                    </button>
                </form>
            </div>
        </div>

        {{-- Promo Codes Table --}}
        <div class="col-lg-8">
            <div class="ma-card overflow-hidden">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                    <h2 class="h6 fw-bold mb-0">Configured Promo Codes</h2>
                    <span class="small text-muted">{{ $promoCodes->total() }} total</span>
                </div>

                @if ($promoCodes->isEmpty())
                    <div class="p-5 text-center text-muted small">
                        <i class="bi bi-ticket-perforated fs-1 d-block mb-2" aria-hidden="true"></i>
                        No promo codes created yet.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 small">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Discount</th>
                                    <th>Usage</th>
                                    <th>Expires</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($promoCodes as $promo)
                                    @php
                                        $label = $promo->statusLabel();
                                        $badgeClass = match ($label) {
                                            'Active' => 'bg-navy-accent',
                                            'Disabled' => 'bg-secondary',
                                            default => 'bg-danger',
                                        };
                                    @endphp
                                    <tr>
                                        <td><code class="fw-bold fs-6 text-navy">{{ $promo->code }}</code></td>
                                        <td class="fw-semibold">{{ $promo->formattedDiscount() }}</td>
                                        <td>
                                            {{ $promo->used_count }} / {{ $promo->max_uses ?? '∞' }}
                                        </td>
                                        <td class="text-muted text-nowrap">
                                            {{ $promo->expires_at ? $promo->expires_at->format('M d, Y H:i') : 'Never' }}
                                        </td>
                                        <td><span class="badge {{ $badgeClass }}">{{ $label }}</span></td>
                                        <td class="text-end text-nowrap">
                                            <div class="d-inline-flex gap-1">
                                                <form method="POST" action="{{ route('admin.promo-codes.toggle', $promo) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="btn btn-ma-pref btn-sm" title="{{ $promo->is_active ? 'Disable' : 'Enable' }}">
                                                        <i class="bi {{ $promo->is_active ? 'bi-pause-circle' : 'bi-play-circle' }}" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                                <button type="button" class="btn btn-ma-outline btn-sm" data-bs-toggle="modal" data-bs-target="#editPromoModal{{ $promo->id }}" title="Edit">
                                                    <i class="bi bi-pencil" aria-hidden="true"></i>
                                                </button>
                                                <form method="POST" action="{{ route('admin.promo-codes.destroy', $promo) }}" onsubmit="return confirm('Delete promo code {{ $promo->code }}?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete">
                                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($promoCodes->hasPages())
                        <div class="p-3 border-top">{{ $promoCodes->links() }}</div>
                    @endif
                @endif
            </div>
        </div>
    </div>

    {{-- Edit Promo Code Modals --}}
    @foreach ($promoCodes as $promo)
        <div class="modal fade" id="editPromoModal{{ $promo->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('admin.promo-codes.update', $promo) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h3 class="modal-title h6 fw-bold">Edit Promo Code — {{ $promo->code }}</h3>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Code</label>
                                <input type="text" name="code" value="{{ $promo->code }}" class="form-control text-uppercase" required maxlength="64">
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label small fw-semibold">Type</label>
                                    <select name="discount_type" class="form-select" required>
                                        <option value="percentage" {{ $promo->discount_type === 'percentage' ? 'selected' : '' }}>Percentage (%)</option>
                                        <option value="fixed" {{ $promo->discount_type === 'fixed' ? 'selected' : '' }}>Fixed (EGP)</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-semibold">Value</label>
                                    <input type="number" step="0.01" min="0.01" name="discount_value" value="{{ $promo->discount_value }}" class="form-control" required>
                                </div>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label small fw-semibold">Max Uses</label>
                                    <input type="number" min="1" name="max_uses" value="{{ $promo->max_uses }}" class="form-control" placeholder="Unlimited">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-semibold">Expires At</label>
                                    <input type="datetime-local" name="expires_at" value="{{ $promo->expires_at?->format('Y-m-d\TH:i') }}" class="form-control">
                                </div>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" {{ $promo->is_active ? 'checked' : '' }}>
                                <label class="form-check-label small fw-semibold">Active</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-ma-primary btn-sm">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endsection
