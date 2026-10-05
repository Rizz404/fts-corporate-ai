<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\TestDatabaseGuard;
use Tests\TestCase;

class SetupSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_renders_brand(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(config('corporate.brand.name'))
            ->assertSee('<html lang="id">', false)
            ->assertSee('name="csrf-token"', false);
    }

    public function test_health_endpoint_is_up(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_tests_run_against_a_separate_test_database(): void
    {
        $connection = DB::connection();

        TestDatabaseGuard::assertSafe($connection->getDriverName(), $connection->getDatabaseName());

        $this->assertSame(1, (int) DB::selectOne('select 1 as one')->one);
    }

    public function test_framework_migrations_are_present(): void
    {
        foreach (['users', 'password_reset_tokens', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table {$table}");
        }
    }

    public function test_storage_timezone_is_utc_and_display_timezone_is_configured(): void
    {
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('Asia/Jakarta', config('corporate.timezone'));
    }

    public function test_valid_lang_query_sets_locale_and_persists_in_session(): void
    {
        $this->get('/?lang=ja')
            ->assertOk()
            ->assertSee('<html lang="ja">', false)
            ->assertSessionHas('locale', 'ja');

        $this->get('/')->assertSee('<html lang="ja">', false);
    }

    public function test_invalid_lang_on_page_falls_back_to_session_or_default(): void
    {
        $this->get('/?lang=fr')
            ->assertOk()
            ->assertSee('<html lang="id">', false)
            ->assertSessionMissing('locale');

        $this->withSession(['locale' => 'en'])
            ->get('/?lang=xx')
            ->assertOk()
            ->assertSee('<html lang="en">', false);
    }

    public function test_every_response_has_a_request_id(): void
    {
        $response = $this->get('/');

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $response->headers->get('X-Request-Id'));
    }

    public function test_dev_preview_links_show_on_landing_outside_production(): void
    {
        $this->get('/')->assertSee(route('dev.ui'));
    }
}
