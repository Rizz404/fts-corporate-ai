<?php

namespace App\Modules\Operations\Retention;

use App\Models\AuditEvent;
use App\Models\Conversation;
use App\Models\OperationReceipt;
use App\Models\Ticket;
use App\Models\TicketDraft;
use App\Models\TicketMessage;
use App\Modules\Support\Enums\TicketStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Retention baseline (PLAN_08), in batches of 200 with short transactions:
 *
 * - conversations: 90 days after last activity; turns, messages, evidence,
 *   and tool invocations cascade. Tickets/drafts keep their rows
 *   (conversation/turn links are null-on-delete).
 * - closed tickets: 365 days after closed_at, with messages, events, the
 *   consumed draft, their receipts, and their notifications. The draft↔ticket
 *   FKs are circular, so the ticket goes first (draft_id is restrict), then
 *   the saved draft ids. Open/in_progress/resolved tickets are never purged.
 * - unconsumed drafts: 24 hours after expires_at.
 * - audit events: 365 days; other receipts (not tied to a ticket): 365 days.
 *
 * Users are never deleted here. Counts only; no personal data is returned.
 */
class PurgeExpiredData
{
    public const BATCH = 200;

    private const TICKET_RECEIPT_TYPES = ['ticket', 'ticket_message'];

    /**
     * @return array<string, int>
     */
    public function counts(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();

        return [
            'conversations' => $this->expiredConversations($now)->count(),
            'closed_tickets' => $this->expiredTickets($now)->count(),
            'unconsumed_drafts' => $this->expiredDrafts($now)->count(),
            'audit_events' => $this->expiredAudit($now)->count(),
            'other_receipts' => $this->expiredReceipts($now)->count(),
        ];
    }

    /**
     * @return array<string, int> Rows deleted per category.
     */
    public function run(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();

        return [
            'conversations' => $this->inBatches($this->expiredConversations($now), fn (array $ids) => Conversation::query()->whereKey($ids)->delete()),
            'closed_tickets' => $this->inBatches($this->expiredTickets($now), fn (array $ids) => $this->deleteTickets($ids)),
            'unconsumed_drafts' => $this->inBatches($this->expiredDrafts($now), fn (array $ids) => TicketDraft::query()->whereKey($ids)->whereNull('consumed_ticket_id')->delete()),
            'audit_events' => $this->inBatches($this->expiredAudit($now), fn (array $ids) => AuditEvent::query()->whereKey($ids)->delete()),
            'other_receipts' => $this->inBatches($this->expiredReceipts($now), fn (array $ids) => OperationReceipt::query()->whereKey($ids)->delete()),
        ];
    }

    /**
     * @param  list<int>  $ticketIds
     */
    private function deleteTickets(array $ticketIds): int
    {
        return DB::transaction(function () use ($ticketIds) {
            $tickets = Ticket::query()
                ->whereKey($ticketIds)
                ->where('status', TicketStatus::Closed->value)
                ->lockForUpdate()
                ->get(['id', 'public_id', 'draft_id']);

            if ($tickets->isEmpty()) {
                return 0;
            }

            $ids = $tickets->pluck('id')->all();
            $publicIds = $tickets->pluck('public_id')->all();
            $draftIds = $tickets->pluck('draft_id')->filter()->all();
            $messageIds = TicketMessage::query()->whereIn('ticket_id', $ids)->pluck('id')->map(fn ($id) => (string) $id)->all();

            OperationReceipt::query()
                ->where(fn (Builder $query) => $query
                    ->where(fn (Builder $q) => $q->where('resource_type', 'ticket')->whereIn('resource_public_id', $publicIds))
                    ->orWhere(fn (Builder $q) => $q->where('resource_type', 'ticket_message')->whereIn('resource_public_id', $messageIds ?: ['-'])))
                ->delete();

            DB::table('notifications')
                ->where(function ($query) use ($publicIds) {
                    foreach ($publicIds as $publicId) {
                        $query->orWhere('data', 'like', '%"ticket_public_id":"'.$publicId.'"%');
                    }
                })
                ->delete();

            // Messages and events cascade; the draft link is restrict, so the
            // ticket goes first and the saved draft ids after.
            $deleted = Ticket::query()->whereKey($ids)->delete();
            TicketDraft::query()->whereKey($draftIds)->delete();

            return $deleted;
        });
    }

    /**
     * Deletes matching ids in batches; each batch is its own short operation.
     *
     * @param  Builder<Model>  $query
     * @param  callable(list<int>): int  $delete
     */
    private function inBatches(Builder $query, callable $delete): int
    {
        $total = 0;

        do {
            $ids = (clone $query)->orderBy('id')->limit(self::BATCH)->pluck('id')->map(fn ($id) => (int) $id)->all();
            if ($ids === []) {
                break;
            }

            $deleted = $delete($ids);
            $total += $deleted;
        } while (count($ids) === self::BATCH && $deleted > 0);

        return $total;
    }

    /**
     * @return Builder<Conversation>
     */
    private function expiredConversations(CarbonImmutable $now): Builder
    {
        $cutoff = $now->subDays((int) config('corporate.retention.conversations_days'));

        return Conversation::query()->where(fn (Builder $query) => $query
            ->where('last_activity_at', '<', $cutoff)
            ->orWhere(fn (Builder $q) => $q->whereNull('last_activity_at')->where('created_at', '<', $cutoff)));
    }

    /**
     * @return Builder<Ticket>
     */
    private function expiredTickets(CarbonImmutable $now): Builder
    {
        return Ticket::query()
            ->where('status', TicketStatus::Closed->value)
            ->whereNotNull('closed_at')
            ->where('closed_at', '<', $now->subDays((int) config('corporate.retention.tickets_after_close_days')));
    }

    /**
     * @return Builder<TicketDraft>
     */
    private function expiredDrafts(CarbonImmutable $now): Builder
    {
        return TicketDraft::query()
            ->whereNull('consumed_ticket_id')
            ->where('expires_at', '<', $now->subHours(24))
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('tickets')->whereColumn('tickets.draft_id', 'ticket_drafts.id'));
    }

    /**
     * @return Builder<AuditEvent>
     */
    private function expiredAudit(CarbonImmutable $now): Builder
    {
        return AuditEvent::query()->where('occurred_at', '<', $now->subDays((int) config('corporate.retention.audit_days')));
    }

    /**
     * @return Builder<OperationReceipt>
     */
    private function expiredReceipts(CarbonImmutable $now): Builder
    {
        return OperationReceipt::query()
            ->whereNotIn('resource_type', self::TICKET_RECEIPT_TYPES)
            ->where('created_at', '<', $now->subDays(365));
    }
}
