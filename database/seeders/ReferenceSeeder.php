<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use App\Models\Department;
use App\Models\Role;
use App\Models\SupportTeam;
use App\Modules\Identity\Enums\RoleCode;
use App\Modules\Identity\Enums\SupportTeamCode;
use Illuminate\Database\Seeder;

/**
 * Idempotent reference data: departments, roles, support teams, and the
 * default company settings row.
 * Safe to run on every deploy; never creates employee accounts.
 */
class ReferenceSeeder extends Seeder
{
    public const DEPARTMENTS = [
        'general' => 'General',
        'hr' => 'Human Resources',
        'it' => 'Information Technology',
        'engineering' => 'Engineering',
    ];

    public const SUPPORT_TEAMS = [
        'hr' => 'HR',
        'it' => 'IT',
    ];

    public function run(): void
    {
        foreach (self::DEPARTMENTS as $slug => $name) {
            Department::firstOrCreate(['slug' => $slug], ['name' => $name, 'is_active' => true]);
        }

        foreach (RoleCode::cases() as $role) {
            Role::firstOrCreate(['code' => $role->value]);
        }

        foreach (SupportTeamCode::cases() as $team) {
            SupportTeam::firstOrCreate(['code' => $team->value], ['name' => self::SUPPORT_TEAMS[$team->value], 'is_active' => true]);
        }

        if (! CompanySetting::query()->whereKey(CompanySetting::SINGLETON_ID)->exists()) {
            $settings = new CompanySetting(CompanySetting::defaults());
            $settings->id = CompanySetting::SINGLETON_ID;
            $settings->save();
        }
    }
}
