<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends Model
{
    public const TYPE_FILE = 'file';

    public const TYPE_MCQ = 'mcq';

    public const TYPE_BOTH = 'both';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'course_id',
        'lesson_id',
        'title',
        'submission_type',
        'instructions',
        'pass_mark',
        'due_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pass_mark' => 'integer',
            'due_at' => 'datetime',
        ];
    }

    /**
     * @return list<string>
     */
    public static function submissionTypes(): array
    {
        return [self::TYPE_FILE, self::TYPE_MCQ, self::TYPE_BOTH];
    }

    public function supportsFile(): bool
    {
        return in_array($this->submission_type, [self::TYPE_FILE, self::TYPE_BOTH], true);
    }

    public function supportsMcq(): bool
    {
        return in_array($this->submission_type, [self::TYPE_MCQ, self::TYPE_BOTH], true);
    }

    public function typeLabel(): string
    {
        return match ($this->submission_type) {
            self::TYPE_MCQ => __('messages.assignment_type_mcq'),
            self::TYPE_BOTH => __('messages.assignment_type_both'),
            default => __('messages.assignment_type_file'),
        };
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
        return $this->hasMany(AssignmentQuestion::class)->orderBy('position')->orderBy('id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class)->latest('submitted_at');
    }
}
