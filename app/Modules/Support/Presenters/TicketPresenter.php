<?php

namespace App\Modules\Support\Presenters;

use App\Models\Ticket;
use App\Models\TicketDraft;
use App\Models\TicketMessage;
use App\Modules\Support\Enums\TicketCategory;

/**
 * API shapes for drafts, tickets, and messages. Requester views never
 * include internal data; staff views add assignment details.
 */
final class TicketPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function draft(TicketDraft $draft): array
    {
        $draft->loadMissing(['team:id,code', 'consumedTicket:id,public_id,reference']);
        $team = $draft->team->code->value;

        return [
            'draft_id' => $draft->public_id,
            'team_code' => $team,
            'team_label' => __('ui.teams.'.$team),
            'category' => $draft->category,
            'category_label' => TicketCategory::label($team, $draft->category),
            'subject' => $draft->subject,
            'description' => $draft->description,
            'revision' => $draft->revision,
            'expires_at' => $draft->expires_at->toIso8601String(),
            'expired' => $draft->isExpired(),
            'ticket' => $draft->consumedTicket ? [
                'ticket_id' => $draft->consumedTicket->public_id,
                'reference' => $draft->consumedTicket->reference,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function ticket(Ticket $ticket, bool $forStaff = false): array
    {
        $ticket->loadMissing(['team:id,code', 'assignee:id,name', 'requester:id,name']);
        $team = $ticket->team->code->value;

        $data = [
            'ticket_id' => $ticket->public_id,
            'reference' => $ticket->reference,
            'team_code' => $team,
            'team_label' => __('ui.teams.'.$team),
            'category' => $ticket->category,
            'category_label' => TicketCategory::label($team, $ticket->category),
            'subject' => $ticket->subject,
            'description' => $ticket->description,
            'status' => $ticket->status->value,
            'status_label' => $ticket->status->label(),
            'status_tone' => $ticket->status->tone(),
            'version' => $ticket->version,
            'assignee_name' => $ticket->assignee?->name,
            'created_at' => $ticket->created_at?->toIso8601String(),
            'updated_at' => $ticket->updated_at?->toIso8601String(),
            'resolved_at' => $ticket->resolved_at?->toIso8601String(),
            'closed_at' => $ticket->closed_at?->toIso8601String(),
            'path' => $forStaff
                ? route('admin.tickets.show', $ticket, false)
                : route('office.requests.show', $ticket, false),
        ];

        if ($forStaff) {
            $data['assignee_id'] = $ticket->assignee_id;
            $data['requester_name'] = $ticket->requester?->name;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public static function message(TicketMessage $message, int $viewerId): array
    {
        $message->loadMissing('author:id,name');

        return [
            'id' => $message->id,
            'visibility' => $message->visibility->value,
            'text' => $message->text,
            'author_name' => $message->author?->name,
            'mine' => $message->author_id === $viewerId,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }
}
