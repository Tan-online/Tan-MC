<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class OperationArea extends Model
{
    protected $fillable = [
        'name',
        'code',
        'state_id',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function executiveMappings(): HasMany
    {
        return $this->hasMany(ExecutiveMapping::class);
    }
}
