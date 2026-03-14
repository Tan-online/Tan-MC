<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ComplianceReportingService
{
    public function dashboardStateComplianceData(?int $month = null, ?int $year = null): array
    {
        $rows = $this->stateComplianceQuery($this->normalizeFilters([
            'month' => $month,
            'year' => $year,
        ]))
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
        ]))
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

    public function report(string $type, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $filters = $this->normalizeFilters($filters);

        return match ($type) {
            'client-compliance' => $this->clientComplianceQuery($filters)
                ->orderByDesc('late_count')
                ->orderBy('client_name')
                ->paginate($perPage)
                ->withQueryString(),
            'state-compliance' => $this->stateComplianceQuery($filters)
                ->orderByDesc('late_count')
                ->orderBy('state_name')
                ->paginate($perPage)
                ->withQueryString(),
            'executive-performance' => $this->executivePerformanceQuery($filters)
                ->orderByDesc('compliance_rate')
                ->orderBy('executive_name')
                ->paginate($perPage)
                ->withQueryString(),
            default => abort(404),
        };
    }

    public function reportExportRows(string $type, array $filters): array
    {
        $rows = match ($type) {
            'client-compliance' => $this->clientComplianceQuery($this->normalizeFilters($filters))->orderBy('client_name')->get(),
            'state-compliance' => $this->stateComplianceQuery($this->normalizeFilters($filters))->orderBy('state_name')->get(),
            'executive-performance' => $this->executivePerformanceQuery($this->normalizeFilters($filters))->orderBy('executive_name')->get(),
            default => collect(),
        };

        return [
            'headings' => $this->headings($type),
            'rows' => $rows->map(fn ($row) => $this->mapExportRow($type, $row))->all(),
            'title' => $this->title($type),
        ];
    }

    public function reportOptions(): array
    {
        return [
            'client-compliance' => 'Client Compliance Report',
            'state-compliance' => 'State Compliance Report',
            'executive-performance' => 'Executive Performance Report',
        ];
    }

    private function normalizeFilters(array $filters): array
    {
        return [
            'month' => max(1, min(12, (int) ($filters['month'] ?? now()->month))),
            'year' => (int) ($filters['year'] ?? now()->year),
        ];
    }

    private function baseComplianceQuery(array $filters): Builder
    {
        return DB::table('muster_expected as me')
            ->join('muster_cycles as mc', 'mc.id', '=', 'me.muster_cycle_id')
            ->join('contracts as c', 'c.id', '=', 'me.contract_id')
            ->join('clients as cl', 'cl.id', '=', 'c.client_id')
            ->join('locations as l', 'l.id', '=', 'me.location_id')
            ->join('states as s', 's.id', '=', 'l.state_id')
            ->leftJoin('executive_mappings as em', 'em.id', '=', 'me.executive_mapping_id')
            ->leftJoin('users as u', 'u.id', '=', 'em.executive_user_id')
            ->where('mc.month', $filters['month'])
            ->where('mc.year', $filters['year']);
    }

    private function clientComplianceQuery(array $filters): Builder
    {
        return $this->baseComplianceQuery($filters)
            ->selectRaw('cl.id as client_id, cl.name as client_name')
            ->selectRaw('COUNT(*) as total_expected')
            ->selectRaw("SUM(CASE WHEN me.status = 'Received' THEN 1 ELSE 0 END) as received_count")
            ->selectRaw("SUM(CASE WHEN me.status = 'Approved' THEN 1 ELSE 0 END) as approved_count")
            ->selectRaw("SUM(CASE WHEN me.status = 'Pending' THEN 1 ELSE 0 END) as pending_count")
            ->selectRaw("SUM(CASE WHEN me.status = 'Late' THEN 1 ELSE 0 END) as late_count")
            ->selectRaw("SUM(CASE WHEN me.status = 'Returned' THEN 1 ELSE 0 END) as returned_count")
            ->selectRaw("ROUND((SUM(CASE WHEN me.status IN ('Received', 'Approved') THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0)) * 100, 2) as compliance_rate")
            ->groupBy('cl.id', 'cl.name');
    }

    private function stateComplianceQuery(array $filters): Builder
    {
        return $this->baseComplianceQuery($filters)
            ->selectRaw('s.id as state_id, s.name as state_name')
            ->selectRaw('COUNT(*) as total_expected')
            ->selectRaw("SUM(CASE WHEN me.status = 'Received' THEN 1 ELSE 0 END) as received_count")
            ->selectRaw("SUM(CASE WHEN me.status = 'Approved' THEN 1 ELSE 0 END) as approved_count")
            ->selectRaw("SUM(CASE WHEN me.status = 'Pending' THEN 1 ELSE 0 END) as pending_count")
            ->selectRaw("SUM(CASE WHEN me.status = 'Late' THEN 1 ELSE 0 END) as late_count")
            ->selectRaw("SUM(CASE WHEN me.status = 'Returned' THEN 1 ELSE 0 END) as returned_count")
            ->selectRaw("ROUND((SUM(CASE WHEN me.status IN ('Received', 'Approved') THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0)) * 100, 2) as compliance_rate")
            ->groupBy('s.id', 's.name');
    }

    private function executivePerformanceQuery(array $filters): Builder
    {
        return $this->baseComplianceQuery($filters)
            ->selectRaw("COALESCE(u.id, 0) as executive_id")
            ->selectRaw("COALESCE(u.name, em.executive_name, 'Unassigned') as executive_name")
            ->selectRaw('COUNT(*) as total_expected')
            ->selectRaw("SUM(CASE WHEN me.status = 'Received' THEN 1 ELSE 0 END) as received_count")
            ->selectRaw("SUM(CASE WHEN me.status = 'Approved' THEN 1 ELSE 0 END) as approved_count")
            ->selectRaw("SUM(CASE WHEN me.status = 'Pending' THEN 1 ELSE 0 END) as pending_count")
            ->selectRaw("SUM(CASE WHEN me.status = 'Late' THEN 1 ELSE 0 END) as late_count")
            ->selectRaw("SUM(CASE WHEN me.status = 'Returned' THEN 1 ELSE 0 END) as returned_count")
            ->selectRaw("ROUND((SUM(CASE WHEN me.status IN ('Received', 'Approved') THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0)) * 100, 2) as compliance_rate")
            ->groupByRaw("COALESCE(u.id, 0), COALESCE(u.name, em.executive_name, 'Unassigned')");
    }

    private function headings(string $type): array
    {
        $label = match ($type) {
            'client-compliance' => 'Client',
            'state-compliance' => 'State',
            'executive-performance' => 'Executive',
            default => 'Label',
        };

        return [$label, 'Total Expected', 'Received', 'Approved', 'Pending', 'Late', 'Returned', 'Compliance Rate %'];
    }

    private function mapExportRow(string $type, object $row): array
    {
        $label = match ($type) {
            'client-compliance' => $row->client_name,
            'state-compliance' => $row->state_name,
            'executive-performance' => $row->executive_name,
            default => 'N/A',
        };

        return [
            $label,
            (int) $row->total_expected,
            (int) $row->received_count,
            (int) $row->approved_count,
            (int) $row->pending_count,
            (int) $row->late_count,
            (int) $row->returned_count,
            (float) $row->compliance_rate,
        ];
    }

    private function title(string $type): string
    {
        return $this->reportOptions()[$type] ?? 'Compliance Report';
    }
}
