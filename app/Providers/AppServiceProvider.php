<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Middleware\TrustProxies;

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
        $trustedProxies = config('proxy.trusted_proxies', []);
        if ($trustedProxies !== []) {
            TrustProxies::at($trustedProxies);
        }

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
