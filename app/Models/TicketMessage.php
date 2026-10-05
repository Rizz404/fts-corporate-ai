<?php

namespace App\Models;

use App\Modules\Support\Enums\MessageVisibility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Plain-text ticket message. Internal notes are staff-only and never shown
 * to the requester or sent to the model.
 */
#[Fillable(['ticket_id', 'author_id', 'visibility', 'text', 'created_at'])]
class TicketMessage extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'visibility' => MessageVisibility::class,
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
