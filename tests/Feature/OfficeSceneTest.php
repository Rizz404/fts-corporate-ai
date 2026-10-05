<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Modules\Experience\Enums\SceneCode;
use App\Modules\Experience\Support\SceneImages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCorporateData;
use Tests\TestCase;

/**
 * Plan 3: office scenes, locale handling, image resolver (placeholder vs
 * local final assets), and company branding settings.
 */
class OfficeSceneTest extends TestCase
{
    use BuildsCorporateData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        foreach (['/office', '/office/knowledge', '/office/hr', '/office/it', '/office/requests'] as $path) {
            $this->get($path)->assertRedirect(route('login'));
        }
    }

    public function test_all_five_scenes_render_with_context_and_assets(): void
    {
        $user = $this->employee();

        foreach (['/office' => 'reception', '/office/knowledge' => 'knowledge', '/office/hr' => 'hr', '/office/it' => 'it', '/office/requests' => 'requests'] as $path => $scene) {
            $this->actingAs($user)->get($path)
                ->assertOk()
                ->assertSee("assets/corporate/{$scene}.svg", false)
                ->assertSee('assets/corporate/assistant.svg', false)
                ->assertSee('"scene":"'.$scene.'"', false);
        }
    }

    public function test_scene_labels_follow_locale_and_ignore_invalid_values(): void
    {
        $user = $this->employee();

        $this->actingAs($user)->get('/office/hr?lang=ja')->assertOk()->assertSee('<html lang="ja">', false)->assertSee(__('ui.nav.rooms.hr', [], 'ja'));
        $this->actingAs($user)->get('/office/hr?lang=en')->assertOk()->assertSee(__('ui.nav.rooms.hr', [], 'en'));
        $this->actingAs($user)->get('/office/hr?lang=zz')->assertOk()->assertSee('<html lang="en">', false);
    }

    public function test_inactive_user_is_signed_out(): void
    {
        $user = $this->makeUser(attributes: ['is_active' => false]);

        $this->actingAs($user)->get('/office')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_placeholder_urls_are_seeded_per_scene_without_user_data(): void
    {
        config(['corporate.scenes.image_source' => 'placeholder']);
        $images = app(SceneImages::class);

        $this->assertSame('https://picsum.photos/seed/corporate-hr/1600/900', $images->backdrop(SceneCode::Hr));
        $this->assertSame($images->backdrop(SceneCode::Hr), $images->backdrop(SceneCode::Hr));
        $this->assertNotSame($images->backdrop(SceneCode::Hr), $images->backdrop(SceneCode::It));
    }

    public function test_local_mode_points_to_final_assets_and_files_exist(): void
    {
        config(['corporate.scenes.image_source' => 'local']);
        $images = app(SceneImages::class);

        foreach (SceneCode::cases() as $scene) {
            $this->assertStringEndsWith("/assets/corporate/{$scene->value}.svg", $images->backdrop($scene));
            $this->assertFileExists(public_path("assets/corporate/{$scene->value}.svg"));
        }
        $this->assertFileExists(public_path('assets/corporate/assistant.svg'));
        $this->assertFileExists(public_path('favicon.svg'));
    }

    public function test_production_never_uses_remote_placeholders(): void
    {
        config(['corporate.scenes.image_source' => 'placeholder']);
        $this->app['env'] = 'production';

        $this->assertSame(SceneImages::SOURCE_LOCAL, app(SceneImages::class)->source());
        $this->assertStringNotContainsString('picsum', app(SceneImages::class)->character());
    }

    public function test_only_admin_updates_branding_with_validation_escaping_and_audit(): void
    {
        $payload = [
            'brand_name' => 'Acme Office',
            'welcome_text' => ['id' => 'Halo "Tim" & semua', 'en' => 'Hello', 'ja' => 'こんにちは'],
        ];

        $this->actingAs($this->staff('hr'))->patch('/admin/settings', $payload)->assertForbidden();

        $this->actingAs($this->admin())->patch('/admin/settings', ['brand_name' => '<script>x</script>'] + $payload)
            ->assertSessionHasErrors('brand_name');

        $this->actingAs($this->admin())->patch('/admin/settings', ['welcome_text' => ['id' => '<b>x</b>']] + $payload)
            ->assertSessionHasErrors('welcome_text.id');

        $this->actingAs($this->admin())->patch('/admin/settings', ['brand_name' => str_repeat('a', 161)] + $payload)
            ->assertSessionHasErrors('brand_name');

        $this->actingAs($this->admin())->patch('/admin/settings', $payload)->assertSessionHasNoErrors();
        $this->assertTrue(AuditEvent::query()->where('action', 'like', 'company_settings%')->exists());

        $this->actingAs($this->employee())->get('/office')
            ->assertOk()
            ->assertDontSee('Halo "Tim" & semua', false)
            ->assertSee('Halo &quot;Tim&quot; &amp; semua', false);
    }
}
