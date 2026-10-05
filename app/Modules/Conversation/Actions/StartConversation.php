<?php

namespace App\Modules\Conversation\Actions;

use App\Models\Conversation;
use App\Models\User;
use App\Modules\Experience\Enums\SceneCode;
use Illuminate\Support\Str;

class StartConversation
{
    public function handle(User $user, string $locale, SceneCode $scene): Conversation
    {
        return Conversation::create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'locale' => $locale,
            'scene_context' => ['scene' => $scene->value, 'article_id' => null, 'ticket_public_id' => null],
            'context_revision' => 1,
            'last_activity_at' => now(),
        ]);
    }
}
