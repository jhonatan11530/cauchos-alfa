<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use OpenWA\Client;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Client::class, function (): Client {
            return new Client([
                'baseUrl' => (string) config('whatsapp.api_url'),
                'apiKey' => (string) config('whatsapp.api_key'),
                'timeout' => (float) config('whatsapp.timeout', 30),
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
