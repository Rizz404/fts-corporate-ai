<?php

namespace App\Modules\Identity\Actions;

use App\Models\Role;
use App\Models\SupportTeam;
use App\Models\User;
use App\Modules\Identity\Support\AdminGuard;
use App\Modules\Identity\Validation\UserAccessRules;
use Illuminate\Support\Facades\DB;

/**
 * Creates an employee account with a temporary password.
 *
 * $actor is the admin doing it, or null for trusted CLI provisioning.
 */
class ProvisionUser
{
    public function __construct(
        private readonly AdminGuard $guard,
        private readonly WriteAuditEvent $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Validated with UserAccessRules::rules().
     */
    public function handle(?User $actor, array $data): User
    {
        UserAccessRules::assertConsistent($data);

        return DB::transaction(function () use ($actor, $data) {
            $this->guard->assertActorIsAdmin($actor);

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'department_id' => $data['department_id'],
                'preferred_locale' => $data['preferred_locale'] ?? config('corporate.default_locale'),
                'is_active' => true,
                'must_change_password' => true,
            ]);

            $access = self::syncAccess($user, $data);

            $this->audit->handle($actor, 'user.provisioned', $user, [
                'department_id' => $user->department_id,
            ] + $access);

            return $user;
        });
    }

    /**
     * Syncs roles, support teams, and editor departments.
     *
     * @param  array<string, mixed>  $data
     * @return array{roles: list<string>, support_teams: list<string>, editor_departments: list<int>}
     */
    public static function syncAccess(User $user, array $data): array
    {
        $roles = array_values(array_unique((array) ($data['roles'] ?? [])));
        $teams = array_values(array_unique((array) ($data['support_teams'] ?? [])));
        $departments = array_values(array_unique(array_map('intval', (array) ($data['editor_departments'] ?? []))));

        sort($roles);
        sort($teams);
        sort($departments);

        $user->roles()->sync(Role::query()->whereIn('code', $roles)->pluck('id'));
        $user->supportTeams()->sync(SupportTeam::query()->whereIn('code', $teams)->pluck('id'));
        $user->editorDepartments()->sync($departments);

        return ['roles' => $roles, 'support_teams' => $teams, 'editor_departments' => $departments];
    }
}
