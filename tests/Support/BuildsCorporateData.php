<?php

namespace Tests\Support;

use App\Models\Department;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeRevision;
use App\Models\Ticket;
use App\Models\TicketDraft;
use App\Models\User;
use App\Modules\Identity\Actions\ProvisionUser;
use App\Modules\Identity\Enums\RoleCode;
use App\Modules\Knowledge\Actions\CreateArticle;
use App\Modules\Knowledge\Actions\PublishRevision;
use App\Modules\Support\Actions\ConfirmTicketDraft;
use App\Modules\Support\Actions\PrepareTicketDraft;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Support\Str;

/**
 * Synthetic users, articles, drafts, and tickets for feature tests. Uses the
 * real actions so tests exercise the same rules as the application.
 */
trait BuildsCorporateData
{
    protected function seedReference(): void
    {
        $this->seed(ReferenceSeeder::class);
    }

    protected function department(string $slug = 'general'): Department
    {
        return Department::query()->where('slug', $slug)->firstOrFail();
    }

    /**
     * @param  list<string>  $roles
     * @param  list<string>  $teams
     * @param  list<string>  $editorDepartments  Department slugs.
     */
    protected function makeUser(array $roles = ['employee'], string $department = 'general', array $teams = [], array $editorDepartments = [], array $attributes = []): User
    {
        $user = User::factory()->create(['department_id' => $this->department($department)->id] + $attributes);

        ProvisionUser::syncAccess($user, [
            'roles' => $roles,
            'support_teams' => $teams,
            'editor_departments' => array_map(fn (string $slug) => $this->department($slug)->id, $editorDepartments),
        ]);

        return $user->refresh();
    }

    protected function employee(string $department = 'general'): User
    {
        return $this->makeUser(['employee'], $department);
    }

    protected function admin(): User
    {
        return $this->makeUser(['employee', 'admin']);
    }

    protected function staff(string $team): User
    {
        return $this->makeUser(['employee', 'staff'], $team === 'hr' ? 'hr' : 'it', [$team]);
    }

    /**
     * Published article with id/en/ja translations.
     *
     * @param  array<string, array{title: string, body: string, tags?: list<string>, aliases?: list<string>}>|null  $translations
     * @param  list<string>  $departments  Audience department slugs (audience "departments").
     */
    protected function publishedArticle(string $slug, string $category = 'hr', ?array $translations = null, array $departments = [], ?User $author = null): KnowledgeArticle
    {
        $author ??= User::query()->withRole(RoleCode::Admin)->first() ?? $this->admin();
        $translations ??= [
            'id' => ['title' => "Panduan {$slug}", 'body' => "Isi panduan {$slug} untuk karyawan."],
            'en' => ['title' => "Guide {$slug}", 'body' => "Guide {$slug} content for employees."],
            'ja' => ['title' => "{$slug} ガイド", 'body' => "{$slug} の社員向けガイドです。"],
        ];

        $article = app(CreateArticle::class)->handle($author, [
            'slug' => $slug,
            'owner_department_id' => $this->department('general')->id,
            'category' => $category,
            'audience' => $departments === [] ? 'common' : 'departments',
            'department_ids' => array_map(fn (string $d) => $this->department($d)->id, $departments),
            'effective_from' => null,
            'effective_until' => null,
            'translations' => array_map(fn (array $t) => $t + ['tags' => [], 'aliases' => []], $translations),
        ]);

        app(PublishRevision::class)->handle($author, $article);

        return $article->refresh();
    }

    protected function publishedRevision(KnowledgeArticle $article): KnowledgeRevision
    {
        return $article->revisions()->where('status', 'published')->firstOrFail();
    }

    /**
     * @param  array<string, string>  $data
     */
    protected function draftFor(User $requester, array $data = []): TicketDraft
    {
        return app(PrepareTicketDraft::class)->handle($requester, $data + [
            'team_code' => 'it',
            'category' => 'network',
            'subject' => 'Wi-Fi tidak tersambung',
            'description' => 'Laptop tidak bisa terhubung ke Wi-Fi kantor.',
        ]);
    }

    /**
     * @param  array<string, string>  $data
     */
    protected function ticketFor(User $requester, array $data = []): Ticket
    {
        $draft = $this->draftFor($requester, $data);

        return app(ConfirmTicketDraft::class)->handle($requester, $draft, 1, (string) Str::uuid())['ticket'];
    }
}
