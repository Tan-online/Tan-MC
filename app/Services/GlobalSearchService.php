<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Location;
use App\Models\ServiceOrder;
use Illuminate\Http\Request;

class GlobalSearchService
{
    public function search(Request $request, string $query, int $limit = 5): array
    {
        $term = trim($query);

        if ($term === '') {
            return [];
        }

        $results = [];

        if ($request->user()?->hasPermission('clients.view')) {
            $results[] = [
                'module' => 'Clients',
                'items' => Client::query()
                    ->select(['id', 'name', 'code', 'contact_person'])
                    ->where(function ($builder) use ($term) {
                        $builder
                            ->where('name', 'like', "%{$term}%")
                            ->orWhere('code', 'like', "%{$term}%")
                            ->orWhere('contact_person', 'like', "%{$term}%");
                    })
                    ->orderBy('name')
                    ->limit($limit)
                    ->get()
                    ->map(fn (Client $client) => [
                        'label' => $client->name,
                        'meta' => $client->code ?: $client->contact_person,
                        'url' => route('clients.index', ['search' => $client->name]),
                    ])
                    ->all(),
            ];
        }

        if ($request->user()?->hasPermission('locations.view')) {
            $results[] = [
                'module' => 'Locations',
                'items' => Location::query()
                    ->select(['id', 'name', 'city', 'postal_code'])
                    ->where(function ($builder) use ($term) {
                        $builder
                            ->where('name', 'like', "%{$term}%")
                            ->orWhere('city', 'like', "%{$term}%")
                            ->orWhere('postal_code', 'like', "%{$term}%");
                    })
                    ->orderBy('name')
                    ->limit($limit)
                    ->get()
                    ->map(fn (Location $location) => [
                        'label' => $location->name,
                        'meta' => trim(collect([$location->city, $location->postal_code])->filter()->implode(' | ')),
                        'url' => route('locations.index', ['search' => $location->name]),
                    ])
                    ->all(),
            ];
        }

        if ($request->user()?->hasPermission('contracts.view')) {
            $results[] = [
                'module' => 'Contracts',
                'items' => Contract::query()
                    ->select(['id', 'contract_no', 'status', 'scope'])
                    ->where(function ($builder) use ($term) {
                        $builder
                            ->where('contract_no', 'like', "%{$term}%")
                            ->orWhere('status', 'like', "%{$term}%")
                            ->orWhere('scope', 'like', "%{$term}%");
                    })
                    ->orderByDesc('start_date')
                    ->limit($limit)
                    ->get()
                    ->map(fn (Contract $contract) => [
                        'label' => $contract->contract_no,
                        'meta' => $contract->status,
                        'url' => route('contracts.index', ['search' => $contract->contract_no]),
                    ])
                    ->all(),
            ];
        }

        if ($request->user()?->hasPermission('service_orders.view')) {
            $results[] = [
                'module' => 'Service Orders',
                'items' => ServiceOrder::query()
                    ->select(['id', 'order_no', 'status', 'priority'])
                    ->where(function ($builder) use ($term) {
                        $builder
                            ->where('order_no', 'like', "%{$term}%")
                            ->orWhere('status', 'like', "%{$term}%")
                            ->orWhere('priority', 'like', "%{$term}%");
                    })
                    ->orderByDesc('requested_date')
                    ->limit($limit)
                    ->get()
                    ->map(fn (ServiceOrder $serviceOrder) => [
                        'label' => $serviceOrder->order_no,
                        'meta' => trim(collect([$serviceOrder->status, $serviceOrder->priority])->filter()->implode(' | ')),
                        'url' => route('service-orders.index', ['search' => $serviceOrder->order_no]),
                    ])
                    ->all(),
            ];
        }

        return array_values(array_filter($results, fn (array $group) => $group['items'] !== []));
    }
}