<?php

namespace App\Modules\Conversation\Support;

use App\Modules\Experience\Enums\SceneCode;
use App\Modules\Knowledge\Data\KnowledgeEvidence;

/**
 * Server-built UI descriptors. Only whitelisted types; paths are named
 * routes resolved by the server, never URLs written by the model.
 */
final class TurnCards
{
    /**
     * @param  list<KnowledgeEvidence>  $evidence
     * @return array{sources: list<array<string, mixed>>, ui: list<array<string, mixed>>, actions: list<array<string, mixed>>}
     */
    public static function forEvidence(array $evidence, array $actions = []): array
    {
        $sources = [];
        $cards = [];

        foreach ($evidence as $item) {
            $source = [
                'article_id' => $item->articleId,
                'revision_id' => $item->revisionId,
                'version' => $item->version,
                'title' => $item->title,
                'effective_from' => $item->effectiveFrom?->toIso8601String(),
                'effective_until' => $item->effectiveUntil?->toIso8601String(),
                'path' => $item->sourcePath,
            ];
            $sources[] = $source;
            $cards[] = ['type' => 'source_card'] + $source;
        }

        return ['sources' => $sources, 'ui' => $cards, 'actions' => $actions];
    }

    /**
     * @return array<string, mixed>
     */
    public static function openScene(SceneCode $scene): array
    {
        return [
            'type' => 'open_scene',
            'scene' => $scene->value,
            'label' => $scene->label(),
            'path' => route($scene->routeName(), absolute: false),
        ];
    }

    /**
     * Suggested next steps when no official source answered the question.
     *
     * @return list<array<string, mixed>>
     */
    public static function helpActions(): array
    {
        return [
            self::openScene(SceneCode::Knowledge),
            self::openScene(SceneCode::Hr),
            self::openScene(SceneCode::It),
        ];
    }
}
