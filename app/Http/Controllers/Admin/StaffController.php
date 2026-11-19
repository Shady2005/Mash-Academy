<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResetStaffPasswordRequest;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', User::class);

        $order = [
            UserRole::SuperAdmin1->value => 1,
            UserRole::SuperAdmin2->value => 2,
            UserRole::Teacher->value => 3,
            UserRole::Support->value => 4,
        ];

        $staffMembers = User::withTrashed()
            ->whereIn('role', UserRole::staffValues())
            ->get()
            ->sortBy(fn (User $member): int => $order[$member->role->value] ?? 99)
            ->values();

        return view('admin.staff.index', [
            'staffMembers' => $staffMembers,
        ]);
    }

    public function resetPassword(ResetStaffPasswordRequest $request, int $staff): RedirectResponse
    {
        $target = User::withTrashed()->findOrFail($staff);

        Gate::authorize('resetPassword', $target);

        $target->forceFill([
            'password' => Hash::make((string) $request->input('password')),
            'must_change_password' => true,
        ])->save();

        ActivityLogger::log(
            action: 'staff.password_reset',
            subject: $target,
            meta: [
                'target_email' => $target->email,
                'target_role' => $target->role->value,
            ],
            actor: $request->user(),
            ip: $request->ip()
        );

        return redirect()
            ->route('admin.staff.index')
            ->with('status', "Password has been reset for {$target->name}.");
    }

    public function destroy(Request $request, int $staff): RedirectResponse
    {
        $target = User::withTrashed()->findOrFail($staff);

        Gate::authorize('delete', $target);

        DB::transaction(function () use ($target, $request): void {
            $target->forceFill(['is_active' => false])->save();
            $target->delete();

            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $target->id)->delete();
            }

            ActivityLogger::log(
                action: 'staff.deleted',
                subject: $target,
                meta: [
                    'target_name' => $target->name,
                    'target_email' => $target->email,
                    'target_role' => $target->role->value,
                ],
                actor: $request->user(),
                ip: $request->ip()
            );
        });

        return redirect()
            ->route('admin.staff.index')
            ->with('status', "{$target->name} ({$target->role->label()}) has been deleted.");
    }
}
