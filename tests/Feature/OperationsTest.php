<?php

namespace Tests\Feature;

use App\Models\AiTurn;
use App\Models\AuditEvent;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\OperationReceipt;
use App\Models\Ticket;
use App\Models\TicketDraft;
use App\Models\TicketEvent;
use App\Models\TicketMessage;
use App\Modules\Assistant\Gateway\FakeModelGateway;
use App\Modules\Operations\Queries\OperationsMetrics;
use App\Modules\Operations\Support\Heartbeat;
use App\Modules\Support\Actions\ConfirmTicketDraft;
use App\Modules\Support\Actions\PrepareTicketDraft;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\BuildsCorporateData;
use Tests\Support\TalksToAssistant;
use Tests\TestCase;

/**
 * Plan 8: retention purge (dry-run and real, circular draft↔ticket FKs),
 * operations metrics/alerts, readiness, and the schedule.
 */
class OperationsTest extends TestCase
{
    use BuildsCorporateData;
    use RefreshDatabase;
    use TalksToAssistant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();
    }

    private function closeTicket(Ticket $ticket, int $daysAgo): void
    {
        $ticket->forceFill(['status' => 'closed', 'closed_at' => now()->subDays($daysAgo)])->save();
    }

    public function test_purge_dry_run_counts_without_deleting(): void
    {
        $user = $this->employee();
        $old = $this->ticketFor($user);
        $this->closeTicket($old, 400);
        Conversation::query()->create(['public_id' => (string) Str::uuid(), 'user_id' => $user->id, 'locale' => 'id', 'scene_context' => [], 'last_activity_at' => now()->subDays(91)]);

        $this->artisan('corporate:purge-data', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(1, Ticket::query()->count());
        $this->assertSame(1, Conversation::query()->count());
    }

    public function test_purge_removes_expired_closed_tickets_with_children_receipts_and_notifications(): void
    {
        $requester = $this->employee();
        $staff = $this->staff('it');
        $old = $this->ticketFor($requester);
        $this->actingAs($staff)->postJson("/api/v1/staff/tickets/{$old->public_id}/messages", ['text' => 'balasan', 'visibility' => 'public', 'idempotency_key' => (string) Str::uuid()])->assertCreated();
        $this->closeTicket($old, 366);

        $recentClosed = $this->ticketFor($requester);
        $this->closeTicket($recentClosed, 10);
        $oldButOpen = $this->ticketFor($requester);
        $oldButOpen->forceFill(['created_at' => now()->subYears(2)])->save();

        $draftId = $old->draft_id;
        $this->assertGreaterThan(0, DB::table('notifications')->where('data', 'like', '%'.$old->public_id.'%')->count());

        $this->artisan('corporate:purge-data')->assertSuccessful();

        $this->assertNull(Ticket::query()->find($old->id));
        $this->assertNull(TicketDraft::query()->find($draftId));
        $this->assertSame(0, TicketMessage::query()->where('ticket_id', $old->id)->count());
        $this->assertSame(0, TicketEvent::query()->where('ticket_id', $old->id)->count());
        $this->assertSame(0, OperationReceipt::query()->where('resource_public_id', $old->public_id)->count());
        $this->assertSame(0, DB::table('notifications')->where('data', 'like', '%'.$old->public_id.'%')->count());

        $this->assertNotNull($recentClosed->fresh());
        $this->assertNotNull($oldButOpen->fresh(), 'open tickets are never purged by age');
        $this->assertNotNull($requester->fresh(), 'users are never deleted');
    }

    public function test_purge_removes_old_conversations_but_keeps_their_tickets(): void
    {
        $user = $this->employee();
        $revision = $this->publishedRevision($this->leaveArticle());
        $this->fakeModel([FakeModelGateway::answer('Jawaban', [$revision->id])]);
        $conversationId = $this->startConversation($user);
        $this->say($user, $conversationId, 'Bagaimana cara mengajukan cuti?')->assertOk();

        $conversation = Conversation::query()->where('public_id', $conversationId)->firstOrFail();
        $turn = AiTurn::query()->firstOrFail();
        $draft = app(PrepareTicketDraft::class)->handle($user, ['team_code' => 'it', 'category' => 'other', 'subject' => 's', 'description' => 'd'], $conversationId, $turn->id);
        $ticket = app(ConfirmTicketDraft::class)->handle($user, $draft, 1, (string) Str::uuid())['ticket'];
        $conversation->forceFill(['last_activity_at' => now()->subDays(91)])->save();

        $this->artisan('corporate:purge-data')->assertSuccessful();

        $this->assertNull($conversation->fresh());
        $this->assertSame(0, AiTurn::query()->count());
        $this->assertSame(0, ConversationMessage::query()->count());
        $this->assertSame(0, DB::table('turn_evidence')->count());
        $this->assertNotNull($ticket->fresh());
        $this->assertNull($ticket->fresh()->conversation_id);
        $this->assertNull($draft->fresh()->originating_turn_id);
    }

    public function test_purge_removes_old_unconsumed_drafts_and_audit_events(): void
    {
        $user = $this->employee();
        $stale = $this->draftFor($user);
        $stale->forceFill(['expires_at' => now()->subHours(25)])->save();
        $fresh = $this->draftFor($user);
        $fresh->forceFill(['expires_at' => now()->subHours(2)])->save();
        AuditEvent::query()->create(['action' => 'x', 'subject_type' => 'user', 'subject_id' => '1', 'occurred_at' => now()->subDays(400)]);

        $this->artisan('corporate:purge-data')->assertSuccessful();

        $this->assertNull($stale->fresh());
        $this->assertNotNull($fresh->fresh());
        $this->assertSame(0, AuditEvent::query()->where('action', 'x')->count());
    }

    public function test_metrics_count_turns_tools_tickets_and_raise_alerts(): void
    {
        $user = $this->employee();
        $conversation = Conversation::query()->create(['public_id' => (string) Str::uuid(), 'user_id' => $user->id, 'locale' => 'id', 'scene_context' => [], 'last_activity_at' => now()]);
        foreach (range(1, 20) as $i) {
            AiTurn::create([
                'public_id' => (string) Str::uuid(), 'conversation_id' => $conversation->id, 'client_turn_id' => (string) Str::uuid(),
                'request_fingerprint' => str_repeat('a', 64), 'status' => $i <= 3 ? 'failed' : 'completed', 'locale' => 'id', 'context_snapshot' => [],
                'started_at' => now()->subMinutes(10), 'finished_at' => now()->subMinutes(10)->addSeconds($i * 3), 'deadline_at' => now(),
                'error_code' => $i === 1 ? 'ai_timeout' : null, 'outcome' => $i === 5 ? 'no_source' : 'answered', 'prompt_version' => 'corporate-v2',
            ]);
        }
        $ticket = $this->ticketFor($user);
        $ticket->forceFill(['created_at' => now()->subHours(30)])->save();

        $metrics = app(OperationsMetrics::class)->handle();

        $this->assertSame(17, $metrics['turns']['completed']);
        $this->assertSame(3, $metrics['turns']['failed']);
        $this->assertSame(1, $metrics['turns']['timeouts']);
        $this->assertSame(1, $metrics['turns']['no_match']);
        $this->assertSame(30000, $metrics['turns']['p50_ms']);
        $this->assertSame(57000, $metrics['turns']['p95_ms']);
        $this->assertTrue($metrics['alerts']['error_rate']);
        $this->assertTrue($metrics['alerts']['latency_p95']);
        $this->assertTrue($metrics['alerts']['unassigned_tickets']);
        $this->assertSame(1, $metrics['tickets']['unassigned_over_24h']);
    }

    public function test_operations_page_and_readiness_are_admin_only_and_never_call_the_model(): void
    {
        $fake = $this->fakeModel([]);
        $this->ticketFor($this->employee(), ['subject' => 'Rahasia subjek']);

        $this->actingAs($this->staff('it'))->get('/admin/operations')->assertForbidden();
        $this->actingAs($this->staff('it'))->getJson('/api/v1/admin/readiness')->assertForbidden();

        $this->actingAs($this->admin())->get('/admin/operations')->assertOk()->assertDontSee('Rahasia subjek');

        Heartbeat::beat(Heartbeat::SCHEDULER);
        $this->actingAs($this->admin())->getJson('/api/v1/admin/readiness')
            ->assertOk()
            ->assertJsonPath('data.ready', true)
            ->assertJsonPath('data.checks.database.status', 'ok')
            ->assertJsonPath('data.checks.scheduler.status', 'ok')
            ->assertJsonPath('data.checks.worker.status', 'not_required');

        $this->assertSame([], $fake->requests);
    }

    public function test_readiness_requires_worker_heartbeat_when_configured(): void
    {
        config(['corporate.operations.worker_required' => true]);

        $this->actingAs($this->admin())->getJson('/api/v1/admin/readiness')
            ->assertStatus(503)
            ->assertJsonPath('data.checks.worker.status', 'stale');
    }

    public function test_schedule_runs_recovery_every_minute_and_purge_at_two_jakarta(): void
    {
        $events = collect(app(Schedule::class)->events());

        $recover = $events->first(fn ($event) => str_contains($event->command, 'corporate:recover-turns'));
        $purge = $events->first(fn ($event) => str_contains($event->command, 'corporate:purge-data'));

        $this->assertSame('* * * * *', $recover->expression);
        $this->assertSame('0 2 * * *', $purge->expression);
        $this->assertSame('Asia/Jakarta', $purge->timezone);
        $this->assertTrue($purge->withoutOverlapping);
    }
}
