<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Lesson extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'course_id',
        'title',
        'description',
        'position',
        'video_path',
        'video_original_name',
        'video_mime',
        'video_size_bytes',
        'duration_seconds',
        'is_published',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'video_size_bytes' => 'integer',
            'duration_seconds' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function progressRecords(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function hasVideo(): bool
    {
        return ! empty($this->video_path) && Storage::disk('local')->exists($this->video_path);
    }

    public function formattedDuration(): string
    {
        if (! $this->duration_seconds || $this->duration_seconds <= 0) {
            return '—';
        }

        $minutes = intdiv($this->duration_seconds, 60);
        $seconds = $this->duration_seconds % 60;

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    public function formattedFileSize(): string
    {
        if (! $this->video_size_bytes) {
            return '—';
        }

        if ($this->video_size_bytes >= 1048576) {
            return number_format($this->video_size_bytes / 1048576, 1).' MB';
        }

        return number_format($this->video_size_bytes / 1024, 1).' KB';
    }
}
