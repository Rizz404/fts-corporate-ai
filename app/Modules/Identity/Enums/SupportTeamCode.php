<?php

namespace App\Modules\Identity\Enums;

enum SupportTeamCode: string
{
    case Hr = 'hr';
    case It = 'it';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return __('ui.teams.'.$this->value);
    }
}
