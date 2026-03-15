<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Location;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class DashboardStatsService
{
    public function summary(): array
    {
        return Cache::remember('dashboard_stats', 300, function (): array {
            return [
                'clients' => Client::query()->count(),
                'locations' => Location::query()->count(),
                'contracts' => Contract::query()->count(),
                'service_orders' => ServiceOrder::query()->count(),
                'users' => User::query()->count(),
            ];
        });
    }

    public function forget(): void
    {
        Cache::forget('dashboard_stats');
    }
}