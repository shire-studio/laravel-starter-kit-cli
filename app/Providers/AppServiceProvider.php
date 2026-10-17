<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Routing\RoutingServiceProvider;
use Illuminate\Support\ServiceProvider;
use Laravel\Boost\BoostServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        //
    }

    public function register(): void
    {
        if (class_exists(BoostServiceProvider::class)) {
            $this->app->register(RoutingServiceProvider::class);
            $this->app->register(BoostServiceProvider::class);
        }
    }
}
