<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedExport extends Model
{
    protected $fillable = [
        'user_id',
        'category',
        'type',
        'format',
        'status',
        'disk',
        'path',
        'file_name',
        'filters',
        'record_count',
        'error_message',
        'completed_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}