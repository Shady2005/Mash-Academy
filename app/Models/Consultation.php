<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consultation extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const PRICING_FREE = 'free';

    public const PRICING_PAID = 'paid';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'instructor_id',
        'preferred_date',
        'preferred_time',
        'duration_minutes',
        'pricing_mode',
        'price',
        'currency',
        'topic',
        'notes',
        'status',
        'meeting_link',
        'admin_note',
        'payment_request_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'preferred_date' => 'date',
            'duration_minutes' => 'integer',
            'price' => 'decimal:2',
        ];
    }

    public function isPaid(): bool
    {
        return $this->pricing_mode === self::PRICING_PAID && (float) $this->price > 0;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id')->withTrashed();
    }

    public function paymentRequest(): BelongsTo
    {
        return $this->belongsTo(PaymentRequest::class);
    }
}
