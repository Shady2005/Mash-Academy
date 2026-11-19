<?php

use App\Http\Controllers\Account\PasswordController;
use App\Http\Controllers\Admin\AcademySettingsController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\PolicySettingsController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PolicyController;
use App\Http\Controllers\Student\DashboardController as StudentDashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes (Phase 1)
|--------------------------------------------------------------------------
*/
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/policy', [PolicyController::class, 'show'])->name('policy.show');

/*
|--------------------------------------------------------------------------
| Guest Authentication Routes
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');

    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');

    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');

    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])->name('password.update');
});

/*
|--------------------------------------------------------------------------
| Shared Authenticated Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'active'])->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/account/password', [PasswordController::class, 'edit'])->name('account.password.edit');
    Route::put('/account/password', [PasswordController::class, 'update'])->name('account.password.update');
});

/*
|--------------------------------------------------------------------------
| Student Portal Routes (Student Role Only)
|--------------------------------------------------------------------------
*/
Route::prefix('student')
    ->name('student.')
    ->middleware(['auth', 'active', 'role:student'])
    ->group(function (): void {
        Route::get('/dashboard', [StudentDashboardController::class, 'index'])->name('dashboard');
    });

/*
|--------------------------------------------------------------------------
| Admin Portal Routes (4 Seeded Staff Roles)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'active', 'role:super_admin_1,super_admin_2,teacher,support'])
    ->group(function (): void {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Super Admin 1 & Super Admin 2 Only
        Route::middleware('role:super_admin_1,super_admin_2')->group(function (): void {
            Route::get('/settings', [AcademySettingsController::class, 'edit'])->name('settings.edit');
            Route::put('/settings', [AcademySettingsController::class, 'update'])->name('settings.update');
            Route::delete('/settings/logo', [AcademySettingsController::class, 'destroyLogo'])->name('settings.logo.destroy');

            Route::get('/settings/policy', [PolicySettingsController::class, 'edit'])->name('settings.policy.edit');
            Route::post('/settings/policy', [PolicySettingsController::class, 'store'])->name('settings.policy.store');

            Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
            Route::put('/staff/{staff}/reset-password', [StaffController::class, 'resetPassword'])->name('staff.reset-password');
            Route::delete('/staff/{staff}', [StaffController::class, 'destroy'])->name('staff.destroy');

            Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
        });
    });
