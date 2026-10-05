<?php

namespace App\Modules\Support\Actions;

use App\Models\Ticket;
use App\Models\TicketEvent;
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
use App\Notifications\TicketActivity;
use App\Support\Http\ApiException;

/**
 * Status changes (TECHNICAL_CONTRACTS matrix):
 *   open → in_progress        staff; ticket must have an assignee
 *   in_progress → resolved    staff; resolution message required (public)
 *   resolved → in_progress    staff; reason required (public)
 *   resolved → closed         requester or staff
 *   closed                    terminal
 * Row lock + expected_version; bumps the version.
 */
class TransitionTicket
{
    use RunsIdempotently;

    /**
     * @return array{result: Ticket, replayed: bool}
     */
    public function handle(User $actor, Ticket $ticket, TicketStatus $target, int $expectedVersion, ?string $note, string $key): array
    {
        $note = $note === null ? null : trim($note);
        $fingerprint = Fingerprint::of([
            'actor_id' => $actor->id,
            'ticket' => $ticket->public_id,
            'target' => $target->value,
            'version' => $expectedVersion,
            'note' => $note,
        ]);

        return $this->idempotent($actor->id, Receipts::TRANSITION, $key, $fingerprint, function () use ($actor, $ticket, $target, $expectedVersion, $note) {
            /** @var Ticket $ticket */
            $ticket = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            $isStaff = TicketAccess::canHandle(ActorContext::load($actor), $ticket);
            $isRequester = TicketAccess::isRequester($actor, $ticket);
            if (! $isStaff && ! $isRequester) {
                abort(404);
            }

            if ($ticket->version !== $expectedVersion) {
                throw new ApiException('version_conflict', 409);
            }

            $from = $ticket->status;
            $this->assertAllowed($from, $target, $isStaff, $isRequester, $ticket, $note);

            $changes = ['status' => $target, 'version' => $ticket->version + 1];
            if ($target === TicketStatus::Resolved) {
                $changes['resolved_at'] = now();
            }
            if ($target === TicketStatus::InProgress && $from === TicketStatus::Resolved) {
                $changes['resolved_at'] = null;
            }
            if ($target === TicketStatus::Closed) {
                $changes['closed_at'] = now();
            }
            $ticket->forceFill($changes)->save();

            if ($note !== null && $note !== '') {
                TicketMessage::create([
                    'ticket_id' => $ticket->id,
                    'author_id' => $actor->id,
                    'visibility' => MessageVisibility::Public,
                    'text' => $note,
                    'created_at' => now(),
                ]);
            }

            TicketEvent::create([
                'ticket_id' => $ticket->id,
                'actor_id' => $actor->id,
                'event_code' => TicketActivity::STATUS_CHANGED,
                'previous_status' => $from->value,
                'next_status' => $target->value,
                'created_at' => now(),
            ]);

            TicketNotifier::statusChanged($ticket, $actor);

            return [$ticket, 'ticket', $ticket->public_id];
        }, fn ($receipt) => Ticket::query()->where('public_id', $receipt->resource_public_id)->firstOrFail());
    }

    private function assertAllowed(TicketStatus $from, TicketStatus $to, bool $isStaff, bool $isRequester, Ticket $ticket, ?string $note): void
    {
        $allowed = match ([$from, $to]) {
            [TicketStatus::Open, TicketStatus::InProgress],
            [TicketStatus::InProgress, TicketStatus::Resolved],
            [TicketStatus::Resolved, TicketStatus::InProgress] => $isStaff,
            [TicketStatus::Resolved, TicketStatus::Closed] => $isStaff || $isRequester,
            default => false,
        };

        if (! $allowed) {
            throw new ApiException('invalid_transition', 422, ['status' => [__('ui.tickets.errors.invalid_transition')]]);
        }

        if ($from === TicketStatus::Open && $to === TicketStatus::InProgress) {
            $assignee = $ticket->assignee_id ? User::query()->find($ticket->assignee_id) : null;
            if ($assignee === null || ! TicketAccess::canHandle(ActorContext::load($assignee), $ticket)) {
                throw new ApiException('validation_failed', 422, ['assignee_id' => [__('ui.tickets.errors.assignee_required')]]);
            }
        }

        $needsNote = ($from === TicketStatus::InProgress && $to === TicketStatus::Resolved)
            || ($from === TicketStatus::Resolved && $to === TicketStatus::InProgress);

        if ($needsNote && ($note === null || $note === '')) {
            throw new ApiException('validation_failed', 422, ['note' => [__('ui.tickets.errors.note_required')]]);
        }
    }
}
