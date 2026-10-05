<?php

namespace App\Modules\Conversation\Support;

use App\Models\AiTurn;
use App\Models\ConversationMessage;
use App\Modules\Conversation\Enums\TurnStatus;

/**
 * Shapes turn and message data for the API (TECHNICAL_CONTRACTS turn data).
 */
final class TurnPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function turn(AiTurn $turn): array
    {
        /** @var ConversationMessage|null $answer */
        $answer = $turn->status === TurnStatus::Processing
            ? null
            : $turn->messages()->where('role', 'assistant')->orderByDesc('id')->first();
        $ui = $answer?->ui ?? [];

        return [
            'turn_id' => $turn->public_id,
            'status' => $turn->status->value,
            'message' => $answer === null ? null : [
                'id' => $answer->id,
                'role' => 'assistant',
                'text' => $answer->text,
            ],
            'sources' => $ui['sources'] ?? [],
            'ui' => $ui['ui'] ?? [],
            'actions' => $ui['actions'] ?? [],
            'operation' => null,
            'error_code' => $turn->status === TurnStatus::Failed ? $turn->error_code : null,
        ];
    }

    /**
     * @param  'ok'|'stale'|'unavailable'  $availability
     * @return array<string, mixed>
     */
    public static function message(ConversationMessage $message, string $availability = 'ok'): array
    {
        $ui = $message->ui ?? [];
        $unavailable = $availability === 'unavailable';

        return [
            'id' => $message->id,
            'role' => $message->role,
            'text' => $unavailable ? __('ui.assistant.history_unavailable') : $message->text,
            'turn_id' => $message->turn?->public_id,
            'sources' => $unavailable ? [] : ($ui['sources'] ?? []),
            'ui' => $unavailable ? [] : ($ui['ui'] ?? []),
            'actions' => $unavailable ? [] : ($ui['actions'] ?? []),
            'stale' => $availability === 'stale',
            'unavailable' => $unavailable,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }
}
