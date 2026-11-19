@extends('layouts.admin')

@section('title', 'Activity Logs')
@section('page-title', 'Activity Logs')

@section('content')
    <div class="ma-card p-4 mb-4">
        <form method="GET" action="{{ route('admin.activity-logs.index') }}" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label for="action" class="form-label small fw-semibold">Filter by Action</label>
                <select id="action" name="action" class="form-select form-select-sm">
                    <option value="">All Actions</option>
                    @foreach ($availableActions as $act)
                        <option value="{{ $act }}" {{ $actionFilter === $act ? 'selected' : '' }}>{{ $act }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-ma-primary btn-sm px-3">Filter</button>
                @if ($actionFilter !== '')
                    <a href="{{ route('admin.activity-logs.index') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
                @endif
            </div>
        </form>
    </div>

    <div class="ma-card overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Timestamp</th>
                        <th>Action</th>
                        <th>Actor</th>
                        <th>Details</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="text-nowrap text-muted">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                            <td><code class="text-primary">{{ $log->action }}</code></td>
                            <td>
                                @if ($log->actor)
                                    <div class="fw-semibold">{{ $log->actor->name }}</div>
                                    <div class="text-muted" style="font-size: 0.78rem;">{{ $log->actor->role->shortLabel() }}</div>
                                @else
                                    <span class="text-muted">System</span>
                                @endif
                            </td>
                            <td>
                                @if ($log->meta)
                                    <span class="text-muted font-monospace" style="font-size: 0.78rem;">{{ json_encode($log->meta) }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-muted">{{ $log->ip ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No activity log entries match this filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())
            <div class="p-3 border-top">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
@endsection
