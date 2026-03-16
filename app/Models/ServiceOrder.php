<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class ServiceOrder extends Model
{
    public function auditModule(): string
    {
        return 'service_orders';
    }

    protected $fillable = [
        'contract_id',
        'location_id',
        'team_id',
        'operation_executive_id',
        'order_no',
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

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
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
            ->withPivot(['start_date', 'end_date'])
            ->withTimestamps();
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
