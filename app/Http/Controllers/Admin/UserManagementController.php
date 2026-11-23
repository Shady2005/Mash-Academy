<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $request->user();
        $search = trim((string) $request->query('q', ''));
        $roleFilter = trim((string) $request->query('role', ''));
        $statusFilter = trim((string) $request->query('status', ''));

        $users = User::query()
            ->with('blocker')
            ->withCount(['enrollments', 'certificates'])
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($roleFilter === 'student', fn ($q) => $q->where('role', UserRole::Student->value))
            ->when($roleFilter === 'staff', fn ($q) => $q->whereIn('role', UserRole::staffValues()))
            ->when($statusFilter === 'blocked', fn ($q) => $q->where('is_blocked', true))
            ->when($statusFilter === 'active', fn ($q) => $q->where('is_blocked', false)->where('is_active', true))
            ->orderByRaw("CASE role WHEN 'super_admin_1' THEN 1 WHEN 'super_admin_2' THEN 2 WHEN 'teacher' THEN 3 WHEN 'support' THEN 4 ELSE 5 END ASC")
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $counts = [
            'total' => User::query()->count(),
            'students' => User::query()->where('role', UserRole::Student->value)->count(),
            'blocked' => User::query()->where('is_blocked', true)->count(),
        ];

        $availableStaffRoles = [];
        if ($actor->isSuperAdmin()) {
            foreach ([UserRole::Teacher, UserRole::Support] as $staffRole) {
                if (! User::withTrashed()->where('role', $staffRole->value)->exists()) {
                    $availableStaffRoles[] = $staffRole;
                }
            }
        }

        return view('admin.users.index', [
            'users' => $users,
            'counts' => $counts,
            'search' => $search,
            'roleFilter' => $roleFilter,
            'statusFilter' => $statusFilter,
            'availableStaffRoles' => $availableStaffRoles,
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $actor = $request->user();
        $validated = $request->validated();

        // Strict server-side enforcement: Teacher & Support can ONLY ever create Student accounts
        $assignedRole = $actor->isSuperAdmin()
            ? UserRole::from((string) ($validated['role'] ?? UserRole::Student->value))
            : UserRole::Student;

        $user = new User();
        $user->name = trim((string) $validated['name']);
        $user->email = Str::lower(trim((string) $validated['email']));
        $user->phone = isset($validated['phone']) && trim((string) $validated['phone']) !== ''
            ? trim((string) $validated['phone'])
            : null;
        $user->password = Hash::make((string) $validated['password']);
        $user->role = $assignedRole;
        $user->is_active = true;
        $user->is_blocked = false;
        $user->must_change_password = false;
        $user->email_verified_at = now();
        $user->save();

        ActivityLogger::log(
            action: 'user.created_from_dashboard',
            subject: $user,
            meta: [
                'email' => $user->email,
                'role' => $user->role->value,
                'created_by_role' => $actor->role->value,
            ],
            actor: $actor,
            ip: $request->ip()
        );

        return redirect()
            ->route('admin.users.index')
            ->with('status', "User \"{$user->name}\" ({$user->role->label()}) created successfully.");
    }

    public function block(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($user->canBeBlockedBy($actor), 403, 'You are not permitted to block this account.');

        $validated = $request->validate([
            'blocked_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $reason = isset($validated['blocked_reason']) && trim((string) $validated['blocked_reason']) !== ''
            ? trim((string) $validated['blocked_reason'])
            : 'Violated Mash Academy Platform Policy.';

        $user->is_blocked = true;
        $user->blocked_reason = $reason;
        $user->blocked_at = now();
        $user->blocked_by = $actor->id;
        $user->save();

        // Immediately terminate any active database sessions for the blocked user
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->delete();
        }

        ActivityLogger::log(
            action: 'user.blocked',
            subject: $user,
            meta: [
                'email' => $user->email,
                'role' => $user->role->value,
                'reason' => $reason,
            ],
            actor: $actor,
            ip: $request->ip()
        );

        return redirect()
            ->route('admin.users.index')
            ->with('status', "Account \"{$user->name}\" has been blocked immediately.");
    }

    public function unblock(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($user->canBeBlockedBy($actor), 403, 'You are not permitted to unblock this account.');

        $user->is_blocked = false;
        $user->blocked_reason = null;
        $user->blocked_at = null;
        $user->blocked_by = null;
        $user->save();

        ActivityLogger::log(
            action: 'user.unblocked',
            subject: $user,
            meta: ['email' => $user->email, 'role' => $user->role->value],
            actor: $actor,
            ip: $request->ip()
        );

        return redirect()
            ->route('admin.users.index')
            ->with('status', "Account \"{$user->name}\" has been unblocked and restored.");
    }
}
