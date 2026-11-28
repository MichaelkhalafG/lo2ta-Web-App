<?php

namespace App\Providers;

use App\Models\AdminUser;
use App\Support\Activity\ActivityLogger;
use App\Support\Mail\Outbox;
use App\Support\Settings\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One logger per request so withReason() reaches every automatic log line inside it.
        $this->app->scoped(ActivityLogger::class);
        $this->app->singleton(Settings::class);
        $this->app->singleton(Outbox::class);
    }

    public function boot(): void
    {
        // Catch lazy loading and silently discarded attributes while developing and testing.
        Model::shouldBeStrict(! $this->app->isProduction());

        // PLAN 2.4: the reviewer works on units, documents, requests and deals; only the admin touches
        // settings, site texts, staff accounts and commissions.
        // The activity log and CSV exports are admin only too (RUN 2026-11-17, D1 and D2).
        foreach (['manage-settings', 'manage-site-texts', 'manage-admins', 'manage-commissions', 'view-activity', 'export-data'] as $ability) {
            Gate::define($ability, static fn (AdminUser $admin): bool => $admin->isAdmin());
        }
        // Units' private files: both staff roles review units (PLAN 2.4, 2.2.15); each opening is logged.
        Gate::define('view-documents', static fn (AdminUser $admin): bool => $admin->is_active);
        // Units review (part 7.2): both roles work the queue; publishing as verified and the badge are admin only (G1).
        Gate::define('review-units', static fn (AdminUser $admin): bool => $admin->is_active);
        Gate::define('verify-units', static fn (AdminUser $admin): bool => $admin->is_active && $admin->isAdmin());
    }
}
