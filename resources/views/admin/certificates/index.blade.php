@extends('layouts.admin')

@section('title', __('messages.certificates_registry'))
@section('page-title', __('messages.certificates_registry'))

@section('content')
    <div class="ma-card p-4 mb-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h2 class="h5 fw-bold mb-1">Issued Certificates &amp; Verification Registry</h2>
            <p class="small text-muted mb-0">
                Certificates are generated automatically only after students complete ≥ 95% of a course and submit its Final Project.
            </p>
        </div>
        <div class="d-flex gap-2">
            @if (auth()->user()->isSupport())
                <span class="badge bg-black-badge px-3 py-2">
                    <i class="bi bi-eye me-1" aria-hidden="true"></i>{{ __('messages.read_only_badge') }}
                </span>
            @endif
            <a href="{{ route('certificates.verify') }}" target="_blank" rel="noopener" class="btn btn-ma-outline btn-sm">
                <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>Public Verification Page
            </a>
        </div>
    </div>

    <div class="ma-card p-3 mb-3">
        <form method="GET" action="{{ route('admin.certificates.index') }}" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label for="cert_q" class="form-label small fw-semibold">Search by Code, Student, or Course</label>
                <input type="search" id="cert_q" name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="MA-2026-... or student name">
            </div>
            <div class="col-md-3">
                <label for="cert_status" class="form-label small fw-semibold">Status</label>
                <select id="cert_status" name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="valid" {{ $statusFilter === 'valid' ? 'selected' : '' }}>Valid</option>
                    <option value="revoked" {{ $statusFilter === 'revoked' ? 'selected' : '' }}>Revoked</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-ma-primary btn-sm px-3">Filter</button>
                @if ($search !== '' || $statusFilter !== '')
                    <a href="{{ route('admin.certificates.index') }}" class="btn btn-ma-outline btn-sm">Clear</a>
                @endif
            </div>
        </form>
    </div>

    <div class="ma-card overflow-hidden">
        @if ($certificates->isEmpty())
            <div class="p-5 text-center text-muted small">No certificates have been issued yet.</div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0 small">
                    <thead>
                        <tr>
                            <th>Verification ID</th>
                            <th>Student</th>
                            <th>Course</th>
                            <th>Issued Date</th>
                            <th>Status</th>
                            @can('manage-certificates')
                                <th class="text-end">Admin Controls</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($certificates as $cert)
                            <tr>
                                <td><code class="fw-bold text-navy">{{ $cert->certificate_code }}</code></td>
                                <td>
                                    <div class="fw-semibold">{{ $cert->user?->name ?? 'Deleted' }}</div>
                                    <div class="text-muted">{{ $cert->user?->email }}</div>
                                </td>
                                <td class="fw-semibold">{{ $cert->course?->title ?? 'Deleted' }}</td>
                                <td class="text-muted text-nowrap">{{ $cert->issued_at->format('M d, Y H:i') }}</td>
                                <td>
                                    @if ($cert->isValid())
                                        <span class="badge bg-success">Valid</span>
                                    @else
                                        <span class="badge bg-danger">Revoked</span>
                                        <div class="text-muted mt-1">{{ $cert->revocation_reason }}</div>
                                    @endif
                                </td>
                                @can('manage-certificates')
                                    <td class="text-end text-nowrap">
                                        @if ($cert->isValid())
                                            <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#revokeCertModal{{ $cert->id }}">
                                                Revoke
                                            </button>
                                        @else
                                            <form method="POST" action="{{ route('admin.certificates.reinstate', $cert) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-ma-primary btn-sm">Reinstate</button>
                                            </form>
                                        @endif
                                    </td>
                                @endcan
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($certificates->hasPages())
                <div class="p-3 border-top">{{ $certificates->links() }}</div>
            @endif
        @endif
    </div>

    @can('manage-certificates')
        @foreach ($certificates as $cert)
            @if ($cert->isValid())
                <div class="modal fade" id="revokeCertModal{{ $cert->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('admin.certificates.revoke', $cert) }}">
                                @csrf
                                <div class="modal-header">
                                    <h3 class="modal-title h6 fw-bold text-danger">Revoke Certificate {{ $cert->certificate_code }}</h3>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <label class="form-label small fw-semibold">Revocation Reason <span class="text-danger">*</span></label>
                                    <input type="text" name="revocation_reason" class="form-control" required minlength="3" maxlength="255" placeholder="e.g. Academic integrity violation">
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-danger btn-sm">Confirm Revoke</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
    @endcan
@endsection
