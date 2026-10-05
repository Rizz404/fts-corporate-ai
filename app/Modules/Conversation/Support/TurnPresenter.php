<?php

namespace App\Modules\Conversation\Support;

use App\Models\AiTurn;
use App\Models\ConversationMessage;
use App\Models\TicketDraft;
use App\Modules\Conversation\Enums\TurnStatus;
use App\Modules\Support\Presenters\TicketCards;

/**
 * Shapes turn and message data for the API (TECHNICAL_CONTRACTS turn data).
 * Draft cards are re-read on output so a confirmed draft shows its ticket.
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
        $ui = TicketCards::refresh($answer?->ui ?? [], $turn->conversation->user_id);

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
            'operation' => self::operation($turn, $ui),
            'error_code' => $turn->status === TurnStatus::Failed ? $turn->error_code : null,
        ];
    }

    /**
     * @param  'ok'|'stale'|'unavailable'  $availability
     * @return array<string, mixed>
     */
    public static function message(ConversationMessage $message, int $userId, string $availability = 'ok'): array
    {
        $ui = TicketCards::refresh($message->ui ?? [], $userId);
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

    /**
     * Draft/ticket reference created by this turn. Falls back to the draft
     * row so the reference survives a turn that failed after saving it.
     *
     * @param  array<string, mixed>  $ui
     * @return array<string, mixed>|null
     */
    private static function operation(AiTurn $turn, array $ui): ?array
    {
        if ($turn->status === TurnStatus::Processing) {
            return null;
        }

        $draft = TicketDraft::query()
            ->where('originating_turn_id', $turn->id)
            ->where('requester_id', $turn->conversation->user_id)
            ->first();

        return $draft !== null ? TicketCards::operation($draft) : ($ui['operation'] ?? null);
    }
}
