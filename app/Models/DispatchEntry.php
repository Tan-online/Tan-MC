<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispatchEntry extends Model
{
    protected $fillable = [
        'service_order_id',
        'dispatched_by_user_id',
        'status',
        'dispatched_at',
        'remarks',
    ];

    protected $casts = [
        'dispatched_at' => 'datetime',
    ];

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function dispatchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by_user_id');
    }
}
