<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceOrderLocationStatusHistory extends Model
{
    protected $table = 'service_order_location_status_history';

    protected $fillable = [
        'service_order_location_id',
        'status',
        'remarks',
        'changed_by_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function serviceOrderLocation(): BelongsTo
    {
        return $this->belongsTo(ServiceOrderLocation::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_id');
    }
}
