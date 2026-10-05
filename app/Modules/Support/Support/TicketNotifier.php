<?php

namespace App\Modules\Support\Support;

use App\Models\Ticket;
use App\Models\User;
use App\Modules\Identity\Enums\RoleCode;
use App\Notifications\TicketActivity;
use Illuminate\Support\Collection;

/**
 * Chooses recipients (deduplicated, never the actor) and writes database
 * notifications. Call inside the ticket transaction.
 */
final class TicketNotifier
{
    public static function created(Ticket $ticket, User $actor): void
    {
        self::send(self::teamStaff($ticket), $ticket, TicketActivity::CREATED, $actor);
    }

    public static function assigned(Ticket $ticket, User $actor): void
    {
        self::send(collect([$ticket->assignee_id]), $ticket, TicketActivity::ASSIGNED, $actor);
    }

    public static function publicMessage(Ticket $ticket, User $author): void
    {
        $recipients = collect([$ticket->requester_id]);
        $recipients = $ticket->assignee_id !== null
            ? $recipients->push($ticket->assignee_id)
            : $recipients->merge(self::teamStaff($ticket));

        self::send($recipients, $ticket, TicketActivity::PUBLIC_MESSAGE, $author);
    }

    public static function internalNote(Ticket $ticket, User $author): void
    {
        $recipients = $ticket->assignee_id !== null ? collect([$ticket->assignee_id]) : self::teamStaff($ticket);

        self::send($recipients->reject(fn ($id) => $id === $ticket->requester_id), $ticket, TicketActivity::INTERNAL_NOTE, $author);
    }

    public static function statusChanged(Ticket $ticket, User $actor): void
    {
        self::send(collect([$ticket->requester_id]), $ticket, TicketActivity::STATUS_CHANGED, $actor);
    }

    /**
     * @return Collection<int, int>
     */
    private static function teamStaff(Ticket $ticket): Collection
    {
        return User::query()
            ->active()
            ->withRole(RoleCode::Staff)
            ->whereHas('supportTeams', fn ($query) => $query->whereKey($ticket->team_id))
            ->pluck('id');
    }

    /**
     * @param  Collection<int, int|null>  $recipientIds
     */
    private static function send(Collection $recipientIds, Ticket $ticket, string $event, User $actor): void
    {
        $ids = $recipientIds->filter()->map(fn ($id) => (int) $id)->unique()->reject(fn ($id) => $id === $actor->id)->values();
        if ($ids->isEmpty()) {
            return;
        }

        $notification = new TicketActivity($ticket->public_id, $event, $actor->id);
        User::query()->whereIn('id', $ids)->active()->get()->each->notify($notification);
    }
}
