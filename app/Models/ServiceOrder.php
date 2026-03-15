<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'order_no',
        'requested_date',
        'scheduled_date',
        'period_start_date',
        'period_end_date',
        'muster_cycle_type',
        'muster_due_days',
        'auto_generate_muster',
        'status',
        'priority',
        'amount',
        'remarks',
    ];

    protected $casts = [
        'requested_date' => 'date',
        'scheduled_date' => 'date',
        'period_start_date' => 'date',
        'period_end_date' => 'date',
        'muster_due_days' => 'integer',
        'auto_generate_muster' => 'boolean',
        'amount' => 'decimal:2',
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

    public function musterCycles(): HasMany
    {
        return $this->hasMany(MusterCycle::class);
    }

    public function dispatchEntries(): HasMany
    {
        return $this->hasMany(DispatchEntry::class);
    }
}
