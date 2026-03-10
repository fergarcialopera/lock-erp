<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Src\Identity\Application\Contracts\UserRepositoryInterface;
use Src\Identity\Infrastructure\Repositories\UserRepository;
use Src\Inventory\Application\Contracts\ProductRepositoryInterface;
use Src\Inventory\Application\Contracts\CompartmentInventoryRepositoryInterface;
use Src\Inventory\Infrastructure\Repositories\ProductRepository;
use Src\Inventory\Infrastructure\Repositories\CompartmentInventoryRepository;
use Src\Lockers\Application\Contracts\ClinicRepositoryInterface;
use Src\Lockers\Application\Contracts\LockerRepositoryInterface;
use Src\Lockers\Application\Contracts\CompartmentRepositoryInterface;
use Src\Lockers\Infrastructure\Repositories\ClinicRepository;
use Src\Lockers\Infrastructure\Repositories\LockerRepository;
use Src\Lockers\Infrastructure\Repositories\CompartmentRepository;
use Src\Dispenses\Application\Contracts\DispenseRepositoryInterface;
use Src\Dispenses\Infrastructure\Repositories\DispenseRepository;
use Src\Audit\Application\Contracts\AuditLogRepositoryInterface;
use Src\Audit\Infrastructure\Repositories\AuditLogRepository;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(ProductRepositoryInterface::class, ProductRepository::class);
        $this->app->bind(CompartmentInventoryRepositoryInterface::class, CompartmentInventoryRepository::class);
        $this->app->bind(ClinicRepositoryInterface::class, ClinicRepository::class);
        $this->app->bind(LockerRepositoryInterface::class, LockerRepository::class);
        $this->app->bind(CompartmentRepositoryInterface::class, CompartmentRepository::class);
        $this->app->bind(DispenseRepositoryInterface::class, DispenseRepository::class);
        $this->app->bind(AuditLogRepositoryInterface::class, AuditLogRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('manage-settings', fn($user) => $user->role === 'ADMIN');
        Gate::define('manage-users', fn($user) => $user->role === 'ADMIN');
        Gate::define('manage-inventory', fn($user) => in_array($user->role, ['ADMIN', 'RESPONSABLE']));
        Gate::define('view-audit', fn($user) => in_array($user->role, ['ADMIN', 'RESPONSABLE']));
    }
}
