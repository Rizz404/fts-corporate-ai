<?php

namespace App\Modules\Support\Actions;

use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use App\Modules\Conversation\Support\Fingerprint;
use App\Modules\Identity\ActorContext;
use App\Modules\Support\Actions\Concerns\RunsIdempotently;
use App\Modules\Support\Enums\TicketStatus;
use App\Modules\Support\Support\Receipts;
use App\Modules\Support\Support\TicketAccess;
use App\Modules\Support\Support\TicketNotifier;
use App\Notifications\TicketActivity;
use App\Support\Http\ApiException;

/**
 * Staff of the ticket's team assign an active staff member of that team.
 * Row lock + expected_version; bumps the version.
 */
class AssignTicket
{
    use RunsIdempotently;

    /**
     * @return array{result: Ticket, replayed: bool}
     */
    public function handle(User $actor, Ticket $ticket, int $assigneeId, int $expectedVersion, string $key): array
    {
        $fingerprint = Fingerprint::of(['actor_id' => $actor->id, 'ticket' => $ticket->public_id, 'assignee_id' => $assigneeId, 'version' => $expectedVersion]);

        return $this->idempotent($actor->id, Receipts::ASSIGN, $key, $fingerprint, function () use ($actor, $ticket, $assigneeId, $expectedVersion) {
            /** @var Ticket $ticket */
            $ticket = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            if (! TicketAccess::canHandle(ActorContext::load($actor), $ticket)) {
                abort(404);
            }
            if ($ticket->status === TicketStatus::Closed) {
                throw new ApiException('ticket_closed', 409);
            }
            if ($ticket->version !== $expectedVersion) {
                throw new ApiException('version_conflict', 409);
            }

            $assignee = User::query()->find($assigneeId);
            if ($assignee === null || ! TicketAccess::canHandle(ActorContext::load($assignee), $ticket)) {
                throw new ApiException('validation_failed', 422, ['assignee_id' => [__('ui.tickets.errors.invalid_assignee')]]);
            }

            $previous = $ticket->assignee_id;
            $ticket->forceFill(['assignee_id' => $assignee->id, 'version' => $ticket->version + 1])->save();

            TicketEvent::create([
                'ticket_id' => $ticket->id,
                'actor_id' => $actor->id,
                'event_code' => TicketActivity::ASSIGNED,
                'metadata' => ['from_assignee_id' => $previous, 'to_assignee_id' => $assignee->id],
                'created_at' => now(),
            ]);

            TicketNotifier::assigned($ticket, $actor);

            return [$ticket, 'ticket', $ticket->public_id];
        }, fn ($receipt) => Ticket::query()->where('public_id', $receipt->resource_public_id)->firstOrFail());
    }
}
