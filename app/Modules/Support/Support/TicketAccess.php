<?php

namespace App\Modules\Support\Support;

use App\Models\SupportTeam;
use App\Models\Ticket;
use App\Models\User;
use App\Modules\Identity\ActorContext;
use App\Modules\Identity\Enums\SupportTeamCode;

/**
 * Ticket read/handle rules (TECHNICAL_CONTRACTS): the requester reads their
 * own ticket (public messages only); active staff of the ticket's team
 * handle it (including internal notes). Admin role alone grants nothing.
 */
final class TicketAccess
{
    public static function isRequester(User $user, Ticket $ticket): bool
    {
        return $ticket->requester_id === $user->id;
    }

    public static function canHandle(ActorContext $actor, Ticket $ticket): bool
    {
        $ticket->loadMissing('team:id,code,is_active');
        $team = $ticket->team;

        return $team !== null && $team->is_active && $actor->isStaffOf($team->code instanceof SupportTeamCode ? $team->code : SupportTeamCode::from($team->code));
    }

    /**
     * Team ids the actor currently handles as staff.
     *
     * @return list<int>
     */
    public static function handledTeamIds(ActorContext $actor): array
    {
        if (! $actor->isStaff() || $actor->teamCodes === []) {
            return [];
        }

        return SupportTeam::query()
            ->active()
            ->whereIn('code', $actor->teamCodes)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
