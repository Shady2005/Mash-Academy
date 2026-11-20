<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\PaymentRequest;
use App\Models\Policy;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $activePolicy = Policy::active();

        $coursesQuery = Course::query();
        if ($user->isTeacher()) {
            $coursesQuery->where('created_by', $user->id);
        }

        $metrics = [
            'active_staff' => User::query()->whereIn('role', UserRole::staffValues())->where('is_active', true)->count(),
            'total_students' => User::query()->where('role', UserRole::Student->value)->count(),
            'total_courses' => (clone $coursesQuery)->count(),
            'published_courses' => (clone $coursesQuery)->where('is_published', true)->count(),
            'pending_payments' => $user->isSuperAdmin()
                ? PaymentRequest::query()->where('status', PaymentRequest::STATUS_PENDING)->count()
                : 0,
            'active_enrollments' => $user->isSuperAdmin()
                ? Enrollment::query()->currentlyActive()->count()
                : 0,
            'policy_version' => $activePolicy?->version ?? 0,
            'recent_logs' => $user->isSuperAdmin()
                ? ActivityLog::query()->with('actor')->latest('created_at')->limit(6)->get()
                : collect(),
        ];

        return view('admin.dashboard', [
            'user' => $user,
            'metrics' => $metrics,
            'activePolicy' => $activePolicy,
        ]);
    }
}
