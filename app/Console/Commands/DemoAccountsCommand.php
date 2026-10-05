<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\User;
use App\Modules\Identity\Actions\ProvisionUser;
use App\Modules\Identity\Actions\ResetTemporaryPassword;
use App\Modules\Identity\Enums\RoleCode;
use App\Modules\Identity\Enums\SupportTeamCode;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Synthetic accounts for local/testing. Refuses any other environment and is
 * never run automatically on deploy. Re-running resets the passwords.
 */
#[Signature('corporate:demo-accounts')]
#[Description('Create synthetic demo accounts with random temporary passwords (local/testing only)')]
class DemoAccountsCommand extends Command
{
    public function handle(ProvisionUser $provision, ResetTemporaryPassword $reset): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->components->error('Demo accounts can only be created in local or testing environments.');

            return self::FAILURE;
        }

        $this->callSilently('db:seed', ['--class' => ReferenceSeeder::class, '--force' => true]);

        $departments = Department::query()->pluck('id', 'slug');
        $rows = [];

        foreach ($this->accounts($departments->all()) as $account) {
            $password = Str::password(16, symbols: false);
            $existing = User::query()->where('email', $account['email'])->first();

            if ($existing === null) {
                $provision->handle(null, $account + ['password' => $password]);
            } else {
                // Run as the existing record itself is not an admin path; use
                // the trusted system path (null actor) for both steps.
                $reset->handle(null, $existing, $password);
            }

            $rows[] = [$account['email'], implode(', ', $account['roles']), $password];
        }

        $this->table(['Email', 'Roles', 'Temporary password'], $rows);
        $this->components->warn('Passwords are shown once and must be changed at first sign-in. Do not store them in docs.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $departments
     * @return list<array<string, mixed>>
     */
    private function accounts(array $departments): array
    {
        $base = ['preferred_locale' => 'id', 'support_teams' => [], 'editor_departments' => []];

        return [
            ['name' => 'Demo Employee', 'email' => 'demo.employee@example.test', 'department_id' => $departments['engineering'], 'roles' => [RoleCode::Employee->value]] + $base,
            ['name' => 'Demo Editor', 'email' => 'demo.editor@example.test', 'department_id' => $departments['general'], 'roles' => [RoleCode::Employee->value, RoleCode::Editor->value], 'editor_departments' => [$departments['general'], $departments['hr']]] + $base,
            ['name' => 'Demo Staff HR', 'email' => 'demo.staff.hr@example.test', 'department_id' => $departments['hr'], 'roles' => [RoleCode::Employee->value, RoleCode::Staff->value], 'support_teams' => [SupportTeamCode::Hr->value]] + $base,
            ['name' => 'Demo Staff IT', 'email' => 'demo.staff.it@example.test', 'department_id' => $departments['it'], 'roles' => [RoleCode::Employee->value, RoleCode::Staff->value], 'support_teams' => [SupportTeamCode::It->value]] + $base,
            ['name' => 'Demo Admin', 'email' => 'demo.admin@example.test', 'department_id' => $departments['general'], 'roles' => [RoleCode::Employee->value, RoleCode::Admin->value]] + $base,
        ];
    }
}
