<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class ServiceOrder extends Model
{
    public const ACTIVE_STATUSES = ['Active', 'Open', 'Assigned', 'In Progress'];

    public const TERMINATED_STATUSES = ['Terminate', 'Completed', 'Cancelled'];

    public function auditModule(): string
    {
        return 'service_orders';
    }

    protected $fillable = [
        'contract_id',
        'state_id',
        'location_id',
        'team_id',
        'operation_executive_id',
        'order_no',
        'so_name',
        'requested_date',
        'scheduled_date',
        'period_start_date',
        'period_end_date',
        'muster_start_day',
        'muster_cycle_type',
        'muster_due_days',
        'auto_generate_muster',
        'status',
        'remarks',
    ];

    protected $casts = [
        'requested_date' => 'date',
        'scheduled_date' => 'date',
        'period_start_date' => 'date',
        'period_end_date' => 'date',
        'muster_start_day' => 'integer',
        'muster_due_days' => 'integer',
        'auto_generate_muster' => 'boolean',
    ];

    public static function displayStatusOptions(): array
    {
        return ['Active', 'Terminate'];
    }

    public static function allowedStatusValues(): array
    {
        return array_values(array_unique(array_merge(self::ACTIVE_STATUSES, self::TERMINATED_STATUSES)));
    }

    public static function filterStatusesFor(?string $status): array
    {
        $normalized = self::normalizeStatus($status);

        return $normalized === 'Terminate'
            ? self::TERMINATED_STATUSES
            : self::ACTIVE_STATUSES;
    }

    public static function normalizeStatus(?string $status): string
    {
        $normalized = strtolower(trim((string) $status));

        return match ($normalized) {
            '', 'active', 'open', 'assigned', 'in progress', 'in-progress' => 'Active',
            'terminate', 'terminated', 'completed', 'cancelled', 'canceled' => 'Terminate',
            default => 'Active',
        };
    }

    public function getDisplayStatusAttribute(): string
    {
        return self::normalizeStatus($this->status);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function operationExecutive(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operation_executive_id');
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'service_order_location')
            ->withPivot(['start_date', 'end_date', 'operation_executive_id', 'muster_due_days'])
            ->withTimestamps();
    }

    public function activeLocations(): BelongsToMany
    {
        return $this->locations()->where(function ($query) {
            $query
                ->whereNull('service_order_location.end_date')
                ->orWhereDate('service_order_location.end_date', '>=', now()->toDateString());
        });
    }

    public function syncSummaryFromLocationAssignments(): void
    {
        $primaryLocation = $this->activeLocations()
            ->orderByRaw(
                'case when service_order_location.start_date is null then 1 else 0 end'
            )
            ->orderBy('service_order_location.start_date')
            ->orderBy('locations.name')
            ->first();

        $this->forceFill([
            'location_id' => $primaryLocation?->id,
            'operation_executive_id' => $primaryLocation?->pivot?->operation_executive_id,
            'muster_due_days' => (int) ($primaryLocation?->pivot?->muster_due_days ?? 0),
        ])->saveQuietly();
    }

    public function musterCycles(): HasMany
    {
        return $this->hasMany(MusterCycle::class);
    }

    public function dispatchEntries(): HasMany
    {
        return $this->hasMany(DispatchEntry::class);
    }
}
