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
        foreach (['manage-settings', 'manage-site-texts', 'manage-admins', 'manage-commissions'] as $ability) {
            Gate::define($ability, static fn (AdminUser $admin): bool => $admin->isAdmin());
        }
    }
}
