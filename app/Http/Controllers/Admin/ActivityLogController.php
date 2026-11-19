<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $actionFilter = trim((string) $request->query('action', ''));

        $logs = ActivityLog::query()
            ->with('actor')
            ->when($actionFilter !== '', fn ($q) => $q->where('action', $actionFilter))
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        $availableActions = ActivityLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return view('admin.activity-logs.index', [
            'logs' => $logs,
            'actionFilter' => $actionFilter,
            'availableActions' => $availableActions,
        ]);
    }
}
