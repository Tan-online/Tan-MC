<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    protected static function booted(): void
    {
        static::saved(function (Contract $contract): void {
            if ($contract->location_id) {
                $contract->locations()->syncWithoutDetaching([$contract->location_id]);
            }
        });
    }

    protected $fillable = [
        'client_id',
        'location_id',
        'contract_no',
        'start_date',
        'end_date',
        'contract_value',
        'status',
        'scope',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'contract_value' => 'decimal:2',
    ];

    public function auditModule(): string
    {
        return 'contracts';
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'contract_location')
            ->withTimestamps();
    }

    public function executiveMappings(): HasMany
    {
        return $this->hasMany(ExecutiveMapping::class);
    }

    public function musterCycles(): HasMany
    {
        return $this->hasMany(MusterCycle::class);
    }
}
