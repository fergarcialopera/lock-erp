<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
    //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('manage-settings', fn($user) => $user->role === 'ADMIN');
        Gate::define('manage-inventory', fn($user) => in_array($user->role, ['ADMIN', 'RESPONSABLE']));
        Gate::define('manage-orders', fn($user) => in_array($user->role, ['ADMIN', 'RESPONSABLE']));
        Gate::define('view-audit', fn($user) => in_array($user->role, ['ADMIN', 'RESPONSABLE']));
    }
}
