<?php

namespace Tests\Feature;

use App\Models\KnowledgeRevision;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use App\Modules\Identity\Enums\RoleCode;
use App\Modules\Knowledge\Actions\CloneRevision;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\Support\BuildsCorporateData;
use Tests\TestCase;

/**
 * Real races between two PHP processes on PostgreSQL (row locks, unique
 * constraints). No wrapping test transaction: data is committed so both
 * workers see it, and tables are truncated between tests. Skipped on SQLite,
 * where row locks are no-ops.
 */
#[Group('postgres')]
class PostgresConcurrencyTest extends TestCase
{
    use BuildsCorporateData;
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Concurrency tests need PostgreSQL (.env.testing).');
        }

        $this->seedReference();
    }

    /**
     * @param  list<array{0: string, 1: array<string, mixed>}>  $jobs
     * @return list<array<string, mixed>>
     */
    private function race(array $jobs): array
    {
        $startAt = (string) ((int) floor(microtime(true) * 1000) + 2500);
        $processes = array_map(function (array $job) use ($startAt) {
            $process = new Process([PHP_BINARY, base_path('tests/Support/concurrent-action.php'), $job[0], json_encode($job[1]), $startAt], base_path(), ['APP_ENV' => 'testing']);
            $process->setTimeout(60);
            $process->start();

            return $process;
        }, $jobs);

        return array_map(function (Process $process) {
            $process->wait();
            $decoded = json_decode(trim($process->getOutput()), true);
            $this->assertIsArray($decoded, 'Worker output: '.$process->getOutput().$process->getErrorOutput());

            return $decoded;
        }, $processes);
    }

    public function test_concurrent_confirmations_create_exactly_one_ticket_and_one_effect(): void
    {
        $user = $this->employee();
        $staff = $this->staff('it');
        $draft = $this->draftFor($user);

        $results = $this->race([
            ['confirm', ['user' => $user->id, 'draft' => $draft->id, 'key' => (string) Str::uuid()]],
            ['confirm', ['user' => $user->id, 'draft' => $draft->id, 'key' => (string) Str::uuid()]],
        ]);

        $this->assertTrue($results[0]['ok'] && $results[1]['ok'], json_encode($results));
        $this->assertSame($results[0]['ticket'], $results[1]['ticket']);
        $this->assertSame(1, Ticket::query()->count());
        $this->assertSame(1, TicketEvent::query()->where('event_code', 'ticket_created')->count());
        $this->assertSame(1, $staff->notifications()->count());
    }

    public function test_concurrent_same_key_confirmations_replay_one_result(): void
    {
        $user = $this->employee();
        $draft = $this->draftFor($user);
        $key = (string) Str::uuid();

        $results = $this->race([
            ['confirm', ['user' => $user->id, 'draft' => $draft->id, 'key' => $key]],
            ['confirm', ['user' => $user->id, 'draft' => $draft->id, 'key' => $key]],
        ]);

        $this->assertSame(1, Ticket::query()->count());
        $this->assertSame([true, false], collect($results)->pluck('created')->sort()->reverse()->values()->all());
    }

    public function test_two_admins_demoting_each_other_keep_one_admin(): void
    {
        $a = $this->admin();
        $b = $this->admin();

        $results = $this->race([
            ['demote', ['actor' => $a->id, 'target' => $b->id]],
            ['demote', ['actor' => $b->id, 'target' => $a->id]],
        ]);

        $admins = User::query()->active()->withRole(RoleCode::Admin)->count();
        $this->assertSame(1, $admins, json_encode($results));
        $this->assertSame(1, collect($results)->where('ok', true)->count());
    }

    public function test_concurrent_publish_keeps_one_published_revision(): void
    {
        $admin = $this->admin();
        $article = $this->publishedArticle('race');
        app(CloneRevision::class)->handle($admin, $article);

        $this->race([
            ['publish', ['actor' => $admin->id, 'article' => $article->id]],
            ['publish', ['actor' => $admin->id, 'article' => $article->id]],
        ]);

        $this->assertSame(1, KnowledgeRevision::query()->where('article_id', $article->id)->where('status', 'published')->count());
        $this->assertSame(1, KnowledgeRevision::query()->where('article_id', $article->id)->where('status', 'archived')->count());
    }

    public function test_partial_unique_indexes_exist(): void
    {
        $indexes = collect(DB::select("select indexdef from pg_indexes where tablename = 'knowledge_revisions'"))->pluck('indexdef')->implode("\n");

        $this->assertStringContainsString('WHERE', $indexes, 'one published and one draft revision per article are enforced by partial unique indexes');
    }
}
