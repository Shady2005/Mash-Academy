<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class PaymentRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /**
     * Explicitly exclude `user_id`, `status`, `reviewed_by`, `reviewed_at` from mass assignment.
     *
     * @var list<string>
     */
    protected $fillable = [
        'course_id',
        'course_package_id',
        'promo_code_id',
        'promo_code_snapshot',
        'original_price',
        'discount_amount',
        'final_price',
        'currency',
        'name',
        'email',
        'phone',
        'screenshot_path',
        'admin_note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'original_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'final_price' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function hasScreenshot(): bool
    {
        return ! empty($this->screenshot_path) && Storage::disk('local')->exists($this->screenshot_path);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }

    public function coursePackage(): BelongsTo
    {
        return $this->belongsTo(CoursePackage::class, 'course_package_id')->withTrashed();
    }

    public function isPackagePayment(): bool
    {
        return $this->course_package_id !== null;
    }

    public function itemTitle(?string $locale = null): string
    {
        if ($this->isPackagePayment()) {
            return $this->coursePackage?->localizedTitle($locale) ?? 'Course Package';
        }

        return $this->course?->localizedTitle($locale) ?? 'Deleted Course';
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    public function enrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }
}
