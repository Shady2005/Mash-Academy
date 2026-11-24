<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    public const FEATURE_COURSE_DESCRIPTION = 'course_description';
    public const FEATURE_QUIZ_GENERATOR = 'quiz_generator';
    public const FEATURE_ADMIN_SANDBOX = 'admin_sandbox';
    public const FEATURE_STUDENT_TUTOR = 'student_tutor';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'course_id',
        'lesson_id',
        'feature',
        'provider',
        'model',
        'prompt_excerpt',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public static function studentTutorCountToday(User $user): int
    {
        return static::query()
            ->where('user_id', $user->id)
            ->where('feature', self::FEATURE_STUDENT_TUTOR)
            ->where('status', 'success')
            ->whereDate('created_at', now()->toDateString())
            ->count();
    }
}
