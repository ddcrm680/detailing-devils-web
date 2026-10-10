<?php

namespace App\Providers;

use App\Contracts\Franchise\SmsDelivery;
use App\Services\Franchise\SmsAlertDelivery;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmsDelivery::class, SmsAlertDelivery::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
