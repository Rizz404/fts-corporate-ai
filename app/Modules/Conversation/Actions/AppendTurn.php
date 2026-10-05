<?php

namespace App\Modules\Conversation\Actions;

use App\Models\AiTurn;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use App\Modules\Conversation\Enums\TurnStatus;
use App\Modules\Conversation\Support\Fingerprint;
use App\Modules\Conversation\Support\TurnLease;
use App\Modules\Knowledge\KnowledgeRetriever;
use App\Support\Http\ApiException;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Accepts a user message as a new turn, or returns the existing turn for a
 * retried client_turn_id. Same key with a different payload is a 409.
 * Acquires the user + conversation lease before persisting; the caller
 * must pass the lease to ExecuteTurn (which releases it).
 */
class AppendTurn
{
    public function __construct(
        private readonly KnowledgeRetriever $retriever,
        private readonly RecoverStaleTurn $recover,
    ) {}

    /**
     * @param  array{client_turn_id: string, message: string, locale: string, context: array{scene: string, article_id: ?int, ticket_public_id: ?string}}  $input
     * @return array{turn: AiTurn, created: bool, lease: ?TurnLease, article_title: ?string}
     */
    public function handle(User $user, Conversation $conversation, array $input): array
    {
        $this->recover->forConversation($conversation);

        $fingerprint = Fingerprint::of([
            'conversation' => $conversation->public_id,
            'message' => $input['message'],
            'locale' => $input['locale'],
            'context' => $input['context'],
        ]);

        if ($existing = $this->existing($conversation, $input['client_turn_id'], $fingerprint)) {
            return ['turn' => $existing, 'created' => false, 'lease' => null, 'article_title' => null];
        }

        $articleTitle = null;
        if ($input['context']['article_id'] !== null) {
            $revision = $this->retriever->find($user, (int) $input['context']['article_id']);
            if ($revision === null) {
                throw new ApiException('invalid_context', 422, ['context.article_id' => [__('ui.errors.invalid_context')]]);
            }
            $articleTitle = $revision->translation($input['locale'])?->title;
        }

        $lease = TurnLease::acquire($user->id, $conversation->id);
        if ($lease === null) {
            throw new ApiException('turn_in_progress', 409);
        }

        try {
            $turn = DB::transaction(function () use ($conversation, $input, $fingerprint) {
                $now = CarbonImmutable::now();

                $turn = AiTurn::create([
                    'public_id' => (string) Str::uuid(),
                    'conversation_id' => $conversation->id,
                    'client_turn_id' => $input['client_turn_id'],
                    'request_fingerprint' => $fingerprint,
                    'status' => TurnStatus::Processing,
                    'locale' => $input['locale'],
                    'context_snapshot' => $input['context'],
                    'started_at' => $now,
                    'deadline_at' => $now->addSeconds((int) config('assistant.deadline_seconds')),
                    'prompt_version' => config('assistant.prompt_version'),
                ]);

                ConversationMessage::create([
                    'conversation_id' => $conversation->id,
                    'turn_id' => $turn->id,
                    'role' => 'user',
                    'text' => $input['message'],
                    'created_at' => $now,
                ]);

                // Locale is the latest one used; context is replaced (null clears).
                $changed = $conversation->scene_context !== $input['context'];
                $conversation->forceFill([
                    'locale' => $input['locale'],
                    'scene_context' => $input['context'],
                    'context_revision' => $conversation->context_revision + ($changed ? 1 : 0),
                    'last_activity_at' => $now,
                ])->save();

                return $turn;
            });
        } catch (UniqueConstraintViolationException) {
            // A concurrent request with the same client_turn_id won the insert.
            $lease->release();
            $existing = $this->existing($conversation, $input['client_turn_id'], $fingerprint);

            return ['turn' => $existing ?? abort(409), 'created' => false, 'lease' => null, 'article_title' => null];
        } catch (\Throwable $e) {
            $lease->release();

            throw $e;
        }

        return ['turn' => $turn, 'created' => true, 'lease' => $lease, 'article_title' => $articleTitle];
    }

    private function existing(Conversation $conversation, string $clientTurnId, string $fingerprint): ?AiTurn
    {
        $turn = AiTurn::query()
            ->where('conversation_id', $conversation->id)
            ->where('client_turn_id', $clientTurnId)
            ->first();

        if ($turn !== null && ! hash_equals($turn->request_fingerprint, $fingerprint)) {
            throw new ApiException('idempotency_conflict', 409);
        }

        if ($turn !== null) {
            $this->recover->ifStale($turn);
            $turn->refresh();
        }

        return $turn;
    }
}
