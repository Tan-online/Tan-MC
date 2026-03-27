<?php

namespace App\Services;

use App\Models\ServiceOrder;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OperationsWorkspaceService
{
    public function __construct(
        private readonly AccessControlService $accessControlService,
    ) {
    }

    public function activeWageMonth(?Carbon $referenceDate = null): Carbon
    {
        $referenceDate ??= now();

        return $referenceDate->day <= 15
            ? $referenceDate->copy()->subMonth()->startOfMonth()
            : $referenceDate->copy()->startOfMonth();
    }

    public function previousWageMonth(?Carbon $activeWageMonth = null): Carbon
    {
        $activeWageMonth ??= $this->activeWageMonth();

        return $activeWageMonth->copy()->subMonth()->startOfMonth();
    }

    public function wageMonthOptions(?Carbon $referenceMonth = null): Collection
    {
        $referenceMonth ??= $this->activeWageMonth();

        return collect(range(-6, 1))
            ->map(fn (int $offset) => $referenceMonth->copy()->addMonths($offset)->startOfMonth())
            ->map(fn (Carbon $month) => [
                'value' => $month->format('Y-m'),
                'label' => $month->format('M Y'),
            ]);
    }

    public function resolveSelectedMonth(?string $month, ?Carbon $referenceMonth = null): Carbon
    {
        $referenceMonth ??= $this->activeWageMonth();

        if (! $month) {
            return $referenceMonth;
        }

        try {
            $resolved = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable) {
            return $referenceMonth;
        }

        return $this->wageMonthOptions($referenceMonth)
            ->pluck('value')
            ->contains($resolved->format('Y-m'))
                ? $resolved
                : $referenceMonth;
    }

    /**
     * @return list<int>
     */
    public function visibleOperationsUserIds(User $user): array
    {
        if ($this->accessControlService->isOperationsScoped($user)) {
            return $this->accessControlService->visibleExecutiveUserIds($user);
        }

        return [(int) $user->id];
    }

    public function isOperationsSupervisor(User $user): bool
    {
        return $this->accessControlService->isOperationsSupervisor($user);
    }

    public function visibleTeamsQuery(User $user): EloquentBuilder
    {
        $query = Team::query()
            ->with([
                'department:id,name,code',
                'operationArea.state:id,name',
                'operationExecutive:id,name,employee_code',
                'manager:id,name,employee_code',
                'hod:id,name,employee_code',
                'executives:id,name,employee_code',
            ])
            ->withCount('executives')
            ->where('is_active', true)
            ->orderBy('name');

        if ($this->accessControlService->isOperationsSupervisor($user)) {
            return $query->where(function (EloquentBuilder $builder) use ($user) {
                $builder
                    ->where('manager_id', $user->id)
                    ->orWhere('hod_id', $user->id);
            });
        }

        return $query->whereHas('executives', fn (EloquentBuilder $builder) => $builder->where('users.id', $user->id));
    }

    public function primaryTeamName(User $user): string
    {
        $team = $this->primaryTeam($user);

        return $team?->name ?: 'Not Available';
    }

    public function primaryTeam(User $user): ?Team
    {
        return $this->visibleTeamsQuery($user)->first();
    }

    public function operationsLocationRowsQuery(User $user, Carbon $wageMonth, array $filters = []): QueryBuilder
    {
        $monthExpected = $this->monthExpectedSubquery($wageMonth);
        $latestReceipts = $this->latestReceiptSubquery();

        return $this->operationsScopeBaseQuery($user, $wageMonth)
            ->leftJoin('users as executives', 'executives.id', '=', 'sol.operation_executive_id')
            ->leftJoinSub($monthExpected, 'month_expected', function ($join) {
                $join->on('month_expected.service_order_id', '=', 'sol.service_order_id')
                    ->on('month_expected.location_id', '=', 'sol.location_id');
            })
            ->leftJoinSub($latestReceipts, 'latest_receipts', function ($join) {
                $join->on('latest_receipts.muster_expected_id', '=', 'month_expected.muster_expected_id');
            })
            ->leftJoin('muster_received as receipt_logs', 'receipt_logs.id', '=', 'latest_receipts.latest_received_id')
            ->leftJoin('users as receipt_users', 'receipt_users.id', '=', 'receipt_logs.action_by_user_id')
            ->leftJoin('users as expected_users', 'expected_users.id', '=', 'month_expected.acted_by_user_id')
            ->select([
                'sol.id',
                'sol.service_order_id',
                'sol.location_id',
                'sol.operation_executive_id',
                'so.order_no as so_number',
                'so.so_name',
                'so.status as service_order_status',
                'cl.id as client_id',
                'cl.name as client_name',
                'l.name as location_name',
                'l.code as location_code',
                'l.is_active as location_is_active',
                'executives.name as executive_name',
            ])
            ->selectRaw("COALESCE(month_expected.status, 'Pending') as workspace_status")
            ->selectRaw("COALESCE(receipt_users.name, expected_users.name, 'Pending') as received_by_name")
            ->selectRaw('COALESCE(month_expected.last_action_at, receipt_logs.received_at, month_expected.received_at) as action_taken_at')
            ->selectRaw(
                "CASE
                    WHEN COALESCE(receipt_logs.receive_mode, month_expected.received_via) IS NULL THEN 'Pending'
                    WHEN LOWER(COALESCE(receipt_logs.receive_mode, month_expected.received_via)) IN ('hard copy', 'received_hard_copy') THEN 'Hard Copy'
                    WHEN LOWER(COALESCE(receipt_logs.receive_mode, month_expected.received_via)) IN ('email', 'mail', 'received_email') THEN 'Mail'
                    WHEN LOWER(COALESCE(receipt_logs.receive_mode, month_expected.received_via)) LIKE '%upload%' THEN 'Uploaded'
                    ELSE COALESCE(receipt_logs.receive_mode, month_expected.received_via)
                END as submission_mode"
            )
            ->when(($filters['client_id'] ?? 0) > 0, fn (QueryBuilder $builder) => $builder->where('cl.id', (int) $filters['client_id']))
            ->when(($filters['location_id'] ?? 0) > 0, fn (QueryBuilder $builder) => $builder->where('l.id', (int) $filters['location_id']))
            ->when(($filters['status'] ?? '') !== '', function (QueryBuilder $builder) use ($filters) {
                $status = (string) $filters['status'];

                if ($status === 'Pending') {
                    $builder->where(function (QueryBuilder $pendingQuery) {
                        $pendingQuery
                            ->whereNull('month_expected.status')
                            ->orWhere('month_expected.status', 'Pending');
                    });

                    return;
                }

                $builder->where('month_expected.status', $status);
            })
            ->orderBy('cl.name')
            ->orderBy('l.name')
            ->orderBy('so.order_no');
    }

    public function compactLocationRowsQuery(User $user, Carbon $wageMonth, array $filters = []): QueryBuilder
    {
        $wageMonthStr = $wageMonth->format('Y-m');

        return $this->operationsScopeBaseQuery($user, $wageMonth)
            ->leftJoin('users as executives', 'executives.id', '=', 'sol.operation_executive_id')
            ->leftJoin('so_location_monthly_status as slms', function ($join) use ($wageMonthStr) {
                $join->on('slms.service_order_id', '=', 'sol.service_order_id')
                     ->on('slms.location_id', '=', 'sol.location_id')
                     ->where('slms.wage_month', '=', $wageMonthStr);
            })
            ->select([
                'sol.id',
                'sol.service_order_id',
                'sol.location_id',
                'sol.operation_executive_id',
                'sol.type',
                'sol.action_date',
                'sol.action_by_id',
                'sol.start_date as location_start_date',
                'sol.end_date as location_end_date',
                'so.order_no as so_number',
                'so.period_start_date as so_start_date',
                'cl.id as client_id',
                'cl.name as client_name',
                'l.name as location_name',
                'l.code as location_code',
                'executives.name as executive_name',
                'slms.file_path',
                'slms.remarks',
                'slms.reviewer_remarks',
            ])
            ->selectRaw("COALESCE(slms.status, 'pending') as status")
            ->selectRaw("slms.submission_type as submission_type")
            ->selectRaw("CASE WHEN slms.status IN ('submitted','approved') THEN slms.submission_type ELSE NULL END as submission_type_display")
            ->selectRaw("CASE WHEN slms.status IN ('submitted','approved') THEN slms.submitted_at ELSE NULL END as submitted_at")
            ->when(($filters['client_id'] ?? 0) > 0, fn (QueryBuilder $builder) => $builder->where('cl.id', (int) $filters['client_id']))
            ->when(($filters['location_id'] ?? 0) > 0, fn (QueryBuilder $builder) => $builder->where('l.id', (int) $filters['location_id']))
            ->when(($filters['executive_id'] ?? 0) > 0, fn (QueryBuilder $builder) => $builder->where('sol.operation_executive_id', (int) $filters['executive_id']))
            ->when(!empty($filters['status']), function (QueryBuilder $builder) use ($filters) {
                $status = (string) $filters['status'];
                if ($status === 'pending') {
                    return $builder->whereNull('slms.status');
                }
                return $builder->where('slms.status', '=', $status);
            })
            ->orderBy(DB::raw("FIELD(COALESCE(slms.status, 'pending'), 'pending', 'submitted', 'approved', 'rejected', 'returned')"))
            ->orderBy('cl.name')
            ->orderBy('l.name')
            ->orderBy('so.order_no');
    }

    public function teamPerformanceQuery(User $user, Carbon $wageMonth): QueryBuilder
    {
        $locationRows = $this->operationsLocationRowsQuery($user, $wageMonth)
            ->reorder()
            ->select([
                DB::raw('COALESCE(sol.operation_executive_id, 0) as employee_id'),
                DB::raw("COALESCE(executives.name, 'Unassigned') as employee_name"),
                DB::raw("COALESCE(month_expected.status, 'Pending') as workspace_status"),
            ]);

        return DB::query()
            ->fromSub($locationRows, 'location_rows')
            ->select([
                'location_rows.employee_id',
                'location_rows.employee_name',
            ])
            ->selectRaw('COUNT(*) as total_locations')
            ->selectRaw("SUM(CASE WHEN location_rows.workspace_status = 'Pending' THEN 0 ELSE 1 END) as submitted_count")
            ->selectRaw("SUM(CASE WHEN location_rows.workspace_status IN ('Received', 'Late', 'Approved', 'Closed') THEN 1 ELSE 0 END) as received_count")
            ->selectRaw("SUM(CASE WHEN location_rows.workspace_status = 'Returned' THEN 1 ELSE 0 END) as returned_count")
            ->groupBy('location_rows.employee_id', 'location_rows.employee_name')
            ->orderBy('employee_name');
    }

    public function clientOptionsQuery(User $user, Carbon $wageMonth): QueryBuilder
    {
        return $this->operationsScopeBaseQuery($user, $wageMonth)
            ->select([
                'cl.id as client_id',
                'cl.name as client_name',
            ])
            ->distinct()
            ->orderBy('client_name');
    }

    public function locationOptionsQuery(User $user, Carbon $wageMonth): QueryBuilder
    {
        return $this->operationsScopeBaseQuery($user, $wageMonth)
            ->select([
                'l.id as location_id',
                'l.name as location_name',
            ])
            ->distinct()
            ->orderBy('location_name');
    }

    public function executiveOptionsQuery(User $user, Carbon $wageMonth): QueryBuilder
    {
        return $this->operationsScopeBaseQuery($user, $wageMonth)
            ->join('users as executives', 'executives.id', '=', 'sol.operation_executive_id')
            ->select([
                'executives.id as executive_id',
                'executives.name as executive_name',
            ])
            ->distinct()
            ->orderBy('executive_name');
    }

    public function summaryMetrics(User $user, Carbon $activeWageMonth): array
    {
        $previousWageMonth = $this->previousWageMonth($activeWageMonth);

        $previousPending = (clone $this->operationsLocationRowsQuery($user, $previousWageMonth))
            ->where(function (QueryBuilder $builder) {
                $builder
                    ->whereNull('month_expected.status')
                    ->orWhere('month_expected.status', 'Pending');
            })
            ->count();

        $currentPending = (clone $this->operationsLocationRowsQuery($user, $activeWageMonth))
            ->where(function (QueryBuilder $builder) {
                $builder
                    ->whereNull('month_expected.status')
                    ->orWhere('month_expected.status', 'Pending');
            })
            ->count();

        $totalLocations = (clone $this->operationsScopeBaseQuery($user, $activeWageMonth))
            ->distinct()
            ->count('sol.location_id');

        $returnedCount = (clone $this->operationsLocationRowsQuery($user, $activeWageMonth))
            ->where('month_expected.status', 'Returned')
            ->count();

        return [
            'previous_pending' => $previousPending,
            'current_pending' => $currentPending,
            'total_locations' => $totalLocations,
            'returned' => $returnedCount,
        ];
    }

    public function teamWorkspaceMetrics(Team $team, Carbon $wageMonth): array
    {
        $monthStart = $wageMonth->copy()->startOfMonth();
        $monthEnd = $wageMonth->copy()->endOfMonth();
        $baseQuery = DB::table('service_order_location as sol')
            ->join('service_orders as so', 'so.id', '=', 'sol.service_order_id')
            ->join('locations as l', 'l.id', '=', 'sol.location_id')
            ->where('so.team_id', $team->id);
        
        $baseQuery
            ->whereIn('so.status', ServiceOrder::ACTIVE_STATUSES)
            ->where('l.is_active', true)
            ->where(function (QueryBuilder $builder) use ($monthEnd) {
                $builder
                    ->whereNull('sol.wage_month')
                    ->orWhereDate('sol.wage_month', '<=', $monthEnd->toDateString());
            })
            ->where(function (QueryBuilder $builder) use ($monthEnd) {
                $builder
                    ->whereNull('sol.start_date')
                    ->orWhereDate('sol.start_date', '<=', $monthEnd->toDateString());
            })
            ->where(function (QueryBuilder $builder) use ($monthStart) {
                $builder
                    ->whereNull('sol.end_date')
                    ->orWhereDate('sol.end_date', '>=', $monthStart->toDateString());
            });

        return [
            'service_orders' => (clone $baseQuery)->distinct()->count('so.id'),
            'locations' => (clone $baseQuery)->distinct()->count('sol.location_id'),
            'executives' => (int) $team->executives_count,
        ];
    }

    private function operationsScopeBaseQuery(User $user, Carbon $wageMonth): QueryBuilder
    {
        $visibleUserIds = $this->visibleOperationsUserIds($user);
        $monthStart = $wageMonth->copy()->startOfMonth();
        $monthEnd = $wageMonth->copy()->endOfMonth();

        return DB::table('service_order_location as sol')
            ->join('service_orders as so', 'so.id', '=', 'sol.service_order_id')
            ->join('contracts as c', 'c.id', '=', 'so.contract_id')
            ->join('clients as cl', 'cl.id', '=', 'c.client_id')
            ->join('locations as l', 'l.id', '=', 'sol.location_id')
            ->whereIn('sol.operation_executive_id', $visibleUserIds)
            ->whereIn('so.status', ServiceOrder::ACTIVE_STATUSES)
            ->where('l.is_active', true)
            ->where(function (QueryBuilder $builder) use ($monthEnd) {
                $builder
                    ->whereNull('sol.wage_month')
                    ->orWhereDate('sol.wage_month', '<=', $monthEnd->toDateString());
            })
            ->where(function (QueryBuilder $builder) use ($monthEnd) {
                $builder
                    ->whereNull('sol.start_date')
                    ->orWhereDate('sol.start_date', '<=', $monthEnd->toDateString());
            })
            ->where(function (QueryBuilder $builder) use ($monthStart) {
                $builder
                    ->whereNull('sol.end_date')
                    ->orWhereDate('sol.end_date', '>=', $monthStart->toDateString());
            });
    }

    private function monthExpectedSubquery(Carbon $wageMonth): QueryBuilder
    {
        return DB::table('muster_expected as me')
            ->join('muster_cycles as mc', 'mc.id', '=', 'me.muster_cycle_id')
            ->select([
                'mc.service_order_id',
                'me.location_id',
                'me.id as muster_expected_id',
                'me.status',
                'me.received_via',
                'me.received_at',
                'me.last_action_at',
                'me.acted_by_user_id',
            ])
            ->where('mc.month', $wageMonth->month)
            ->where('mc.year', $wageMonth->year);
    }

    private function latestReceiptSubquery(): QueryBuilder
    {
        return DB::table('muster_received as mr')
            ->selectRaw('mr.muster_expected_id, MAX(mr.id) as latest_received_id')
            ->whereIn('mr.status', ['Received', 'Late', 'Approved', 'Returned', 'Closed'])
            ->groupBy('mr.muster_expected_id');
    }

    /**
     * Check if location should be visible for the given wage month
     * Rule: start_date <= last day of wage_month
     */
    public function isLocationVisibleForMonth(\App\Models\Location $location, Carbon $wageMonth): bool
    {
        $startDate = $location->start_date;
        
        if ($startDate === null) {
            return true;  // No start date = always visible
        }

        $monthEnd = $wageMonth->copy()->endOfMonth();
        
        return $startDate->lessThanOrEqualTo($monthEnd);
    }

    /**
     * Get role-based action availabilities for a user and location
     * Determines which buttons should be shown
     */
    public function getActionAvailabilities(User $user, $locationRow = null): array
    {
        $roleKey = $this->accessControlService->roleKey($user);
        
        // Base availabilities - who can do what
        $isOperations = $this->accessControlService->isOperationsScoped($user);
        $isReviewer = $this->accessControlService->hasRole($user, 'reviewer');
        $isApprover = $this->accessControlService->hasRole($user, ['admin', 'super_admin', 'manager', 'hod']);
        
        return [
            'can_submit' => $isOperations && ($locationRow === null || $locationRow->status === 'pending'),
            'can_approve' => $isApprover && ($locationRow === null || $locationRow->status === 'submitted'),
            'can_reject' => $isApprover && ($locationRow === null || in_array($locationRow->status, ['pending', 'submitted'])),
            'can_view_history' => true,  // Everyone can view history
            'is_operations_user' => $isOperations,
            'is_reviewer' => $isReviewer,
            'is_approver' => $isApprover,
        ];
    }

    /**
     * Filter locations by visibility rules for the given month
     * Ensures start_date <= selected month
     */
    public function filterLocationsByMonthVisibility($locationIds, Carbon $wageMonth): array
    {
        $monthEnd = $wageMonth->copy()->endOfMonth();
        
        return \App\Models\Location::query()
            ->whereIn('id', $locationIds)
            ->where(function (EloquentBuilder $builder) use ($monthEnd) {
                $builder
                    ->whereNull('start_date')
                    ->orWhereDate('start_date', '<=', $monthEnd->toDateString());
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Validate and store uploaded file securely
     * Rules: max 10MB, allowed types: pdf, doc, docx, xls, xlsx, zip
     */
    public function validateAndStoreFile($file, int $locationId, int $userId, Carbon $wageMonth): ?string
    {
        if (!$file) {
            return null;
        }

        // Validate file
        if ($file->getSize() > 10 * 1024 * 1024) {
            throw new \Exception('File size exceeds 10MB limit');
        }

        $allowedMimes = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip'];
        $extension = strtolower($file->getClientOriginalExtension());
        
        if (!in_array($extension, $allowedMimes)) {
            throw new \Exception("File type '{$extension}' is not allowed. Allowed types: pdf, doc, docx, xls, xlsx, zip");
        }

        // Store securely in private storage
        $path = $file->store(
            "location-uploads/{$wageMonth->format('Y-m')}/{$locationId}",
            'private'
        );

        return $path;
    }

    /**
     * Record monthly status for a location
     */
    public function recordMonthlyStatus(
        int $locationId,
        Carbon $wageMonth,
        string $status,
        string $submissionType = null,
        ?string $filePath = null,
        ?string $remarks = null,
        ?int $userId = null
    ): \App\Models\LocationMonthlyStatus {
        // Use provided userId or get from auth context
        if (!$userId) {
            $user = \Illuminate\Support\Facades\Auth::user();
            $userId = $user?->id;
        }
        
        $monthlyStatus = \App\Models\LocationMonthlyStatus::updateOrCreate(
            [
                'location_id' => $locationId,
                'wage_month' => $wageMonth->startOfMonth(),
            ],
            [
                'status' => $status,
                'submission_type' => $submissionType,
                'file_path' => $filePath,
                'remarks' => $remarks,
                'submitted_by' => $userId,
                'submitted_at' => now(),
            ]
        );

        return $monthlyStatus;
    }

    /**
     * Get monthly status for location
     */
    public function getMonthlyStatus(int $locationId, Carbon $wageMonth): ?\App\Models\LocationMonthlyStatus
    {
        return \App\Models\LocationMonthlyStatus::where('location_id', $locationId)
            ->where('wage_month', $wageMonth->startOfMonth())
            ->first();
    }

    /**
     * Submit or update SO-Location monthly status
     * Uses updateOrCreate to create or update the monthly status record
     * 
     * Unique key: (service_order_id, location_id, wage_month)
     */
    public function submitSoLocationStatus(
        int $serviceOrderId,
        int $locationId,
        Carbon $wageMonth,
        string $status = 'submitted',
        ?string $filePath = null,
        ?string $remarks = null,
        ?int $submittedBy = null
    ): \App\Models\SoLocationMonthlyStatus {
        $wageMonthStr = $wageMonth->format('Y-m');

        return \App\Models\SoLocationMonthlyStatus::updateOrCreate(
            [
                'service_order_id' => $serviceOrderId,
                'location_id' => $locationId,
                'wage_month' => $wageMonthStr,
            ],
            [
                'status' => $status,
                'file_path' => $filePath,
                'remarks' => $remarks,
                'submitted_by' => $submittedBy,
                'submitted_at' => now(),
            ]
        );
    }

    /**
     * Get SO-Location monthly status or create default if not exists
     * This ensures we always have the proper default 'pending' status
     */
    public function getSoLocationMonthlyStatus(
        int $serviceOrderId,
        int $locationId,
        Carbon $wageMonth
    ): \App\Models\SoLocationMonthlyStatus {
        $wageMonthStr = $wageMonth->format('Y-m');

        return \App\Models\SoLocationMonthlyStatus::firstOrCreate(
            [
                'service_order_id' => $serviceOrderId,
                'location_id' => $locationId,
                'wage_month' => $wageMonthStr,
            ],
            [
                'status' => 'pending',
            ]
        );
    }

    /**
     * Batch get monthly statuses for SO-Location combinations
     * Returns map keyed by "service_order_id:location_id" for easy lookup
     */
    public function batchGetSoLocationMonthlyStatuses(array $soLocationPairs, Carbon $wageMonth): Collection
    {
        $wageMonthStr = $wageMonth->format('Y-m');

        // Build array of (service_order_id, location_id) pairs
        $pairs = array_map(function ($row) {
            return [
                'service_order_id' => $row->service_order_id ?? $row['service_order_id'],
                'location_id' => $row->location_id ?? $row['location_id'],
            ];
        }, $soLocationPairs);

        $statuses = \App\Models\SoLocationMonthlyStatus::where('wage_month', $wageMonthStr)
            ->whereIn('service_order_id', array_column($pairs, 'service_order_id'))
            ->whereIn('location_id', array_column($pairs, 'location_id'))
            ->get();

        // Keyed by "so_id:location_id"
        return $statuses->mapWithKeys(function ($status) {
            return ["{$status->service_order_id}:{$status->location_id}" => $status];
        });
    }

    /**
     * Get batch monthly statuses for locations (legacy, kept for backwards compatibility)
     * Deprecated: Use batchGetSoLocationMonthlyStatuses instead
     */
    public function batchGetMonthlyStatuses(array $locationIds, Carbon $wageMonth)
    {
        return \App\Models\LocationMonthlyStatus::whereIn('location_id', $locationIds)
            ->where('wage_month', $wageMonth->startOfMonth())
            ->get()
            ->keyBy('location_id');
    }

    /**
     * Approve SO-Location status
     * Transitions: submitted -> approved
     * 
     * @param int $serviceOrderId
     * @param int $locationId
     * @param Carbon $wageMonth
     * @param int $reviewedBy User ID of reviewer
     * @param string|null $remarks Optional reviewer remarks
     */
    public function approveSoLocationStatus(
        int $serviceOrderId,
        int $locationId,
        Carbon $wageMonth,
        int $reviewedBy,
        ?string $remarks = null
    ): \App\Models\SoLocationMonthlyStatus {
        $wageMonthStr = $wageMonth->format('Y-m');

        $status = \App\Models\SoLocationMonthlyStatus::firstForUpdate()
            ->where('service_order_id', $serviceOrderId)
            ->where('location_id', $locationId)
            ->where('wage_month', $wageMonthStr)
            ->firstOrFail();

        $status->update([
            'status' => 'approved',
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
        ]);

        // Record in history
        $this->recordStatusHistory(
            $serviceOrderId,
            $locationId,
            $wageMonth,
            'approved',
            $remarks,
            $reviewedBy
        );

        return $status;
    }

    /**
     * Reject SO-Location status (hard reject - needs fresh upload)
     * Transitions: submitted -> rejected
     * 
     * @param int $serviceOrderId
     * @param int $locationId
     * @param Carbon $wageMonth
     * @param int $reviewedBy User ID of reviewer
     * @param string $remarks Rejection reason
     */
    public function rejectSoLocationStatus(
        int $serviceOrderId,
        int $locationId,
        Carbon $wageMonth,
        int $reviewedBy,
        string $remarks
    ): \App\Models\SoLocationMonthlyStatus {
        $wageMonthStr = $wageMonth->format('Y-m');

        $status = \App\Models\SoLocationMonthlyStatus::firstForUpdate()
            ->where('service_order_id', $serviceOrderId)
            ->where('location_id', $locationId)
            ->where('wage_month', $wageMonthStr)
            ->firstOrFail();

        $status->update([
            'status' => 'rejected',
            'reviewer_remarks' => $remarks,
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
        ]);

        // Record in history
        $this->recordStatusHistory(
            $serviceOrderId,
            $locationId,
            $wageMonth,
            'rejected',
            $remarks,
            $reviewedBy
        );

        return $status;
    }

    /**
     * Return SO-Location status for correction (soft reject)
     * Transitions: submitted -> returned
     * 
     * @param int $serviceOrderId
     * @param int $locationId
     * @param Carbon $wageMonth
     * @param int $reviewedBy User ID of reviewer
     * @param string $remarks Reason for return/correction needed
     */
    public function returnSoLocationStatus(
        int $serviceOrderId,
        int $locationId,
        Carbon $wageMonth,
        int $reviewedBy,
        string $remarks
    ): \App\Models\SoLocationMonthlyStatus {
        $wageMonthStr = $wageMonth->format('Y-m');

        $status = \App\Models\SoLocationMonthlyStatus::firstForUpdate()
            ->where('service_order_id', $serviceOrderId)
            ->where('location_id', $locationId)
            ->where('wage_month', $wageMonthStr)
            ->firstOrFail();

        $status->update([
            'status' => 'returned',
            'reviewer_remarks' => $remarks,
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
        ]);

        // Record in history
        $this->recordStatusHistory(
            $serviceOrderId,
            $locationId,
            $wageMonth,
            'returned',
            $remarks,
            $reviewedBy
        );

        return $status;
    }

    /**
     * Record status action in history table
     * Maintains audit trail for all workflow actions
     * 
     * @param int $serviceOrderId
     * @param int $locationId
     * @param Carbon $wageMonth
     * @param string $status The status after this action
     * @param string|null $remarks Details about the action
     * @param int $actionBy User ID performing the action
     */
    public function recordStatusHistory(
        int $serviceOrderId,
        int $locationId,
        Carbon $wageMonth,
        string $status,
        ?string $remarks = null,
        int $actionBy = null
    ): \App\Models\SoLocationStatusHistory {
        $wageMonthStr = $wageMonth->format('Y-m');

        return \App\Models\SoLocationStatusHistory::create([
            'service_order_id' => $serviceOrderId,
            'location_id' => $locationId,
            'wage_month' => $wageMonthStr,
            'status' => $status,
            'remarks' => $remarks,
            'action_by' => $actionBy,
            'action_at' => now(),
        ]);
    }

    /**
     * Get status history for SO-Location-Month
     * Returns timeline of all actions in chronological order
     */
    public function getSoLocationStatusHistory(
        int $serviceOrderId,
        int $locationId,
        Carbon $wageMonth
    ): Collection {
        return \App\Models\SoLocationStatusHistory::forSoLocationMonth(
            $serviceOrderId,
            $locationId,
            $wageMonth
        )->get();
    }
}
