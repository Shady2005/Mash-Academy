<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonProgress extends Model
{
    protected $table = 'lesson_progress';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'lesson_id',
        'watched_seconds',
        'max_reached_seconds',
        'last_position_seconds',
        'duration_seconds',
        'last_heartbeat_at',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'watched_seconds' => 'integer',
            'max_reached_seconds' => 'integer',
            'last_position_seconds' => 'integer',
            'duration_seconds' => 'integer',
            'last_heartbeat_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
