<?php

namespace App\Modules\Experience\Support;

use App\Models\CompanySetting;
use Illuminate\Database\QueryException;

/**
 * Loads the company settings row at most once per request (scoped service).
 * Falls back to defaults before migrations/seeding so the app still boots.
 */
final class CompanySettings
{
    private ?CompanySetting $settings = null;

    public function get(): CompanySetting
    {
        if ($this->settings !== null) {
            return $this->settings;
        }

        try {
            $settings = CompanySetting::query()->find(CompanySetting::SINGLETON_ID);
        } catch (QueryException) {
            $settings = null;
        }

        return $this->settings = $settings ?? new CompanySetting(CompanySetting::defaults());
    }

    public function forget(): void
    {
        $this->settings = null;
    }
}
