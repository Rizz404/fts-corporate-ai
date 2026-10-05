<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * AI conversation owned by one user. Exposed only by public_id; only the
 * owner can read it (ConversationPolicy, 404 otherwise).
 */
#[Fillable(['public_id', 'user_id', 'locale', 'scene_context', 'context_revision', 'last_activity_at'])]
class Conversation extends Model
{
    protected function casts(): array
    {
        return [
            'scene_context' => 'array',
            'context_revision' => 'integer',
            'last_activity_at' => 'immutable_datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<AiTurn, $this>
     */
    public function turns(): HasMany
    {
        return $this->hasMany(AiTurn::class);
    }

    /**
     * @return HasMany<ConversationMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
    }
}
