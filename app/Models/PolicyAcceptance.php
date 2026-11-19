<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PolicyAcceptance extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'policy_id',
        'policy_version',
        'accepted_at',
        'ip_address',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'policy_version' => 'integer',
            'accepted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(Policy::class);
    }
}
