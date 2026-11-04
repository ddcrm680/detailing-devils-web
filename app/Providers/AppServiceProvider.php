<?php

namespace App\Providers;

use App\Contracts\Franchise\SmsDelivery;
use App\Contracts\Seo\MetadataRepository;
use App\Models\User;
use App\Services\Franchise\SmsAlertDelivery;
use App\Services\Seo\DatabaseMetadataRepository;
use App\Services\Seo\SeoResolver;
use App\View\Composers\SeoComposer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmsDelivery::class, SmsAlertDelivery::class);
        $this->app->bind(MetadataRepository::class, DatabaseMetadataRepository::class);
        $this->app->scoped(SeoResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('manage-seo', fn ($user) => $user instanceof User && $user->canManageSeo());
        Gate::define('publish-seo-policy', fn ($user) => $user instanceof User && $user->role === 'super_admin');
        View::composer(['layouts.app', 'layouts.prime'], SeoComposer::class);
    }
}
