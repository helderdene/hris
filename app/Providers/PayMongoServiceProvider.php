<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Paymongo\PaymongoClient;

class PayMongoServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PaymongoClient::class, function () {
            return new PaymongoClient(config('paymongo.secret_key'));
        });
    }
}
