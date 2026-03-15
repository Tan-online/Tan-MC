<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportBatch extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'status',
        'disk',
        'stored_path',
        'original_file_name',
        'inserted_rows',
        'failed_rows',
        'failure_report',
        'error_message',
        'completed_at',
    ];

    protected $casts = [
        'failure_report' => 'array',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}