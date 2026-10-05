<?php

namespace App\Modules\Conversation\Actions;

use App\Models\AiTurn;
use App\Models\Ticket;
use App\Models\TicketDraft;
use App\Models\User;
use App\Modules\Assistant\Gateway\ChatCompletionRequest;
use App\Modules\Assistant\Gateway\ChatCompletionResult;
use App\Modules\Assistant\Gateway\GatewayException;
use App\Modules\Assistant\Gateway\ModelGateway;
use App\Modules\Assistant\Gateway\TurnDeadline;
use App\Modules\Assistant\Prompt\AnswerValidator;
use App\Modules\Assistant\Prompt\PromptComposer;
use App\Modules\Assistant\Tools\ToolContext;
use App\Modules\Assistant\Tools\ToolExecutor;
use App\Modules\Assistant\Tools\ToolRegistry;
use App\Modules\Assistant\Tools\ToolResult;
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
use App\Modules\Support\Presenters\TicketCards;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Runs one AI turn outside any database transaction:
 *
 *  1. Deterministic replies for greetings/thanks/navigation (no model).
 *  2. Retrieval (max 5 evidence) scoped to the user; context article first.
 *  3. Without tools: no evidence → no-source fallback without the model;
 *     otherwise one completion validated by AnswerValidator (Plan 5).
 *  4. With tools (Plan 7): tool loop of at most 6 rounds / 7 completions /
 *     5 calls per response / 12 invocations within the 60 s deadline.
 *     Ticket results (list, detail, draft) are terminal and their reply is
 *     written by the server from canonical data; knowledge answers still go
 *     through AnswerValidator with sources from this turn only.
 *  5. Provider disabled → ai_unavailable fallback with related articles.
 *  6. Before saving: account still active, lease still owned, sources still
 *     readable. Cards are built by the server.
 *
 * Every failure stores a failed turn with a fallback message; the user's
 * message is never deleted. A draft saved before a failure is still shown
 * for review and never prepared twice. The lease is always released.
 */
class ExecuteTurn
{
    /** @var array{completion_calls: int, tool_rounds: int, tool_invocations: int} */
    private array $stats = ['completion_calls' => 0, 'tool_rounds' => 0, 'tool_invocations' => 0];

    public function __construct(
        private readonly ModelGateway $gateway,
        private readonly KnowledgeRetriever $retriever,
        private readonly PromptComposer $composer,
        private readonly AnswerValidator $validator,
        private readonly ToolRegistry $tools,
        private readonly ToolExecutor $executor,
        private readonly ReadHistory $history,
    ) {}

    public function handle(User $user, AiTurn $turn, TurnLease $lease, string $message, ?string $articleTitle = null): AiTurn
    {
        $started = microtime(true);
        $outcome = 'unknown';
        $this->stats = ['completion_calls' => 0, 'tool_rounds' => 0, 'tool_invocations' => 0];

        try {
            $outcome = $this->run($user, $turn, $lease, $message, $articleTitle);
        } catch (\Throwable $e) {
            report($e);
            $outcome = 'server_error';
            $this->fail($turn, 'server_error', __('ui.assistant.error', [], $turn->locale), $this->draftCards($turn));
        } finally {
            $lease->release();
        }

        Log::info('assistant.turn', [
            'turn_id' => $turn->id,
            'user_id' => $user->id,
            'prompt_version' => $turn->prompt_version,
            'provider' => $turn->provider,
            'model' => $turn->model,
            'result' => $outcome,
            'status' => $turn->fresh()?->status?->value,
            'latency_ms' => (int) round((microtime(true) - $started) * 1000),
            'usage' => $turn->usage,
        ] + $this->stats);

        return $turn->refresh();
    }

