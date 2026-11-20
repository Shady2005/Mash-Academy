<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class FinalProjectSubmission extends Model
{
    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_REVIEWED = 'reviewed';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'final_project_id',
        'user_id',
        'file_path',
        'file_original_name',
        'notes',
        'status',
        'grade',
        'feedback',
        'reviewed_by',
        'submitted_at',
        'reviewed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grade' => 'decimal:2',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function hasFile(): bool
    {
        return ! empty($this->file_path) && Storage::disk('local')->exists($this->file_path);
    }

    public function finalProject(): BelongsTo
    {
        return $this->belongsTo(FinalProject::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }
}
