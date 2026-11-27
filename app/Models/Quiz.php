<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'course_id',
        'lesson_id',
        'title',
        'description',
        'pass_mark',
        'is_required_for_next',
        'has_timer',
        'duration_minutes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pass_mark' => 'integer',
            'is_required_for_next' => 'boolean',
            'has_timer' => 'boolean',
            'duration_minutes' => 'integer',
        ];
    }

    public function isTimed(): bool
    {
        return $this->has_timer && (int) $this->duration_minutes > 0;
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('position')->orderBy('id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class)->latest('submitted_at');
    }
}
