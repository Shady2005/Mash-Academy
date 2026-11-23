@extends('layouts.admin')

@section('title', __('messages.course_packages'))
@section('page-title', __('messages.course_packages'))

@section('content')
    <div class="ma-card p-4 mb-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h2 class="h5 fw-bold mb-1">{{ __('messages.course_packages') }}</h2>
            <p class="text-muted small mb-0">
                Bundle multiple courses at a special package price. Approving a package payment automatically unlocks every included course in the student's normal <strong>My Courses</strong> view.
            </p>
        </div>
        <a href="{{ route('admin.packages.create') }}" class="btn btn-ma-primary btn-sm px-3">
            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('messages.create_package') }}
        </a>
    </div>

    <div class="ma-card overflow-hidden">
        @if ($packages->isEmpty())
            <div class="p-5 text-center text-muted small">
                <i class="bi bi-box-seam fs-1 d-block mb-2" aria-hidden="true"></i>
                No course packages created yet. Click <strong>{{ __('messages.create_package') }}</strong> to bundle existing courses.
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0 small">
                    <thead>
                        <tr>
                            <th>Package</th>
                            <th>Included Courses</th>
                            <th>Bundle Price</th>
                            <th>{{ __('messages.status') }}</th>
                            <th class="text-end">{{ __('messages.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($packages as $package)
                            @php
                                $savings = $package->savingsAmount();
                            @endphp
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        @if ($package->thumbnailUrl())
                                            <img src="{{ $package->thumbnailUrl() }}" alt="{{ $package->title }}" class="rounded border" style="width: 64px; height: 42px; object-fit: cover;">
                                        @else
                                            <div class="rounded bg-navy text-white d-inline-flex align-items-center justify-content-center" style="width: 64px; height: 42px;">
                                                <i class="bi bi-box-seam" aria-hidden="true"></i>
                                            </div>
                                        @endif
                                        <div>
                                            <div class="fw-bold">{{ $package->title }}</div>
                                            @if ($package->title_ar)
                                                <div class="text-muted" dir="rtl">{{ $package->title_ar }}</div>
                                            @endif
                                            <div class="text-muted" style="font-size: 0.75rem;">/{{ $package->slug }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-navy mb-1">{{ $package->courses_count }} Courses</span>
                                    <div class="text-muted" style="max-width: 300px;">
                                        {{ $package->courses->pluck('title')->implode(' · ') }}
                                    </div>
                                </td>
                                <td class="text-nowrap">
                                    <div class="fw-bold text-navy">{{ $package->formattedPrice() }}</div>
                                    @if ($savings > 0)
                                        <div class="text-success" style="font-size: 0.75rem;">
                                            Saves {{ number_format($savings, 0) }} {{ $package->currency }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $package->is_published ? 'bg-navy-accent' : 'bg-secondary' }}">
                                        {{ $package->is_published ? __('messages.published') : __('messages.draft') }}
                                    </span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('admin.packages.edit', $package) }}" class="btn btn-ma-outline btn-sm">
                                            <i class="bi bi-pencil me-1" aria-hidden="true"></i>{{ __('messages.edit') }}
                                        </a>
                                        <form method="POST" action="{{ route('admin.packages.toggle-publish', $package) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-ma-pref btn-sm" title="Toggle publish status">
                                                <i class="bi {{ $package->is_published ? 'bi-eye-slash' : 'bi-eye' }}" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.packages.destroy', $package) }}" onsubmit="return confirm('Delete this package? Existing student enrollments will NOT be affected.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete package">
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

            @if ($packages->hasPages())
                <div class="p-3 border-top">{{ $packages->links() }}</div>
            @endif
        @endif
    </div>
@endsection
