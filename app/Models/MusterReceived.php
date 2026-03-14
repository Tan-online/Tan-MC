<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MusterReceived extends Model
{
    protected $table = 'muster_received';

    protected $fillable = [
        'muster_expected_id',
        'action_by_user_id',
        'status',
        'receive_mode',
        'received_at',
        'remarks',
    ];

    protected $casts = [
        'received_at' => 'datetime',
    ];

    public function musterExpected(): BelongsTo
    {
        return $this->belongsTo(MusterExpected::class);
    }

    public function actionBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'action_by_user_id');
    }
}
