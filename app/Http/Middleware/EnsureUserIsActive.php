<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public const BLOCKED_MESSAGE = "Your account has been blocked due to violating Mash Academy's Platform Policy.";

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isBlocked()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => self::BLOCKED_MESSAGE], 403);
            }

            return redirect()
                ->route('login')
                ->withErrors(['email' => self::BLOCKED_MESSAGE]);
        }

        if ($user && (! $user->is_active || $user->trashed())) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your account is no longer active. Please contact Mash Academy administration.'], 403);
            }

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Your account is no longer active. Please contact Mash Academy administration.']);
        }

        return $next($request);
    }
}
