<?php

namespace App\Http\Controllers;

use App\Models\DispatchEntry;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderLocation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DispatchEntryController extends Controller
{
    private const PER_PAGE = 50;

    public function index(Request $request)
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['pending', 'dispatched'])],
            'executive_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $selectedMonth = $this->resolveSelectedMonth((string) ($validated['month'] ?? ''));
        $search = trim((string) ($validated['search'] ?? ''));
        $status = (string) ($validated['status'] ?? '');
        $executiveId = (int) ($validated['executive_id'] ?? 0);
        $wageMonthOptions = $this->wageMonthOptions(now()->startOfMonth());
        $operationExecutives = $this->operationExecutiveOptions($request, $selectedMonth);

        $dispatchEntries = $this->dispatchEntriesQuery($request, $selectedMonth, $search, $status, $executiveId)
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('operations.dispatch-entry.index', [
            'dispatchEntries' => $dispatchEntries,
            'search' => $search,
            'status' => $status,
            'executiveId' => $executiveId,
            'selectedMonth' => $selectedMonth,
            'selectedMonthKey' => $selectedMonth->format('Y-m'),
            'wageMonthOptions' => $wageMonthOptions,
            'operationExecutives' => $operationExecutives,
        ]);
    }

    public function dispatch(Request $request, ServiceOrderLocation $serviceOrderLocation)
    {
        $assignment = $this->findVisibleAssignment($request, $serviceOrderLocation);

        if ($this->isClosedAssignment($assignment)) {
            return redirect()
                ->back()
                ->with('status', 'Closed dispatch rows cannot be dispatched.');
        }

        if ($assignment->dispatched_at !== null) {
            return redirect()
                ->back()
                ->with('status', 'Dispatch already recorded for this row.');
        }

        $timestamp = now();

        $assignment->update([
            'dispatched_at' => $timestamp,
            'dispatched_by_user_id' => $request->user()->id,
        ]);

        $legacyStatus = $this->resolveLegacyDispatchStatus($assignment);

        DispatchEntry::query()->updateOrCreate(
            ['service_order_id' => $assignment->service_order_id],
            [
                'status' => $legacyStatus,
                'dispatched_by_user_id' => $legacyStatus === 'dispatched' ? $request->user()->id : null,
                'dispatched_at' => $legacyStatus === 'dispatched' ? $timestamp : null,
            ]
        );

        $this->logActivity(
            'dispatch_entry',
            'dispatch',
            sprintf(
                'Dispatched sales order %s for location %s.',
                $assignment->serviceOrder?->order_no ?? 'N/A',
                $assignment->location?->name ?? 'N/A'
            ),
            $assignment,
            $request->user()
        );

        return redirect()
            ->back()
            ->with('status', 'Dispatch recorded successfully.');
    }

    public function download(Request $request, ServiceOrderLocation $serviceOrderLocation): StreamedResponse
    {
        $assignment = $this->findVisibleAssignment($request, $serviceOrderLocation);
        $entry = $this->dispatchEntriesQuery($request, $assignment->wage_month)
            ->where('service_order_location.id', $assignment->id)
            ->firstOrFail();

        $filename = sprintf(
            'dispatch-entry-%s-%s-%s.csv',
            str($entry->so_number)->slug('_'),
            str($entry->location_code ?: 'location')->slug('_'),
            $entry->wage_month?->format('Y_m')
        );

        return Response::streamDownload(function () use ($entry): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'SO Number',
                'Client Name',
                'SO Name',
                'Location Code',
                'Location Name',
                'Period',
                'State',
                'Operation Executive',
                'Received By',
                'Action Taken Date',
                'Despatched Type',
                'Dispatch Status',
            ]);

            fputcsv($handle, [
                $entry->so_number,
                $entry->client_name,
                $entry->so_name,
                $entry->location_code,
                $entry->location_name,
                $this->formatPeriod($entry->period_start_date, $entry->period_end_date),
                $entry->state_name,
                $entry->executive_name,
                $entry->received_by_name,
                $entry->action_taken_at?->format('d M Y h:i A'),
                $entry->despatched_type,
                ucfirst((string) $entry->dispatch_status),
            ]);

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function dispatchEntriesQuery(
        Request $request,
        ?Carbon $selectedMonth = null,
        string $search = '',
        string $status = '',
        int $executiveId = 0
    ): Builder
    {
        $selectedMonth ??= $this->resolveSelectedMonth((string) $request->string('month'));
        $monthEnd = $selectedMonth->copy()->endOfMonth();
        $today = now()->toDateString();
        $monthExpected = $this->monthExpectedSubquery($selectedMonth);
        $latestReceipts = $this->latestReceiptSubquery();

        $query = ServiceOrderLocation::query()
            ->select([
                'service_order_location.id',
                'service_order_location.service_order_id',
                'service_order_location.location_id',
                'service_order_location.operation_executive_id',
                'service_order_location.dispatched_by_user_id',
                'service_order_location.wage_month',
                'service_order_location.dispatched_at',
                'service_order_location.start_date',
                'service_order_location.end_date',
                'service_orders.order_no as so_number',
                'service_orders.so_name',
                'service_orders.status as service_order_status',
                'service_orders.period_start_date',
                'service_orders.period_end_date',
                'locations.code as location_code',
                'locations.name as location_name',
                'locations.is_active as location_is_active',
                'clients.name as client_name',
                'states.name as state_name',
                'executives.name as executive_name',
            ])
            ->selectRaw(
                "CASE
                    WHEN service_order_location.dispatched_at IS NOT NULL THEN 'dispatched'
                    ELSE 'pending'
                END as dispatch_status",
            )
            ->selectRaw("COALESCE(receivers.name, expected_receivers.name, 'Pending') as received_by_name")
            ->selectRaw(
                "CASE
                    WHEN COALESCE(receipt_logs.receive_mode, month_expected.received_via) IS NULL THEN 'Pending'
                    WHEN LOWER(COALESCE(receipt_logs.receive_mode, month_expected.received_via)) IN ('hard copy', 'received_hard_copy') THEN 'Hard Copy'
                    WHEN LOWER(COALESCE(receipt_logs.receive_mode, month_expected.received_via)) IN ('email', 'mail', 'received_email') THEN 'Mail'
                    WHEN LOWER(COALESCE(receipt_logs.receive_mode, month_expected.received_via)) LIKE '%upload%' THEN 'Uploaded'
                    ELSE COALESCE(receipt_logs.receive_mode, month_expected.received_via)
                END as despatched_type"
            )
            ->selectRaw('COALESCE(service_order_location.dispatched_at, month_expected.last_action_at, receipt_logs.received_at, month_expected.received_at) as action_taken_at')
            ->join('service_orders', 'service_orders.id', '=', 'service_order_location.service_order_id')
            ->join('contracts', 'contracts.id', '=', 'service_orders.contract_id')
            ->join('clients', 'clients.id', '=', 'contracts.client_id')
            ->join('locations', 'locations.id', '=', 'service_order_location.location_id')
            ->leftJoin('states', 'states.id', '=', 'service_orders.state_id')
            ->leftJoin('users as executives', 'executives.id', '=', 'service_order_location.operation_executive_id')
            ->leftJoinSub($monthExpected, 'month_expected', function ($join) {
                $join->on('month_expected.service_order_id', '=', 'service_order_location.service_order_id')
                    ->on('month_expected.location_id', '=', 'service_order_location.location_id');
            })
            ->leftJoinSub($latestReceipts, 'latest_receipts', function ($join) {
                $join->on('latest_receipts.muster_expected_id', '=', 'month_expected.muster_expected_id');
            })
            ->leftJoin('muster_received as receipt_logs', 'receipt_logs.id', '=', 'latest_receipts.latest_received_id')
            ->leftJoin('users as receivers', 'receivers.id', '=', 'receipt_logs.action_by_user_id')
            ->leftJoin('users as expected_receivers', 'expected_receivers.id', '=', 'month_expected.acted_by_user_id')
            ->whereIn('service_orders.status', ServiceOrder::ACTIVE_STATUSES)
            ->where('locations.is_active', true)
            ->where(function (Builder $builder) use ($monthEnd) {
                $builder
                    ->whereNull('service_order_location.wage_month')
                    ->orWhereDate('service_order_location.wage_month', '<=', $monthEnd->toDateString());
            })
            ->where(function (Builder $builder) use ($today) {
                $builder
                    ->whereNull('service_order_location.start_date')
                    ->orWhereDate('service_order_location.start_date', '<=', $today);
            })
            ->where(function (Builder $builder) use ($today) {
                $builder
                    ->whereNull('service_order_location.end_date')
                    ->orWhereDate('service_order_location.end_date', '>=', $today);
            })
            ->when($search !== '', function (Builder $builder) use ($search) {
                $builder->where(function (Builder $innerQuery) use ($search) {
                    $innerQuery
                        ->where('service_orders.order_no', 'like', "%{$search}%")
                        ->orWhere('clients.name', 'like', "%{$search}%")
                        ->orWhere('locations.name', 'like', "%{$search}%")
                        ->orWhere('executives.name', 'like', "%{$search}%")
                        ->orWhere('receivers.name', 'like', "%{$search}%")
                        ->orWhere('expected_receivers.name', 'like', "%{$search}%")
                        ->orWhere('receipt_logs.receive_mode', 'like', "%{$search}%")
                        ->orWhere('month_expected.received_via', 'like', "%{$search}%");
                });
            })
            ->when($executiveId > 0, fn (Builder $builder) => $builder->where('service_order_location.operation_executive_id', $executiveId))
            ->orderBy('service_orders.order_no')
            ->orderBy('locations.name');

        $this->accessControl()->scopeServiceOrderLocations($query, $request->user(), 'service_order_location');

        if ($status !== '') {
            $this->applyStatusFilter($query, $status);
        }

        return $query;
    }

    private function applyStatusFilter(Builder $query, string $status): void
    {
        match ($status) {
            'pending' => $query
                ->whereNull('service_order_location.dispatched_at'),
            'dispatched' => $query
                ->whereNotNull('service_order_location.dispatched_at'),
            default => null,
        };
    }

    private function findVisibleAssignment(Request $request, ServiceOrderLocation $serviceOrderLocation): ServiceOrderLocation
    {
        return $this->accessControl()
            ->scopeServiceOrderLocations(
                ServiceOrderLocation::query()
                    ->with(['serviceOrder:id,order_no,status', 'location:id,name,code'])
                    ->whereKey($serviceOrderLocation->id),
                $request->user(),
                'service_order_location'
            )
            ->firstOrFail();
    }

    private function isClosedAssignment(ServiceOrderLocation $assignment): bool
    {
        return in_array((string) $assignment->serviceOrder?->status, ServiceOrder::TERMINATED_STATUSES, true);
    }

    private function resolveLegacyDispatchStatus(ServiceOrderLocation $assignment): string
    {
        if ($this->isClosedAssignment($assignment)) {
            return 'closed';
        }

        $hasPendingRows = ServiceOrderLocation::query()
            ->where('service_order_id', $assignment->service_order_id)
            ->whereDate('wage_month', $assignment->wage_month?->toDateString())
            ->whereNull('dispatched_at')
            ->exists();

        return $hasPendingRows ? 'pending' : 'dispatched';
    }

    private function resolveSelectedMonth(string $selectedMonth): Carbon
    {
        $defaultMonth = now()->startOfMonth();

        if ($selectedMonth === '') {
            return $defaultMonth;
        }

        try {
            $month = Carbon::createFromFormat('Y-m', $selectedMonth)->startOfMonth();
        } catch (\Throwable) {
            return $defaultMonth;
        }

        $allowedMonths = $this->wageMonthOptions($defaultMonth)->pluck('value');

        return $allowedMonths->contains($month->format('Y-m'))
            ? $month
            : $defaultMonth;
    }

    private function wageMonthOptions(Carbon $referenceMonth): Collection
    {
        return collect(range(-6, 1))
            ->map(fn (int $offset) => $referenceMonth->copy()->addMonths($offset)->startOfMonth())
            ->map(fn (Carbon $month) => [
                'value' => $month->format('Y-m'),
                'label' => $month->format('M Y'),
            ]);
    }

    private function operationExecutiveOptions(Request $request, Carbon $selectedMonth): Collection
    {
        $query = clone $this->dispatchEntriesQuery($request, $selectedMonth, '', '', 0);

        return $query
            ->reorder()
            ->select([
                'service_order_location.operation_executive_id as id',
                'executives.name',
            ])
            ->whereNotNull('service_order_location.operation_executive_id')
            ->whereNotNull('executives.name')
            ->distinct()
            ->orderBy('executives.name')
            ->get();
    }

    private function monthExpectedSubquery(Carbon $selectedMonth)
    {
        return DB::table('muster_expected as me')
            ->join('muster_cycles as mc', 'mc.id', '=', 'me.muster_cycle_id')
            ->select([
                'mc.service_order_id',
                'me.location_id',
                'me.id as muster_expected_id',
                'me.received_via',
                'me.received_at',
                'me.last_action_at',
                'me.acted_by_user_id',
            ])
            ->where('mc.month', $selectedMonth->month)
            ->where('mc.year', $selectedMonth->year);
    }

    private function latestReceiptSubquery()
    {
        return DB::table('muster_received as mr')
            ->selectRaw('mr.muster_expected_id, MAX(mr.id) as latest_received_id')
            ->whereIn('mr.status', ['Received', 'Late', 'Approved', 'Returned', 'Closed'])
            ->groupBy('mr.muster_expected_id');
    }

    private function formatPeriod(?Carbon $startDate, ?Carbon $endDate): string
    {
        if (! $startDate && ! $endDate) {
            return 'N/A';
        }

        return collect([
            $startDate?->format('d M Y'),
            $endDate?->format('d M Y'),
        ])->map(fn (?string $value) => $value ?: 'N/A')->implode(' - ');
    }
}
