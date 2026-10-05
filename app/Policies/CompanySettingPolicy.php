<?php

namespace App\Policies;

use App\Models\CompanySetting;
use App\Models\User;
use App\Modules\Identity\CurrentActor;

/**
 * Company branding is admin-only. No global before() bypass.
 */
class CompanySettingPolicy
{
    public function __construct(private readonly CurrentActor $actors) {}

    public function update(User $actor, CompanySetting $settings): bool
    {
        return $this->actors->for($actor)->isAdmin();
    }
}
