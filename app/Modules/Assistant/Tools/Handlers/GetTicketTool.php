<?php

namespace App\Modules\Assistant\Tools\Handlers;

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Modules\Assistant\Tools\Tool;
use App\Modules\Assistant\Tools\ToolArguments;
use App\Modules\Assistant\Tools\ToolContext;
use App\Modules\Assistant\Tools\ToolResult;
use App\Modules\Conversation\Support\TurnCards;
use App\Modules\Experience\Enums\SceneCode;
use App\Modules\Support\Enums\MessageVisibility;
use App\Modules\Support\Presenters\TicketCards;

/**
 * One of the session user's own tickets with its latest public messages.
 * Tickets of other people are not_found even for their team's staff (the
 * staff panel uses the staff API, not the model). Terminal either way so
 * the reply about a ticket is always written by the server.
 */
final class GetTicketTool implements Tool
{
    public const MAX_MESSAGES = 10;

    /** Messages shown inside the chat card (the newest ones). */
    public const CARD_MESSAGES = 3;

    public function name(): string
    {
        return 'get_ticket';
    }

    public function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => "Show the status and latest replies of one of the employee's own requests by ticket_public_id (UUID). The result is shown to the employee directly.",
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'ticket_public_id' => ['type' => 'string', 'format' => 'uuid'],
                    ],
                    'required' => ['ticket_public_id'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    public function validate(User $actor, array $arguments): array
    {
        $validated = ToolArguments::validate($arguments, [
            'ticket_public_id' => ['required', 'string', 'uuid'],
        ]);

        return ['ticket_public_id' => strtolower($validated['ticket_public_id'])];
    }

    public function execute(User $actor, array $arguments, ToolContext $context): ToolResult
    {
        $ticket = Ticket::query()
            ->where('public_id', $arguments['ticket_public_id'])
            ->where('requester_id', $actor->id)
            ->first();

        if ($ticket === null) {
            return new ToolResult(
                ToolResult::NOT_FOUND,
                actions: [TurnCards::openScene(SceneCode::Requests)],
                terminal: true,
                text: __('ui.assistant.tools.ticket_not_found'),
            );
        }

        $messages = TicketMessage::query()
            ->with('author:id,name')
            ->where('ticket_id', $ticket->id)
            ->where('visibility', MessageVisibility::Public->value)
            ->orderByDesc('id')
            ->limit(self::MAX_MESSAGES)
            ->get()
            ->reverse()
            ->values();

        return new ToolResult(
            ToolResult::OK,
            data: ['reference' => $ticket->reference, 'status' => $ticket->status->value, 'message_count' => $messages->count()],
            ui: [TicketCards::detail($ticket, $messages->slice(-self::CARD_MESSAGES)->values(), $actor->id)],
            actions: [TicketCards::openAction($ticket)],
            reference: ['ticket_id' => $ticket->public_id],
            terminal: true,
            text: __('ui.assistant.tools.ticket_status', ['reference' => $ticket->reference, 'status' => $ticket->status->label()]),
        );
    }

    public function loggableInput(array $arguments): array
    {
        return ['ticket_public_id' => $arguments['ticket_public_id']];
    }
}
