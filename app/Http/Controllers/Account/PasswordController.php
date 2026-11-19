<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('account.password', [
            'layout' => $user->isStaff() ? 'layouts.admin' : 'layouts.student',
            'user' => $user,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        $user = $request->user();
        $user->forceFill([
            'password' => Hash::make((string) $request->input('password')),
            'must_change_password' => false,
        ])->save();

        ActivityLogger::log(
            action: 'account.password_changed',
            subject: $user,
            actor: $user,
            ip: $request->ip()
        );

        return back()->with('status', 'Your password has been updated successfully.');
    }
}
