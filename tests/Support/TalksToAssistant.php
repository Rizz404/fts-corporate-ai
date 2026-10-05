<?php

namespace Tests\Support;

use App\Models\KnowledgeArticle;
use App\Models\User;
use App\Modules\Assistant\Gateway\FakeModelGateway;
use App\Modules\Assistant\Gateway\ModelGateway;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Conversation API helpers with a fake model (never a real provider).
 */
trait TalksToAssistant
{
    protected function fakeModel(array $queue): FakeModelGateway
    {
        $fake = FakeModelGateway::sequence($queue);
        $this->app->instance(ModelGateway::class, $fake);

        return $fake;
    }

    protected function startConversation(User $user, string $scene = 'hr', string $locale = 'id'): string
    {
        return $this->actingAs($user)
            ->postJson('/api/v1/conversations', ['locale' => $locale, 'scene' => $scene])
            ->assertCreated()
            ->json('data.conversation_id');
    }

    /**
     * @param  array{scene?: string, article_id?: ?int, ticket_public_id?: ?string}  $context
     */
    protected function say(User $user, string $conversation, string $message, array $context = [], ?string $key = null, string $locale = 'id'): TestResponse
    {
        return $this->actingAs($user)->postJson("/api/v1/conversations/{$conversation}/messages", [
            'client_turn_id' => $key ?? (string) Str::uuid(),
            'message' => $message,
            'locale' => $locale,
            'context' => $context + ['scene' => 'hr', 'article_id' => null, 'ticket_public_id' => null],
        ]);
    }

    protected function leaveArticle(): KnowledgeArticle
    {
        return $this->publishedArticle('cara-cuti', 'hr', [
            'id' => ['title' => 'Cara mengajukan cuti', 'body' => 'Ajukan cuti ke HR minimal 7 hari sebelumnya.', 'aliases' => ['leave', '休暇']],
            'en' => ['title' => 'How to request leave', 'body' => 'Send a leave request to HR at least 7 days ahead.', 'aliases' => ['cuti', '休暇']],
            'ja' => ['title' => '休暇の申請方法', 'body' => '7 日前までに人事へ休暇を申請します。', 'aliases' => ['cuti', 'leave']],
        ]);
    }
}
