<?php

namespace App\Modules\Experience\Data;

use App\Modules\Experience\Enums\SceneCode;

/**
 * Everything an office page needs to render a scene. Built server-side by
 * BuildScene; views and JS never compose image URLs, menus, or context.
 */
final class SceneData
{
    /**
     * @param  list<array{code: string, label: string, href: ?string, icon: string, active: bool}>  $menu
     * @param  list<string>  $suggestedTopics
     * @param  array{scene: string, article_id: ?int, ticket_public_id: ?string}  $context
     */
    public function __construct(
        public readonly SceneCode $code,
        public readonly string $locale,
        public readonly string $title,
        public readonly string $intro,
        public readonly string $backdropUrl,
        public readonly string $characterUrl,
        public readonly string $characterName,
        public readonly array $menu,
        public readonly array $suggestedTopics,
        public readonly array $context,
    ) {}

    /**
     * BCP 47 tag for speech synthesis.
     */
    public function speechLang(): string
    {
        return match ($this->locale) {
            'en' => 'en-US',
            'ja' => 'ja-JP',
            default => 'id-ID',
        };
    }
}