    private function run(User $user, AiTurn $turn, TurnLease $lease, string $message, ?string $articleTitle): string
    {
        $locale = $turn->locale;
        $context = $turn->context_snapshot;
        $scene = SceneCode::tryFrom($context['scene'] ?? '') ?? SceneCode::Reception;
        $toolsEnabled = ! $this->tools->isEmpty();

        app()->setLocale($locale);

        if ($quick = QuickReplies::match($message)) {
            $this->complete($turn, $lease, $user, $quick['text'], TurnCards::forEvidence([], $quick['actions']), []);

            return 'quick_'.$quick['kind'];
        }

        $evidence = $this->retrieve($user, $message, $locale, $scene, $context['article_id'] ?? null);

        if ($evidence === [] && ! $toolsEnabled) {
            $this->complete($turn, $lease, $user, __('ui.assistant.no_source'), TurnCards::forEvidence([], TurnCards::helpActions()), []);

            return 'no_source';
        }

        if (! $this->gateway->available()) {
            $this->fail($turn, GatewayException::UNAVAILABLE, __('ui.assistant.unavailable'), TurnCards::forEvidence($evidence, TurnCards::helpActions()), [], $evidence);

            return GatewayException::UNAVAILABLE;
        }

        $ticket = $this->contextTicket($user, $context['ticket_public_id'] ?? null);
        $messages = $this->composer->compose(
            $locale,
            [
                'scene' => $scene->value,
                'article_title' => $articleTitle,
                'ticket' => $ticket ? ['public_id' => $ticket->public_id, 'reference' => $ticket->reference] : null,
            ],
            $evidence,
            $this->history->forPrompt($user, $turn->conversation, $turn->id),
            $message,
            $toolsEnabled,
        );

        $deadline = new TurnDeadline($turn->deadline_at);

        return $toolsEnabled
            ? $this->converse($user, $turn, $lease, $messages, $evidence, $deadline)
            : $this->answerOnce($user, $turn, $lease, $messages, $evidence, $deadline);
    }

    /**
     * Plan 5 path: one completion, no tools.
     *
     * @param  list<array<string, mixed>>  $messages
     * @param  list<KnowledgeEvidence>  $evidence
     */
    private function answerOnce(User $user, AiTurn $turn, TurnLease $lease, array $messages, array $evidence, TurnDeadline $deadline): string
    {
        try {
            $this->stats['completion_calls']++;
            $result = $this->gateway->complete(new ChatCompletionRequest($messages), $deadline);
        } catch (GatewayException $e) {
            $this->fail($turn, $e->errorCode, __('ui.assistant.error'), TurnCards::forEvidence($evidence, TurnCards::helpActions()), ['provider' => $this->gateway->providerName()], $evidence);

            return $e->errorCode;
        }

        $meta = ['provider' => $this->gateway->providerName(), 'model' => $result->model, 'usage' => $result->usage];

        return $this->finalAnswer($user, $turn, $lease, $result, $this->keyed($evidence), $evidence, $meta, null);
    }

    /**
     * Plan 7 tool loop.
     *
     * @param  list<array<string, mixed>>  $messages
     * @param  list<KnowledgeEvidence>  $evidence  Initial retrieval (kept so no-tool answers stay grounded).
     */
    private function converse(User $user, AiTurn $turn, TurnLease $lease, array $messages, array $evidence, TurnDeadline $deadline): string
    {
        $pool = $this->keyed($evidence);
        $meta = ['provider' => $this->gateway->providerName(), 'model' => null, 'usage' => []];
        $context = new ToolContext($turn, $turn->conversation, $turn->locale);
        $definitions = $this->tools->definitions();
        /** @var ToolResult|null $draft */
        $draft = null;

        $maxCompletions = (int) config('assistant.max_completion_calls');
        $maxRounds = (int) config('assistant.max_tool_rounds');
        $perResponse = (int) config('assistant.max_tool_calls_per_response');
        $maxInvocations = (int) config('assistant.max_total_invocations');

        while ($this->stats['completion_calls'] < $maxCompletions) {
            if ($deadline->expired()) {
                return $this->toolFailure($turn, GatewayException::TIMEOUT, $evidence, $draft, $meta);
            }

            try {
                $this->stats['completion_calls']++;
                $result = $this->gateway->complete(new ChatCompletionRequest($messages, $definitions), $deadline);
            } catch (GatewayException $e) {
                // A 400 on the first request that carries tools usually means
                // the provider/model does not support function calling.
                $code = $e->errorCode === GatewayException::BAD_REQUEST && $this->stats['completion_calls'] === 1
                    ? 'tool_capability_unavailable'
                    : $e->errorCode;

                return $this->toolFailure($turn, $code, $evidence, $draft, $meta);
            }

            $meta['model'] = $result->model ?? $meta['model'];
            $meta['usage'] = $this->addUsage($meta['usage'], $result->usage);

            if ($result->toolCalls === []) {
                return $this->finalAnswer($user, $turn, $lease, $result, $pool, $evidence, $meta, $draft);
            }

            if ($this->stats['tool_rounds'] >= $maxRounds) {
                return $this->toolFailure($turn, 'tool_limit_exceeded', $evidence, $draft, $meta);
            }
            $this->stats['tool_rounds']++;

            $calls = $this->normalizeCalls($result->toolCalls);
            $allowed = array_slice($calls, 0, max(0, min($perResponse, $maxInvocations - $this->stats['tool_invocations'])));

            $messages[] = [
                'role' => 'assistant',
                'content' => $this->visibleText($result->text),
                'tool_calls' => array_map(fn (array $call) => [
                    'id' => $call['id'],
                    'type' => 'function',
                    'function' => ['name' => $call['name'], 'arguments' => $call['arguments']],
                ], $allowed),
            ];

            $terminal = [];
            foreach ($allowed as $call) {
                $this->stats['tool_invocations']++;
                $toolResult = $this->executor->run($user, $call, $context);

                foreach ($toolResult->evidence as $item) {
                    $pool[$item->revisionId] ??= $item;
                }
                if ($toolResult->operation !== null) {
                    $draft = $toolResult;
                }
                if ($toolResult->terminal) {
                    $terminal[] = $toolResult;
                }

                $messages[] = ['role' => 'tool', 'tool_call_id' => $call['id'], 'content' => $toolResult->forModel()];
            }

            if (count($allowed) < count($calls)) {
                // Calls above the per-response or per-turn limit are not run.
                return $this->toolFailure($turn, 'tool_limit_exceeded', $evidence, $draft, $meta);
            }

            if ($terminal !== []) {
                $reply = $this->terminalReply($terminal, $draft);
                $this->complete($turn, $lease, $user, $reply['text'], $reply['cards'], [], $meta);

                return 'tool_'.implode('_', array_unique(array_map(fn (ToolResult $item) => $item->code, $terminal)));
            }
        }

        return $this->toolFailure($turn, 'tool_limit_exceeded', $evidence, $draft, $meta);
    }

