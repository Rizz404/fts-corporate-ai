<?php

namespace App\Modules\Knowledge\Enums;

enum Audience: string
{
    case Common = 'common';
    case Departments = 'departments';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return __('ui.knowledge.audience.'.$this->value);
    }
}
