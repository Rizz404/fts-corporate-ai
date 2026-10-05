<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\TicketDraft;
use App\Models\TicketEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\BuildsCorporateData;
use Tests\TestCase;

/**
 * Plan 6: drafts, confirmation idempotency, staff workflow, access
 * isolation, internal notes, message delivery, and notifications.
 */
class TicketWorkflowTest extends TestCase
{
    use BuildsCorporateData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();
    }

    private function createDraft(User $user, array $data = []): array
    {
        return $this->actingAs($user)->postJson('/api/v1/ticket-drafts', $data + [
            'team_code' => 'hr',
            'category' => 'policy_question',
            'subject' => 'Pertanyaan cuti',
            'description' => 'Berapa lama proses persetujuan cuti?',
        ])->assertCreated()->json('data');
    }

    private function confirm(User $user, array $draft, ?string $key = null, ?int $revision = null)
    {
        return $this->actingAs($user)->postJson("/api/v1/ticket-drafts/{$draft['draft_id']}/confirm", [
            'expected_revision' => $revision ?? $draft['revision'],
            'idempotency_key' => $key ?? (string) Str::uuid(),
        ]);
    }

    // --- Drafts and confirmation ------------------------------------------

    public function test_invalid_team_or_category_is_rejected(): void
    {
        $user = $this->employee();

        $this->actingAs($user)->postJson('/api/v1/ticket-drafts', ['team_code' => 'finance', 'category' => 'other', 'subject' => 's', 'description' => 'd'])
            ->assertStatus(422)->assertJsonPath('error.fields.team_code.0', fn ($v) => is_string($v));
        $this->actingAs($user)->postJson('/api/v1/ticket-drafts', ['team_code' => 'hr', 'category' => 'hardware', 'subject' => 's', 'description' => 'd'])
            ->assertStatus(422)->assertJsonStructure(['error' => ['fields' => ['category']]]);
    }

    public function test_draft_belongs_to_owner_only(): void
    {
        $draft = $this->createDraft($this->employee());
        $other = $this->employee();

        $this->actingAs($other)->getJson("/api/v1/ticket-drafts/{$draft['draft_id']}")->assertNotFound();
        $this->confirm($other, $draft)->assertNotFound();
    }

    public function test_confirm_creates_one_ticket_and_replays_with_same_or_different_key(): void
    {
        $user = $this->employee();
        $draft = $this->createDraft($user);
        $key = (string) Str::uuid();

        $first = $this->confirm($user, $draft, $key)->assertCreated();
        $this->assertSame('open', $first->json('data.status'));
        $this->assertMatchesRegularExpression('/^CORP-\d{8}-[A-Z0-9]{8}$/', $first->json('data.reference'));

        $this->confirm($user, $draft, $key)->assertOk()->assertJsonPath('data.ticket_id', $first->json('data.ticket_id'));
        $this->confirm($user, $draft)->assertOk()->assertJsonPath('data.ticket_id', $first->json('data.ticket_id'));

        $this->assertSame(1, Ticket::query()->count());
        $this->assertSame(1, TicketEvent::query()->where('event_code', 'ticket_created')->count());
    }

    public function test_same_key_with_different_payload_conflicts(): void
    {
        $user = $this->employee();
        $key = (string) Str::uuid();
        $this->confirm($user, $this->createDraft($user), $key)->assertCreated();

        $this->confirm($user, $this->createDraft($user, ['subject' => 'Lain']), $key)
            ->assertStatus(409)->assertJsonPath('error.code', 'idempotency_conflict');
    }

    public function test_expired_draft_is_rejected_and_edit_extends_it(): void
    {
        $user = $this->employee();
        $draft = $this->createDraft($user);

        $this->travel(16)->minutes();
        $this->confirm($user, $draft)->assertStatus(409)->assertJsonPath('error.code', 'draft_expired');

        $this->travelBack();
        $draft = $this->createDraft($user);
        $this->travel(10)->minutes();
        $edited = $this->actingAs($user)->patchJson("/api/v1/ticket-drafts/{$draft['draft_id']}", [
            'team_code' => 'hr', 'category' => 'other', 'subject' => 'Diubah', 'description' => 'Isi diubah', 'expected_revision' => 1,
        ])->assertOk()->json('data');
        $this->assertSame(2, $edited['revision']);

        $this->travel(10)->minutes();
        $this->confirm($user, $edited)->assertCreated();
    }

    public function test_stale_revision_conflicts(): void
    {
        $user = $this->employee();
        $draft = $this->createDraft($user);

        $this->actingAs($user)->patchJson("/api/v1/ticket-drafts/{$draft['draft_id']}", [
            'team_code' => 'hr', 'category' => 'other', 'subject' => 'X', 'description' => 'Y', 'expected_revision' => 1,
        ])->assertOk();

        $this->confirm($user, $draft, revision: 1)->assertStatus(409)->assertJsonPath('error.code', 'revision_conflict');
        $this->assertSame(0, Ticket::query()->count());
    }

    // --- Staff workflow and isolation -------------------------------------

    public function test_staff_workflow_with_assignment_transitions_and_versions(): void
    {
        $requester = $this->employee();
        $staff = $this->staff('hr');
        $ticket = $this->ticketFor($requester, ['team_code' => 'hr', 'category' => 'policy_question']);
        $base = "/api/v1/staff/tickets/{$ticket->public_id}";

        // open → in_progress requires an assignee.
        $this->actingAs($staff)->patchJson("{$base}/status", ['status' => 'in_progress', 'expected_version' => 1, 'idempotency_key' => (string) Str::uuid()])
            ->assertStatus(422);

        $this->actingAs($staff)->patchJson("{$base}/assignment", ['assignee_id' => $staff->id, 'expected_version' => 1, 'idempotency_key' => (string) Str::uuid()])->assertOk();
        $this->actingAs($staff)->patchJson("{$base}/status", ['status' => 'in_progress', 'expected_version' => 1, 'idempotency_key' => (string) Str::uuid()])
            ->assertStatus(409)->assertJsonPath('error.code', 'version_conflict');
        $this->actingAs($staff)->patchJson("{$base}/status", ['status' => 'in_progress', 'expected_version' => 2, 'idempotency_key' => (string) Str::uuid()])->assertOk();

        // in_progress → resolved requires a note, shown to the requester.
        $this->actingAs($staff)->patchJson("{$base}/status", ['status' => 'resolved', 'expected_version' => 3, 'idempotency_key' => (string) Str::uuid()])->assertStatus(422);
        $this->actingAs($staff)->patchJson("{$base}/status", ['status' => 'resolved', 'expected_version' => 3, 'note' => 'Sudah diproses.', 'idempotency_key' => (string) Str::uuid()])->assertOk();

        // Illegal transition: resolved → open.
        $this->actingAs($staff)->patchJson("{$base}/status", ['status' => 'open', 'expected_version' => 4, 'idempotency_key' => (string) Str::uuid()])->assertStatus(422);

        // Requester closes; replies after close are rejected.
        $this->actingAs($requester)->patchJson("/api/v1/tickets/{$ticket->public_id}/status", ['status' => 'closed', 'expected_version' => 4, 'idempotency_key' => (string) Str::uuid()])->assertOk();
        $this->actingAs($requester)->postJson("/api/v1/tickets/{$ticket->public_id}/messages", ['text' => 'Masih ada?', 'idempotency_key' => (string) Str::uuid()])
            ->assertStatus(409)->assertJsonPath('error.code', 'ticket_closed');

        $this->assertSame('closed', $ticket->refresh()->status->value);
        $this->assertNotNull($ticket->closed_at);
    }

    public function test_requester_can_only_close_and_cannot_use_staff_api(): void
    {
        $requester = $this->employee();
        $ticket = $this->ticketFor($requester);

        $this->actingAs($requester)->patchJson("/api/v1/tickets/{$ticket->public_id}/status", ['status' => 'in_progress', 'expected_version' => 1, 'idempotency_key' => (string) Str::uuid()])
            ->assertStatus(422);
        $this->actingAs($requester)->patchJson("/api/v1/staff/tickets/{$ticket->public_id}/assignment", ['assignee_id' => $requester->id, 'expected_version' => 1, 'idempotency_key' => (string) Str::uuid()])
            ->assertNotFound();
    }

    public function test_staff_of_other_team_and_admin_without_assignment_cannot_access(): void
    {
        $ticket = $this->ticketFor($this->employee(), ['team_code' => 'hr', 'category' => 'other']);

        $this->actingAs($this->staff('it'))->getJson("/api/v1/staff/tickets/{$ticket->public_id}/messages")->assertNotFound();
        $this->actingAs($this->staff('it'))->get("/admin/tickets/{$ticket->public_id}")->assertNotFound();
        $this->actingAs($this->admin())->get('/admin/tickets')->assertForbidden();
        $this->actingAs($this->admin())->getJson("/api/v1/tickets/{$ticket->public_id}")->assertNotFound();
        $this->actingAs($this->staff('hr'))->get("/admin/tickets/{$ticket->public_id}")->assertOk();
    }

    public function test_assignee_must_be_active_staff_of_the_ticket_team(): void
    {
        $ticket = $this->ticketFor($this->employee(), ['team_code' => 'hr', 'category' => 'other']);
        $staff = $this->staff('hr');

        $this->actingAs($staff)->patchJson("/api/v1/staff/tickets/{$ticket->public_id}/assignment", ['assignee_id' => $this->staff('it')->id, 'expected_version' => 1, 'idempotency_key' => (string) Str::uuid()])
            ->assertStatus(422);
    }

    public function test_internal_notes_never_reach_the_requester(): void
    {
        $requester = $this->employee();
        $staff = $this->staff('it');
        $ticket = $this->ticketFor($requester);

        $this->actingAs($staff)->postJson("/api/v1/staff/tickets/{$ticket->public_id}/messages", ['text' => 'Catatan rahasia', 'visibility' => 'internal', 'idempotency_key' => (string) Str::uuid()])->assertCreated();
        $this->actingAs($staff)->postJson("/api/v1/staff/tickets/{$ticket->public_id}/messages", ['text' => 'Balasan publik', 'visibility' => 'public', 'idempotency_key' => (string) Str::uuid()])->assertCreated();

        $requesterView = $this->actingAs($requester)->getJson("/api/v1/tickets/{$ticket->public_id}/messages")->assertOk();
        $this->assertSame(['Balasan publik'], array_column($requesterView->json('data'), 'text'));

        $staffView = $this->actingAs($staff)->getJson("/api/v1/staff/tickets/{$ticket->public_id}/messages")->assertOk();
        $this->assertCount(2, $staffView->json('data'));

        $this->actingAs($requester)->get("/office/requests/{$ticket->public_id}")->assertOk()->assertDontSee('Catatan rahasia');
    }

    public function test_message_replay_with_same_key_creates_one_message_and_cursor_works(): void
    {
        $requester = $this->employee();
        $ticket = $this->ticketFor($requester);
        $key = (string) Str::uuid();

        $first = $this->actingAs($requester)->postJson("/api/v1/tickets/{$ticket->public_id}/messages", ['text' => 'Halo', 'idempotency_key' => $key])->assertCreated();
        $this->actingAs($requester)->postJson("/api/v1/tickets/{$ticket->public_id}/messages", ['text' => 'Halo', 'idempotency_key' => $key])->assertOk()
            ->assertJsonPath('data.id', $first->json('data.id'));

        $this->assertSame(1, DB::table('ticket_messages')->where('ticket_id', $ticket->id)->count());
        $this->actingAs($requester)->getJson("/api/v1/tickets/{$ticket->public_id}/messages?after_id=".$first->json('data.id'))->assertOk()->assertJsonCount(0, 'data');
    }

    // --- Notifications ---------------------------------------------------

    public function test_notifications_are_written_without_content_and_only_for_owner(): void
    {
        $requester = $this->employee();
        $staff = $this->staff('it');
        $ticket = $this->ticketFor($requester, ['description' => 'Rahasia deskripsi']);

        $this->assertSame(1, $staff->notifications()->count(), 'new ticket notifies team staff');
        $this->assertSame(0, $requester->notifications()->count(), 'actor is not notified');

        $this->actingAs($staff)->postJson("/api/v1/staff/tickets/{$ticket->public_id}/messages", ['text' => 'Dicek ya', 'visibility' => 'public', 'idempotency_key' => (string) Str::uuid()])->assertCreated();

        $notification = $requester->notifications()->firstOrFail();
        $this->assertStringNotContainsString('Rahasia', json_encode($notification->data));
        $this->assertStringNotContainsString('Dicek', json_encode($notification->data));

        $list = $this->actingAs($requester)->getJson('/api/v1/notifications')->assertOk();
        $this->assertSame("/office/requests/{$ticket->public_id}", $list->json('data.0.path'));

        $this->actingAs($this->employee())->patchJson("/api/v1/notifications/{$notification->id}/read")->assertNotFound();
        $this->actingAs($requester)->patchJson("/api/v1/notifications/{$notification->id}/read")->assertOk();
    }

    public function test_ticket_write_limiter_applies(): void
    {
        $requester = $this->employee();

        for ($i = 0; $i < 20; $i++) {
            $this->actingAs($requester)->postJson('/api/v1/ticket-drafts', ['team_code' => 'it', 'category' => 'other', 'subject' => "s{$i}", 'description' => 'd']);
        }

        $this->actingAs($requester)->postJson('/api/v1/ticket-drafts', ['team_code' => 'it', 'category' => 'other', 'subject' => 'x', 'description' => 'd'])
            ->assertStatus(429)->assertHeader('Retry-After');
        $this->assertSame(20, TicketDraft::query()->count());
    }

    public function test_requester_pages_show_own_tickets_only(): void
    {
        $requester = $this->employee();
        $mine = $this->ticketFor($requester, ['subject' => 'Tiket saya']);
        $theirs = $this->ticketFor($this->employee(), ['subject' => 'Tiket orang lain']);

        $this->actingAs($requester)->get('/office/requests')->assertOk()->assertSee('Tiket saya')->assertDontSee('Tiket orang lain');
        $this->actingAs($requester)->get("/office/requests/{$theirs->public_id}")->assertNotFound();
        $this->actingAs($requester)->getJson('/api/v1/tickets')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.ticket_id', $mine->public_id);
    }
}
