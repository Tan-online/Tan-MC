<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SoLocationMonthlyStatus extends Model
{
    protected $table = 'so_location_monthly_status';

    protected $fillable = [
        'service_order_id',
        'location_id',
        'wage_month',
        'status',
        'submission_type',
        'file_path',
        'remarks',
        'reviewer_remarks',
        'submitted_by',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
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

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // Scopes

    /**
     * Scope: Get records for a specific month
     */
    public function scopeForMonth($query, $wageMonth)
    {
        $monthStr = $wageMonth instanceof \Carbon\Carbon 
            ? $wageMonth->format('Y-m')
            : $wageMonth;
        
        return $query->where('wage_month', $monthStr);
    }

    /**
     * Scope: Get records for service order + location + month combination
     */
    public function scopeForSoLocation($query, $serviceOrderId, $locationId, $wageMonth = null)
    {
        $query->where('service_order_id', $serviceOrderId)
            ->where('location_id', $locationId);
        
        if ($wageMonth) {
            $monthStr = $wageMonth instanceof \Carbon\Carbon 
                ? $wageMonth->format('Y-m')
                : $wageMonth;
            $query->where('wage_month', $monthStr);
        }
        
        return $query;
    }

    /**
     * Scope: Get records by status
     */
    public function scopeByStatus($query, $status)
    {
        if (is_array($status)) {
            return $query->whereIn('status', $status);
        }
        
        return $query->where('status', $status);
    }

    /**
     * Get wage month as formatted string
     */
    public function getFormattedWageMonthAttribute(): string
    {
        return \Carbon\Carbon::createFromFormat('Y-m', $this->wage_month)->format('M Y');
    }

    /**
     * Check if status is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if status is submitted
     */
    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }

    /**
     * Check if status is approved
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if status is rejected
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Check if status is returned
     */
    public function isReturned(): bool
    {
        return $this->status === 'returned';
    }

    /**
     * Can resubmit - true if rejected or returned
     */
    public function canResubmit(): bool
    {
        return in_array($this->status, ['rejected', 'returned']);
    }

    /**
     * Get status label
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'Pending',
            'submitted' => 'Submitted',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'returned' => 'Returned',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get status badge CSS class
     */
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

    /**
     * Scope: Get pending statuses
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Get submitted statuses
     */
    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    /**
     * Scope: Get approved statuses
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope: Get rejected statuses
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Scope: Get returned statuses
     */
    public function scopeReturned($query)
    {
        return $query->where('status', 'returned');
    }
}
