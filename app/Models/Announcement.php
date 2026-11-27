<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\App;

class Announcement extends Model
{
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_IMPORTANT = 'important';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'course_id',
        'title_en',
        'title_ar',
        'body_en',
        'body_ar',
        'priority',
        'is_active',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(function (Builder $q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('course_id');
    }

    public function scopeForCourse(Builder $query, int $courseId): Builder
    {
        return $query->where('course_id', $courseId);
    }

    public function isGlobal(): bool
    {
        return $this->course_id === null;
    }

    public function isImportant(): bool
    {
        return $this->priority === self::PRIORITY_IMPORTANT;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function localizedTitle(?string $locale = null): string
    {
        $lang = $locale ?? App::getLocale();
        if ($lang === 'ar' && ! empty($this->title_ar)) {
            return $this->title_ar;
        }

        return $this->title_en;
    }

    public function localizedBody(?string $locale = null): string
    {
        $lang = $locale ?? App::getLocale();
        if ($lang === 'ar' && ! empty($this->body_ar)) {
            return $this->body_ar;
        }

        return $this->body_en;
    }
}
