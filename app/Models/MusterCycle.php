<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MusterCycle extends Model
{
    protected $fillable = [
        'contract_id',
        'service_order_id',
        'month',
        'year',
        'cycle_type',
        'cycle_label',
        'cycle_start_date',
        'cycle_end_date',
        'due_date',
        'generated_at',
    ];

    protected $casts = [
        'cycle_start_date' => 'date',
        'cycle_end_date' => 'date',
        'due_date' => 'date',
        'generated_at' => 'datetime',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function expectedEntries(): HasMany
    {
        return $this->hasMany(MusterExpected::class);
    }
}
