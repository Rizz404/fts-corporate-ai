<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TicketMessageRequest;
use App\Http\Requests\Api\TicketStatusRequest;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Modules\Support\Actions\AppendTicketMessage;
use App\Modules\Support\Actions\ReadTicketHistory;
use App\Modules\Support\Actions\TransitionTicket;
use App\Modules\Support\Enums\MessageVisibility;
use App\Modules\Support\Enums\TicketStatus;
use App\Modules\Support\Presenters\TicketPresenter;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Requester API: own tickets only, public messages only.
 */
class TicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tickets = Ticket::query()
            ->where('requester_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(20);

        return ApiResponse::success(
            $tickets->getCollection()->map(fn (Ticket $ticket) => TicketPresenter::ticket($ticket))->all(),
            ['pagination' => ['current_page' => $tickets->currentPage(), 'last_page' => $tickets->lastPage(), 'per_page' => $tickets->perPage(), 'total' => $tickets->total()]],
        );
    }

    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        $this->ensureRequester($request, $ticket);

        return ApiResponse::success(TicketPresenter::ticket($ticket));
    }

    public function messages(Request $request, Ticket $ticket, ReadTicketHistory $history): JsonResponse
    {
        $this->ensureRequester($request, $ticket);
        $afterId = (int) ($request->validate(['after_id' => ['nullable', 'integer', 'min:0']])['after_id'] ?? 0);

        $page = $history->handle($ticket, $afterId, includeInternal: false);

        return ApiResponse::success(
            $page['messages']->map(fn (TicketMessage $message) => TicketPresenter::message($message, $request->user()->id))->all(),
            ['next_after_id' => $page['next_after_id'], 'ticket' => TicketPresenter::ticket($ticket->refresh())],
        );
    }

    public function storeMessage(TicketMessageRequest $request, Ticket $ticket, AppendTicketMessage $append): JsonResponse
    {
        $this->ensureRequester($request, $ticket);

        $result = $append->handle($request->user(), $ticket, $request->validated('text'), MessageVisibility::Public, $request->validated('idempotency_key'), asStaff: false);

        return ApiResponse::success(TicketPresenter::message($result['result'], $request->user()->id), status: $result['replayed'] ? 200 : 201);
    }

    public function updateStatus(TicketStatusRequest $request, Ticket $ticket, TransitionTicket $transition): JsonResponse
    {
        $this->ensureRequester($request, $ticket);

        $result = $transition->handle(
            $request->user(),
            $ticket,
            TicketStatus::from($request->validated('status')),
            (int) $request->validated('expected_version'),
            null,
            $request->validated('idempotency_key'),
        );

        return ApiResponse::success(TicketPresenter::ticket($result['result']));
    }

    private function ensureRequester(Request $request, Ticket $ticket): void
    {
        abort_unless($ticket->requester_id === $request->user()->id, 404);
    }
}
