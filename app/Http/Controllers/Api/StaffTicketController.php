<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AssignTicketRequest;
use App\Http\Requests\Api\TicketMessageRequest;
use App\Http\Requests\Api\TicketStatusRequest;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Modules\Identity\CurrentActor;
use App\Modules\Support\Actions\AppendTicketMessage;
use App\Modules\Support\Actions\AssignTicket;
use App\Modules\Support\Actions\ReadTicketHistory;
use App\Modules\Support\Actions\TransitionTicket;
use App\Modules\Support\Enums\TicketStatus;
use App\Modules\Support\Presenters\TicketPresenter;
use App\Modules\Support\Support\TicketAccess;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Staff API: requires current staff membership of the ticket's team.
 * Tickets of other teams are 404; internal notes are visible here only.
 */
class StaffTicketController extends Controller
{
    public function __construct(private readonly CurrentActor $actors) {}

    public function assign(AssignTicketRequest $request, Ticket $ticket, AssignTicket $assign): JsonResponse
    {
        $this->ensureStaff($request, $ticket);

        $result = $assign->handle(
            $request->user(),
            $ticket,
            (int) $request->validated('assignee_id'),
            (int) $request->validated('expected_version'),
            $request->validated('idempotency_key'),
        );

        return ApiResponse::success(TicketPresenter::ticket($result['result'], forStaff: true));
    }

    public function updateStatus(TicketStatusRequest $request, Ticket $ticket, TransitionTicket $transition): JsonResponse
    {
        $this->ensureStaff($request, $ticket);

        $result = $transition->handle(
            $request->user(),
            $ticket,
            TicketStatus::from($request->validated('status')),
            (int) $request->validated('expected_version'),
            $request->validated('note'),
            $request->validated('idempotency_key'),
        );

        return ApiResponse::success(TicketPresenter::ticket($result['result'], forStaff: true));
    }

    public function messages(Request $request, Ticket $ticket, ReadTicketHistory $history): JsonResponse
    {
        $this->ensureStaff($request, $ticket);
        $afterId = (int) ($request->validate(['after_id' => ['nullable', 'integer', 'min:0']])['after_id'] ?? 0);

        $page = $history->handle($ticket, $afterId, includeInternal: true);

        return ApiResponse::success(
            $page['messages']->map(fn (TicketMessage $message) => TicketPresenter::message($message, $request->user()->id))->all(),
            ['next_after_id' => $page['next_after_id'], 'ticket' => TicketPresenter::ticket($ticket->refresh(), forStaff: true)],
        );
    }

    public function storeMessage(TicketMessageRequest $request, Ticket $ticket, AppendTicketMessage $append): JsonResponse
    {
        $this->ensureStaff($request, $ticket);

        $result = $append->handle($request->user(), $ticket, $request->validated('text'), $request->visibility(), $request->validated('idempotency_key'), asStaff: true);

        return ApiResponse::success(TicketPresenter::message($result['result'], $request->user()->id), status: $result['replayed'] ? 200 : 201);
    }

    private function ensureStaff(Request $request, Ticket $ticket): void
    {
        abort_unless(TicketAccess::canHandle($this->actors->for($request->user()), $ticket), 404);
    }
}
