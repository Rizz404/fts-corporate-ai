<?php

namespace App\Modules\Conversation\Support;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

/**
 * Cache-backed leases (database store) that allow one active AI turn per
 * user and per conversation. Leases expire after assistant.lease_seconds;
 * a process must still own both before committing a model response.
 */
final class TurnLease
{
    private function __construct(
        private readonly Lock $userLock,
        private readonly Lock $conversationLock,
    ) {}

    public static function acquire(int $userId, int $conversationId): ?self
    {
        $seconds = (int) config('assistant.lease_seconds');
        $user = Cache::lock(self::userKey($userId), $seconds);

        if (! $user->get()) {
            return null;
        }

        $conversation = Cache::lock(self::conversationKey($conversationId), $seconds);
        if (! $conversation->get()) {
            $user->release();

            return null;
        }

        return new self($user, $conversation);
    }

    public function stillOwned(): bool
    {
        return $this->userLock->isOwnedByCurrentProcess() && $this->conversationLock->isOwnedByCurrentProcess();
    }

    public function release(): void
    {
        $this->conversationLock->release();
        $this->userLock->release();
    }

    /**
     * Used when an account is deactivated or its password reset: a running
     * turn then loses its lease and cannot commit.
     */
    public static function forceReleaseForUser(int $userId): void
    {
        Cache::lock(self::userKey($userId))->forceRelease();
    }

    public static function forceReleaseForConversation(int $conversationId): void
    {
        Cache::lock(self::conversationKey($conversationId))->forceRelease();
    }

    private static function userKey(int $userId): string
    {
        return "assistant:lease:user:{$userId}";
    }

    private static function conversationKey(int $conversationId): string
    {
        return "assistant:lease:conversation:{$conversationId}";
    }
}
