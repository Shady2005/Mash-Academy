<?php

namespace App\Providers;

use App\Models\AcademySetting;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        Gate::policy(User::class, UserPolicy::class);

        Gate::define('access-admin-panel', fn (User $user): bool => $user->is_active && $user->isStaff());
        Gate::define('manage-settings', fn (User $user): bool => $user->is_active && $user->isSuperAdmin());
        Gate::define('manage-staff', fn (User $user): bool => $user->is_active && $user->isSuperAdmin());
        Gate::define('view-activity-logs', fn (User $user): bool => $user->is_active && $user->isSuperAdmin());
        Gate::define('access-student-portal', fn (User $user): bool => $user->is_active && $user->isStudent());

        View::composer('*', function ($view): void {
            $settings = AcademySetting::defaults();
            $logoUrl = null;

            try {
                if (Schema::hasTable('academy_settings')) {
                    $settings = AcademySetting::allSettings();
                    $logoUrl = AcademySetting::logoUrl();
                }
            } catch (\Throwable) {
                // Gracefully fall back to defaults during boot before migrations exist.
            }

            $view->with('academySettings', $settings);
            $view->with('academyLogoUrl', $logoUrl);
        });
    }
}
