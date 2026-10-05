<?php

namespace App\Modules\Support\Actions;

use App\Models\Ticket;
use App\Models\TicketDraft;
use App\Models\TicketEvent;
use App\Models\User;
use App\Modules\Conversation\Support\Fingerprint;
use App\Modules\Support\Enums\TicketStatus;
use App\Modules\Support\Support\Receipts;
use App\Modules\Support\Support\TicketNotifier;
use App\Modules\Support\Support\TicketReference;
use App\Notifications\TicketActivity;
use App\Support\Http\ApiException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns a draft into exactly one ticket (PLAN_06 confirmation steps):
 *  1. Receipt for actor/type/key: same payload replays, different payload 409.
 *  2. Lock the draft; check owner, expected revision, expiry.
 *  3. Already consumed with the same revision → the same ticket, even with a
 *     different key (never a second ticket).
 *  4. Ticket, consumed link, receipt, event, and notifications in one
 *     transaction. Does not depend on any model completion.
 */
class ConfirmTicketDraft
{
    /**
     * @return array{ticket: Ticket, created: bool}
     */
    public function handle(User $requester, TicketDraft $draft, int $expectedRevision, string $idempotencyKey): array
    {
        $fingerprint = Fingerprint::of([
            'actor_id' => $requester->id,
            'draft' => $draft->public_id,
            'revision' => $expectedRevision,
        ]);

        if ($receipt = Receipts::replay($requester->id, Receipts::CONFIRM_DRAFT, $idempotencyKey, $fingerprint)) {
            return ['ticket' => Ticket::query()->where('public_id', $receipt->resource_public_id)->firstOrFail(), 'created' => false];
        }

        try {
            return DB::transaction(function () use ($requester, $draft, $expectedRevision, $idempotencyKey, $fingerprint) {
                /** @var TicketDraft $draft */
                $draft = TicketDraft::query()->whereKey($draft->id)->lockForUpdate()->firstOrFail();

                abort_unless($draft->requester_id === $requester->id, 404);

                if ($draft->revision !== $expectedRevision) {
                    throw new ApiException('revision_conflict', 409);
                }

                if ($draft->consumed_ticket_id !== null) {
                    $ticket = Ticket::query()->findOrFail($draft->consumed_ticket_id);
                    Receipts::record($requester->id, Receipts::CONFIRM_DRAFT, $idempotencyKey, $fingerprint, 'ticket', $ticket->public_id);

                    return ['ticket' => $ticket, 'created' => false];
                }

                if ($draft->isExpired()) {
                    throw new ApiException('draft_expired', 409);
                }

                $ticket = Ticket::create([
                    'public_id' => (string) Str::uuid(),
                    'reference' => TicketReference::generate(),
                    'requester_id' => $requester->id,
                    'team_id' => $draft->team_id,
                    'category' => $draft->category,
                    'subject' => $draft->subject,
                    'description' => $draft->description,
                    'status' => TicketStatus::Open,
                    'version' => 1,
                    'draft_id' => $draft->id,
                    'conversation_id' => $draft->conversation_id,
                ]);

                $draft->forceFill(['consumed_ticket_id' => $ticket->id])->save();

                Receipts::record($requester->id, Receipts::CONFIRM_DRAFT, $idempotencyKey, $fingerprint, 'ticket', $ticket->public_id);

                TicketEvent::create([
                    'ticket_id' => $ticket->id,
                    'actor_id' => $requester->id,
                    'event_code' => TicketActivity::CREATED,
                    'next_status' => TicketStatus::Open->value,
                    'created_at' => now(),
                ]);

                TicketNotifier::created($ticket, $requester);

                return ['ticket' => $ticket, 'created' => true];
            });
        } catch (UniqueConstraintViolationException) {
            // Concurrent request with the same key committed first.
            $receipt = Receipts::replay($requester->id, Receipts::CONFIRM_DRAFT, $idempotencyKey, $fingerprint);
            if ($receipt === null) {
                throw new ApiException('conflict', 409);
            }

            return ['ticket' => Ticket::query()->where('public_id', $receipt->resource_public_id)->firstOrFail(), 'created' => false];
        }
    }
}
