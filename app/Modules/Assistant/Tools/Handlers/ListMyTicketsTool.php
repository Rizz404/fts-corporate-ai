<?php

namespace App\Modules\Assistant\Tools\Handlers;

use App\Models\Ticket;
use App\Models\User;
use App\Modules\Assistant\Tools\Tool;
use App\Modules\Assistant\Tools\ToolArguments;
use App\Modules\Assistant\Tools\ToolContext;
use App\Modules\Assistant\Tools\ToolResult;
use App\Modules\Conversation\Support\TurnCards;
use App\Modules\Experience\Enums\SceneCode;
use App\Modules\Support\Enums\TicketStatus;
use App\Modules\Support\Presenters\TicketCards;
use Illuminate\Validation\Rule;

/**
 * The session user's own requests (requester scope even for staff/admin),
 * 20 per page, newest update first. Terminal: the server writes the reply.
 */
final class ListMyTicketsTool implements Tool
{
    public const PER_PAGE = 20;

    public function name(): string
    {
        return 'list_my_tickets';
    }

    public function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => "List the employee's own HR/IT requests (tickets), optionally filtered by status. The result is shown to the employee directly.",
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => ['type' => 'string', 'enum' => TicketStatus::values()],
                        'page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                    ],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    public function validate(User $actor, array $arguments): array
    {
        return ToolArguments::validate($arguments, [
            'status' => ['sometimes', 'nullable', 'string', Rule::in(TicketStatus::values())],
            'page' => ['sometimes', 'integer:strict', 'min:1', 'max:100'],
        ]);
    }

    public function execute(User $actor, array $arguments, ToolContext $context): ToolResult
    {
        $status = $arguments['status'] ?? null;
        $page = (int) ($arguments['page'] ?? 1);

        $tickets = Ticket::query()
            ->where('requester_id', $actor->id)
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE, ['*'], 'page', $page);

        $items = $tickets->getCollection();
        $text = $items->isEmpty()
            ? __('ui.assistant.tools.tickets_none')
            : __('ui.assistant.tools.tickets_found', ['count' => $tickets->total()]);

        return new ToolResult(
            ToolResult::OK,
            data: ['total' => $tickets->total()],
            ui: $items->isEmpty() ? [] : [TicketCards::list($items, $tickets->total(), $page, $status)],
            actions: [TurnCards::openScene(SceneCode::Requests)],
            reference: ['count' => $items->count(), 'total' => $tickets->total()],
            terminal: true,
            text: $text,
        );
    }

    public function loggableInput(array $arguments): array
    {
        return ['status' => $arguments['status'] ?? null, 'page' => (int) ($arguments['page'] ?? 1)];
    }
}
