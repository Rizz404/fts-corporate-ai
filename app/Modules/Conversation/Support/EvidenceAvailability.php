<?php

namespace App\Modules\Conversation\Support;

use App\Models\KnowledgeRevision;
use App\Models\TurnEvidence;
use App\Modules\Identity\ActorContext;
use App\Modules\Knowledge\Queries\VisibleRevisions;

/**
 * Classifies past turns by whether their sources are still readable:
 * "ok" (every revision still visible), "stale" (a revision was replaced but
 * the article is still readable), or "unavailable" (the user can no longer
 * read the article). Stale and unavailable turns are excluded from prompts.
 */
final class EvidenceAvailability
{
    /**
     * @param  list<int>  $turnIds
     * @return array<int, 'ok'|'stale'|'unavailable'> turn id => availability (only turns with evidence)
     */
    public static function forTurns(ActorContext $actor, array $turnIds): array
    {
        if ($turnIds === []) {
            return [];
        }

        $evidence = TurnEvidence::query()->whereIn('turn_id', $turnIds)->get(['turn_id', 'revision_id']);
        if ($evidence->isEmpty()) {
            return [];
        }

        $revisionIds = $evidence->pluck('revision_id')->unique()->values()->all();
        $visible = VisibleRevisions::for($actor)->whereIn('knowledge_revisions.id', $revisionIds)->pluck('knowledge_revisions.id')->map(fn ($id) => (int) $id)->all();
        $articleOf = KnowledgeRevision::query()->whereIn('id', $revisionIds)->pluck('article_id', 'id');
        $readableArticles = VisibleRevisions::for($actor)
            ->whereIn('knowledge_revisions.article_id', $articleOf->values()->unique()->all())
            ->pluck('knowledge_revisions.article_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $result = [];
        foreach ($evidence->groupBy('turn_id') as $turnId => $rows) {
            $state = 'ok';
            foreach ($rows as $row) {
                if (in_array((int) $row->revision_id, $visible, true)) {
                    continue;
                }
                if (! in_array((int) ($articleOf[$row->revision_id] ?? 0), $readableArticles, true)) {
                    $state = 'unavailable';
                    break;
                }
                $state = 'stale';
            }
            $result[(int) $turnId] = $state;
        }

        return $result;
    }
}
