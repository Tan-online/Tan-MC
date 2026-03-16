<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ComplianceReportingService
{
    public function __construct(
        private readonly AccessControlService $accessControlService,
    ) {
    }

    public function dashboardStateComplianceData(?int $month = null, ?int $year = null): array
    {
        $rows = $this->stateComplianceQuery($this->normalizeFilters([
            'month' => $month,
            'year' => $year,
        ]), null)
            ->orderByDesc('total_expected')
            ->limit(10)
            ->get();

        return [
            'labels' => $rows->pluck('state_name')->all(),
            'datasets' => [
                ['label' => 'Received', 'data' => $rows->pluck('received_count')->map(fn ($value) => (int) $value)->all()],
                ['label' => 'Approved', 'data' => $rows->pluck('approved_count')->map(fn ($value) => (int) $value)->all()],
                ['label' => 'Late', 'data' => $rows->pluck('late_count')->map(fn ($value) => (int) $value)->all()],
                ['label' => 'Pending', 'data' => $rows->pluck('pending_count')->map(fn ($value) => (int) $value)->all()],
            ],
        ];
    }

    public function dashboardExecutivePerformanceData(?int $month = null, ?int $year = null): array
    {
        $rows = $this->executivePerformanceQuery($this->normalizeFilters([
            'month' => $month,
            'year' => $year,
        ]), null)
            ->orderByDesc('total_expected')
            ->limit(8)
            ->get();

        return [
            'labels' => $rows->pluck('executive_name')->all(),
            'datasets' => [
                ['label' => 'Received / Approved', 'data' => $rows->map(fn ($row) => (int) $row->received_count + (int) $row->approved_count)->all()],
                ['label' => 'Late', 'data' => $rows->pluck('late_count')->map(fn ($value) => (int) $value)->all()],
            ],
        ];
    }

    public function dashboardMonthlyTrendData(int $months = 6): array
    {
        $end = now()->startOfMonth();
        $start = $end->copy()->subMonths($months - 1);

        $rows = DB::table('muster_expected as me')
            ->join('muster_cycles as mc', 'mc.id', '=', 'me.muster_cycle_id')
            ->selectRaw('mc.year, mc.month, COUNT(*) as total_expected')
            ->selectRaw("SUM(CASE WHEN me.status IN ('Received', 'Approved') THEN 1 ELSE 0 END) as received_count")
            ->selectRaw("SUM(CASE WHEN me.status = 'Late' THEN 1 ELSE 0 END) as late_count")
            ->where(function (Builder $query) use ($start, $end) {
                $query->where('mc.year', '>', $start->year)
                    ->orWhere(function (Builder $innerQuery) use ($start) {
                        $innerQuery->where('mc.year', $start->year)
                            ->where('mc.month', '>=', $start->month);
                    });
            })
            ->where(function (Builder $query) use ($end) {
                $query->where('mc.year', '<', $end->year)
                    ->orWhere(function (Builder $innerQuery) use ($end) {
                        $innerQuery->where('mc.year', $end->year)
                            ->where('mc.month', '<=', $end->month);
                    });
            })
            ->groupBy('mc.year', 'mc.month')
            ->orderBy('mc.year')
            ->orderBy('mc.month')
            ->get()
            ->keyBy(fn ($row) => sprintf('%04d-%02d', $row->year, $row->month));

        $labels = [];
        $expected = [];
        $received = [];
        $late = [];

        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addMonth()) {
            $key = $cursor->format('Y-m');
            $row = $rows->get($key);

            $labels[] = $cursor->format('M Y');
            $expected[] = (int) ($row->total_expected ?? 0);
            $received[] = (int) ($row->received_count ?? 0);
            $late[] = (int) ($row->late_count ?? 0);
        }

        return [
            'labels' => $labels,
            'datasets' => [
                ['label' => 'Expected', 'data' => $expected],
                ['label' => 'Received', 'data' => $received],
                ['label' => 'Late', 'data' => $late],
            ],
        ];
    }

    public function report(string $type, array $filters, User $user, int $perPage = 15): LengthAwarePaginator
    {
        $filters = $this->normalizeFilters($filters);
        abort_unless($this->reportOptions($user)->has($type), 404);

        return match ($type) {
            'uploaded' => $this->musterReportQuery($type, $filters, $user)
                ->orderByDesc('received_at')
                ->orderBy('client_name')
                ->paginate($perPage)
                ->withQueryString(),
            'pending' => $this->musterReportQuery($type, $filters, $user)
                ->orderBy('due_date')
                ->orderBy('client_name')
                ->paginate($perPage)
                ->withQueryString(),
            'history' => $this->musterReportQuery($type, $filters, $user)
                ->orderByDesc('last_action_at')
                ->orderBy('client_name')
                ->paginate($perPage)
                ->withQueryString(),
            'client-compliance' => $this->clientComplianceQuery($filters, $user)
                ->orderByDesc('late_count')
                ->orderBy('client_name')
                ->paginate($perPage)
                ->withQueryString(),
            'state-compliance' => $this->stateComplianceQuery($filters, $user)
                ->orderByDesc('late_count')
                ->orderBy('state_name')
                ->paginate($perPage)
                ->withQueryString(),
            'executive-performance' => $this->executivePerformanceQuery($filters, $user)
                ->orderByDesc('compliance_rate')
                ->orderBy('executive_name')
                ->paginate($perPage)
                ->withQueryString(),
            default => abort(404),
        };
    }

    public function reportExportRows(string $type, array $filters, User $user): array
    {
        abort_unless($this->reportOptions($user)->has($type), 404);

        $rows = match ($type) {
            'uploaded' => $this->musterReportQuery($type, $this->normalizeFilters($filters), $user)->orderByDesc('received_at')->get(),
            'pending' => $this->musterReportQuery($type, $this->normalizeFilters($filters), $user)->orderBy('due_date')->get(),
            'history' => $this->musterReportQuery($type, $this->normalizeFilters($filters), $user)->orderByDesc('last_action_at')->get(),
            'client-compliance' => $this->clientComplianceQuery($this->normalizeFilters($filters), $user)->orderBy('client_name')->get(),
            'state-compliance' => $this->stateComplianceQuery($this->normalizeFilters($filters), $user)->orderBy('state_name')->get(),
            'executive-performance' => $this->executivePerformanceQuery($this->normalizeFilters($filters), $user)->orderBy('executive_name')->get(),
            default => collect(),
        };

        $columns = $this->reportColumns($type);

        return [
            'headings' => array_values($columns),
            'rows' => $rows->map(fn ($row) => $this->mapExportRow($columns, $row))->all(),
            'title' => $this->title($type),
        ];
    }

    public function reportOptions(?User $user = null): Collection
    {
        $roleKey = $this->accessControlService->roleKey($user);

        return collect($this->definitions())
            ->filter(fn (array $definition) => in_array($roleKey, $definition['roles'], true))
            ->mapWithKeys(fn (array $definition, string $key) => [$key => $definition['label']]);
    }

    public function reportColumns(string $type): array
    {
        return $this->definitions()[$type]['columns'] ?? [];
    }

    public function filterOptions(User $user, array $filters): array
    {
        $clientsQuery = Client::query()->where('is_active', true)->orderBy('name');
        $this->accessControlService->scopeClients($clientsQuery, $user);

        $contractsQuery = Contract::query()
            ->where('status', '!=', 'Closed')
            ->orderBy('contract_no');
        $this->accessControlService->scopeContracts($contractsQuery, $user);

        if (($filters['client_id'] ?? 0) > 0) {
            $contractsQuery->where('client_id', $filters['client_id']);
        }

        return [
            'clients' => $clientsQuery->get(['id', 'name']),
            'contracts' => $contractsQuery->get(['id', 'client_id', 'contract_no']),
            'statuses' => ['Pending', 'Received', 'Late', 'Approved', 'Returned', 'Closed'],
        ];
    }

    private function normalizeFilters(array $filters): array
    {
        return [
            'month' => max(1, min(12, (int) ($filters['month'] ?? now()->month))),
            'year' => (int) ($filters['year'] ?? now()->year),
            'search' => trim((string) ($filters['search'] ?? '')),
            'client_id' => (int) ($filters['client_id'] ?? 0),
            'contract_id' => (int) ($filters['contract_id'] ?? 0),
            'status' => trim((string) ($filters['status'] ?? '')),
        ];
    }

    private function baseComplianceQuery(array $filters, ?User $user): Builder
    {
        $query = DB::table('muster_expected as me')
            ->join('muster_cycles as mc', 'mc.id', '=', 'me.muster_cycle_id')
            ->join('contracts as c', 'c.id', '=', 'me.contract_id')
            ->join('clients as cl', 'cl.id', '=', 'c.client_id')
            ->join('locations as l', 'l.id', '=', 'me.location_id')
            ->join('states as s', 's.id', '=', 'l.state_id')
            ->leftJoin('executive_mappings as em', 'em.id', '=', 'me.executive_mapping_id')
            ->leftJoin('users as u', 'u.id', '=', 'em.executive_user_id')
            ->where('mc.month', $filters['month'])
            ->where('mc.year', $filters['year'])
            ->when(($filters['client_id'] ?? 0) > 0, fn (Builder $builder) => $builder->where('c.client_id', $filters['client_id']))
            ->when(($filters['contract_id'] ?? 0) > 0, fn (Builder $builder) => $builder->where('c.id', $filters['contract_id']))
            ->when(($filters['status'] ?? '') !== '', fn (Builder $builder) => $builder->where('me.status', $filters['status']));

        if ($user) {
            $this->accessControlService->scopeMusterExpected($query, $user, 'me');
        }

        return $query;
    }

    private function clientComplianceQuery(array $filters, ?User $user): Builder
    {
        return $this->baseComplianceQuery($filters, $user)
            ->selectRaw('cl.id as client_id, cl.name as client_name')
            ->selectRaw('COUNT(*) as total_expected')
            ->selectRaw("SUM(CASE WHEN me.status = 'Received' THEN 1 ELSE 0 END) as received_count")
            ->selectRaw("SUM(CASE WHEN me.status = 'Approved' THEN 1 ELSE 0 END) as approved_count")
            ->selectRaw("SUM(CASE WHEN me.status = 'Pending' THEN 1 ELSE 0 END) as pending_count")
            ->selectRaw("SUM(CASE WHEN me.status = 'Late' THEN 1 ELSE 0 END) as late_count")
            ->selectRaw("SUM(CASE WHEN me.status = 'Returned' THEN 1 ELSE 0 END) as returned_count")
            ->selectRaw("ROUND((SUM(CASE WHEN me.status IN ('Received', 'Approved') THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0)) * 100, 2) as compliance_rate")
            ->when(($filters['search'] ?? '') !== '', fn (Builder $builder) => $builder->where('cl.name', 'like', '%' . $filters['search'] . '%'))
            ->groupBy('cl.id', 'cl.name');
    }

    private function stateComplianceQuery(array $filters, ?User $user): Builder
    {
        return $this->baseComplianceQuery($filters, $user)
            ->selectRaw('s.id as state_id, s.name as state_name')
            ->selectRaw('COUNT(*) as total_expected')
            ->selectRaw("SUM(CASE WHEN me.status = 'Received' THEN 1 ELSE 0 END) as received_count")
            ->selectRaw("SUM(CASE WHEN me.status = 'Approved' THEN 1 ELSE 0 END) as approved_count")
            ->selectRaw("SUM(CASE WHEN me.status = 'Pending' THEN 1 ELSE 0 END) as pending_count")
            ->selectRaw("SUM(CASE WHEN me.status = 'Late' THEN 1 ELSE 0 END) as late_count")
            ->selectRaw("SUM(CASE WHEN me.status = 'Returned' THEN 1 ELSE 0 END) as returned_count")
            ->selectRaw("ROUND((SUM(CASE WHEN me.status IN ('Received', 'Approved') THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0)) * 100, 2) as compliance_rate")
            ->when(($filters['search'] ?? '') !== '', fn (Builder $builder) => $builder->where('s.name', 'like', '%' . $filters['search'] . '%'))
            ->groupBy('s.id', 's.name');
    }

    private function executivePerformanceQuery(array $filters, ?User $user): Builder
    {
        return $this->baseComplianceQuery($filters, $user)
            ->selectRaw("COALESCE(u.id, 0) as executive_id")
            ->selectRaw("COALESCE(u.name, em.executive_name, 'Unassigned') as executive_name")
            ->selectRaw('COUNT(*) as total_expected')
            ->selectRaw("SUM(CASE WHEN me.status = 'Received' THEN 1 ELSE 0 END) as received_count")
            ->selectRaw("SUM(CASE WHEN me.status = 'Approved' THEN 1 ELSE 0 END) as approved_count")
            ->selectRaw("SUM(CASE WHEN me.status = 'Pending' THEN 1 ELSE 0 END) as pending_count")
            ->selectRaw("SUM(CASE WHEN me.status = 'Late' THEN 1 ELSE 0 END) as late_count")
            ->selectRaw("SUM(CASE WHEN me.status = 'Returned' THEN 1 ELSE 0 END) as returned_count")
            ->selectRaw("ROUND((SUM(CASE WHEN me.status IN ('Received', 'Approved') THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0)) * 100, 2) as compliance_rate")
            ->when(($filters['search'] ?? '') !== '', function (Builder $builder) use ($filters) {
                $builder->where(function (Builder $searchQuery) use ($filters) {
                    $searchQuery
                        ->where('u.name', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('em.executive_name', 'like', '%' . $filters['search'] . '%');
                });
            })
            ->groupByRaw("COALESCE(u.id, 0), COALESCE(u.name, em.executive_name, 'Unassigned')");
    }

    private function musterReportQuery(string $type, array $filters, User $user): Builder
    {
        $query = DB::table('muster_expected as me')
            ->join('muster_cycles as mc', 'mc.id', '=', 'me.muster_cycle_id')
            ->join('contracts as c', 'c.id', '=', 'me.contract_id')
            ->join('clients as cl', 'cl.id', '=', 'c.client_id')
            ->join('locations as l', 'l.id', '=', 'me.location_id')
            ->leftJoin('executive_mappings as em', 'em.id', '=', 'me.executive_mapping_id')
            ->leftJoin('users as u', 'u.id', '=', 'em.executive_user_id')
            ->leftJoin('users as acted_by', 'acted_by.id', '=', 'me.acted_by_user_id')
            ->where('mc.month', $filters['month'])
            ->where('mc.year', $filters['year'])
            ->when(($filters['client_id'] ?? 0) > 0, fn (Builder $builder) => $builder->where('c.client_id', $filters['client_id']))
            ->when(($filters['contract_id'] ?? 0) > 0, fn (Builder $builder) => $builder->where('c.id', $filters['contract_id']))
            ->when(($filters['status'] ?? '') !== '', fn (Builder $builder) => $builder->where('me.status', $filters['status']))
            ->when(($filters['search'] ?? '') !== '', function (Builder $builder) use ($filters) {
                $search = $filters['search'];

                $builder->where(function (Builder $searchQuery) use ($search) {
                    $searchQuery
                        ->where('cl.name', 'like', '%' . $search . '%')
                        ->orWhere('c.contract_no', 'like', '%' . $search . '%')
                        ->orWhere('l.name', 'like', '%' . $search . '%')
                        ->orWhere('u.name', 'like', '%' . $search . '%')
                        ->orWhere('em.executive_name', 'like', '%' . $search . '%')
                        ->orWhere('me.status', 'like', '%' . $search . '%');
                });
            })
            ->selectRaw('me.id')
            ->selectRaw('cl.name as client_name')
            ->selectRaw('c.contract_no')
            ->selectRaw('l.name as location_name')
            ->selectRaw("COALESCE(u.name, em.executive_name, 'Unassigned') as executive_name")
            ->selectRaw('mc.cycle_label')
            ->selectRaw('mc.due_date')
            ->selectRaw('me.status')
            ->selectRaw('me.received_via')
            ->selectRaw('me.received_at')
            ->selectRaw('me.last_action_at')
            ->selectRaw("COALESCE(acted_by.name, 'System') as acted_by_name");

        $this->accessControlService->scopeMusterExpected($query, $user, 'me');

        return match ($type) {
            'uploaded' => $query->whereIn('me.status', ['Received', 'Late', 'Approved', 'Returned', 'Closed']),
            'pending' => $query->where('me.status', 'Pending'),
            default => $query,
        };
    }

    private function mapExportRow(array $columns, object $row): array
    {
        return collect(array_keys($columns))
            ->map(function (string $column) use ($row) {
                $value = $row->{$column} ?? null;

                if ($value instanceof \DateTimeInterface) {
                    return $value->format('Y-m-d H:i');
                }

                return $value;
            })
            ->all();
    }

    private function title(string $type): string
    {
        return $this->definitions()[$type]['label'] ?? 'Compliance Report';
    }

    private function definitions(): array
    {
        return [
            'uploaded' => [
                'label' => 'Uploaded Muster Roll',
                'roles' => ['super_admin', 'admin', 'operations', 'reviewer'],
                'columns' => [
                    'client_name' => 'Client',
                    'contract_no' => 'Contract',
                    'location_name' => 'Location',
                    'executive_name' => 'Executive',
                    'cycle_label' => 'Cycle',
                    'status' => 'Status',
                    'received_via' => 'Received Via',
                    'received_at' => 'Uploaded At',
                    'acted_by_name' => 'Last Action By',
                ],
            ],
            'pending' => [
                'label' => 'Pending Muster Roll',
                'roles' => ['super_admin', 'admin', 'operations', 'reviewer'],
                'columns' => [
                    'client_name' => 'Client',
                    'contract_no' => 'Contract',
                    'location_name' => 'Location',
                    'executive_name' => 'Executive',
                    'cycle_label' => 'Cycle',
                    'due_date' => 'Due Date',
                    'status' => 'Status',
                ],
            ],
            'history' => [
                'label' => 'Muster Roll History',
                'roles' => ['super_admin', 'admin', 'operations', 'reviewer'],
                'columns' => [
                    'client_name' => 'Client',
                    'contract_no' => 'Contract',
                    'location_name' => 'Location',
                    'executive_name' => 'Executive',
                    'cycle_label' => 'Cycle',
                    'status' => 'Status',
                    'received_via' => 'Received Via',
                    'last_action_at' => 'Last Action At',
                    'acted_by_name' => 'Last Action By',
                ],
            ],
            'client-compliance' => [
                'label' => 'Client Compliance Report',
                'roles' => ['super_admin', 'admin'],
                'columns' => [
                    'client_name' => 'Client',
                    'total_expected' => 'Total Expected',
                    'received_count' => 'Received',
                    'approved_count' => 'Approved',
                    'pending_count' => 'Pending',
                    'late_count' => 'Late',
                    'returned_count' => 'Returned',
                    'compliance_rate' => 'Compliance %',
                ],
            ],
            'state-compliance' => [
                'label' => 'State Compliance Report',
                'roles' => ['super_admin', 'admin'],
                'columns' => [
                    'state_name' => 'State',
                    'total_expected' => 'Total Expected',
                    'received_count' => 'Received',
                    'approved_count' => 'Approved',
                    'pending_count' => 'Pending',
                    'late_count' => 'Late',
                    'returned_count' => 'Returned',
                    'compliance_rate' => 'Compliance %',
                ],
            ],
            'executive-performance' => [
                'label' => 'Executive Performance Report',
                'roles' => ['super_admin', 'admin'],
                'columns' => [
                    'executive_name' => 'Executive',
                    'total_expected' => 'Total Expected',
                    'received_count' => 'Received',
                    'approved_count' => 'Approved',
                    'pending_count' => 'Pending',
                    'late_count' => 'Late',
                    'returned_count' => 'Returned',
                    'compliance_rate' => 'Compliance %',
                ],
            ],
        ];
    }
}
