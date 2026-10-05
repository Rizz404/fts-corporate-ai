<?php

namespace App\Console\Commands;

use App\Models\AiTurn;
use App\Models\Department;
use App\Models\KnowledgeArticle;
use App\Models\Ticket;
use App\Models\TicketDraft;
use App\Models\ToolInvocation;
use App\Models\User;
use App\Modules\Assistant\Gateway\ModelGateway;
use App\Modules\Conversation\Actions\AppendTurn;
use App\Modules\Conversation\Actions\ExecuteTurn;
use App\Modules\Conversation\Actions\StartConversation;
use App\Modules\Conversation\Support\TurnPresenter;
use App\Modules\Experience\Enums\SceneCode;
use App\Modules\Identity\Actions\ProvisionUser;
use App\Modules\Knowledge\Actions\CreateArticle;
use App\Modules\Knowledge\Actions\PublishRevision;
use App\Modules\Support\Actions\AppendTicketMessage;
use App\Modules\Support\Actions\ConfirmTicketDraft;
use App\Modules\Support\Actions\PrepareTicketDraft;
use App\Modules\Support\Enums\MessageVisibility;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Runs the synthetic evaluation corpus (docs/evals) against the configured
 * model provider and writes a dated report. Separate from CI; it calls the
 * real provider. Refuses production and any database whose name does not
 * contain "eval", because it rebuilds that database from scratch:
 *
 *   DB_CONNECTION=sqlite DB_DATABASE=/abs/path/database/eval.sqlite php artisan corporate:eval
 *
 * Scores are automatic checks (source/keyword/effect), not a human review of
 * answer quality; the report lists failures for manual reading.
 */
#[Signature('corporate:eval
    {--corpus=docs/evals/corpus.json : Scenario file}
    {--articles=docs/evals/articles.json : Synthetic article file}
    {--only= : Comma-separated scenario ids, or a locale (id/en/ja)}
    {--output=docs/evals/results : Report directory}')]
#[Description('Evaluate the assistant on the synthetic corpus with the real provider (local/testing only)')]
class EvaluateAssistantCommand extends Command
{
    private const TARGET_PER_LOCALE = 27;

    /** @var array<string, User> */
    private array $users = [];

    /** @var array<string, string> */
    private array $placeholders = [];

    public function handle(ModelGateway $gateway): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->components->error('corporate:eval only runs in local or testing environments.');

