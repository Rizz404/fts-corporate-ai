<?php

namespace App\Modules\Experience\Enums;

enum SceneCode: string
{
    case Reception = 'reception';
    case Knowledge = 'knowledge';
    case Hr = 'hr';
    case It = 'it';
    case Requests = 'requests';

    public function routeName(): string
    {
        return 'office.'.$this->value;
    }

    public function icon(): string
    {
        return match ($this) {
            self::Reception => 'building-office-2',
            self::Knowledge => 'book-open',
            self::Hr => 'users',
            self::It => 'computer-desktop',
            self::Requests => 'clipboard-document-list',
        };
    }

    public function label(): string
    {
        return __('ui.nav.rooms.'.$this->value);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
