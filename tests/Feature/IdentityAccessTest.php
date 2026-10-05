<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\User;
use App\Modules\Identity\Actions\DisableUser;
use App\Modules\Identity\Actions\ResetTemporaryPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\BuildsCorporateData;
use Tests\TestCase;

/**
 * Plan 2: sign-in, temporary passwords, throttling, admin area, last-admin
 * protection, provisioning, preferences, and audit (no passwords logged).
 */
class IdentityAccessTest extends TestCase
{
    use BuildsCorporateData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();
    }

    public function test_login_normalizes_email_and_stores_preferred_locale(): void
    {
        $user = $this->makeUser(attributes: ['email' => 'mixed.case@example.test', 'preferred_locale' => 'ja']);

        $this->post('/login', ['email' => '  Mixed.Case@Example.TEST ', 'password' => 'password-for-tests'])
            ->assertRedirect(route('office.reception'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('ja', session('locale'));
    }

    public function test_wrong_password_and_inactive_account_cannot_sign_in(): void
    {
        $this->makeUser(attributes: ['email' => 'a@example.test']);
        $this->makeUser(attributes: ['email' => 'off@example.test', 'is_active' => false]);

        $this->post('/login', ['email' => 'a@example.test', 'password' => 'wrong-password'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'off@example.test', 'password' => 'password-for-tests'])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_is_throttled_after_five_attempts(): void
    {
        $this->makeUser(attributes: ['email' => 'throttle@example.test']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'throttle@example.test', 'password' => 'wrong-password']);
        }

        $response = $this->post('/login', ['email' => 'throttle@example.test', 'password' => 'password-for-tests']);
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_temporary_password_must_be_changed_on_pages_and_json(): void
    {
        $user = $this->makeUser(attributes: ['must_change_password' => true]);

        $this->actingAs($user)->get('/office')->assertRedirect(route('password.change'));
        $this->actingAs($user)->getJson('/api/v1/tickets')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'password_change_required');

        $this->actingAs($user)->post('/password/change', [
            'current_password' => 'password-for-tests',
            'password' => 'a-new-long-password',
            'password_confirmation' => 'a-new-long-password',
        ])->assertRedirect(route('office.reception'));

        $this->assertFalse($user->refresh()->must_change_password);
        $this->assertTrue(Hash::check('a-new-long-password', $user->password));
        $this->actingAs($user)->get('/office')->assertOk();
    }

    public function test_admin_area_requires_admin_editor_or_staff(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
        $this->actingAs($this->employee())->get('/admin')->assertForbidden();
        $this->actingAs($this->admin())->get('/admin')->assertOk();
        $this->actingAs($this->staff('hr'))->get('/admin')->assertOk();
    }

    public function test_user_with_two_teams_and_several_roles_gets_no_global_permission(): void
    {
        $user = $this->makeUser(['employee', 'staff', 'editor'], 'it', ['hr', 'it'], ['it']);

        $this->actingAs($user)->get('/admin')->assertOk();
        $this->actingAs($user)->get('/admin/users')->assertForbidden();
        $this->actingAs($user)->get('/admin/settings')->assertForbidden();
        $this->actingAs($user)->get('/admin/operations')->assertForbidden();
    }

    public function test_admin_provisions_user_with_temporary_password_and_audit_without_password(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'New Person',
            'email' => 'New.Person@Example.test',
            'department_id' => $this->department('it')->id,
            'preferred_locale' => 'en',
            'roles' => ['employee', 'staff'],
            'support_teams' => ['it'],
            'password' => 'temporary-pass-123',
            'password_confirmation' => 'temporary-pass-123',
        ])->assertRedirect(route('admin.users.index'));

        $user = User::query()->where('email', 'new.person@example.test')->firstOrFail();
        $this->assertTrue($user->must_change_password);
        $this->assertEqualsCanonicalizing(['employee', 'staff'], $user->roles()->pluck('code')->map(fn ($c) => $c->value ?? $c)->all());

        $audit = AuditEvent::query()->where('action', 'user.provisioned')->firstOrFail();
        $this->assertStringNotContainsString('temporary-pass-123', json_encode($audit->metadata));
    }

    public function test_invalid_assignments_are_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'X',
            'email' => 'x@example.test',
            'department_id' => $this->department()->id,
            'preferred_locale' => 'xx',
            'roles' => ['superuser'],
            'support_teams' => ['finance'],
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors(['preferred_locale', 'roles.0', 'support_teams.0', 'password']);
    }

    public function test_last_active_admin_cannot_lose_admin_role_or_be_disabled(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put("/admin/users/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'department_id' => $admin->department_id,
            'preferred_locale' => 'id',
            'roles' => ['employee'],
            'is_active' => '1',
        ])->assertSessionHasErrors('roles');

        $this->assertTrue($admin->refresh()->roles()->where('code', 'admin')->exists());
    }

    public function test_disable_and_reset_revoke_other_sessions(): void
    {
        $admin = $this->admin();
        $other = $this->admin();
        $user = $this->employee();

        app(ResetTemporaryPassword::class)->handle($admin, $user, 'brand-new-temp-pass');
        $this->assertTrue($user->refresh()->must_change_password);

        app(DisableUser::class)->handle($admin, $user);
        $this->assertFalse($user->refresh()->is_active);

        $this->actingAs($user)->get('/office')->assertRedirect(route('login'));
        $this->assertNotNull($other);
    }

    public function test_preferences_api_validates_and_saves_locale(): void
    {
        $user = $this->employee();

        $this->actingAs($user)->postJson('/api/v1/preferences', ['locale' => 'fr'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->actingAs($user)->postJson('/api/v1/preferences', ['locale' => 'ja'])->assertOk();

        $this->assertSame('ja', $user->refresh()->preferred_locale);
        $this->assertSame('ja', session('locale'));
    }

    public function test_logout_invalidates_session(): void
    {
        $user = $this->employee();

        $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_demo_accounts_command_refuses_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('corporate:demo-accounts')->assertFailed();
    }

    public function test_create_admin_command_prompts_for_secret_password(): void
    {
        $this->artisan('corporate:create-admin', ['--email' => 'Boss@Example.test', '--name' => 'Boss'])
            ->expectsQuestion('Temporary password (min 12 characters)', 'admin-password-123')
            ->expectsQuestion('Confirm temporary password', 'admin-password-123')
            ->assertSuccessful();

        $boss = User::query()->where('email', 'boss@example.test')->firstOrFail();
        $this->assertTrue($boss->roles()->where('code', 'admin')->exists());
        $this->assertTrue($boss->must_change_password);
    }
}
