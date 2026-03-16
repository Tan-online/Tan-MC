<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Contract;
use App\Models\DispatchEntry;
use App\Models\Location;
use App\Models\MusterExpected;
use App\Models\MusterReceived;
use App\Models\Permission;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Services\ComplianceReportingService;
use App\Services\DashboardStatsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function __invoke(ComplianceReportingService $complianceReportingService, DashboardStatsService $dashboardStatsService)
    {
        $user = request()->user();
        $dashboardRole = $user?->dashboardRole() ?? 'viewer';
        $dashboardStats = $dashboardStatsService->summary();

        $view = match ($dashboardRole) {
            'super_admin' => 'dashboard.super_admin',
            'admin' => 'dashboard.admin',
            'operations' => 'dashboard.operations',
            'reviewer' => 'dashboard.reviewer',
            default => 'dashboard.viewer',
        };

        $data = Cache::remember("dashboard:{$dashboardRole}:" . ($user?->id ?? 0), 300, function () use ($dashboardRole, $complianceReportingService, $dashboardStats, $user) {
            return match ($dashboardRole) {
                'super_admin' => $this->superAdminData($dashboardStats),
                'admin' => $this->adminData($complianceReportingService, $dashboardStats),
                'operations' => $this->operationsData($user),
                'reviewer' => $this->reviewerData(),
                default => $this->viewerData($dashboardStats),
            };
        });

        return view($view, array_merge($data, [
            'dashboardRole' => $dashboardRole,
        ]));
    }

    private function superAdminData(array $dashboardStats): array
    {
        $totalClients = $dashboardStats['clients'];
        $totalLocations = $dashboardStats['locations'];
        $totalContracts = $dashboardStats['contracts'];
        $totalUsers = $dashboardStats['users'];
        $totalServiceOrders = $dashboardStats['service_orders'];

        return [
            'totalClients' => $totalClients,
            'totalLocations' => $totalLocations,
            'totalContracts' => $totalContracts,
            'totalUsers' => $totalUsers,
            'recentActivityLog' => $this->recentActivityLog(),
            'systemHealth' => [
                'activeUsers' => User::query()->where('status', 'Active')->count(),
                'pendingDispatch' => $this->pendingDispatchCount(),
                'pendingApprovals' => $this->pendingApprovalsCount(),
                'definedPermissions' => Schema::hasTable('permissions') ? Permission::query()->count() : 0,
            ],
            'systemUsageOverview' => [
                'labels' => ['Clients', 'Locations', 'Contracts', 'Users', 'Service Orders'],
                'datasets' => [
                    [
                        'label' => 'Records',
                        'data' => [
                            $totalClients,
                            $totalLocations,
                            $totalContracts,
                            $totalUsers,
                            $totalServiceOrders,
                        ],
                    ],
                ],
            ],
        ];
    }

    private function adminData(ComplianceReportingService $complianceReportingService, array $dashboardStats): array
    {
        $totalClients = $dashboardStats['clients'];
        $totalLocations = $dashboardStats['locations'];
        $totalContracts = $dashboardStats['contracts'];
        $totalServiceOrders = $dashboardStats['service_orders'];

        return [
            'totalClients' => $totalClients,
            'totalLocations' => $totalLocations,
            'totalContracts' => $totalContracts,
            'totalServiceOrders' => $totalServiceOrders,
            'pendingReviewsCount' => $this->pendingApprovalsCount(),
            'contractStatusSummary' => Contract::query()
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
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

    private function operationsData(User $user): array
    {
        $serviceOrderCountQuery = ServiceOrder::query();
        $this->accessControl()->scopeServiceOrders($serviceOrderCountQuery, $user);

        $assignedContractCountQuery = Contract::query();
        $this->accessControl()->scopeContracts($assignedContractCountQuery, $user);

        $recentServiceOrdersQuery = ServiceOrder::query()
            ->with(['contract.client:id,name', 'location:id,name,city', 'team:id,name']);
        $this->accessControl()->scopeServiceOrders($recentServiceOrdersQuery, $user);

        return [
            'pendingDispatchCount' => $this->pendingDispatchCount($user),
            'todayMusterSubmissionCount' => $this->todayMusterSubmissionCount($user),
            'activeLocationCount' => $this->activeLocationCount($user),
            'visibleServiceOrderCount' => $serviceOrderCountQuery->count(),
            'assignedContractCount' => $assignedContractCountQuery->count(),
            'locationActivities' => $this->locationActivities($user),
            'recentServiceOrders' => $recentServiceOrdersQuery
                ->latest('requested_date')
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

    private function viewerData(array $dashboardStats): array
    {
        $totalClients = $dashboardStats['clients'];
        $totalLocations = $dashboardStats['locations'];
        $totalContracts = $dashboardStats['contracts'];

        return [
            'totalClients' => $totalClients,
            'totalLocations' => $totalLocations,
            'totalContracts' => $totalContracts,
            'recentServiceOrders' => ServiceOrder::query()
                ->with(['contract.client:id,name', 'location:id,name'])
                ->latest('requested_date')
                ->limit(5)
                ->get(),
        ];
    }

    private function recentActivityLog(): Collection
    {
        if (! Schema::hasTable('activity_logs')) {
            return collect();
        }

        return ActivityLog::query()
            ->with('user:id,name,employee_code')
            ->latest('created_at')
            ->limit(8)
            ->get()
            ->map(fn (ActivityLog $activity) => [
                'module' => str($activity->module)->replace('_', ' ')->title()->toString(),
                'label' => $activity->user?->name
                    ? $activity->user->name.' ('.$activity->user->employee_code.')'
                    : 'System',
                'action' => $activity->description ?: str($activity->action)->replace('_', ' ')->title()->toString(),
                'occurred_at' => $activity->created_at,
            ]);
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

    private function locationActivities(?User $user = null): Collection
    {
        if (! $this->hasMusterTables()) {
            return collect();
        }

        $query = MusterExpected::query()
            ->with([
                'location:id,name,city',
                'contract:id,contract_no',
                'musterCycle:id,month,year,cycle_label',
            ])
            ->whereNotNull('last_action_at')
            ->latest('last_action_at');

        if ($user) {
            $this->accessControl()->scopeMusterExpected($query, $user);
        }

        return $query->limit(8)->get();
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

    private function activeLocationCount(?User $user = null): int
    {
        if (! $this->hasMusterTables()) {
            return Location::query()->count();
        }

        $query = MusterExpected::query()
            ->whereDate('updated_at', now()->toDateString())
            ->distinct('location_id');

        if ($user) {
            $this->accessControl()->scopeMusterExpected($query, $user);
        }

        return $query->count('location_id');
    }

    private function todayMusterSubmissionCount(?User $user = null): int
    {
        if (! Schema::hasTable('muster_received')) {
            return 0;
        }

        return MusterReceived::query()
            ->whereDate('created_at', now()->toDateString())
            ->when($user !== null, function ($query) use ($user) {
                $query->whereHas('musterExpected', function ($musterQuery) use ($user) {
                    $this->accessControl()->scopeMusterExpected($musterQuery, $user);
                });
            })
            ->count();
    }

    private function pendingDispatchCount(?User $user = null): int
    {
        if (! Schema::hasTable('dispatch_entries')) {
            return 0;
        }

        $query = DispatchEntry::query()->where('status', 'pending');

        if ($user) {
            $this->accessControl()->scopeDispatchEntries($query, $user);
        }

        return $query->count();
    }

    private function hasMusterTables(): bool
    {
        return Schema::hasTable('muster_expected') && Schema::hasTable('muster_cycles');
    }
}
