<?php

namespace App\Modules\Conversation\Support;

use App\Models\AiTurn;
use App\Models\ConversationMessage;
use App\Models\TurnEvidence;
use App\Modules\Conversation\Enums\TurnStatus;
use App\Modules\Knowledge\Data\KnowledgeEvidence;
use Illuminate\Support\Facades\DB;

/**
 * Writes the terminal state of a turn exactly once: assistant message,
 * evidence references (excerpt hash only), and status. Locks the turn row
 * and does nothing if the turn is no longer processing.
 */
final class TurnRecorder
{
    /**
     * @param  list<KnowledgeEvidence>  $evidence  Evidence referenced by the answer.
     * @param  array{sources: list<array<string, mixed>>, ui: list<array<string, mixed>>, actions: list<array<string, mixed>>}  $cards
     * @param  array{provider?: ?string, model?: ?string, usage?: array<string, int>}  $meta
     */
    public static function finish(
        AiTurn $turn,
        TurnStatus $status,
        string $text,
        array $cards,
        array $evidence = [],
        ?string $errorCode = null,
        array $meta = [],
    ): bool {
        return DB::transaction(function () use ($turn, $status, $text, $cards, $evidence, $errorCode, $meta) {
            /** @var AiTurn|null $locked */
            $locked = AiTurn::query()->whereKey($turn->id)->lockForUpdate()->first();
            if ($locked === null || $locked->status !== TurnStatus::Processing) {
                return false;
            }

            ConversationMessage::create([
                'conversation_id' => $locked->conversation_id,
                'turn_id' => $locked->id,
                'role' => 'assistant',
                'text' => $text,
                'ui' => $cards,
                'created_at' => now(),
            ]);

            foreach ($evidence as $item) {
                TurnEvidence::query()->firstOrCreate(
                    ['turn_id' => $locked->id, 'revision_id' => $item->revisionId, 'locale' => $item->locale],
                    ['excerpt_hash' => hash('sha256', $item->excerpt)],
                );
            }

            $locked->forceFill([
                'status' => $status,
                'error_code' => $errorCode,
                'finished_at' => now(),
                'provider' => $meta['provider'] ?? $locked->provider,
                'model' => $meta['model'] ?? $locked->model,
                'usage' => ($meta['usage'] ?? []) ?: $locked->usage,
            ])->save();

            $locked->conversation()->update(['last_activity_at' => now()]);
            $turn->setRawAttributes($locked->getAttributes(), true);

            return true;
        });
    }
}
