<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExecutiveReplacementHistory extends Model
{
    protected $fillable = [
        'client_id',
        'contract_id',
        'location_id',
        'old_executive_id',
        'new_executive_id',
        'replaced_by_user_id',
        'effective_date',
        'notes',
    ];

    protected $casts = [
        'effective_date' => 'date',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function oldExecutive(): BelongsTo
    {
        return $this->belongsTo(User::class, 'old_executive_id');
    }

    public function newExecutive(): BelongsTo
    {
        return $this->belongsTo(User::class, 'new_executive_id');
    }

    public function replacedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replaced_by_user_id');
    }
}
