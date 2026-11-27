<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportMessage extends Model
{
    public const SENDER_USER = 'user';
    public const SENDER_GUEST = 'guest';
    public const SENDER_STAFF = 'staff';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'conversation_id',
        'sender_id',
        'sender_type',
        'message',
        'is_read',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SupportConversation::class, 'conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id')->withTrashed();
    }

    public function isFromStaff(): bool
    {
        return $this->sender_type === self::SENDER_STAFF;
    }

    public function senderDisplayName(): string
    {
        if ($this->isFromStaff()) {
            return $this->sender?->name ? $this->sender->name.' (Support)' : 'Mash Academy Support';
        }

        if ($this->sender) {
            return $this->sender->name;
        }

        return $this->conversation?->guest_name ?: 'Guest';
    }
}
