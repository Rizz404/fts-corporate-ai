<?php

namespace App\Modules\Support\Actions;

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Modules\Conversation\Support\Fingerprint;
use App\Modules\Identity\ActorContext;
use App\Modules\Support\Actions\Concerns\RunsIdempotently;
use App\Modules\Support\Enums\MessageVisibility;
use App\Modules\Support\Enums\TicketStatus;
use App\Modules\Support\Support\Receipts;
use App\Modules\Support\Support\TicketAccess;
use App\Modules\Support\Support\TicketNotifier;
use App\Support\Http\ApiException;

/**
 * Public messages from requester or team staff; internal notes from staff
 * only. Locks the ticket so nothing is added after close; does not change
 * the state version. The message id is the history cursor.
 */
class AppendTicketMessage
{
    use RunsIdempotently;

    /**
     * @return array{result: TicketMessage, replayed: bool}
     */
    public function handle(User $author, Ticket $ticket, string $text, MessageVisibility $visibility, string $key, bool $asStaff): array
    {
        $text = trim(str_replace("\r\n", "\n", $text));
        $fingerprint = Fingerprint::of([
            'actor_id' => $author->id,
            'ticket' => $ticket->public_id,
            'text' => $text,
            'visibility' => $visibility->value,
        ]);

        return $this->idempotent($author->id, Receipts::MESSAGE, $key, $fingerprint, function () use ($author, $ticket, $text, $visibility, $asStaff) {
            /** @var Ticket $ticket */
            $ticket = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            $allowed = $asStaff
                ? TicketAccess::canHandle(ActorContext::load($author), $ticket)
                : TicketAccess::isRequester($author, $ticket) && $visibility === MessageVisibility::Public;
            if (! $allowed) {
                abort(404);
            }

            if ($ticket->status === TicketStatus::Closed) {
                throw new ApiException('ticket_closed', 409);
            }

            $message = TicketMessage::create([
                'ticket_id' => $ticket->id,
                'author_id' => $author->id,
                'visibility' => $visibility,
                'text' => $text,
                'created_at' => now(),
            ]);
            $ticket->touch();

            $visibility === MessageVisibility::Public
                ? TicketNotifier::publicMessage($ticket, $author)
                : TicketNotifier::internalNote($ticket, $author);

            return [$message, 'ticket_message', (string) $message->id];
        }, fn ($receipt) => TicketMessage::query()->findOrFail((int) $receipt->resource_public_id));
    }
}
