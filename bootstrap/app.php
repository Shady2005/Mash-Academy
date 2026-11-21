<?php

use App\Http\Middleware\EnsureCourseAccess;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Http\Middleware\SetLocaleAndTheme;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocaleAndTheme::class,
            SecurityHeadersMiddleware::class,
        ]);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'active' => EnsureUserIsActive::class,
            'course.access' => EnsureCourseAccess::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->dashboardRoute() ?? route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            if ($request->expectsJson()) {
                $postMax = ini_get('post_max_size') ?: 'unknown';
                $uploadMax = ini_get('upload_max_filesize') ?: 'unknown';

                return response()->json([
                    'message' => "Uploaded file is too large for the current PHP server configuration (post_max_size={$postMax}, upload_max_filesize={$uploadMax}). Increase these limits in php.ini and restart PHP.",
                ], 413);
            }
        });
    })->create();
