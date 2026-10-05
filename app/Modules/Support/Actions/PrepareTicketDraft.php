<?php

namespace App\Modules\Support\Actions;

use App\Models\Conversation;
use App\Models\TicketDraft;
use App\Models\User;
use App\Modules\Conversation\Support\Fingerprint;
use App\Modules\Support\Validation\TicketDraftRules;
use App\Support\Http\ApiException;
use Illuminate\Support\Str;

/**
 * Saves a reviewable draft for the session user (revision 1, 15 minutes).
 * Never creates a ticket; ConfirmTicketDraft does that after an explicit
 * user confirmation. Plan 7 calls this from the prepare_ticket tool.
 */
class PrepareTicketDraft
{
    public const TTL_MINUTES = 15;

    /**
     * @param  array{team_code: string, category: string, subject: string, description: string}  $data  Validated with TicketDraftRules.
     */
    public function handle(User $requester, array $data, ?string $conversationPublicId = null, ?int $originatingTurnId = null): TicketDraft
    {
        $conversationId = null;
        if ($conversationPublicId !== null) {
            $conversation = Conversation::query()->where('public_id', $conversationPublicId)->first();
            if ($conversation === null || $conversation->user_id !== $requester->id) {
                throw new ApiException('validation_failed', 422, ['conversation_public_id' => [__('ui.tickets.errors.invalid_conversation')]]);
            }
            $conversationId = $conversation->id;
        }

        $team = TicketDraftRules::team($data['team_code']);

        return TicketDraft::create([
            'public_id' => (string) Str::uuid(),
            'requester_id' => $requester->id,
            'conversation_id' => $conversationId,
            'originating_turn_id' => $originatingTurnId,
            'team_id' => $team->id,
            'category' => $data['category'],
            'subject' => $data['subject'],
            'description' => $data['description'],
            'revision' => 1,
            'payload_fingerprint' => self::fingerprint($requester->id, $data, 1),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);
    }

    /**
     * @param  array{team_code: string, category: string, subject: string, description: string}  $data
     */
    public static function fingerprint(int $requesterId, array $data, int $revision): string
    {
        return Fingerprint::of([
            'requester_id' => $requesterId,
            'team_code' => $data['team_code'],
            'category' => $data['category'],
            'subject' => $data['subject'],
            'description' => $data['description'],
            'revision' => $revision,
        ]);
    }
}
