<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Certificate extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'course_id',
        'final_project_submission_id',
        'certificate_code',
        'issued_at',
        'revoked_at',
        'revoked_by',
        'revocation_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public static function generateUniqueCode(): string
    {
        do {
            $code = 'MA-'.date('Y').'-'.strtoupper(Str::random(8));
        } while (static::query()->where('certificate_code', $code)->exists());

        return $code;
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isValid(): bool
    {
        return ! $this->isRevoked();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }

    public function finalProjectSubmission(): BelongsTo
    {
        return $this->belongsTo(FinalProjectSubmission::class);
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by')->withTrashed();
    }
}
