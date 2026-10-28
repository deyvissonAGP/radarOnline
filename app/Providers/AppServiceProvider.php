<?php

namespace App\Providers;

use App\Radar\Services\CollectorRegistry;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CollectorRegistry::class);
    }

    public function boot(): void
    {
        Carbon::setLocale('pt_BR');
    }
}
