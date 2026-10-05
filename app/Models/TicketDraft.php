<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reviewable ticket request owned by the requester. Confirmed at most once
 * (tickets.draft_id unique); expires 15 minutes after the last edit.
 */
#[Fillable([
    'public_id', 'requester_id', 'conversation_id', 'originating_turn_id', 'team_id', 'category',
    'subject', 'description', 'revision', 'payload_fingerprint', 'expires_at', 'consumed_ticket_id',
])]
class TicketDraft extends Model
{
    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'expires_at' => 'immutable_datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * @return BelongsTo<SupportTeam, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(SupportTeam::class, 'team_id');
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function consumedTicket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'consumed_ticket_id');
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
