<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Location;
use App\Models\MusterExpected;
use App\Models\MusterReceived;
use App\Models\ServiceOrder;
use App\Models\Team;
use App\Models\User;
use App\Services\ComplianceReportingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function __invoke(ComplianceReportingService $complianceReportingService)
    {
        $user = request()->user();
        $dashboardRole = $user?->dashboardRole() ?? 'viewer';

        $view = match ($dashboardRole) {
            'super_admin' => 'dashboard.super_admin',
            'admin' => 'dashboard.admin',
            'operations' => 'dashboard.operations',
            'reviewer' => 'dashboard.reviewer',
            default => 'dashboard.viewer',
        };

        $data = match ($dashboardRole) {
            'super_admin' => $this->superAdminData(),
            'admin' => $this->adminData($complianceReportingService),
            'operations' => $this->operationsData(),
            'reviewer' => $this->reviewerData(),
            default => $this->viewerData(),
        };

        return view($view, array_merge($data, [
            'dashboardRole' => $dashboardRole,
        ]));
    }

    private function superAdminData(): array
    {
        return [
            'totalClients' => Client::query()->count(),
            'totalLocations' => Location::query()->count(),
            'totalContracts' => Contract::query()->count(),
            'totalUsers' => User::query()->count(),
            'recentActivityLog' => $this->recentActivityLog(),
            'systemUsageOverview' => [
                'labels' => ['Clients', 'Locations', 'Contracts', 'Users', 'Service Orders'],
                'datasets' => [
                    [
                        'label' => 'Records',
                        'data' => [
                            Client::query()->count(),
                            Location::query()->count(),
                            Contract::query()->count(),
                            User::query()->count(),
                            ServiceOrder::query()->count(),
                        ],
                    ],
                ],
            ],
        ];
    }

    private function adminData(ComplianceReportingService $complianceReportingService): array
    {
        return [
            'totalClients' => Client::query()->count(),
            'totalContracts' => Contract::query()->count(),
            'totalServiceOrders' => ServiceOrder::query()->count(),
            'pendingReviewsCount' => $this->pendingDispatchCount(),
            'stateComplianceChart' => $this->hasMusterTables()
                ? $complianceReportingService->dashboardStateComplianceData()
                : ['labels' => [], 'datasets' => []],
            'recentServiceOrders' => ServiceOrder::query()
                ->with(['contract.client:id,name', 'location:id,name,city', 'team:id,name'])
                ->latest('requested_date')
                ->limit(6)
                ->get(),
            'lateAlerts' => $this->lateAlerts(),
        ];
    }

    private function operationsData(): array
    {
        return [
            'pendingDispatchCount' => $this->pendingDispatchCount(),
            'todayMusterSubmissionCount' => $this->todayMusterSubmissionCount(),
            'activeLocationCount' => $this->activeLocationCount(),
            'activeTeamsCount' => Team::query()->where('is_active', true)->count(),
            'locationActivities' => $this->locationActivities(),
            'teamStatusOverview' => [
                'labels' => ['Active Teams', 'Inactive Teams'],
                'datasets' => [
                    [
                        'label' => 'Teams',
                        'data' => [
                            Team::query()->where('is_active', true)->count(),
                            Team::query()->where('is_active', false)->count(),
                        ],
                    ],
                ],
            ],
            'teamStatuses' => Team::query()
                ->with(['department:id,name', 'operationArea:id,name'])
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->limit(8)
                ->get(),
        ];
    }

    private function reviewerData(): array
    {
        return [
            'pendingApprovalsCount' => $this->pendingApprovalsCount(),
            'recentlyApprovedCount' => $this->recentlyApprovedCount(),
            'escalatedItemsCount' => $this->escalatedItemsCount(),
            'pendingApprovals' => $this->pendingApprovals(),
            'recentApprovals' => $this->recentApprovals(),
            'escalatedItems' => $this->escalatedItems(),
        ];
    }

    private function viewerData(): array
    {
        return [
            'totalClients' => Client::query()->count(),
            'totalLocations' => Location::query()->count(),
            'totalContracts' => Contract::query()->count(),
            'recentServiceOrders' => ServiceOrder::query()
                ->with(['contract.client:id,name', 'location:id,name'])
                ->latest('requested_date')
                ->limit(5)
                ->get(),
        ];
    }

    private function recentActivityLog(): Collection
    {
        $activities = collect();

        $activities = $activities->concat(
            Client::query()
                ->latest()
                ->limit(3)
                ->get(['id', 'name', 'created_at'])
                ->map(fn (Client $client) => [
                    'module' => 'Client Master',
                    'label' => $client->name,
                    'action' => 'New client added',
                    'occurred_at' => $client->created_at,
                ])
        );

        $activities = $activities->concat(
            Location::query()
                ->latest()
                ->limit(3)
                ->get(['id', 'name', 'city', 'created_at'])
                ->map(fn (Location $location) => [
                    'module' => 'Location Master',
                    'label' => trim($location->name.' '.$location->city),
                    'action' => 'Location onboarded',
                    'occurred_at' => $location->created_at,
                ])
        );

        $activities = $activities->concat(
            Contract::query()
                ->latest()
                ->limit(3)
                ->get(['id', 'contract_no', 'created_at'])
                ->map(fn (Contract $contract) => [
                    'module' => 'Contracts',
                    'label' => $contract->contract_no,
                    'action' => 'Contract activated',
                    'occurred_at' => $contract->created_at,
                ])
        );

        $activities = $activities->concat(
            User::query()
                ->latest()
                ->limit(3)
                ->get(['id', 'name', 'employee_code', 'created_at'])
                ->map(fn (User $record) => [
                    'module' => 'User Access',
                    'label' => $record->name.' ('.$record->employee_code.')',
                    'action' => 'User provisioned',
                    'occurred_at' => $record->created_at,
                ])
        );

        return $activities
            ->filter(fn (array $activity) => $activity['occurred_at'] !== null)
            ->sortByDesc('occurred_at')
            ->take(8)
            ->values();
    }

    private function lateAlerts(): Collection
    {
        if (! $this->hasMusterTables()) {
            return collect();
        }

        return MusterExpected::query()
            ->with(['contract:id,contract_no', 'location:id,name,city'])
            ->where('status', 'Late')
            ->latest('updated_at')
            ->limit(5)
            ->get();
    }

    private function locationActivities(): Collection
    {
        if (! $this->hasMusterTables()) {
            return collect();
        }

        return MusterExpected::query()
            ->with([
                'location:id,name,city',
                'contract:id,contract_no',
                'musterCycle:id,month,year,cycle_label',
            ])
            ->whereNotNull('last_action_at')
            ->latest('last_action_at')
            ->limit(8)
            ->get();
    }

    private function pendingApprovals(): Collection
    {
        if (! $this->hasMusterTables()) {
            return collect();
        }

        return MusterExpected::query()
            ->with(['location:id,name,city', 'contract:id,contract_no'])
            ->whereIn('status', ['Received', 'Late'])
            ->latest('updated_at')
            ->limit(8)
            ->get();
    }

    private function recentApprovals(): Collection
    {
        if (! $this->hasMusterTables()) {
            return collect();
        }

        return MusterExpected::query()
            ->with(['location:id,name,city', 'contract:id,contract_no', 'actedBy:id,name'])
            ->where('status', 'Approved')
            ->latest('approved_at')
            ->limit(8)
            ->get();
    }

    private function escalatedItems(): Collection
    {
        if (! $this->hasMusterTables()) {
            return collect();
        }

        return MusterExpected::query()
            ->with(['location:id,name,city', 'contract:id,contract_no'])
            ->whereIn('status', ['Late', 'Returned'])
            ->latest('updated_at')
            ->limit(8)
            ->get();
    }

    private function pendingApprovalsCount(): int
    {
        if (! $this->hasMusterTables()) {
            return 0;
        }

        return MusterExpected::query()
            ->whereIn('status', ['Received', 'Late'])
            ->count();
    }

    private function recentlyApprovedCount(): int
    {
        if (! $this->hasMusterTables()) {
            return 0;
        }

        return MusterExpected::query()
            ->where('status', 'Approved')
            ->whereDate('approved_at', '>=', now()->subDays(7)->toDateString())
            ->count();
    }

    private function escalatedItemsCount(): int
    {
        if (! $this->hasMusterTables()) {
            return 0;
        }

        return MusterExpected::query()
            ->whereIn('status', ['Late', 'Returned'])
            ->count();
    }

    private function activeLocationCount(): int
    {
        if (! $this->hasMusterTables()) {
            return Location::query()->count();
        }

        return MusterExpected::query()
            ->whereDate('updated_at', now()->toDateString())
            ->distinct('location_id')
            ->count('location_id');
    }

    private function todayMusterSubmissionCount(): int
    {
        if (! Schema::hasTable('muster_received')) {
            return 0;
        }

        return MusterReceived::query()
            ->whereDate('created_at', now()->toDateString())
            ->count();
    }

    private function pendingDispatchCount(): int
    {
        if (! Schema::hasTable('dispatch_entries')) {
            return 0;
        }

        return DB::table('dispatch_entries')
            ->where('status', 'pending')
            ->count();
    }

    private function hasMusterTables(): bool
    {
        return Schema::hasTable('muster_expected') && Schema::hasTable('muster_cycles');
    }
}
