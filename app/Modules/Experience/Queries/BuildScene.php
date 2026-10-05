<?php

namespace App\Modules\Experience\Queries;

use App\Modules\Experience\Data\SceneData;
use App\Modules\Experience\Enums\SceneCode;
use App\Modules\Experience\Support\CompanySettings;
use App\Modules\Experience\Support\SceneImages;

/**
 * Read-only: composes a scene from translations, company settings (loaded
 * once per request), and the image resolver. No cross-user caching, because
 * later plans add personal data to scenes.
 */
final class BuildScene
{
    public function __construct(
        private readonly CompanySettings $settings,
        private readonly SceneImages $images,
    ) {}

    /**
     * @param  array{article_id?: ?int, ticket_public_id?: ?string}  $context  Already authorized by the caller.
     */
    public function handle(SceneCode $scene, array $context = []): SceneData
    {
        $locale = app()->getLocale();
        $key = 'ui.office.scenes.'.$scene->value;

        $intro = $scene === SceneCode::Reception
            ? ($this->settings->get()->welcomeFor($locale) ?? __($key.'.intro'))
            : __($key.'.intro');

        $topics = __($key.'.topics');

        return new SceneData(
            code: $scene,
            locale: $locale,
            title: $scene->label(),
            intro: $intro,
            backdropUrl: $this->images->backdrop($scene),
            characterUrl: $this->images->character(),
            characterName: __('ui.office.assistant_name'),
            menu: $this->menu($scene),
            suggestedTopics: is_array($topics) ? array_values($topics) : [],
            context: [
                'scene' => $scene->value,
                'article_id' => $context['article_id'] ?? null,
                'ticket_public_id' => $context['ticket_public_id'] ?? null,
            ],
        );
    }

    /**
     * @return list<array{code: string, label: string, href: ?string, icon: string, active: bool}>
     */
    private function menu(SceneCode $current): array
    {
        return array_map(fn (SceneCode $scene) => [
            'code' => $scene->value,
            'label' => $scene->label(),
            'href' => route($scene->routeName()),
            'icon' => $scene->icon(),
            'active' => $scene === $current,
        ], SceneCode::cases());
    }
}
