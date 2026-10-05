<?php

namespace App\Modules\Conversation\Actions;

use App\Models\AiTurn;
use App\Models\User;
use App\Modules\Assistant\Gateway\ChatCompletionRequest;
use App\Modules\Assistant\Gateway\GatewayException;
use App\Modules\Assistant\Gateway\ModelGateway;
use App\Modules\Assistant\Gateway\TurnDeadline;
use App\Modules\Assistant\Prompt\AnswerValidator;
use App\Modules\Assistant\Prompt\PromptComposer;
use App\Modules\Assistant\Tools\ToolRegistry;
use App\Modules\Conversation\Enums\TurnStatus;
use App\Modules\Conversation\Support\QuickReplies;
use App\Modules\Conversation\Support\TurnCards;
use App\Modules\Conversation\Support\TurnLease;
use App\Modules\Conversation\Support\TurnRecorder;
use App\Modules\Experience\Enums\SceneCode;
use App\Modules\Identity\ActorContext;
use App\Modules\Knowledge\Data\KnowledgeEvidence;
use App\Modules\Knowledge\KnowledgeRetriever;
use App\Modules\Knowledge\Queries\VisibleRevisions;
use Illuminate\Support\Facades\Log;

/**
 * Runs one AI turn outside any database transaction:
 *
 *  1. Deterministic replies for greetings/thanks/navigation (no model).
 *  2. Retrieval (max 5 evidence) scoped to the user; context article first.
 *  3. No evidence → no-source fallback without calling the model.
 *  4. Provider disabled → ai_unavailable fallback with the related articles.
 *  5. Prompt (corporate-v1) → gateway (60 s deadline, no retry) →
 *     AnswerValidator (strict JSON, sources only from this turn).
 *  6. Before saving: account still active, lease still owned, sources still
 *     readable. Source cards are built by the server from evidence.
 *
 * Every failure stores a failed turn with a fallback message; the user's
 * message is never deleted. The lease is always released.
 */
class ExecuteTurn
{
    public function __construct(
        private readonly ModelGateway $gateway,
        private readonly KnowledgeRetriever $retriever,
        private readonly PromptComposer $composer,
        private readonly AnswerValidator $validator,
        private readonly ToolRegistry $tools,
        private readonly ReadHistory $history,
    ) {}

    public function handle(User $user, AiTurn $turn, TurnLease $lease, string $message, ?string $articleTitle = null): AiTurn
    {
        $started = microtime(true);
        $outcome = 'unknown';

        try {
            $outcome = $this->run($user, $turn, $lease, $message, $articleTitle);
        } catch (\Throwable $e) {
            report($e);
            $outcome = 'server_error';
            $this->fail($turn, 'server_error', __('ui.assistant.error', [], $turn->locale));
        } finally {
            $lease->release();
        }

        Log::info('assistant.turn', [
            'turn_id' => $turn->id,
            'user_id' => $user->id,
            'prompt_version' => config('assistant.prompt_version'),
            'provider' => $turn->provider,
            'model' => $turn->model,
            'result' => $outcome,
            'status' => $turn->fresh()?->status?->value,
            'latency_ms' => (int) round((microtime(true) - $started) * 1000),
            'usage' => $turn->usage,
        ]);

        return $turn->refresh();
    }

