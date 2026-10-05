<?php

namespace Tests\Feature;

use App\Models\AiTurn;
use App\Models\Conversation;
use App\Models\Ticket;
use App\Models\TicketDraft;
use App\Models\ToolInvocation;
use App\Modules\Assistant\Gateway\ChatCompletionRequest;
use App\Modules\Assistant\Gateway\ChatCompletionResult;
use App\Modules\Assistant\Gateway\FakeModelGateway;
use App\Modules\Assistant\Gateway\GatewayException;
use App\Modules\Assistant\Tools\ToolRegistry;
use App\Modules\Support\Actions\ConfirmTicketDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BuildsCorporateData;
use Tests\Support\TalksToAssistant;
use Tests\TestCase;

/**
 * Plan 7: tool registry and validation, tool loop protocol and limits, and
 * the corporate journey SOP → draft → button confirmation → ticket → staff
 * reply. The model is always a fake sequence.
 */
class AssistantToolsTest extends TestCase
{
    use BuildsCorporateData;
    use RefreshDatabase;
    use TalksToAssistant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();
        config(['assistant.tools.enabled' => true]);
    }

    /**
     * @param  list<array{0: string, 1: array<string, mixed>|string}>  $calls  [name, arguments]
     */
    private static function calls(array $calls): ChatCompletionResult
    {
        return new ChatCompletionResult(null, array_map(fn (array $call, int $i) => [
            'id' => 'call_'.$i,
            'name' => $call[0],
            'arguments' => is_string($call[1]) ? $call[1] : json_encode($call[1], JSON_UNESCAPED_UNICODE),
        ], $calls, array_keys($calls)), 'tool_calls');
    }

    private const DRAFT = ['team_code' => 'it', 'category' => 'network', 'subject' => 'Wi-Fi mati', 'description' => 'Laptop tidak bisa tersambung ke Wi-Fi kantor.'];

    // --- Registry and validation -----------------------------------------

    public function test_registry_exposes_exactly_the_five_whitelisted_tools_with_strict_schemas(): void
    {
        $definitions = app(ToolRegistry::class)->definitions();
        $names = array_map(fn (array $d) => $d['function']['name'], $definitions);

        $this->assertSame(['search_knowledge', 'get_article', 'list_my_tickets', 'get_ticket', 'prepare_ticket'], $names);
        foreach ($definitions as $definition) {
            $this->assertSame('function', $definition['type']);
            $this->assertFalse($definition['function']['parameters']['additionalProperties']);
        }

        config(['assistant.tools.enabled' => false]);
        $this->assertTrue(app(ToolRegistry::class)->isEmpty());
    }

    /**
     * @return array<string, array{0: string, 1: array<string, mixed>|string}>
     */
    public static function invalidCalls(): array
    {
        return [
            'broken json' => ['search_knowledge', '{"query":'],
            'list instead of object' => ['search_knowledge', '["cuti"]'],
            'unknown key' => ['search_knowledge', ['query' => 'cuti', 'requester_id' => 1]],
            'empty query' => ['search_knowledge', ['query' => '']],
            'bad category' => ['search_knowledge', ['query' => 'cuti', 'category' => 'finance']],
            'string article id' => ['get_article', ['article_id' => '5']],
            'page out of range' => ['list_my_tickets', ['page' => 101]],
            'bad status' => ['list_my_tickets', ['status' => 'deleted']],
            'not a uuid' => ['get_ticket', ['ticket_public_id' => '../admin']],
            'category of other team' => ['prepare_ticket', ['team_code' => 'hr', 'category' => 'network', 'subject' => 's', 'description' => 'd']],
            'assignee injection' => ['prepare_ticket', self::DRAFT + ['assignee_id' => 1]],
            'subject too long' => ['prepare_ticket', ['subject' => str_repeat('x', 161)] + self::DRAFT],
        ];
    }

    #[DataProvider('invalidCalls')]
    public function test_invalid_arguments_are_rejected_before_the_handler(string $tool, array|string $arguments): void
    {
        $user = $this->employee();
        $fake = $this->fakeModel([self::calls([[$tool, $arguments]]), FakeModelGateway::answer('ok', [])]);

        $this->say($user, $this->startConversation($user), 'tolong bantu')->assertOk();

        $this->assertSame('validation_failed', ToolInvocation::query()->value('status'));
        $this->assertSame(0, TicketDraft::query()->count());
        $toolMessage = collect($fake->requests[1]->messages)->firstWhere('role', 'tool');
        $this->assertSame('validation_failed', json_decode($toolMessage['content'], true)['status']);
    }

    public function test_unknown_or_side_effect_tools_are_never_executed(): void
    {
        $user = $this->employee();
        $ticket = $this->ticketFor($user);
        $this->fakeModel([
            self::calls([['close_ticket', ['ticket_public_id' => $ticket->public_id]], ['create_ticket', self::DRAFT]]),
            FakeModelGateway::answer('ok', []),
        ]);

        $this->say($user, $this->startConversation($user), 'tutup tiket saya')->assertOk();

        $this->assertSame(['unknown_tool', 'unknown_tool'], ToolInvocation::query()->orderBy('id')->pluck('status')->all());
        $this->assertSame('open', $ticket->refresh()->status->value);
        $this->assertSame(1, Ticket::query()->count());
    }

    public function test_ticket_tools_use_requester_scope_even_for_staff(): void
    {
        $staff = $this->staff('it');
        $otherTicket = $this->ticketFor($this->employee());
        $this->fakeModel([self::calls([['get_ticket', ['ticket_public_id' => $otherTicket->public_id]]])]);

        $this->say($staff, $this->startConversation($staff), 'lihat tiket itu')
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.message.text', __('ui.assistant.tools.ticket_not_found', [], 'id'))
            ->assertJsonPath('data.ui', []);

        $this->assertSame('not_found', ToolInvocation::query()->value('status'));
    }

    public function test_list_my_tickets_returns_canonical_server_cards(): void
    {
        $user = $this->employee();
        $this->ticketFor($user, ['subject' => '<script>alert(1)</script>']);
        $this->ticketFor($this->employee(), ['subject' => 'Bukan milik saya']);
        $fake = $this->fakeModel([self::calls([['list_my_tickets', []]])]);

        $response = $this->say($user, $this->startConversation($user), 'status tiket saya?')
            ->assertOk()
            ->assertJsonPath('data.ui.0.type', 'ticket_list')
            ->assertJsonPath('data.ui.0.total', 1)
            ->assertJsonPath('data.ui.0.items.0.subject', '<script>alert(1)</script>')
            ->assertJsonPath('data.ui.0.items.0.status', 'open');

        $this->assertSame(__('ui.assistant.tools.tickets_found', ['count' => 1], 'id'), $response->json('data.message.text'));
        $this->assertCount(1, $fake->requests, 'terminal result needs no extra completion');
    }

    // --- Loop protocol and limits ----------------------------------------

    public function test_tool_results_are_sent_back_with_tool_call_ids_and_sources_must_come_from_the_turn(): void
    {
        $user = $this->employee();
        $revision = $this->publishedRevision($this->leaveArticle());
        $fake = $this->fakeModel([
            self::calls([['search_knowledge', ['query' => 'cuti']]]),
            fn (ChatCompletionRequest $request) => FakeModelGateway::answer('Ajukan 7 hari sebelumnya.', [$revision->id]),
        ]);

        $this->say($user, $this->startConversation($user), 'zzz pertanyaan tanpa kata kunci')
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.sources.0.revision_id', $revision->id);

        $second = $fake->requests[1]->messages;
        $assistant = $second[count($second) - 2];
        $tool = $second[count($second) - 1];
        $this->assertSame('assistant', $assistant['role']);
        $this->assertSame('call_0', $assistant['tool_calls'][0]['id']);
        $this->assertSame('search_knowledge', $assistant['tool_calls'][0]['function']['name']);
        $this->assertSame(['role' => 'tool', 'tool_call_id' => 'call_0'], array_intersect_key($tool, ['role' => 1, 'tool_call_id' => 1]));
        $this->assertSame($revision->id, json_decode($tool['content'], true)['sources'][0]['revision_id']);
        $this->assertNotEmpty($fake->requests[0]->tools);
    }

    public function test_more_than_five_calls_in_one_response_are_not_run(): void
    {
        $user = $this->employee();
        $this->fakeModel([self::calls(array_fill(0, 6, ['search_knowledge', ['query' => 'cuti']]))]);

        $this->say($user, $this->startConversation($user), 'cari')
            ->assertOk()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.error_code', 'tool_limit_exceeded');

        $this->assertSame(5, ToolInvocation::query()->count());
    }

    public function test_round_and_completion_limits_end_the_loop(): void
    {
        $user = $this->employee();
        $fake = $this->fakeModel(array_fill(0, 10, self::calls([['search_knowledge', ['query' => 'cuti']]])));

        $this->say($user, $this->startConversation($user), 'cari terus')
            ->assertOk()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.error_code', 'tool_limit_exceeded');

        $this->assertSame(6, ToolInvocation::query()->count());
        $this->assertCount(7, $fake->requests);
        $this->assertSame(['completion_calls' => 7, 'tool_rounds' => 6, 'tool_invocations' => 6], array_intersect_key(AiTurn::query()->first()->usage, array_flip(['completion_calls', 'tool_rounds', 'tool_invocations'])));
    }

    public function test_total_invocations_are_capped_at_twelve(): void
    {
        $user = $this->employee();
        $this->fakeModel(array_fill(0, 7, self::calls(array_fill(0, 5, ['search_knowledge', ['query' => 'cuti']]))));

        $this->say($user, $this->startConversation($user), 'cari banyak')->assertJsonPath('data.error_code', 'tool_limit_exceeded');

        $this->assertSame(12, ToolInvocation::query()->count());
    }

    public function test_provider_without_tool_support_reports_capability_unavailable(): void
    {
        $user = $this->employee();
        $this->fakeModel([new GatewayException(GatewayException::BAD_REQUEST)]);

        $this->say($user, $this->startConversation($user), 'buatkan tiket')
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.error_code', 'tool_capability_unavailable')
            ->assertJsonPath('data.message.text', __('ui.assistant.tools.unavailable', [], 'id'));
    }

    // --- Drafts: idempotency per turn ------------------------------------

    public function test_prepare_ticket_saves_one_draft_and_never_a_ticket(): void
    {
        $user = $this->employee();
        $fake = $this->fakeModel([self::calls([['prepare_ticket', self::DRAFT]])]);
        $conversation = $this->startConversation($user, 'it');

        $response = $this->say($user, $conversation, 'Wi-Fi saya mati, tolong buatkan permintaan ke IT', ['scene' => 'it'])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.ui.0.type', 'ticket_draft')
            ->assertJsonPath('data.ui.0.subject', 'Wi-Fi mati')
            ->assertJsonPath('data.actions.0.type', 'review_ticket_draft')
            ->assertJsonPath('data.operation.type', 'ticket_draft');

        $draft = TicketDraft::query()->sole();
        $this->assertSame($draft->public_id, $response->json('data.operation.draft_id'));
        $this->assertSame(AiTurn::query()->value('id'), $draft->originating_turn_id);
        $this->assertSame(Conversation::query()->value('id'), $draft->conversation_id);
        $this->assertSame(0, Ticket::query()->count());
        $this->assertCount(1, $fake->requests);

        $log = ToolInvocation::query()->sole();
        $this->assertStringNotContainsString('Laptop tidak bisa', json_encode($log->validated_input));
    }

    public function test_same_payload_twice_returns_first_draft_and_different_payload_conflicts(): void
    {
        $user = $this->employee();
        $this->fakeModel([self::calls([
            ['prepare_ticket', self::DRAFT],
            ['prepare_ticket', self::DRAFT],
            ['prepare_ticket', ['subject' => 'Lain'] + self::DRAFT],
        ])]);

        $this->say($user, $this->startConversation($user), 'buatkan tiket')->assertJsonPath('data.status', 'completed');

        $this->assertSame(1, TicketDraft::query()->count());
        $this->assertSame(['ok', 'ok', 'conflict'], ToolInvocation::query()->orderBy('id')->pluck('status')->all());
    }

    public function test_failure_after_draft_still_returns_the_draft_without_preparing_again(): void
    {
        $user = $this->employee();
        $this->fakeModel([self::calls(array_merge([['prepare_ticket', self::DRAFT]], array_fill(0, 5, ['search_knowledge', ['query' => 'x']])))]);
        $conversation = $this->startConversation($user);
        $key = (string) Str::uuid();

        $this->say($user, $conversation, 'buatkan tiket', key: $key)
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.error_code', 'tool_limit_exceeded')
            ->assertJsonPath('data.ui.0.type', 'ticket_draft')
            ->assertJsonPath('data.operation.type', 'ticket_draft')
            ->assertJsonPath('data.message.text', __('ui.assistant.tools.draft_fallback', [], 'id'));

        // Replay of the same turn returns the stored result; no second draft or model call.
        $this->say($user, $conversation, 'buatkan tiket', key: $key)->assertJsonPath('data.operation.type', 'ticket_draft');
        $this->assertSame(1, TicketDraft::query()->count());
    }

    // --- Journey ---------------------------------------------------------

    public function test_corporate_journey_from_sop_to_staff_reply(): void
    {
        $user = $this->employee();
        $staff = $this->staff('it');
        $revision = $this->publishedRevision($this->leaveArticle());
        $conversation = $this->startConversation($user, 'it');

        // 1. SOP question → grounded answer with a source card.
        $this->fakeModel([FakeModelGateway::answer('Ajukan ke HR 7 hari sebelumnya.', [$revision->id])]);
        $this->say($user, $conversation, 'Bagaimana cara mengajukan cuti?')->assertJsonPath('data.ui.0.type', 'source_card');

        // 2. Ask for IT help → one draft card.
        $this->fakeModel([self::calls([['prepare_ticket', self::DRAFT]])]);
        $draftId = $this->say($user, $conversation, 'Wi-Fi saya mati, tolong laporkan ke IT')->json('data.operation.draft_id');

        // 3. Typing "ya" is not a confirmation.
        $this->fakeModel([FakeModelGateway::answer('Silakan tekan tombol konfirmasi.', [])]);
        $this->say($user, $conversation, 'ya, kirim sekarang')->assertOk();
        $this->assertSame(0, Ticket::query()->count());

        // 4. Edit then confirm through the button API (revision + idempotency key).
        $this->actingAs($user)->patchJson("/api/v1/ticket-drafts/{$draftId}", ['expected_revision' => 1, 'subject' => 'Wi-Fi lantai 3 mati'] + self::DRAFT)->assertOk();
        $key = (string) Str::uuid();
        $ticket = $this->actingAs($user)->postJson("/api/v1/ticket-drafts/{$draftId}/confirm", ['expected_revision' => 2, 'idempotency_key' => $key])
            ->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.status_tone', 'info')
            ->json('data');
        $this->actingAs($user)->postJson("/api/v1/ticket-drafts/{$draftId}/confirm", ['expected_revision' => 2, 'idempotency_key' => $key])->assertOk();
        $this->assertSame(1, Ticket::query()->count());

        // 5. History now shows the confirmed ticket instead of the draft card.
        $history = $this->actingAs($user)->getJson("/api/v1/conversations/{$conversation}/messages")->json('data');
        $card = collect($history)->pluck('ui')->flatten(1)->firstWhere('type', 'ticket_confirmation');
        $this->assertSame($ticket['reference'], $card['reference']);

        // 6. Staff replies; the requester sees it via the API and via get_ticket.
        $this->actingAs($staff)->postJson("/api/v1/staff/tickets/{$ticket['ticket_id']}/messages", ['text' => 'Sudah kami cek.', 'visibility' => 'public', 'idempotency_key' => (string) Str::uuid()])->assertCreated();
        $this->actingAs($user)->getJson("/api/v1/tickets/{$ticket['ticket_id']}/messages")->assertJsonPath('data.0.text', 'Sudah kami cek.');

        $this->fakeModel([self::calls([['get_ticket', ['ticket_public_id' => $ticket['ticket_id']]]])]);
        $this->say($user, $conversation, 'status tiket saya?', ['scene' => 'requests', 'ticket_public_id' => $ticket['ticket_id']])
            ->assertJsonPath('data.ui.0.type', 'ticket_detail')
            ->assertJsonPath('data.ui.0.messages.0.text', 'Sudah kami cek.');
    }

    public function test_ticket_context_must_belong_to_the_user(): void
    {
        $user = $this->employee();
        $foreign = $this->ticketFor($this->employee());
        $conversation = $this->startConversation($user);

        $this->say($user, $conversation, 'halo', ['ticket_public_id' => $foreign->public_id])
            ->assertStatus(422)->assertJsonPath('error.code', 'invalid_context');
        $this->say($user, $conversation, 'halo', ['ticket_public_id' => 'not-a-uuid'])->assertStatus(422);

        $mine = $this->ticketFor($user);
        $fake = $this->fakeModel([FakeModelGateway::answer('ok', [])]);
        $this->say($user, $conversation, 'bagaimana tiket ini?', ['ticket_public_id' => $mine->public_id])->assertOk();
        $this->assertStringContainsString($mine->reference, $fake->requests[0]->messages[0]['content']);
        $this->assertStringNotContainsString($mine->description, json_encode($fake->requests[0]->messages));
    }

    public function test_wizard_opens_assistant_draft_and_redirects_after_confirmation(): void
    {
        $user = $this->employee();
        $draft = $this->draftFor($user);

        $this->actingAs($user)->get("/office/requests/create?draft={$draft->public_id}")->assertOk()->assertSee($draft->public_id);
        $this->actingAs($this->employee())->get("/office/requests/create?draft={$draft->public_id}")->assertNotFound();

        $ticket = app(ConfirmTicketDraft::class)->handle($user, $draft, 1, (string) Str::uuid())['ticket'];
        $this->actingAs($user)->get("/office/requests/create?draft={$draft->public_id}")->assertRedirect(route('office.requests.show', $ticket));
    }
}
