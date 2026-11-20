<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrollment extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REVOKED = 'revoked';

    public const UNIT_DAYS = 'days';

    public const UNIT_MONTHS = 'months';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'course_id',
        'payment_request_id',
        'starts_at',
        'expires_at',
        'duration_value',
        'duration_unit',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'duration_value' => 'integer',
        ];
    }

    /**
     * Request-time access check: valid ONLY when status = active AND starts_at <= now <= expires_at.
     */
    public function isCurrentlyActive(?CarbonInterface $at = null): bool
    {
        $now = $at ?? now();

        return $this->status === self::STATUS_ACTIVE
            && $this->starts_at !== null
            && $this->expires_at !== null
            && $this->starts_at->lte($now)
            && $this->expires_at->gte($now);
    }

    public function effectiveStatus(?CarbonInterface $at = null): string
    {
        $now = $at ?? now();

        if ($this->status === self::STATUS_REVOKED) {
            return self::STATUS_REVOKED;
        }

        if ($this->status === self::STATUS_EXPIRED || ($this->expires_at && $this->expires_at->lt($now))) {
            return self::STATUS_EXPIRED;
        }

        return self::STATUS_ACTIVE;
    }

    public function remainingDays(?CarbonInterface $at = null): int
    {
        $now = $at ?? now();
        if (! $this->isCurrentlyActive($now)) {
            return 0;
        }

        return max(0, (int) ceil($now->diffInSeconds($this->expires_at, false) / 86400));
    }

    public function scopeCurrentlyActive(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $now = $at ?? now();

        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->where('starts_at', '<=', $now)
            ->where('expires_at', '>=', $now);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }

    public function paymentRequest(): BelongsTo
    {
        return $this->belongsTo(PaymentRequest::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
