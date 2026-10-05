<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SendMessageRequest;
use App\Http\Requests\Api\StartConversationRequest;
use App\Models\AiTurn;
use App\Models\Conversation;
use App\Modules\Conversation\Actions\AppendTurn;
use App\Modules\Conversation\Actions\ExecuteTurn;
use App\Modules\Conversation\Actions\ReadHistory;
use App\Modules\Conversation\Actions\RecoverStaleTurn;
use App\Modules\Conversation\Actions\StartConversation;
use App\Modules\Conversation\Enums\TurnStatus;
use App\Modules\Conversation\Support\TurnPresenter;
use App\Modules\Experience\Enums\SceneCode;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AI conversation API. Conversations and turns of other users are 404.
 */
class ConversationController extends Controller
{
    public function store(StartConversationRequest $request, StartConversation $start): JsonResponse
    {
        $conversation = $start->handle(
            $request->user(),
            $request->validated('locale'),
            SceneCode::from($request->validated('scene')),
        );

        return ApiResponse::success($this->conversation($conversation), status: 201);
    }

    public function sendMessage(SendMessageRequest $request, Conversation $conversation, AppendTurn $append, ExecuteTurn $execute): JsonResponse
    {
        $this->ensureOwner($request, $conversation);
        set_time_limit((int) config('assistant.lease_seconds') + 10);

        $input = $request->turnInput();
        $result = $append->handle($request->user(), $conversation, $input);
        $turn = $result['turn'];

        if ($result['created']) {
            $turn = $execute->handle($request->user(), $turn, $result['lease'], $input['message'], $result['article_title']);
        }

        return ApiResponse::success(TurnPresenter::turn($turn), status: $turn->status === TurnStatus::Processing ? 202 : 200);
    }

    public function history(Request $request, Conversation $conversation, ReadHistory $history): JsonResponse
    {
        $this->ensureOwner($request, $conversation);
        $afterId = $request->validate(['after_id' => ['nullable', 'integer', 'min:0']])['after_id'] ?? 0;

        $page = $history->handle($request->user(), $conversation, (int) $afterId);

        return ApiResponse::success($page['messages'], [
            'next_after_id' => $page['next_after_id'],
            'conversation' => $this->conversation($conversation),
        ]);
    }

    public function turn(Request $request, Conversation $conversation, AiTurn $turn, RecoverStaleTurn $recover): JsonResponse
    {
        $this->ensureOwner($request, $conversation);
        abort_unless($turn->conversation_id === $conversation->id, 404);

        $recover->ifStale($turn);

        return ApiResponse::success(TurnPresenter::turn($turn->refresh()));
    }

    private function ensureOwner(Request $request, Conversation $conversation): void
    {
        abort_unless($conversation->user_id === $request->user()->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function conversation(Conversation $conversation): array
    {
        return [
            'conversation_id' => $conversation->public_id,
            'locale' => $conversation->locale,
            'scene' => $conversation->scene_context['scene'] ?? null,
            'created_at' => $conversation->created_at?->toIso8601String(),
        ];
    }
}
