<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceOrderLocation extends Model
{
    protected $table = 'service_order_location';

    protected $fillable = [
        'service_order_id',
        'location_id',
        'start_date',
        'end_date',
        'operation_executive_id',
        'muster_due_days',
        'wage_month',
        'dispatched_by_user_id',
        'dispatched_at',
        'status',
        'type',
        'remarks',
        'action_date',
        'action_by_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'wage_month' => 'date',
        'dispatched_at' => 'datetime',
        'period_start_date' => 'date',
        'period_end_date' => 'date',
        'received_at' => 'datetime',
        'action_taken_at' => 'datetime',
        'action_date' => 'datetime',
        'muster_due_days' => 'integer',
    ];

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function executive(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operation_executive_id');
    }

    public function dispatchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by_user_id');
    }

    public function actionBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'action_by_id');
    }

    public function statusHistory()
    {
        return $this->hasMany(ServiceOrderLocationStatusHistory::class, 'service_order_location_id')->latest('created_at');
    }
}