    /**
     * Validates a final model answer against the evidence of this turn.
     *
     * @param  array<int, KnowledgeEvidence>  $pool  revision id => evidence given to this turn
     * @param  list<KnowledgeEvidence>  $initial
     * @param  array<string, mixed>  $meta
     */
    private function finalAnswer(User $user, AiTurn $turn, TurnLease $lease, ChatCompletionResult $result, array $pool, array $initial, array $meta, ?ToolResult $draft): string
    {
        $answer = $this->validator->validate($result, array_keys($pool), ! $this->tools->isEmpty());

        if (! $answer['ok']) {
            if ($draft !== null) {
                return $this->toolFailure($turn, $answer['code'], $initial, $draft, $meta);
            }

            if ($answer['code'] === AnswerValidator::NO_SOURCE) {
                $this->complete($turn, $lease, $user, __('ui.assistant.no_source'), TurnCards::forEvidence([], TurnCards::helpActions()), [], $meta);

                return 'model_no_source';
            }

            $this->fail($turn, $answer['code'], __('ui.assistant.error'), TurnCards::forEvidence($initial, TurnCards::helpActions()), $meta, $initial);

            return $answer['code'];
        }

        $used = array_slice(array_map(fn (int $id) => $pool[$id], $answer['source_revision_ids']), 0, (int) config('assistant.evidence.max_items'));
        $this->complete($turn, $lease, $user, $answer['text'], TurnCards::forEvidence($used), $used, $meta);

        return 'answered';
    }

    /**
     * Server-written reply for terminal tool results (tickets and drafts).
     *
     * @param  list<ToolResult>  $results
     * @return array{text: string, cards: array<string, mixed>}
     */
    private function terminalReply(array $results, ?ToolResult $draft): array
    {
        $texts = [];
        $ui = [];
        $actions = [];
        foreach ($results as $result) {
            $texts[] = (string) $result->text;
            array_push($ui, ...$result->ui);
            foreach ($result->actions as $action) {
                $actions[($action['type'] ?? '').'|'.($action['path'] ?? '')] = $action;
            }
        }

        return [
            'text' => implode("\n\n", array_values(array_unique(array_filter($texts)))),
            'cards' => ['sources' => [], 'ui' => $ui, 'actions' => array_values($actions), 'operation' => $draft?->operation],
        ];
    }

