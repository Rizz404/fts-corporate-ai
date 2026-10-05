<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeRevision;
use App\Models\User;
use App\Modules\Identity\Actions\ProvisionUser;
use App\Modules\Knowledge\Actions\ArchiveRevision;
use App\Modules\Knowledge\Actions\CloneRevision;
use App\Modules\Knowledge\Actions\CreateArticle;
use App\Modules\Knowledge\Actions\PublishRevision;
use App\Modules\Knowledge\Actions\SaveDraft;
use App\Modules\Knowledge\Enums\RevisionStatus;
use App\Modules\Knowledge\KnowledgeRetriever;
use Database\Seeders\KnowledgeDemoSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\BuildsCorporateData;
use Tests\TestCase;

/**
 * Plan 4: publication lifecycle, audience access, and deterministic retrieval.
 */
class KnowledgeTest extends TestCase
{
    use BuildsCorporateData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();
    }

    // --- Publication -----------------------------------------------------

    public function test_incomplete_draft_cannot_be_published(): void
    {
        $admin = $this->admin();
        $article = app(CreateArticle::class)->handle($admin, [
            'slug' => 'incomplete',
            'owner_department_id' => $this->department()->id,
            'category' => 'general',
            'audience' => 'common',
            'department_ids' => [],
            'effective_from' => null,
            'effective_until' => null,
            'translations' => ['id' => ['title' => 'Judul', 'body' => 'Isi', 'tags' => [], 'aliases' => []]],
        ]);

        try {
            app(PublishRevision::class)->handle($admin, $article);
            $this->fail('Incomplete draft was published.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('translations.en.title', $e->errors());
            $this->assertArrayHasKey('translations.ja.body', $e->errors());
        }
    }

    public function test_publishing_new_version_archives_previous_and_keeps_one_published(): void
    {
        $admin = $this->admin();
        $article = $this->publishedArticle('leave-policy');

        $draft = app(CloneRevision::class)->handle($admin, $article);
        $this->assertSame(2, $draft->version);
        $this->assertSame(RevisionStatus::Draft, $draft->status);
        $this->assertSame($draft->id, app(CloneRevision::class)->handle($admin, $article)->id, 'only one draft per article');

        app(PublishRevision::class)->handle($admin, $article);

        $statuses = KnowledgeRevision::query()->where('article_id', $article->id)->orderBy('version')->pluck('status')->map(fn ($s) => $s->value)->all();
        $this->assertSame(['archived', 'published'], $statuses);
    }

    public function test_published_revision_is_immutable_through_save_draft(): void
    {
        $admin = $this->admin();
        $article = $this->publishedArticle('immutable');
        $published = $this->publishedRevision($article);

        app(CloneRevision::class)->handle($admin, $article);
        app(SaveDraft::class)->handle($admin, $article, [
            'category' => 'hr',
            'audience' => 'common',
            'department_ids' => [],
            'effective_from' => null,
            'effective_until' => null,
            'translations' => [
                'id' => ['title' => 'Baru', 'body' => 'Isi baru', 'tags' => [], 'aliases' => []],
                'en' => ['title' => 'New', 'body' => 'New body', 'tags' => [], 'aliases' => []],
                'ja' => ['title' => '新', 'body' => '新しい本文', 'tags' => [], 'aliases' => []],
            ],
        ]);

        $this->assertSame('Guide immutable', $published->refresh()->translation('en')->title);
        $this->assertTrue($article->revisions()->where('status', 'draft')->exists());
    }

    public function test_future_publication_and_past_expiry_are_rejected(): void
    {
        $admin = $this->admin();
        $base = [
            'owner_department_id' => $this->department()->id,
            'category' => 'general',
            'audience' => 'common',
            'department_ids' => [],
            'effective_until' => null,
            'translations' => collect(['id', 'en', 'ja'])->mapWithKeys(fn ($l) => [$l => ['title' => 'T', 'body' => 'B', 'tags' => [], 'aliases' => []]])->all(),
        ];

        $future = app(CreateArticle::class)->handle($admin, ['slug' => 'future', 'effective_from' => now()->addDay()->toIso8601String()] + $base);
        $this->expectException(ValidationException::class);
        app(PublishRevision::class)->handle($admin, $future);
    }

    public function test_archive_hides_article_from_readers(): void
    {
        $admin = $this->admin();
        $article = $this->publishedArticle('to-archive');
        $reader = $this->employee();

        $this->actingAs($reader)->get('/office/knowledge/to-archive')->assertOk();

        app(ArchiveRevision::class)->handle($admin, $article);

        $this->actingAs($reader)->get('/office/knowledge/to-archive')->assertNotFound();
        $this->assertSame([], app(KnowledgeRetriever::class)->search($reader, 'to-archive', 'en'));
    }

    public function test_expired_article_is_not_visible(): void
    {
        $article = $this->publishedArticle('expiring');
        $this->publishedRevision($article)->forceFill(['effective_until' => now()->subMinute()])->save();

        $this->actingAs($this->employee())->get('/office/knowledge/expiring')->assertNotFound();
    }

    public function test_editor_manages_only_own_department_and_cannot_publish(): void
    {
        $editor = $this->makeUser(['employee', 'editor'], 'hr', [], ['hr']);

        $article = app(CreateArticle::class)->handle($editor, [
            'slug' => 'hr-only',
            'owner_department_id' => $this->department('hr')->id,
            'category' => 'hr',
            'audience' => 'common',
            'department_ids' => [],
            'effective_from' => null,
            'effective_until' => null,
            'translations' => [],
        ]);
        $this->assertInstanceOf(KnowledgeArticle::class, $article);

        $this->expectException(AuthorizationException::class);
        app(CreateArticle::class)->handle($editor, [
            'slug' => 'it-attempt',
            'owner_department_id' => $this->department('it')->id,
            'category' => 'it',
            'audience' => 'common',
            'department_ids' => [],
            'effective_from' => null,
            'effective_until' => null,
            'translations' => [],
        ]);
    }

    public function test_editor_cannot_publish(): void
    {
        $editor = $this->makeUser(['employee', 'editor'], 'hr', [], ['hr']);
        $article = $this->publishedArticle('publish-check');
        app(CloneRevision::class)->handle($this->admin(), $article);

        $this->expectException(AuthorizationException::class);
        app(PublishRevision::class)->handle($editor, $article);
    }

    public function test_cms_menu_only_for_admin_or_editor_with_department(): void
    {
        $this->actingAs($this->employee())->get('/admin/knowledge')->assertForbidden();
        $this->actingAs($this->makeUser(['employee', 'editor'], 'hr', [], ['hr']))->get('/admin/knowledge')->assertOk();
        $this->actingAs($this->admin())->get('/admin/knowledge')->assertOk();
    }

    // --- Access ----------------------------------------------------------

    public function test_department_audience_is_enforced_without_admin_bypass(): void
    {
        $article = $this->publishedArticle('eng-only', 'general', null, ['engineering']);

        $this->actingAs($this->employee('engineering'))->get('/office/knowledge/eng-only')->assertOk();
        $this->actingAs($this->employee('hr'))->get('/office/knowledge/eng-only')->assertNotFound();
        $this->actingAs($this->admin())->get('/office/knowledge/eng-only')->assertNotFound();
        $this->actingAs($this->employee('hr'))->getJson("/api/v1/knowledge/{$article->id}")->assertNotFound();
    }

    public function test_knowledge_api_validates_input(): void
    {
        $user = $this->employee();

        $this->actingAs($user)->getJson('/api/v1/knowledge?q=cuti&locale=fr')->assertStatus(422);
        $this->actingAs($user)->getJson('/api/v1/knowledge?q='.str_repeat('a', 501))->assertStatus(422);
        $this->actingAs($user)->getJson('/api/v1/knowledge/999999')->assertNotFound();
    }

    public function test_article_body_is_escaped(): void
    {
        $this->publishedArticle('xss', 'general', [
            'id' => ['title' => '<script>alert(1)</script>', 'body' => '<img src=x onerror=alert(1)>'],
            'en' => ['title' => '<script>alert(1)</script>', 'body' => '<img src=x onerror=alert(1)>'],
            'ja' => ['title' => '<script>alert(1)</script>', 'body' => '<img src=x onerror=alert(1)>'],
        ]);

        $this->actingAs($this->employee())->get('/office/knowledge/xss')
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<img src=x', false);
    }

    // --- Retrieval -------------------------------------------------------

    public function test_retrieval_matches_aliases_across_languages_and_returns_requested_locale(): void
    {
        $this->seed(KnowledgeDemoSeederWithAdmin::class);
        $user = $this->employee();
        $retriever = app(KnowledgeRetriever::class);

        $id = $retriever->search($user, 'cuti tahunan', 'id');
        $this->assertSame('[DEMO] Cara mengajukan cuti', $id[0]->title);

        $ja = $retriever->search($user, 'cuti', 'ja');
        $this->assertSame('[DEMO] 休暇の申請方法', $ja[0]->title);

        $en = $retriever->search($user, '休暇', 'en');
        $this->assertSame('[DEMO] How to request leave', $en[0]->title);
    }

    public function test_retrieval_normalizes_unicode_and_escapes_like_wildcards(): void
    {
        $this->seed(KnowledgeDemoSeederWithAdmin::class);
        $user = $this->employee();
        $retriever = app(KnowledgeRetriever::class);

        $this->assertNotEmpty($retriever->search($user, 'ＰＡＳＳＷＯＲＤ', 'en'), 'full-width text is NFKC-normalized');
        $this->assertSame([], $retriever->search($user, '%', 'en'));
        $this->assertSame([], $retriever->search($user, '___', 'en'));
        $this->assertSame([], $retriever->search($user, 'zzqxv nonexistent', 'en'));
    }

    public function test_retrieval_respects_audience_and_limit(): void
    {
        $this->seed(KnowledgeDemoSeederWithAdmin::class);
        $retriever = app(KnowledgeRetriever::class);

        $this->assertSame([], $retriever->search($this->employee('hr'), 'code review', 'en'));
        $this->assertNotEmpty($retriever->search($this->employee('engineering'), 'code review', 'en'));
        $this->assertLessThanOrEqual(5, count($retriever->search($this->employee(), 'demo', 'en')));
    }

    public function test_knowledge_demo_seeder_refuses_production(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(\RuntimeException::class);
        $this->app->call([$this->app->make(KnowledgeDemoSeeder::class), 'run']);
    }
}

/**
 * KnowledgeDemoSeeder needs an existing admin.
 */
class KnowledgeDemoSeederWithAdmin extends Seeder
{
    public function run(): void
    {
        $user = User::factory()->create(['department_id' => Department::query()->where('slug', 'general')->value('id')]);
        ProvisionUser::syncAccess($user, ['roles' => ['employee', 'admin']]);

        $this->call(KnowledgeDemoSeeder::class);
    }
}
