<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\ExecutiveMapping;
use App\Models\MusterCycle;
use App\Models\MusterExpected;
use App\Models\MusterReceived;
use App\Models\ServiceOrder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MusterComplianceService
{
    public function ensureCycleForContractMonth(Contract $contract, int $month, int $year): ?MusterCycle
    {
        $existingCycle = MusterCycle::query()
            ->where('contract_id', $contract->id)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        if ($existingCycle) {
            $this->refreshCycleStatuses($existingCycle);

            return $existingCycle->fresh();
        }

        $serviceOrder = $this->resolveSourceServiceOrder($contract, $month, $year);

        if (! $serviceOrder) {
            return null;
        }

        [$cycleStart, $cycleEnd] = $this->cycleDates($month, $year, $serviceOrder->muster_cycle_type);

        if ($serviceOrder->period_start_date && $serviceOrder->period_start_date->gt($cycleEnd)) {
            return null;
        }

        if ($serviceOrder->period_end_date && $serviceOrder->period_end_date->lt($cycleStart)) {
            return null;
        }

        return DB::transaction(function () use ($contract, $serviceOrder, $month, $year, $cycleStart, $cycleEnd) {
            $cycle = MusterCycle::query()->create([
                'contract_id' => $contract->id,
                'service_order_id' => $serviceOrder->id,
                'month' => $month,
                'year' => $year,
                'cycle_type' => $serviceOrder->muster_cycle_type,
                'cycle_label' => $this->cycleLabel($month, $year, $serviceOrder->muster_cycle_type),
                'cycle_start_date' => $cycleStart,
                'cycle_end_date' => $cycleEnd,
                'due_date' => $cycleEnd->copy()->addDays($serviceOrder->muster_due_days),
                'generated_at' => now(),
            ]);

            $locationIds = $contract->locations()->pluck('locations.id');
            $mappingIdsByLocation = $this->executiveMappingsForContract($contract)->all();
            $timestamp = now();

            $rows = $locationIds
                ->map(function (int $locationId) use ($cycle, $contract, $mappingIdsByLocation, $timestamp) {
                    return [
                        'muster_cycle_id' => $cycle->id,
                        'contract_id' => $contract->id,
                        'location_id' => $locationId,
                        'executive_mapping_id' => $mappingIdsByLocation[$locationId] ?? null,
                        'status' => 'Pending',
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                })
                ->values()
                ->all();

            if ($rows !== []) {
                MusterExpected::query()->insert($rows);
            }

            $this->refreshCycleStatuses($cycle);

            return $cycle->fresh();
        });
    }

    public function expectedEntriesForCycle(MusterCycle $cycle, array $filters = []): LengthAwarePaginator
    {
        $this->refreshCycleStatuses($cycle);

        return MusterExpected::query()
            ->with([
                'location.client:id,name',
                'location.state:id,name',
                'executiveMapping:id,location_id,executive_name,contract_id',
                'receiptHistory' => fn ($query) => $query->latest()->limit(1),
            ])
            ->where('muster_cycle_id', $cycle->id)
            ->when(($filters['status'] ?? '') !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->orderBy('location_id')
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString();
    }

    public function applyBulkReceive(MusterCycle $cycle, Collection $expectedEntries, string $action, ?string $remarks, User $user): void
    {
        $timestamp = now();

        DB::transaction(function () use ($cycle, $expectedEntries, $action, $remarks, $user, $timestamp) {
            foreach ($expectedEntries->chunk(200) as $chunk) {
                $historyRows = [];

                foreach ($chunk as $expected) {
                    [$status, $receiveMode, $receivedAt] = $this->resolveReceiveOutcome($cycle, $action, $timestamp);

                    $expected->update([
                        'status' => $status,
                        'received_via' => $receiveMode,
                        'received_at' => $receivedAt,
                        'remarks' => $remarks,
                        'acted_by_user_id' => $user->id,
                        'last_action_at' => $timestamp,
                        'approved_at' => null,
                        'returned_at' => null,
                    ]);

                    $historyRows[] = [
                        'muster_expected_id' => $expected->id,
                        'action_by_user_id' => $user->id,
                        'status' => $status,
                        'receive_mode' => $receiveMode,
                        'received_at' => $receivedAt,
                        'remarks' => $remarks,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }

                if ($historyRows !== []) {
                    MusterReceived::query()->insert($historyRows);
                }
            }
        });
    }

    public function applyReviewDecision(MusterExpected $expected, string $status, ?string $remarks, User $user): void
    {
        $timestamp = now();

        $expected->update([
            'status' => $status,
            'remarks' => $remarks,
            'acted_by_user_id' => $user->id,
            'last_action_at' => $timestamp,
            'approved_at' => $status === 'Approved' ? $timestamp : $expected->approved_at,
            'returned_at' => $status === 'Returned' ? $timestamp : $expected->returned_at,
        ]);

        MusterReceived::query()->create([
            'muster_expected_id' => $expected->id,
            'action_by_user_id' => $user->id,
            'status' => $status,
            'receive_mode' => $expected->received_via,
            'received_at' => $expected->received_at,
            'remarks' => $remarks,
        ]);
    }

    public function refreshCycleStatuses(MusterCycle $cycle): void
    {
        $today = now();

        MusterExpected::query()
            ->where('muster_cycle_id', $cycle->id)
            ->whereNull('received_at')
            ->where('status', '!=', 'Approved')
            ->where('status', '!=', 'Returned')
            ->update([
                'status' => $cycle->cycle_end_date->lt($today) ? 'Late' : 'Pending',
                'updated_at' => $today,
            ]);

        MusterExpected::query()
            ->where('muster_cycle_id', $cycle->id)
            ->whereNotNull('received_at')
            ->whereNotIn('status', ['Approved', 'Returned'])
            ->get()
            ->each(function (MusterExpected $expected) use ($cycle): void {
                $status = $expected->received_at && $expected->received_at->gt($cycle->cycle_end_date->endOfDay()) ? 'Late' : 'Received';

                if ($expected->status !== $status) {
                    $expected->update([
                        'status' => $status,
                    ]);
                }
            });
    }

    public function currentMonthSummary(): array
    {
        $month = (int) now()->format('n');
        $year = (int) now()->format('Y');

        $baseQuery = MusterExpected::query()
            ->whereHas('musterCycle', fn (Builder $query) => $query->where('month', $month)->where('year', $year));

        return [
            'expected' => (clone $baseQuery)->count(),
            'received' => (clone $baseQuery)->where('status', 'Received')->count(),
            'late' => (clone $baseQuery)->where('status', 'Late')->count(),
            'pending' => (clone $baseQuery)->where('status', 'Pending')->count(),
        ];
    }

    public function latestLateAlerts(int $limit = 5): Collection
    {
        return MusterExpected::query()
            ->with(['contract:id,contract_no', 'location:id,name,city'])
            ->where('status', 'Late')
            ->latest('updated_at')
            ->limit($limit)
            ->get();
    }

    public function cycleDates(int $month, int $year, string $cycleType): array
    {
        $monthStart = Carbon::create($year, $month, 1)->startOfDay();

        if ($cycleType === '21-20') {
            $cycleStart = $monthStart->copy()->subMonth()->day(21);
            $cycleEnd = $monthStart->copy()->day(20)->endOfDay();

            return [$cycleStart, $cycleEnd];
        }

        return [
            $monthStart->copy()->startOfMonth(),
            $monthStart->copy()->endOfMonth(),
        ];
    }

    public function cycleLabel(int $month, int $year, string $cycleType): string
    {
        $monthLabel = Carbon::create($year, $month, 1)->format('M Y');

        return $cycleType === '21-20'
            ? "Cycle 21-20 ({$monthLabel})"
            : "Cycle 1-Last ({$monthLabel})";
    }

    private function resolveSourceServiceOrder(Contract $contract, int $month, int $year): ?ServiceOrder
    {
        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        return $contract->serviceOrders()
            ->where('auto_generate_muster', true)
            ->whereDate('period_start_date', '<=', $monthEnd)
            ->whereDate('period_end_date', '>=', $monthStart->copy()->subMonth())
            ->orderBy('period_start_date')
            ->first();
    }

    private function executiveMappingsForContract(Contract $contract): Collection
    {
        return ExecutiveMapping::query()
            ->where('client_id', $contract->client_id)
            ->where('is_active', true)
            ->where(function (Builder $query) use ($contract) {
                $query->whereNull('contract_id')->orWhere('contract_id', $contract->id);
            })
            ->whereNotNull('location_id')
            ->orderByDesc('is_primary')
            ->get(['id', 'location_id'])
            ->unique('location_id')
            ->mapWithKeys(fn (ExecutiveMapping $mapping) => [$mapping->location_id => $mapping->id]);
    }

    private function resolveReceiveOutcome(MusterCycle $cycle, string $action, Carbon $timestamp): array
    {
        if ($action === 'pending') {
            return ['Pending', null, null];
        }

        $receiveMode = $action === 'received_hard_copy' ? 'Hard Copy' : 'Email';
        $status = $timestamp->gt($cycle->cycle_end_date->endOfDay()) ? 'Late' : 'Received';

        return [$status, $receiveMode, $timestamp];
    }
}