    /**
     * Failed turn after the tool loop started. A draft saved earlier in the
     * turn is still returned for review; prepare_ticket is never re-run.
     *
     * @param  list<KnowledgeEvidence>  $evidence
     * @param  array<string, mixed>  $meta
     */
    private function toolFailure(AiTurn $turn, string $code, array $evidence, ?ToolResult $draft, array $meta): string
    {
        if ($draft !== null) {
            $this->fail($turn, $code, __('ui.assistant.tools.draft_fallback'), [
                'sources' => [],
                'ui' => $draft->ui,
                'actions' => $draft->actions,
                'operation' => $draft->operation,
            ], $meta);

            return $code;
        }

        $text = $code === 'tool_capability_unavailable' ? __('ui.assistant.tools.unavailable') : __('ui.assistant.error');
        $this->fail($turn, $code, $text, TurnCards::forEvidence($evidence, TurnCards::helpActions()), $meta, $evidence);

        return $code;
    }

    /**
     * Cards for a draft already saved by this turn (used after a crash).
     *
     * @return array<string, mixed>|null
     */
    private function draftCards(AiTurn $turn): ?array
    {
        $draft = TicketDraft::query()->where('originating_turn_id', $turn->id)->first();
        if ($draft === null) {
            return null;
        }

        return [
            'sources' => [],
            'ui' => [TicketCards::draft($draft)],
            'actions' => [TicketCards::reviewAction($draft)],
            'operation' => TicketCards::operation($draft),
        ];
    }

    /**
     * @param  list<array{id: string, name: string, arguments: string}>  $calls
     * @return list<array{id: string, name: string, arguments: string}>
     */
    private function normalizeCalls(array $calls): array
    {
        return array_map(fn (array $call) => [
            'id' => $call['id'] !== '' ? mb_substr($call['id'], 0, 128) : 'call_'.Str::lower(Str::random(16)),
            'name' => $call['name'],
            'arguments' => $call['arguments'],
        ], $calls);
    }

    private function visibleText(?string $text): ?string
    {
        $text = trim((string) preg_replace('/^\s*<think>.*?<\/think>/s', '', (string) $text));

        return $text === '' ? null : $text;
    }

    /**
     * @param  array<string, int>  $total
     * @param  array<string, int>  $usage
     * @return array<string, int>
     */
    private function addUsage(array $total, array $usage): array
    {
        foreach ($usage as $key => $value) {
            $total[$key] = ($total[$key] ?? 0) + $value;
        }

        return $total;
    }

    /**
     * @param  list<KnowledgeEvidence>  $evidence
     * @return array<int, KnowledgeEvidence>
     */
    private function keyed(array $evidence): array
    {
        $keyed = [];
        foreach ($evidence as $item) {
            $keyed[$item->revisionId] = $item;
        }

        return $keyed;
    }

    /**
     * The context ticket only when it still belongs to the user.
     */
    private function contextTicket(User $user, ?string $publicId): ?Ticket
    {
        if ($publicId === null) {
            return null;
        }

        return Ticket::query()->where('public_id', $publicId)->where('requester_id', $user->id)->first();
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
     * @param  array{sources: list<array<string, mixed>>, ui: list<array<string, mixed>>, actions: list<array<string, mixed>>, operation?: ?array<string, mixed>}  $cards
     * @param  list<KnowledgeEvidence>  $evidence
     * @param  array<string, mixed>  $meta
     */
    private function complete(AiTurn $turn, TurnLease $lease, User $user, string $text, array $cards, array $evidence, array $meta = []): void
    {
        $meta['usage'] = ($meta['usage'] ?? []) + $this->usageStats();

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
     * @param  array<string, mixed>|null  $cards
     * @param  array<string, mixed>  $meta
     * @param  list<KnowledgeEvidence>  $evidence  Shown as related articles; recorded so history can hide them if access is revoked.
     */
    private function fail(AiTurn $turn, string $code, string $text, ?array $cards = null, array $meta = [], array $evidence = []): void
    {
        $meta['usage'] = ($meta['usage'] ?? []) + $this->usageStats();

        TurnRecorder::finish($turn, TurnStatus::Failed, $text, $cards ?? TurnCards::forEvidence([], TurnCards::helpActions()), $evidence, $code, $meta);
    }

    /**
     * Loop counters stored with the token usage (for operations metrics).
     *
     * @return array<string, int>
     */
    private function usageStats(): array
    {
        return array_filter($this->stats);
    }
}
