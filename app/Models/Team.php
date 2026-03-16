<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $fillable = [
        'name',
        'code',
        'department_id',
        'operation_area_id',
        'operation_executive_id',
        'manager_id',
        'hod_id',
        'lead_name',
        'members_count',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'members_count' => 'integer',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function operationArea(): BelongsTo
    {
        return $this->belongsTo(OperationArea::class);
    }

    public function operationExecutive(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operation_executive_id');
    }

    public function executives(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_executives', 'team_id', 'user_id')
            ->withPivot('is_primary')
            ->withTimestamps()
            ->orderByPivot('is_primary', 'desc')
            ->orderBy('name');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function hod(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hod_id');
    }

    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }
}
