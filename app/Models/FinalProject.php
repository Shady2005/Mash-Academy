<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class FinalProject extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'course_id',
        'title',
        'instructions',
        'attachment_path',
        'attachment_original_name',
        'required_completion_percentage',
        'is_published',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'required_completion_percentage' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function hasAttachment(): bool
    {
        return ! empty($this->attachment_path) && Storage::disk('local')->exists($this->attachment_path);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FinalProjectSubmission::class)->latest('submitted_at');
    }
}
