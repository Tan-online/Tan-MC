<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MusterExpected extends Model
{
    protected $table = 'muster_expected';

    public function auditModule(): string
    {
        return 'muster_roll';
    }

    public function auditExcludedAttributes(): array
    {
        return ['updated_at', 'last_action_at'];
    }

    protected $fillable = [
        'muster_cycle_id',
        'contract_id',
        'location_id',
        'executive_mapping_id',
        'acted_by_user_id',
        'status',
        'received_via',
        'received_at',
        'approved_at',
        'final_closed_at',
        'final_closed_by_user_id',
        'returned_at',
        'last_action_at',
        'remarks',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'approved_at' => 'datetime',
        'final_closed_at' => 'datetime',
        'returned_at' => 'datetime',
        'last_action_at' => 'datetime',
    ];

    public function musterCycle(): BelongsTo
    {
        return $this->belongsTo(MusterCycle::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function executiveMapping(): BelongsTo
    {
        return $this->belongsTo(ExecutiveMapping::class);
    }

    public function actedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by_user_id');
    }

    public function finalClosedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'final_closed_by_user_id');
    }

    public function receiptHistory(): HasMany
    {
        return $this->hasMany(MusterReceived::class);
    }
}
