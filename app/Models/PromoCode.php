<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromoCode extends Model
{
    public const TYPE_PERCENTAGE = 'percentage';

    public const TYPE_FIXED = 'fixed';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'discount_type',
        'discount_value',
        'max_uses',
        'used_count',
        'expires_at',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'max_uses' => 'integer',
            'used_count' => 'integer',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = strtoupper(trim($value));
    }

    public function isExpired(?CarbonInterface $at = null): bool
    {
        if (! $this->expires_at) {
            return false;
        }

        return $this->expires_at->lt($at ?? now());
    }

    public function isUsageLimitReached(): bool
    {
        if ($this->max_uses === null) {
            return false;
        }

        return $this->used_count >= $this->max_uses;
    }

    public function isUsable(?CarbonInterface $at = null): bool
    {
        return $this->is_active && ! $this->isExpired($at) && ! $this->isUsageLimitReached();
    }

    public function statusLabel(): string
    {
        if (! $this->is_active) {
            return 'Disabled';
        }
        if ($this->isExpired()) {
            return 'Expired';
        }
        if ($this->isUsageLimitReached()) {
            return 'Limit Reached';
        }

        return 'Active';
    }

    public function formattedDiscount(): string
    {
        $val = (float) $this->discount_value;
        $display = fmod($val, 1.0) === 0.0 ? number_format($val, 0) : number_format($val, 2);

        return $this->discount_type === self::TYPE_PERCENTAGE
            ? "{$display}%"
            : "{$display} EGP";
    }

    public function calculateDiscount(float $originalPrice): float
    {
        $price = max(0.0, $originalPrice);

        if ($this->discount_type === self::TYPE_PERCENTAGE) {
            $pct = min(100.0, max(0.0, (float) $this->discount_value));

            return round(($price * $pct) / 100, 2);
        }

        return round(min($price, max(0.0, (float) $this->discount_value)), 2);
    }

    public function calculateFinalPrice(float $originalPrice): float
    {
        $discount = $this->calculateDiscount($originalPrice);

        return round(max(0.0, $originalPrice - $discount), 2);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function paymentRequests(): HasMany
    {
        return $this->hasMany(PaymentRequest::class);
    }
}
