<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * In-app (database) notification about a ticket. Stores only the ticket
 * public id, event code, and actor id; never descriptions or notes. Sent
 * synchronously inside the ticket transaction.
 */
class TicketActivity extends Notification
{
    public const CREATED = 'ticket_created';

    public const ASSIGNED = 'ticket_assigned';

    public const PUBLIC_MESSAGE = 'public_message';

    public const INTERNAL_NOTE = 'internal_note';

    public const STATUS_CHANGED = 'status_changed';

    public function __construct(
        public readonly string $ticketPublicId,
        public readonly string $eventCode,
        public readonly ?int $actorId,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'ticket_public_id' => $this->ticketPublicId,
            'event_code' => $this->eventCode,
            'actor_id' => $this->actorId,
        ];
    }
}
