<?php

namespace App\Modules\Support\Actions;

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Modules\Support\Enums\MessageVisibility;

/**
 * Messages after a cursor (id ascending, max 50). Requester views get
 * public messages only; internal notes are added for staff views.
 */
class ReadTicketHistory
{
    public const PAGE_SIZE = 50;

    /**
     * @return array{messages: \Illuminate\Support\Collection<int, TicketMessage>, next_after_id: ?int}
     */
    public function handle(Ticket $ticket, int $afterId, bool $includeInternal): array
    {
        $messages = TicketMessage::query()
            ->with('author:id,name')
            ->where('ticket_id', $ticket->id)
            ->where('id', '>', $afterId)
            ->when(! $includeInternal, fn ($query) => $query->where('visibility', MessageVisibility::Public->value))
            ->orderBy('id')
            ->limit(self::PAGE_SIZE)
            ->get();

        return [
            'messages' => $messages,
            'next_after_id' => $messages->count() === self::PAGE_SIZE ? $messages->last()->id : null,
        ];
    }
}
