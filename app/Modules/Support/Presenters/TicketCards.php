<?php

namespace App\Modules\Support\Presenters;

use App\Models\Ticket;
use App\Models\TicketDraft;
use App\Models\TicketMessage;
use App\Modules\Support\Enums\TicketCategory;
use Illuminate\Support\Collection;

/**
 * Chat UI descriptors for tickets and drafts (TECHNICAL_CONTRACTS ui/action
 * types). Built from canonical rows in the active locale; the model never
 * writes these fields or their paths. The browser renders every value as
 * text through safe-card-renderer.
 */
final class TicketCards
{
    public const EXCERPT = 300;

    /**
     * @return array<string, mixed>
     */
    public static function draft(TicketDraft $draft): array
    {
        $draft->loadMissing('team:id,code');
        $team = $draft->team->code->value;

        return [
            'type' => 'ticket_draft',
            'draft_id' => $draft->public_id,
            'team_code' => $team,
            'team_label' => __('ui.teams.'.$team),
            'category_label' => TicketCategory::label($team, $draft->category),
            'subject' => $draft->subject,
            'description' => self::excerpt($draft->description),
            'revision' => $draft->revision,
            'expires_at' => $draft->expires_at->toIso8601String(),
            'expired' => $draft->isExpired(),
            'path' => self::reviewPath($draft),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function confirmation(Ticket $ticket): array
    {
        return ['type' => 'ticket_confirmation'] + self::summary($ticket);
    }

    /**
     * @param  Collection<int, Ticket>  $tickets
     * @return array<string, mixed>
     */
    public static function list(Collection $tickets, int $total, int $page, ?string $status): array
    {
        return [
            'type' => 'ticket_list',
            'total' => $total,
            'page' => $page,
            'status_filter' => $status,
            'items' => $tickets->map(fn (Ticket $ticket) => self::summary($ticket))->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, TicketMessage>  $messages  Public messages only, oldest first.
     * @return array<string, mixed>
     */
    public static function detail(Ticket $ticket, Collection $messages, int $viewerId): array
    {
        $ticket->loadMissing(['team:id,code', 'assignee:id,name']);
        $team = $ticket->team->code->value;

        return ['type' => 'ticket_detail'] + self::summary($ticket) + [
            'team_label' => __('ui.teams.'.$team),
            'category_label' => TicketCategory::label($team, $ticket->category),
            'assignee_name' => $ticket->assignee?->name,
            'messages' => $messages->map(fn (TicketMessage $message) => [
                'id' => $message->id,
                'author_name' => $message->author?->name,
                'mine' => $message->author_id === $viewerId,
                'text' => self::excerpt($message->text),
                'created_at' => $message->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function reviewAction(TicketDraft $draft): array
    {
        return [
            'type' => 'review_ticket_draft',
            'draft_id' => $draft->public_id,
            'label' => __('ui.chat.cards.review_draft'),
            'path' => self::reviewPath($draft),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function openAction(Ticket $ticket): array
    {
        return [
            'type' => 'open_ticket',
            'ticket_id' => $ticket->public_id,
            'label' => __('ui.chat.cards.open_ticket', ['reference' => $ticket->reference]),
            'path' => route('office.requests.show', $ticket, false),
        ];
    }

    /**
     * Operation reference for the turn response (never executable).
     *
     * @return array<string, mixed>
     */
    public static function operation(TicketDraft $draft): array
    {
        $draft->loadMissing('consumedTicket:id,public_id,reference');

        return [
            'type' => 'ticket_draft',
            'draft_id' => $draft->public_id,
            'path' => self::reviewPath($draft),
            'ticket' => $draft->consumedTicket ? [
                'ticket_id' => $draft->consumedTicket->public_id,
                'reference' => $draft->consumedTicket->reference,
                'path' => route('office.requests.show', $draft->consumedTicket, false),
            ] : null,
        ];
    }

    /**
     * Re-reads drafts referenced by stored cards so history shows their
     * current state: a confirmed draft becomes a ticket_confirmation card,
     * a purged or foreign draft disappears. Other cards are unchanged.
     *
     * @param  array{sources?: list<array<string, mixed>>, ui?: list<array<string, mixed>>, actions?: list<array<string, mixed>>}  $stored
     * @return array<string, mixed>
     */
    public static function refresh(array $stored, int $userId): array
    {
        $ids = [];
        foreach (array_merge($stored['ui'] ?? [], $stored['actions'] ?? []) as $item) {
            if (in_array($item['type'] ?? null, ['ticket_draft', 'review_ticket_draft'], true) && is_string($item['draft_id'] ?? null)) {
                $ids[] = $item['draft_id'];
            }
        }

        if ($ids === []) {
            return $stored;
        }

        $drafts = TicketDraft::query()
            ->with(['team:id,code', 'consumedTicket'])
            ->whereIn('public_id', array_unique($ids))
            ->where('requester_id', $userId)
            ->get()
            ->keyBy('public_id');

        $stored['ui'] = array_values(array_filter(array_map(function (array $card) use ($drafts) {
            if (($card['type'] ?? null) !== 'ticket_draft') {
                return $card;
            }
            $draft = $drafts->get($card['draft_id'] ?? '');
            if ($draft === null) {
                return null;
            }

            return $draft->consumedTicket ? self::confirmation($draft->consumedTicket) : self::draft($draft);
        }, $stored['ui'] ?? [])));

        $stored['actions'] = array_values(array_filter(array_map(function (array $action) use ($drafts) {
            if (($action['type'] ?? null) !== 'review_ticket_draft') {
                return $action;
            }
            $draft = $drafts->get($action['draft_id'] ?? '');
            if ($draft === null) {
                return null;
            }

            return $draft->consumedTicket ? self::openAction($draft->consumedTicket) : self::reviewAction($draft);
        }, $stored['actions'] ?? [])));

        return $stored;
    }

    /**
     * @return array<string, mixed>
     */
    private static function summary(Ticket $ticket): array
    {
        return [
            'ticket_id' => $ticket->public_id,
            'reference' => $ticket->reference,
            'subject' => $ticket->subject,
            'status' => $ticket->status->value,
            'status_label' => $ticket->status->label(),
            'status_tone' => $ticket->status->tone(),
            'updated_at' => $ticket->updated_at?->toIso8601String(),
            'path' => route('office.requests.show', $ticket, false),
        ];
    }

    private static function reviewPath(TicketDraft $draft): string
    {
        return route('office.requests.create', ['draft' => $draft->public_id], false);
    }

    private static function excerpt(string $text): string
    {
        $text = trim($text);

        return mb_strlen($text) > self::EXCERPT ? rtrim(mb_substr($text, 0, self::EXCERPT - 1)).'…' : $text;
    }
}
