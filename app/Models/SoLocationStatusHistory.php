<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SoLocationStatusHistory extends Model
{
    protected $table = 'so_location_status_history';

    protected $fillable = [
        'service_order_id',
        'location_id',
        'wage_month',
        'status',
        'remarks',
        'action_by',
        'action_at',
    ];

    protected $casts = [
        'action_at' => 'datetime',
    ];

    // Relationships

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function actionBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'action_by');
    }

    // Scopes

    /**
     * Get history records for a specific SO-Location-Month combination
     */
    public function scopeForSoLocationMonth($query, $serviceOrderId, $locationId, $wageMonth)
    {
        $monthStr = $wageMonth instanceof \Carbon\Carbon
            ? $wageMonth->format('Y-m')
            : $wageMonth;

        return $query
            ->where('service_order_id', $serviceOrderId)
            ->where('location_id', $locationId)
            ->where('wage_month', $monthStr)
            ->orderBy('action_at', 'asc');
    }

    /**
     * Get history records for a specific month
     */
    public function scopeForMonth($query, $wageMonth)
    {
        $monthStr = $wageMonth instanceof \Carbon\Carbon
            ? $wageMonth->format('Y-m')
            : $wageMonth;

        return $query->where('wage_month', $monthStr);
    }

    /**
     * Get history records by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Helper: Get history ordered newest first
     */
    public function scopeOrderByNewest($query)
    {
        return $query->orderBy('action_at', 'desc');
    }

    // Convenience methods

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isReturned(): bool
    {
        return $this->status === 'returned';
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'Pending',
            'submitted' => 'Submitted',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'returned' => 'Returned for Correction',
            default => ucfirst($this->status),
        };
    }

    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            'pending' => 'bg-warning text-dark',
            'submitted' => 'bg-info text-white',
            'approved' => 'bg-success text-white',
            'rejected' => 'bg-danger text-white',
            'returned' => 'bg-orange text-white',
            default => 'bg-secondary text-white',
        };
    }
}
