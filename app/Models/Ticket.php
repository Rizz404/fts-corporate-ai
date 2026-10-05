<?php

namespace App\Models;

use App\Modules\Support\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * HR/IT ticket. Readable only by the requester or active staff of its team.
 * `reference` is a display label, never an authorization identifier.
 */
#[Fillable([
    'public_id', 'reference', 'requester_id', 'team_id', 'category', 'subject', 'description',
    'assignee_id', 'status', 'version', 'draft_id', 'conversation_id', 'resolved_at', 'closed_at',
])]
class Ticket extends Model
{
    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'version' => 'integer',
            'resolved_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * @return BelongsTo<SupportTeam, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(SupportTeam::class, 'team_id');
    }

    /**
     * @return HasMany<TicketMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }

    /**
     * @return HasMany<TicketEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(TicketEvent::class);
    }

    /**
     * @return BelongsTo<TicketDraft, $this>
     */
    public function draft(): BelongsTo
    {
        return $this->belongsTo(TicketDraft::class, 'draft_id');
    }
}
