<?php

namespace App\Modules\Assistant\Tools;

use App\Models\AiTurn;
use App\Models\Conversation;

/**
 * Turn data a tool may use: the turn (for per-turn idempotency), its
 * conversation, and the active locale.
 */
final class ToolContext
{
    public function __construct(
        public readonly AiTurn $turn,
        public readonly Conversation $conversation,
        public readonly string $locale,
    ) {}
}
