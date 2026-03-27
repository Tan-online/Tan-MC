<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    public function auditModule(): string
    {
        return 'locations';
    }

    protected $fillable = [
        'client_id',
        'state_id',
        'operation_area_id',
        'code',
        'name',
        'city',
        'address',
        'postal_code',
        'is_active',
        'start_date',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'start_date' => 'date',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function operationArea(): BelongsTo
    {
        return $this->belongsTo(OperationArea::class);
    }

    public function contracts(): BelongsToMany
    {
        return $this->belongsToMany(Contract::class, 'contract_location')
            ->withTimestamps();
    }

    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }

    public function executiveMappings(): HasMany
    {
        return $this->hasMany(ExecutiveMapping::class);
    }

    public function musterExpected(): HasMany
    {
        return $this->hasMany(MusterExpected::class);
    }

    public function monthlyStatuses(): HasMany
    {
        return $this->hasMany(LocationMonthlyStatus::class);
    }
}
