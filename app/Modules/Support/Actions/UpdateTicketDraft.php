<?php

namespace App\Modules\Support\Actions;

use App\Models\TicketDraft;
use App\Models\User;
use App\Modules\Support\Validation\TicketDraftRules;
use App\Support\Http\ApiException;
use Illuminate\Support\Facades\DB;

/**
 * Edits an unconfirmed draft. Requires the expected revision; each edit
 * increments the revision, renews the 15-minute window, and changes the
 * fingerprint.
 */
class UpdateTicketDraft
{
    /**
     * @param  array{team_code: string, category: string, subject: string, description: string}  $data
     */
    public function handle(User $requester, TicketDraft $draft, int $expectedRevision, array $data): TicketDraft
    {
        return DB::transaction(function () use ($requester, $draft, $expectedRevision, $data) {
            /** @var TicketDraft $draft */
            $draft = TicketDraft::query()->whereKey($draft->id)->lockForUpdate()->firstOrFail();

            abort_unless($draft->requester_id === $requester->id, 404);

            if ($draft->consumed_ticket_id !== null) {
                throw new ApiException('draft_consumed', 409);
            }
            if ($draft->revision !== $expectedRevision) {
                throw new ApiException('revision_conflict', 409);
            }
            if ($draft->isExpired()) {
                throw new ApiException('draft_expired', 409);
            }

            $revision = $draft->revision + 1;
            $draft->forceFill([
                'team_id' => TicketDraftRules::team($data['team_code'])->id,
                'category' => $data['category'],
                'subject' => $data['subject'],
                'description' => $data['description'],
                'revision' => $revision,
                'payload_fingerprint' => PrepareTicketDraft::fingerprint($requester->id, $data, $revision),
                'expires_at' => now()->addMinutes(PrepareTicketDraft::TTL_MINUTES),
            ])->save();

            return $draft;
        });
    }
}