    private function run(User $user, AiTurn $turn, TurnLease $lease, string $message, ?string $articleTitle): string
    {
        $locale = $turn->locale;
        $context = $turn->context_snapshot;
        $scene = SceneCode::tryFrom($context['scene'] ?? '') ?? SceneCode::Reception;

        app()->setLocale($locale);

        if ($quick = QuickReplies::match($message)) {
            $this->complete($turn, $lease, $user, $quick['text'], TurnCards::forEvidence([], $quick['actions']), []);

            return 'quick_'.$quick['kind'];
        }

        $evidence = $this->retrieve($user, $message, $locale, $scene, $context['article_id'] ?? null);

        if ($evidence === []) {
            $this->complete($turn, $lease, $user, __('ui.assistant.no_source'), TurnCards::forEvidence([], TurnCards::helpActions()), []);

            return 'no_source';
        }

        if (! $this->gateway->available()) {
            $this->fail($turn, GatewayException::UNAVAILABLE, __('ui.assistant.unavailable'), TurnCards::forEvidence($evidence, TurnCards::helpActions()), [], $evidence);

            return GatewayException::UNAVAILABLE;
        }

        $deadline = new TurnDeadline($turn->deadline_at);
        $request = new ChatCompletionRequest(
            messages: $this->composer->compose(
                $locale,
                ['scene' => $scene->value, 'article_title' => $articleTitle],
                $evidence,
                $this->history->forPrompt($user, $turn->conversation, $turn->id),
                $message,
            ),
            tools: $this->tools->definitions(),
        );

        try {
            $result = $this->gateway->complete($request, $deadline);
        } catch (GatewayException $e) {
            $this->fail($turn, $e->errorCode, __('ui.assistant.error'), TurnCards::forEvidence($evidence, TurnCards::helpActions()), ['provider' => $this->gateway->providerName()], $evidence);

            return $e->errorCode;
        }

        $meta = ['provider' => $this->gateway->providerName(), 'model' => $result->model, 'usage' => $result->usage];
        $byRevision = [];
        foreach ($evidence as $item) {
            $byRevision[$item->revisionId] = $item;
        }

        $answer = $this->validator->validate($result, array_keys($byRevision), ! $this->tools->isEmpty());

        if (! $answer['ok']) {
            if ($answer['code'] === AnswerValidator::NO_SOURCE) {
                $this->complete($turn, $lease, $user, __('ui.assistant.no_source'), TurnCards::forEvidence([], TurnCards::helpActions()), [], $meta);

                return 'model_no_source';
            }

            $this->fail($turn, $answer['code'], __('ui.assistant.error'), TurnCards::forEvidence($evidence, TurnCards::helpActions()), $meta, $evidence);

            return $answer['code'];
        }

        $used = array_map(fn (int $id) => $byRevision[$id], $answer['source_revision_ids']);
        $this->complete($turn, $lease, $user, $answer['text'], TurnCards::forEvidence($used), $used, $meta);

        return 'answered';
    }

    /**
     * @return list<KnowledgeEvidence>
     */
    private function retrieve(User $user, string $message, string $locale, SceneCode $scene, ?int $articleId): array
    {
        $category = match ($scene) {
            SceneCode::Hr => 'hr',
            SceneCode::It => 'it',
            default => null,
        };

        $evidence = [];

        if ($articleId !== null && ($revision = $this->retriever->find($user, $articleId))) {
            $evidence[$revision->id] = KnowledgeRetriever::toEvidence($revision, $locale);
        }

        $found = $this->retriever->search($user, $message, $locale, $category);
        if ($found === [] && $category !== null) {
            $found = $this->retriever->search($user, $message, $locale);
        }

        foreach ($found as $item) {
            $evidence[$item->revisionId] ??= $item;
        }

        return array_slice(array_values($evidence), 0, (int) config('assistant.evidence.max_items'));
    }

    /**
     * Commits a completed turn after re-verifying account, lease, and sources.
     *
     * @param  array{sources: list<array<string, mixed>>, ui: list<array<string, mixed>>, actions: list<array<string, mixed>>}  $cards
     * @param  list<KnowledgeEvidence>  $evidence
     * @param  array<string, mixed>  $meta
     */
    private function complete(AiTurn $turn, TurnLease $lease, User $user, string $text, array $cards, array $evidence, array $meta = []): void
    {
        $fresh = User::query()->find($user->id);
        if ($fresh === null || ! $fresh->is_active) {
            $this->fail($turn, 'account_inactive', __('ui.assistant.error'));

            return;
        }

        if (! $lease->stillOwned()) {
            // Recovery or account revocation took over; do not commit stale output.
            return;
        }

        if ($evidence !== []) {
            $readable = VisibleRevisions::for(ActorContext::load($fresh))
                ->whereIn('knowledge_revisions.id', array_map(fn ($item) => $item->revisionId, $evidence))
                ->pluck('knowledge_revisions.id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (count($readable) !== count($evidence)) {
                $this->complete($turn, $lease, $fresh, __('ui.assistant.no_source'), TurnCards::forEvidence([], TurnCards::helpActions()), [], $meta);

                return;
            }
        }

        TurnRecorder::finish($turn, TurnStatus::Completed, $text, $cards, $evidence, null, $meta);
    }

    /**
     * @param  array{sources: list<array<string, mixed>>, ui: list<array<string, mixed>>, actions: list<array<string, mixed>>}|null  $cards
     * @param  array<string, mixed>  $meta
     * @param  list<KnowledgeEvidence>  $evidence  Shown as related articles; recorded so history can hide them if access is revoked.
     */
    private function fail(AiTurn $turn, string $code, string $text, ?array $cards = null, array $meta = [], array $evidence = []): void
    {
        TurnRecorder::finish($turn, TurnStatus::Failed, $text, $cards ?? TurnCards::forEvidence([], TurnCards::helpActions()), $evidence, $code, $meta);
    }
}
