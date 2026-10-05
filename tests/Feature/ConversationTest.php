<?php

namespace Tests\Feature;

use App\Models\AiTurn;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\TurnEvidence;
use App\Modules\Assistant\Gateway\ChatCompletionResult;
use App\Modules\Assistant\Gateway\FakeModelGateway;
use App\Modules\Assistant\Gateway\GatewayException;
use App\Modules\Conversation\Support\TurnLease;
use App\Modules\Identity\Actions\DisableUser;
use App\Modules\Knowledge\Actions\ArchiveRevision;
use App\Modules\Knowledge\Actions\CloneRevision;
use App\Modules\Knowledge\Actions\PublishRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BuildsCorporateData;
use Tests\Support\TalksToAssistant;
use Tests\TestCase;

/**
 * Plan 5: ownership, retries/idempotency, grounded answers, fallbacks,
 * leases/recovery, context lifecycle, and history (tools disabled here;
 * Plan 7 tool behavior is in AssistantToolsTest).
 */
class ConversationTest extends TestCase
{
    use BuildsCorporateData;
    use RefreshDatabase;
    use TalksToAssistant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();
        config(['assistant.tools.enabled' => false]);
    }

    public function test_conversations_of_other_users_are_not_found(): void
    {
        $owner = $this->employee();
        $conversation = $this->startConversation($owner);
        $intruder = $this->employee();

        $this->actingAs($intruder)->getJson("/api/v1/conversations/{$conversation}/messages")->assertNotFound();
        $this->say($intruder, $conversation, 'halo')->assertNotFound();
    }

    public function test_greeting_is_answered_without_calling_the_model(): void
    {
        $user = $this->employee();
        $fake = $this->fakeModel([]);

        $this->say($user, $this->startConversation($user), 'Halo!')
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.message.text', __('ui.assistant.greeting', [], 'id'));

        $this->assertSame([], $fake->requests);
    }

    public function test_grounded_answer_returns_server_built_source_cards(): void
    {
        $user = $this->employee();
        $revision = $this->publishedRevision($this->leaveArticle());
        $fake = $this->fakeModel([FakeModelGateway::answer('Ajukan ke HR 7 hari sebelumnya.', [$revision->id])]);

        $response = $this->say($user, $this->startConversation($user), 'Bagaimana cara mengajukan cuti?')
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.sources.0.revision_id', $revision->id)
            ->assertJsonPath('data.ui.0.type', 'source_card')
            ->assertJsonPath('data.ui.0.path', '/office/knowledge/cara-cuti');

        $this->assertSame('Ajukan ke HR 7 hari sebelumnya.', $response->json('data.message.text'));
        $this->assertSame(1, TurnEvidence::query()->where('revision_id', $revision->id)->count());
        $this->assertSame([], $fake->requests[0]->tools, 'no tools are sent while the registry is empty');
        $this->assertStringContainsString('<source revision_id="'.$revision->id.'">', $fake->requests[0]->messages[0]['content']);
    }

    /**
     * @return array<string, array{0: callable(int): ChatCompletionResult, 1: string}>
     */
    public static function invalidAnswers(): array
    {
        return [
            'fake source' => [fn (int $id) => FakeModelGateway::answer('x', [$id + 999]), 'ai_invalid_source'],
            'malformed json' => [fn () => new ChatCompletionResult('not json', [], 'stop'), 'ai_bad_response'],
            'code fence' => [fn (int $id) => new ChatCompletionResult("```json\n{\"text\":\"x\",\"source_revision_ids\":[{$id}]}\n```", [], 'stop'), 'ai_bad_response'],
            'truncated' => [fn () => new ChatCompletionResult('{"text":"x"', [], 'length'), 'ai_truncated'],
            'tool call without tools' => [fn () => new ChatCompletionResult(null, [['id' => 'c', 'name' => 'x', 'arguments' => '{}']], 'tool_calls'), 'ai_unexpected_tool_call'],
        ];
    }

    #[DataProvider('invalidAnswers')]
    public function test_invalid_model_output_fails_with_fallback_and_keeps_user_message(callable $make, string $code): void
    {
        $user = $this->employee();
        $revision = $this->publishedRevision($this->leaveArticle());
        $this->fakeModel([$make($revision->id)]);

        $this->say($user, $this->startConversation($user), 'Bagaimana cara mengajukan cuti?')
            ->assertOk()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.error_code', $code)
            ->assertJsonPath('data.message.text', __('ui.assistant.error', [], 'id'));

        $this->assertTrue(ConversationMessage::query()->where('role', 'user')->where('text', 'Bagaimana cara mengajukan cuti?')->exists());
    }

    public function test_answer_without_sources_becomes_no_source_fallback(): void
    {
        $user = $this->employee();
        $this->leaveArticle();
        $this->fakeModel([FakeModelGateway::answer('Saya kira 12 hari.', [])]);

        $this->say($user, $this->startConversation($user), 'Bagaimana cara mengajukan cuti?')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.message.text', __('ui.assistant.no_source', [], 'id'));
    }

    public function test_question_without_evidence_does_not_call_the_model(): void
    {
        $user = $this->employee();
        $fake = $this->fakeModel([]);

        $this->say($user, $this->startConversation($user), 'Berapa harga saham perusahaan kompetitor?')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.message.text', __('ui.assistant.no_source', [], 'id'));

        $this->assertSame([], $fake->requests);
        $this->assertSame('no_source', AiTurn::query()->value('outcome'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function providerErrors(): array
    {
        return ['timeout' => [GatewayException::TIMEOUT], 'rate limited' => [GatewayException::RATE_LIMITED], 'server' => [GatewayException::UNAVAILABLE]];
    }

    #[DataProvider('providerErrors')]
    public function test_provider_errors_store_failed_turn_with_related_articles(string $code): void
    {
        $user = $this->employee();
        $this->leaveArticle();
        $this->fakeModel([new GatewayException($code)]);

        $this->say($user, $this->startConversation($user), 'Bagaimana cara mengajukan cuti?')
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.error_code', $code)
            ->assertJsonPath('data.ui.0.type', 'source_card');
    }

    public function test_disabled_provider_returns_ai_unavailable_fallback(): void
    {
        config(['assistant.enabled' => false]);
        $user = $this->employee();
        $this->leaveArticle();

        $this->say($user, $this->startConversation($user), 'Bagaimana cara mengajukan cuti?')
            ->assertOk()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.error_code', 'ai_unavailable');

        $this->actingAs($user)->get('/office/knowledge/cara-cuti')->assertOk();
    }

    public function test_retry_with_same_key_returns_stored_result_without_new_model_call(): void
    {
        $user = $this->employee();
        $revision = $this->publishedRevision($this->leaveArticle());
        $fake = $this->fakeModel([FakeModelGateway::answer('Jawaban', [$revision->id])]);
        $conversation = $this->startConversation($user);
        $key = (string) Str::uuid();

        $first = $this->say($user, $conversation, 'Bagaimana cara mengajukan cuti?', key: $key)->assertOk();
        $second = $this->say($user, $conversation, 'Bagaimana cara mengajukan cuti?', key: $key)->assertOk();

        $this->assertSame($first->json('data.turn_id'), $second->json('data.turn_id'));
        $this->assertCount(1, $fake->requests);
        $this->assertSame(1, AiTurn::query()->count());
    }

    public function test_same_key_with_different_message_is_a_conflict(): void
    {
        $user = $this->employee();
        $conversation = $this->startConversation($user);
        $key = (string) Str::uuid();

        $this->say($user, $conversation, 'halo', key: $key)->assertOk();
        $this->say($user, $conversation, 'terima kasih', key: $key)->assertStatus(409)->assertJsonPath('error.code', 'idempotency_conflict');
    }

    public function test_concurrent_turn_is_rejected_while_lease_is_held(): void
    {
        $user = $this->employee();
        $conversation = $this->startConversation($user);
        $lease = TurnLease::acquire($user->id, Conversation::query()->where('public_id', $conversation)->value('id'));

        $this->say($user, $conversation, 'halo')->assertStatus(409)->assertJsonPath('error.code', 'turn_in_progress');

        $lease->release();
        $this->say($user, $conversation, 'halo')->assertOk();
    }

    public function test_stale_processing_turn_is_recovered_on_poll_and_by_scheduler(): void
    {
        $user = $this->employee();
        $conversationId = $this->startConversation($user);
        $conversation = Conversation::query()->where('public_id', $conversationId)->firstOrFail();
        $turn = AiTurn::create([
            'public_id' => (string) Str::uuid(), 'conversation_id' => $conversation->id, 'client_turn_id' => (string) Str::uuid(),
            'request_fingerprint' => str_repeat('a', 64), 'status' => 'processing', 'locale' => 'id', 'context_snapshot' => ['scene' => 'hr'],
            'started_at' => now()->subSeconds(80), 'deadline_at' => now()->subSeconds(20), 'prompt_version' => 'corporate-v1',
        ]);

        $this->actingAs($user)->getJson("/api/v1/conversations/{$conversationId}/turns/{$turn->public_id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.error_code', 'turn_interrupted');

        $other = AiTurn::create(['public_id' => (string) Str::uuid(), 'client_turn_id' => (string) Str::uuid()] + $turn->only(['conversation_id', 'request_fingerprint', 'locale', 'context_snapshot', 'deadline_at', 'prompt_version']) + ['status' => 'processing', 'started_at' => now()->subSeconds(90)]);
        $this->artisan('corporate:recover-turns')->assertSuccessful();
        $this->assertSame('failed', $other->refresh()->status->value);
        $this->assertSame(1, ConversationMessage::query()->where('turn_id', $other->id)->count(), 'fallback written once');

        $this->artisan('corporate:recover-turns')->assertSuccessful();
        $this->assertSame(1, ConversationMessage::query()->where('turn_id', $other->id)->count());
    }

    public function test_context_article_must_be_readable_and_locale_must_be_valid(): void
    {
        $user = $this->employee('hr');
        $engineering = $this->publishedArticle('eng-guide', 'general', null, ['engineering']);
        $conversation = $this->startConversation($user);

        $this->say($user, $conversation, 'halo', ['article_id' => $engineering->id])->assertStatus(422)->assertJsonPath('error.code', 'invalid_context');
        $this->say($user, $conversation, 'halo', locale: 'fr')->assertStatus(422);
        $this->actingAs($user)->postJson("/api/v1/conversations/{$conversation}/messages", [
            'client_turn_id' => (string) Str::uuid(), 'message' => 'halo', 'locale' => 'id',
            'context' => ['scene' => 'hr', 'article_id' => null, 'ticket_public_id' => null, 'user_id' => 1],
        ])->assertStatus(422);
    }

    public function test_locale_switch_changes_prompt_language(): void
    {
        $user = $this->employee();
        $revision = $this->publishedRevision($this->leaveArticle());
        $fake = $this->fakeModel([FakeModelGateway::answer('A', [$revision->id]), FakeModelGateway::answer('B', [$revision->id])]);
        $conversation = $this->startConversation($user);

        $this->say($user, $conversation, 'cara mengajukan cuti')->assertOk();
        $this->say($user, $conversation, 'how to request leave', locale: 'en')->assertOk();

        $this->assertStringContainsString('Reply in Bahasa Indonesia', $fake->requests[0]->messages[0]['content']);
        $this->assertStringContainsString('Reply in English', $fake->requests[1]->messages[0]['content']);
    }

    public function test_history_hides_answers_whose_source_is_no_longer_readable_and_flags_stale(): void
    {
        $admin = $this->admin();
        $user = $this->employee();
        $article = $this->leaveArticle();
        $revision = $this->publishedRevision($article);
        $this->fakeModel([FakeModelGateway::answer('Jawaban lama', [$revision->id])]);
        $conversation = $this->startConversation($user);
        $this->say($user, $conversation, 'Bagaimana cara mengajukan cuti?')->assertOk();

        app(CloneRevision::class)->handle($admin, $article);
        app(PublishRevision::class)->handle($admin, $article);
        $stale = $this->actingAs($user)->getJson("/api/v1/conversations/{$conversation}/messages")->assertOk()->json('data');
        $this->assertTrue(collect($stale)->firstWhere('role', 'assistant')['stale']);

        app(ArchiveRevision::class)->handle($admin, $article);
        $hidden = $this->actingAs($user)->getJson("/api/v1/conversations/{$conversation}/messages")->assertOk()->json('data');
        $assistant = collect($hidden)->firstWhere('role', 'assistant');
        $this->assertTrue($assistant['unavailable']);
        $this->assertSame(__('ui.assistant.history_unavailable', [], 'id'), $assistant['text']);
        $this->assertSame([], $assistant['ui']);
    }

    public function test_deactivated_account_does_not_receive_the_model_answer(): void
    {
        $admin = $this->admin();
        $user = $this->employee();
        $revision = $this->publishedRevision($this->leaveArticle());
        $this->fakeModel([function () use ($admin, $user, $revision) {
            app(DisableUser::class)->handle($admin, $user);

            return FakeModelGateway::answer('Tidak boleh terkirim', [$revision->id]);
        }]);

        $response = $this->say($user, $this->startConversation($user), 'Bagaimana cara mengajukan cuti?');

        $this->assertNotSame('Tidak boleh terkirim', $response->json('data.message.text'));
        $this->assertFalse(ConversationMessage::query()->where('text', 'Tidak boleh terkirim')->exists());
        $this->assertSame('failed', AiTurn::query()->first()->status->value);
    }

    public function test_assistant_rate_limit_is_ten_per_minute(): void
    {
        $user = $this->employee();
        $conversation = $this->startConversation($user);

        for ($i = 0; $i < 10; $i++) {
            $this->say($user, $conversation, 'halo')->assertOk();
        }

        $this->say($user, $conversation, 'halo')->assertStatus(429)->assertJsonPath('error.code', 'too_many_requests');
    }
}
