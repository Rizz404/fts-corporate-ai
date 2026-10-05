<?php

namespace App\Modules\Support\Enums;

use App\Modules\Identity\Enums\SupportTeamCode;

/**
 * Fixed categories per team (TECHNICAL_CONTRACTS); not editable in v1.
 */
final class TicketCategory
{
    public const BY_TEAM = [
        'hr' => ['policy_question', 'employee_support', 'other'],
        'it' => ['account_access', 'hardware', 'software', 'network', 'other'],
    ];

    /**
     * @return list<string>
     */
    public static function for(SupportTeamCode|string $team): array
    {
        $code = $team instanceof SupportTeamCode ? $team->value : $team;

        return self::BY_TEAM[$code] ?? [];
    }

    public static function label(string $team, string $category): string
    {
        return __("ui.tickets.categories.{$team}.{$category}");
    }

    /**
     * @return array<string, array<string, string>> team => [category => label]
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::BY_TEAM as $team => $categories) {
            foreach ($categories as $category) {
                $options[$team][$category] = self::label($team, $category);
            }
        }

        return $options;
    }
}
