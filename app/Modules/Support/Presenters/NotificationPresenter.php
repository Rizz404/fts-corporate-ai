<?php

namespace App\Modules\Support\Presenters;

use App\Models\Ticket;
use App\Models\User;
use App\Modules\Identity\ActorContext;
use App\Modules\Support\Support\TicketAccess;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Builds notification items with a path the viewer may open: the staff
 * page when they handle the ticket, the requester page when it is theirs.
 * Access is re-checked here and again when the ticket page is opened.
 */
final class NotificationPresenter
{
    /**
     * @param  iterable<DatabaseNotification>  $notifications
     * @return list<array<string, mixed>>
     */
    public static function list(User $viewer, iterable $notifications): array
    {
        $items = collect($notifications);
        $ticketIds = $items->map(fn ($n) => $n->data['ticket_public_id'] ?? null)->filter()->unique()->values();
        $tickets = Ticket::query()->with('team:id,code,is_active')->whereIn('public_id', $ticketIds)->get(['id', 'public_id', 'reference', 'subject', 'requester_id', 'team_id'])->keyBy('public_id');
        $actor = ActorContext::load($viewer);

        return $items->map(function (DatabaseNotification $notification) use ($tickets, $actor, $viewer) {
            $ticket = $tickets->get($notification->data['ticket_public_id'] ?? '');
            $path = null;
            if ($ticket !== null && TicketAccess::canHandle($actor, $ticket)) {
                $path = route('admin.tickets.show', $ticket, false);
            } elseif ($ticket !== null && TicketAccess::isRequester($viewer, $ticket)) {
                $path = route('office.requests.show', $ticket, false);
            }

            return [
                'id' => $notification->id,
                'event_code' => $notification->data['event_code'] ?? null,
                'label' => __('ui.notifications.events.'.($notification->data['event_code'] ?? 'unknown')),
                'reference' => $path !== null ? $ticket?->reference : null,
                'subject' => $path !== null ? $ticket?->subject : null,
                'path' => $path,
                'read' => $notification->read_at !== null,
                'created_at' => $notification->created_at?->toIso8601String(),
            ];
        })->values()->all();
    }
}
