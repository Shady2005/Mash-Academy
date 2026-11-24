<?php

namespace App\Providers;

use App\Models\AcademySetting;
use App\Models\Course;
use App\Models\User;
use App\Policies\CoursePolicy;
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
        Gate::policy(Course::class, CoursePolicy::class);

        Gate::define('access-admin-panel', fn (User $user): bool => $user->is_active && ! $user->isBlocked() && $user->isStaff());
        Gate::define('manage-courses', fn (User $user): bool => $user->is_active && ! $user->isBlocked() && ($user->isSuperAdmin() || $user->isTeacher()));
        Gate::define('manage-packages', fn (User $user): bool => $user->is_active && ! $user->isBlocked() && $user->isSuperAdmin());
        Gate::define('manage-consultations', fn (User $user): bool => $user->is_active && ! $user->isBlocked() && $user->isInstructor());
        Gate::define('manage-users', fn (User $user): bool => $user->is_active && ! $user->isBlocked() && $user->isStaff());
        Gate::define('view-marketing', fn (User $user): bool => $user->is_active && ! $user->isBlocked() && $user->isStaff());
        Gate::define('manage-marketing', fn (User $user): bool => $user->is_active && ! $user->isBlocked() && $user->isSuperAdmin());
        Gate::define('manage-instructor-profile', fn (User $user): bool => $user->is_active && ! $user->isBlocked() && $user->isInstructor());
        Gate::define('manage-payments', fn (User $user): bool => $user->is_active && $user->isSuperAdmin());
        Gate::define('manage-promo-codes', fn (User $user): bool => $user->is_active && $user->isSuperAdmin());
        Gate::define('manage-enrollments', fn (User $user): bool => $user->is_active && $user->isSuperAdmin());
        Gate::define('view-analytics', fn (User $user): bool => $user->is_active && ($user->isSuperAdmin() || $user->isSupport()));
        Gate::define('view-support-students', fn (User $user): bool => $user->is_active && ($user->isSuperAdmin() || $user->isSupport()));
        Gate::define('view-certificates', fn (User $user): bool => $user->is_active && ($user->isSuperAdmin() || $user->isSupport()));
        Gate::define('manage-certificates', fn (User $user): bool => $user->is_active && $user->isSuperAdmin());
        Gate::define('manage-settings', fn (User $user): bool => $user->is_active && $user->isSuperAdmin());
        Gate::define('manage-staff', fn (User $user): bool => $user->is_active && $user->isSuperAdmin());
        Gate::define('view-activity-logs', fn (User $user): bool => $user->is_active && $user->isSuperAdmin());
        Gate::define('access-student-portal', fn (User $user): bool => $user->is_active && ($user->isStudent() || $user->isSuperAdmin()));

        View::composer('*', function ($view): void {
            $settings = AcademySetting::defaults();
            $logoUrl = null;
            $instapayQrUrl = null;

            try {
                $settings = AcademySetting::allSettings();
                $logoUrl = AcademySetting::logoUrl();
                $instapayQrUrl = AcademySetting::instapayQrUrl();
            } catch (\Throwable) {
                // Gracefully fall back to defaults before migrations exist.
            }

            $view->with('academySettings', $settings);
            $view->with('academyLogoUrl', $logoUrl);
            if (! array_key_exists('instapayQrUrl', $view->getData())) {
                $view->with('instapayQrUrl', $instapayQrUrl);
            }
        });

        // Share recent in-app notifications ONLY on master layouts (prevents repeated queries per partial)
        View::composer(['layouts.admin', 'layouts.student'], function ($view): void {
            $user = auth()->user();
            if (! $user) {
                $view->with('navUnreadNotificationsCount', 0);
                $view->with('navRecentNotifications', collect());

                return;
            }

            try {
                $recent = $user->notifications()->latest()->limit(8)->get();
                $unreadCount = $user->unreadNotifications()->count();
            } catch (\Throwable) {
                $recent = collect();
                $unreadCount = 0;
            }

            $view->with('navUnreadNotificationsCount', $unreadCount);
            $view->with('navRecentNotifications', $recent);
        });
    }
}
