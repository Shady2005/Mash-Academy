<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Policy;
use App\Models\PolicyAcceptance;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register', [
            'policy' => Policy::active(),
        ]);
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $activePolicy = Policy::active();

        if (! $activePolicy) {
            return back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->withErrors(['agree_policy' => 'No active Academy Policy is configured. Please contact Mash Academy administration.']);
        }

        $validated = $request->safe()->only(['name', 'email', 'phone', 'password']);

        $user = DB::transaction(function () use ($validated, $activePolicy, $request): User {
            $user = new User();
            $user->fill($validated);
            // Explicitly force student role; ignore any tampered request fields.
            $user->role = UserRole::Student;
            $user->is_active = true;
            $user->last_login_at = now();
            $user->save();

            PolicyAcceptance::query()->create([
                'user_id' => $user->id,
                'policy_id' => $activePolicy->id,
                'policy_version' => $activePolicy->version,
                'accepted_at' => now(),
                'ip_address' => $request->ip(),
            ]);

            ActivityLogger::log(
                action: 'student.registered',
                subject: $user,
                meta: [
                    'policy_id' => $activePolicy->id,
                    'policy_version' => $activePolicy->version,
                ],
                actor: $user,
                ip: $request->ip()
            );

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->route('student.dashboard')
            ->with('status', 'Welcome to Mash Academy! Your student account has been created.');
    }
}
