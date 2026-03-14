<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class ExecutiveMapping extends Model
{
    protected $fillable = [
        'client_id',
        'contract_id',
        'location_id',
        'operation_area_id',
        'executive_user_id',
        'executive_name',
        'designation',
        'email',
        'phone',
        'is_primary',
        'is_active',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function operationArea(): BelongsTo
    {
        return $this->belongsTo(OperationArea::class);
    }

    public function executiveUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executive_user_id');
    }

    public function musterExpected(): HasMany
    {
        return $this->hasMany(MusterExpected::class);
    }
}
