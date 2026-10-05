<?php

namespace App\Modules\Conversation\Listeners;

use App\Models\AiTurn;
use App\Modules\Conversation\Actions\RecoverStaleTurn;
use App\Modules\Conversation\Enums\TurnStatus;
use App\Modules\Conversation\Support\TurnLease;
use App\Modules\Identity\Events\UserSessionsRevoked;

/**
 * On deactivation or password reset (Plan 2), the user's running turn loses
 * its lease and is failed, so a late model response can never be saved.
 */
class CancelTurnsWhenSessionsRevoked
{
    public function __construct(private readonly RecoverStaleTurn $recover) {}

    public function handle(UserSessionsRevoked $event): void
    {
        TurnLease::forceReleaseForUser($event->userId);

        $turns = AiTurn::query()
            ->where('status', TurnStatus::Processing->value)
            ->whereHas('conversation', fn ($query) => $query->where('user_id', $event->userId))
            ->get();

        foreach ($turns as $turn) {
            TurnLease::forceReleaseForConversation($turn->conversation_id);
            $this->recover->recover($turn, 'account_revoked');
        }
    }
}
