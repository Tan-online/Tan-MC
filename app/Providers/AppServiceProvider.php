<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Location;
use App\Models\MusterExpected;
use App\Models\ServiceOrder;
use App\Observers\AuditableObserver;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        require_once app_path('Services/helpers.php');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        Client::observe(AuditableObserver::class);
        Location::observe(AuditableObserver::class);
        Contract::observe(AuditableObserver::class);
        ServiceOrder::observe(AuditableObserver::class);
        MusterExpected::observe(AuditableObserver::class);
    }
}
