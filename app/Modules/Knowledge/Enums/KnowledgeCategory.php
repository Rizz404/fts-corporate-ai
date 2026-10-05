<?php

namespace App\Modules\Knowledge\Enums;

enum KnowledgeCategory: string
{
    case General = 'general';
    case Hr = 'hr';
    case It = 'it';
    case Onboarding = 'onboarding';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return __('ui.knowledge.categories.'.$this->value);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $category) => [$category->value => $category->label()])->all();
    }
}
