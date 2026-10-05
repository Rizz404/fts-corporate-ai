<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;
use App\Modules\Identity\CurrentActor;

/**
 * Department management is admin-only. No global before() bypass.
 */
class DepartmentPolicy
{
    public function __construct(private readonly CurrentActor $actors) {}

    public function viewAny(User $actor): bool
    {
        return $this->actors->for($actor)->isAdmin();
    }

    public function create(User $actor): bool
    {
        return $this->actors->for($actor)->isAdmin();
    }

    public function update(User $actor, Department $department): bool
    {
        return $this->actors->for($actor)->isAdmin();
    }
}
