<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationMonthlyStatus extends Model
{
    protected $table = 'location_monthly_status';

    protected $fillable = [
        'location_id',
        'wage_month',
        'status',
        'submission_type',
        'file_path',
        'remarks',
        'submitted_by',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'wage_month' => 'date',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const SUBMISSION_HARD_COPY = 'hard_copy';
    public const SUBMISSION_EMAIL = 'email';
    public const SUBMISSION_COURIER = 'courier';
    public const SUBMISSION_UPLOAD = 'soft_copy_upload';

    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_SUBMITTED => 'Submitted',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
        ];
    }

    public static function getSubmissionTypes(): array
    {
        return [
            self::SUBMISSION_HARD_COPY => 'Hard Copy',
            self::SUBMISSION_EMAIL => 'Email',
            self::SUBMISSION_COURIER => 'Courier',
            self::SUBMISSION_UPLOAD => 'Soft Copy Upload',
        ];
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

    /**
     * Get formatted month label
     */
    public function getMonthLabel(): string
    {
        return $this->wage_month->format('M Y');
    }

    /**
     * Check if location is active for this month
     */
    public function isLocationActive(): bool
    {
        $startDate = $this->location->start_date ?? null;
        
        if ($startDate === null) {
            return true;
        }

        return $startDate->lessThanOrEqualTo($this->wage_month->endOfMonth());
    }

    /**
     * Get or create monthly status for location
     */
    public static function getOrCreateForMonth(int $locationId, $wageMonth): self
    {
        return self::firstOrCreate(
            [
                'location_id' => $locationId,
                'wage_month' => $wageMonth,
            ],
            [
                'status' => self::STATUS_PENDING,
            ]
        );
    }

    /**
     * Batch get monthly statuses for locations
     */
    public static function getForLocationsAndMonth(array $locationIds, $wageMonth)
    {
        return self::whereIn('location_id', $locationIds)
            ->where('wage_month', $wageMonth)
            ->get()
            ->keyBy('location_id');
    }
}
