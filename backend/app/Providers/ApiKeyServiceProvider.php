<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\ApiKeyResolver;
use Illuminate\Support\ServiceProvider;

class ApiKeyServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(ApiKeyResolver::class, function ($app) {
            return new ApiKeyResolver($app->make('session'));
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
