<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Location;
use App\Models\ServiceOrder;
use Illuminate\Http\Request;

class GlobalSearchService
{
    public function __construct(
        private readonly AccessControlService $accessControlService,
    ) {
    }

    public function search(Request $request, string $query, int $limit = 5): array
    {
        $term = trim($query);
        $user = $request->user();

        if ($term === '') {
            return [];
        }

        $results = [];

        if ($request->user()?->hasPermission('clients.view')) {
            $clientQuery = Client::query()
                ->select(['id', 'name', 'code', 'contact_person'])
                ->where(function ($builder) use ($term) {
                    $builder
                        ->where('name', 'like', "%{$term}%")
                        ->orWhere('code', 'like', "%{$term}%")
                        ->orWhere('contact_person', 'like', "%{$term}%");
                })
                ->orderBy('name')
                ->limit($limit);

            $this->accessControlService->scopeClients($clientQuery->getQuery(), $user);

            $results[] = [
                'module' => 'Clients',
                'items' => $clientQuery
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
            $locationQuery = Location::query()
                ->select(['id', 'name', 'city', 'postal_code'])
                ->where(function ($builder) use ($term) {
                    $builder
                        ->where('name', 'like', "%{$term}%")
                        ->orWhere('city', 'like', "%{$term}%")
                        ->orWhere('postal_code', 'like', "%{$term}%");
                })
                ->orderBy('name')
                ->limit($limit);

            $this->accessControlService->scopeLocations($locationQuery->getQuery(), $user);

            $results[] = [
                'module' => 'Locations',
                'items' => $locationQuery
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
            $contractQuery = Contract::query()
                ->select(['id', 'contract_no', 'status', 'scope'])
                ->where(function ($builder) use ($term) {
                    $builder
                        ->where('contract_no', 'like', "%{$term}%")
                        ->orWhere('status', 'like', "%{$term}%")
                        ->orWhere('scope', 'like', "%{$term}%");
                })
                ->orderByDesc('start_date')
                ->limit($limit);

            $this->accessControlService->scopeContracts($contractQuery->getQuery(), $user);

            $results[] = [
                'module' => 'Contracts',
                'items' => $contractQuery
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
            $serviceOrderQuery = ServiceOrder::query()
                ->select(['id', 'order_no', 'status', 'priority'])
                ->where(function ($builder) use ($term) {
                    $builder
                        ->where('order_no', 'like', "%{$term}%")
                        ->orWhere('status', 'like', "%{$term}%")
                        ->orWhere('priority', 'like', "%{$term}%");
                })
                ->orderByDesc('requested_date')
                ->limit($limit);

            $this->accessControlService->scopeServiceOrders($serviceOrderQuery->getQuery(), $user);

            $results[] = [
                'module' => 'Service Orders',
                'items' => $serviceOrderQuery
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