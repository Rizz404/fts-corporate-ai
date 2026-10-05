<?php

namespace App\Modules\Identity;

use App\Models\User;
use App\Modules\Identity\Enums\RoleCode;
use App\Modules\Identity\Enums\SupportTeamCode;

/**
 * Immutable snapshot of who the actor is, read from the database.
 *
 * Identity, department, roles, teams, and editor scope never come from
 * request bodies, scene context, or AI tool arguments. Team membership only
 * counts with the staff role and an active team; editor scope only counts
 * with the editor role and an active department.
 */
final class ActorContext
{
    /**
     * @param  list<string>  $roles
     * @param  list<string>  $teamCodes
     * @param  list<int>  $editorDepartmentIds
     */
    public function __construct(
        public readonly int $userId,
        public readonly int $departmentId,
        public readonly bool $isActive,
        public readonly bool $mustChangePassword,
        public readonly array $roles,
        public readonly array $teamCodes,
        public readonly array $editorDepartmentIds,
    ) {}

    /**
     * Loads a fresh snapshot from the database.
     */
    public static function load(User|int $user): self
    {
        $id = $user instanceof User ? $user->getKey() : $user;

        /** @var User $fresh */
        $fresh = User::query()
            ->with([
                'roles:id,code',
                'supportTeams' => fn ($query) => $query->where('support_teams.is_active', true)->select('support_teams.id', 'code'),
                'editorDepartments' => fn ($query) => $query->where('departments.is_active', true)->select('departments.id'),
            ])
            ->findOrFail($id);

        $roles = $fresh->roles->map(fn ($role) => $role->code->value)->values()->all();
        $isStaff = in_array(RoleCode::Staff->value, $roles, true);
        $isEditor = in_array(RoleCode::Editor->value, $roles, true);

        return new self(
            userId: $fresh->id,
            departmentId: $fresh->department_id,
            isActive: $fresh->is_active,
            mustChangePassword: $fresh->must_change_password,
            roles: $roles,
            teamCodes: $isStaff ? $fresh->supportTeams->map(fn ($team) => $team->code->value)->values()->all() : [],
            editorDepartmentIds: $isEditor ? $fresh->editorDepartments->pluck('id')->map(fn ($id) => (int) $id)->values()->all() : [],
        );
    }

    public function hasRole(RoleCode $role): bool
    {
        return $this->isActive && in_array($role->value, $this->roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(RoleCode::Admin);
    }

    public function isEditor(): bool
    {
        return $this->hasRole(RoleCode::Editor);
    }

    public function isStaff(): bool
    {
        return $this->hasRole(RoleCode::Staff);
    }

    public function isStaffOf(SupportTeamCode $team): bool
    {
        return $this->isStaff() && in_array($team->value, $this->teamCodes, true);
    }

    public function canAuthorFor(int $departmentId): bool
    {
        return $this->isEditor() && in_array($departmentId, $this->editorDepartmentIds, true);
    }

    /**
     * Whether the actor may open the /admin area at all. Each page still
     * checks its own ability.
     */
    public function canAccessAdminArea(): bool
    {
        return $this->isAdmin() || $this->isEditor() || $this->isStaff();
    }
}