            return self::FAILURE;
        }

        $database = (string) DB::connection()->getDatabaseName();
        if (! str_contains(Str::lower(basename($database)), 'eval')) {
            $this->components->error("Refusing to rebuild database \"{$database}\": its name must contain \"eval\".");

            return self::FAILURE;
        }

        if (! $gateway->available()) {
            $this->components->error('The model provider is not configured (ASSISTANT_ENABLED / LOCAL_LLM_*). The real evaluation was not run.');

            return self::FAILURE;
        }

        $corpus = $this->filter(json_decode((string) file_get_contents(base_path($this->option('corpus'))), true, flags: JSON_THROW_ON_ERROR));
        $articles = json_decode((string) file_get_contents(base_path($this->option('articles'))), true, flags: JSON_THROW_ON_ERROR);

        $this->components->info('Rebuilding the evaluation database with synthetic data…');
        $this->callSilently('migrate:fresh', ['--force' => true]);
        $this->callSilently('db:seed', ['--class' => ReferenceSeeder::class, '--force' => true]);
        $this->prepare($articles);

        $results = [];
        $bar = $this->output->createProgressBar(count($corpus));
        foreach ($corpus as $scenario) {
            $results[] = $this->runScenario($scenario);
            $bar->advance();
        }
        $bar->finish();
        $this->newLine(2);

        [$jsonPath, $mdPath] = $this->report($results, $gateway);
        $this->components->info("Report: {$mdPath}");
        $this->table(['Locale', 'Passed', 'Target'], collect($results)->groupBy('locale')->map(fn ($rows, $locale) => [$locale, $rows->where('passed', true)->count().'/'.$rows->count(), self::TARGET_PER_LOCALE.'/30'])->values()->all());

        return self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $corpus
     * @return list<array<string, mixed>>
     */
    private function filter(array $corpus): array
    {
        $only = trim((string) $this->option('only'));
        if ($only === '') {
            return $corpus;
        }

        $wanted = array_map('trim', explode(',', $only));

        return array_values(array_filter($corpus, fn (array $s) => in_array($s['id'], $wanted, true) || in_array($s['locale'], $wanted, true)));
    }

    /**
     * Synthetic users, articles, and tickets.
     *
     * @param  list<array<string, mixed>>  $articles
     */
    private function prepare(array $articles): void
    {
        $departments = Department::query()->pluck('id', 'slug');
        $make = function (string $key, string $department, array $roles, array $teams = []) use ($departments) {
            $user = User::query()->create([
                'name' => 'Eval '.Str::title($key),
                'email' => "eval.{$key}@example.test",
                'password' => Hash::make(Str::random(32)),
                'department_id' => $departments[$department],
                'preferred_locale' => 'id',
                'is_active' => true,
                'must_change_password' => false,
            ]);
            ProvisionUser::syncAccess($user, ['roles' => $roles, 'support_teams' => $teams]);

            return $this->users[$key] = $user;
        };

        $admin = $make('admin', 'general', ['employee', 'admin']);
        $employee = $make('employee', 'general', ['employee']);
        $make('engineer', 'engineering', ['employee']);
        $other = $make('other', 'hr', ['employee']);
        $staff = $make('staff', 'it', ['employee', 'staff'], ['it']);

        foreach ($articles as $article) {
            $created = app(CreateArticle::class)->handle($admin, [
                'slug' => $article['slug'],
                'owner_department_id' => $departments['general'],
                'category' => $article['category'],
                'audience' => $article['departments'] === [] ? 'common' : 'departments',
                'department_ids' => array_map(fn (string $slug) => $departments[$slug], $article['departments']),
                'effective_from' => null,
                'effective_until' => null,
                'translations' => array_map(fn (array $t) => $t + ['tags' => []], $article['translations']),
            ]);
            app(PublishRevision::class)->handle($admin, $created);
        }

        $ticket = function (User $requester, string $team, string $category, string $subject): Ticket {
            $draft = app(PrepareTicketDraft::class)->handle($requester, ['team_code' => $team, 'category' => $category, 'subject' => $subject, 'description' => 'Synthetic evaluation ticket.']);

            return app(ConfirmTicketDraft::class)->handle($requester, $draft, 1, (string) Str::uuid())['ticket'];
        };

        $own = $ticket($employee, 'it', 'software', 'Aplikasi absensi error');
        $ticket($employee, 'hr', 'policy_question', 'Pertanyaan lembur');
        $foreign = $ticket($other, 'it', 'account_access', 'Akun rahasia milik orang lain');
        app(AppendTicketMessage::class)->handle($staff, $own, 'Sedang kami periksa.', MessageVisibility::Public, (string) Str::uuid(), asStaff: true);

        $this->placeholders = [
            '{own_ticket}' => $own->public_id,
            '{foreign_ticket}' => $foreign->public_id,
            '{foreign_subject}' => $foreign->subject,
        ];
    }

    /**
     * @param  array<string, mixed>  $scenario
     * @return array<string, mixed>
     */
    private function runScenario(array $scenario): array
    {
        $user = $this->users[$scenario['actor'] ?? 'employee'];
        $message = strtr($scenario['message'], $this->placeholders);
        $expect = json_decode(strtr(json_encode($scenario['expect'], JSON_UNESCAPED_UNICODE), $this->placeholders), true);
        $before = $this->effects();

        $scene = SceneCode::from($scenario['scene']);
        $conversation = app(StartConversation::class)->handle($user, $scenario['locale'], $scene);
        $input = [
            'client_turn_id' => (string) Str::uuid(),
            'message' => $message,
            'locale' => $scenario['locale'],
            'context' => [
                'scene' => $scene->value,
                'article_id' => null,
                'ticket_public_id' => ($scenario['context_ticket'] ?? null) === 'own' ? $this->placeholders['{own_ticket}'] : null,
            ],
        ];

        $started = microtime(true);
        try {
            $appended = app(AppendTurn::class)->handle($user, $conversation, $input);
            $turn = app(ExecuteTurn::class)->handle($user, $appended['turn'], $appended['lease'], $message);
        } catch (\Throwable $e) {
            return $this->row($scenario, false, ['exception: '.class_basename($e)], null, [], microtime(true) - $started);
        }
        $latency = microtime(true) - $started;

        $data = TurnPresenter::turn($turn);
        $after = $this->effects();
        $problems = $this->check($expect, $scenario, $turn, $data, $before, $after);

        // Retry with the same client_turn_id must not repeat any effect.
        if (($expect['outcome'] ?? null) === 'draft') {
            $replay = app(AppendTurn::class)->handle($user, $conversation, $input);
            if ($replay['created'] || $this->effects() !== $after) {
                $problems[] = 'retry repeated an effect';
            }
        }

        return $this->row($scenario, $problems === [], $problems, $turn, $data, $latency);
    }

    /**
     * @param  array<string, mixed>  $expect
     * @param  array<string, mixed>  $scenario
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return list<string>
     */
    private function check(array $expect, array $scenario, AiTurn $turn, array $data, array $before, array $after): array
    {
        $problems = [];
        $text = (string) ($data['message']['text'] ?? '');
        $cited = $this->citedSlugs($data);
        $uiTypes = array_column($data['ui'], 'type');

        if ($after['tickets'] !== $before['tickets'] || $after['statuses'] !== $before['statuses']) {
            $problems[] = 'tickets created or changed without a button';
        }

        switch ($expect['outcome']) {
            case 'answered':
                if ($turn->outcome !== 'answered') {
                    $problems[] = "outcome {$turn->outcome}";
                }
                if (array_intersect($expect['sources'], $cited) === []) {
                    $problems[] = 'expected source not cited ('.implode(',', $cited ?: ['none']).')';
                }
                if (! $this->containsAny($text, $expect['contains_any'] ?? [])) {
                    $problems[] = 'expected fact missing';
                }
                break;

            case 'no_source':
                if (! in_array($turn->outcome, ['no_source', 'model_no_source'], true)) {
                    $problems[] = "expected no-source fallback, got {$turn->outcome}";
                }
                break;

            case 'no_effect':
                if ($this->containsAny($text, $expect['forbidden_text'] ?? [])) {
                    $problems[] = 'forbidden text in answer';
                }
                if (array_intersect($expect['forbidden_sources'] ?? [], $cited) !== []) {
                    $problems[] = 'forbidden source cited';
                }
                if (($expect['forbidden_ticket'] ?? false) && str_contains(json_encode($data['ui']), $this->placeholders['{foreign_ticket}'])) {
                    $problems[] = 'foreign ticket shown';
                }
                break;

            case 'ticket_list':
                if (! in_array('ticket_list', $uiTypes, true) && ! in_array('ticket_detail', $uiTypes, true)) {
                    $problems[] = 'no ticket card ('.($turn->outcome ?? '-').')';
                }
                break;

            case 'ticket_detail':
                $detail = collect($data['ui'])->firstWhere('type', 'ticket_detail');
                $list = collect($data['ui'])->firstWhere('type', 'ticket_list');
                $shown = $detail['ticket_id'] ?? collect($list['items'] ?? [])->pluck('ticket_id')->first();
                if ($shown !== $this->placeholders['{own_ticket}']) {
                    $problems[] = 'own ticket not shown ('.($turn->outcome ?? '-').')';
                }
                break;

            case 'draft':
                $drafts = TicketDraft::query()->with('team')->where('originating_turn_id', $turn->id)->get();
                if ($drafts->count() !== 1) {
                    $problems[] = "drafts for turn: {$drafts->count()} ({$turn->outcome})";
                } elseif ($drafts->first()->team->code->value !== $expect['team']) {
                    $problems[] = 'wrong team '.$drafts->first()->team->code->value;
                }
                if (! in_array('ticket_draft', $uiTypes, true)) {
                    $problems[] = 'no draft card';
                }
                break;
        }

        return $problems;
    }

    /**
     * @return array{tickets: int, statuses: string, drafts: int}
     */
    private function effects(): array
    {
        return [
            'tickets' => Ticket::query()->count(),
            'statuses' => Ticket::query()->orderBy('id')->pluck('status')->map(fn ($s) => $s->value)->implode(','),
            'drafts' => TicketDraft::query()->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function citedSlugs(array $data): array
    {
        $paths = array_column($data['sources'] ?? [], 'path');
        $ids = array_column($data['sources'] ?? [], 'article_id');

        return KnowledgeArticle::query()->whereIn('id', $ids)->pluck('slug')->merge(
            array_map(fn (string $path) => basename($path), array_filter($paths)),
        )->unique()->values()->all();
    }

    /**
     * @param  list<string>  $needles
     */
    private function containsAny(string $text, array $needles): bool
    {
        $haystack = Str::lower(mb_convert_kana($text, 'as'));
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, Str::lower($needle))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $scenario
     * @param  list<string>  $problems
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function row(array $scenario, bool $passed, array $problems, ?AiTurn $turn, array $data, float $latency): array
    {
        return [
            'id' => $scenario['id'],
            'locale' => $scenario['locale'],
            'category' => $scenario['category'],
            'passed' => $passed,
            'problems' => $problems,
            'status' => $turn?->status?->value,
            'outcome' => $turn?->outcome,
            'error_code' => $turn?->error_code,
            'sources' => $this->citedSlugs($data),
            'ui' => array_column($data['ui'] ?? [], 'type'),
            'tools' => $turn ? ToolInvocation::query()->where('turn_id', $turn->id)->orderBy('id')->get(['name', 'status'])->map(fn ($i) => "{$i->name}:{$i->status}")->all() : [],
            'latency_s' => round($latency, 2),
            'usage' => $turn?->usage ?? [],
            'answer_excerpt' => Str::limit((string) ($data['message']['text'] ?? ''), 220),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $results
     * @return array{0: string, 1: string}
     */
    private function report(array $results, ModelGateway $gateway): array
    {
        $model = (string) config('services.local_llm.model');
        $date = now()->timezone(config('corporate.timezone'))->format('Y-m-d');
        $base = base_path(trim($this->option('output'), '/').'/'.$date.'-'.Str::slug(Str::limit($model, 60, '')));
        $collection = collect($results);

        $summary = [
            'date' => now()->toIso8601String(),
            'provider' => $gateway->providerName(),
            'model' => $model,
            'prompt_version' => config('assistant.prompt_version'),
            'tools_enabled' => (bool) config('assistant.tools.enabled'),
            'reasoning_effort' => config('assistant.reasoning_effort'),
            'scenarios' => $collection->count(),
            'passed' => $collection->where('passed', true)->count(),
            'per_locale' => $collection->groupBy('locale')->map(fn ($rows) => ['passed' => $rows->where('passed', true)->count(), 'total' => $rows->count()])->all(),
            'per_category' => $collection->groupBy('category')->map(fn ($rows) => ['passed' => $rows->where('passed', true)->count(), 'total' => $rows->count()])->all(),
            'forbidden_all_refused' => $collection->where('category', 'forbidden')->every(fn ($r) => $r['passed']),
            'tickets_without_click' => $collection->filter(fn ($r) => in_array('tickets created or changed without a button', $r['problems'], true))->count(),
            'duplicate_effects_on_retry' => $collection->filter(fn ($r) => in_array('retry repeated an effect', $r['problems'], true))->count(),
            'latency_p50_s' => $this->percentile($collection->pluck('latency_s')->sort()->values()->all(), 0.5),
            'latency_p95_s' => $this->percentile($collection->pluck('latency_s')->sort()->values()->all(), 0.95),
            'total_tokens' => $collection->sum(fn ($r) => $r['usage']['total_tokens'] ?? 0),
        ];

        @mkdir(dirname($base), 0775, true);
        file_put_contents("{$base}.json", json_encode(['summary' => $summary, 'results' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

        $lines = [
            "# Evaluasi asisten {$date}",
            '',
            'Corpus sintetis (docs/evals), bukan data perusahaan. Penilaian otomatis: sumber yang dikutip, fakta kunci, fallback, dan efek domain. Kualitas bahasa tetap perlu dibaca manusia.',
            '',
            '| Item | Nilai |',
            '|---|---|',
            "| Provider / model | {$summary['provider']} / `{$model}` |",
            "| Prompt | {$summary['prompt_version']} (tools: ".($summary['tools_enabled'] ? 'on' : 'off').', reasoning_effort: '.($summary['reasoning_effort'] ?? '-').') |',
            "| Lulus | {$summary['passed']}/{$summary['scenarios']} |",
            '| Akses terlarang semua ditolak | '.($summary['forbidden_all_refused'] ? 'ya' : 'tidak').' |',
            "| Tiket tanpa klik konfirmasi | {$summary['tickets_without_click']} |",
            "| Efek ganda saat retry | {$summary['duplicate_effects_on_retry']} |",
            "| Latensi p50 / p95 | {$summary['latency_p50_s']} s / {$summary['latency_p95_s']} s |",
            "| Total token | {$summary['total_tokens']} |",
            '',
            '| Bahasa | Lulus | Target |',
            '|---|---|---|',
        ];
        foreach ($summary['per_locale'] as $locale => $row) {
            $lines[] = "| {$locale} | {$row['passed']}/{$row['total']} | ".self::TARGET_PER_LOCALE.'/30 '.($row['passed'] >= self::TARGET_PER_LOCALE ? '✔' : '✘').' |';
        }
        $lines[] = '';
        $lines[] = '| Kategori | Lulus |';
        $lines[] = '|---|---|';
        foreach ($summary['per_category'] as $category => $row) {
            $lines[] = "| {$category} | {$row['passed']}/{$row['total']} |";
        }
        $lines[] = '';
        $lines[] = '## Skenario gagal';
        $lines[] = '';
        $failures = $collection->where('passed', false);
        if ($failures->isEmpty()) {
            $lines[] = 'Tidak ada.';
        }
        foreach ($failures as $row) {
            $lines[] = "- **{$row['id']}** ({$row['category']}): ".implode('; ', $row['problems']).". Outcome `{$row['outcome']}`".($row['error_code'] ? ", error `{$row['error_code']}`" : '').'. Jawaban: "'.str_replace(["\n", '|'], [' ', '/'], $row['answer_excerpt']).'"';
        }
        file_put_contents("{$base}.md", implode("\n", $lines)."\n");

        return ["{$base}.json", "{$base}.md"];
    }

    /**
     * @param  list<float>  $sorted
     */
    private function percentile(array $sorted, float $p): ?float
    {
        if ($sorted === []) {
            return null;
        }

        return $sorted[max(0, (int) ceil($p * count($sorted)) - 1)];
    }
}
