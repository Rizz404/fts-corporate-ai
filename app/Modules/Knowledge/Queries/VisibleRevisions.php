<?php

namespace App\Modules\Knowledge\Queries;

use App\Models\KnowledgeRevision;
use App\Modules\Identity\ActorContext;
use App\Modules\Knowledge\Enums\Audience;
use App\Modules\Knowledge\Enums\RevisionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * The single read scope for published knowledge: published status, inside
 * the effective window, and audience common or the actor's department.
 * Used by pages, API, and retrieval alike; no admin bypass.
 */
final class VisibleRevisions
{
    /**
     * @return Builder<KnowledgeRevision>
     */
    public static function for(ActorContext $actor, ?CarbonImmutable $now = null): Builder
    {
        $now ??= CarbonImmutable::now();

        return KnowledgeRevision::query()
            ->where('knowledge_revisions.status', RevisionStatus::Published->value)
            ->where('knowledge_revisions.effective_from', '<=', $now)
            ->where(fn (Builder $query) => $query
                ->whereNull('knowledge_revisions.effective_until')
                ->orWhere('knowledge_revisions.effective_until', '>', $now))
            ->where(fn (Builder $query) => $query
                ->where('knowledge_revisions.audience', Audience::Common->value)
                ->orWhereExists(fn (QueryBuilder $exists) => $exists
                    ->selectRaw('1')
                    ->from('knowledge_revision_department')
                    ->whereColumn('knowledge_revision_department.revision_id', 'knowledge_revisions.id')
                    ->where('knowledge_revision_department.department_id', $actor->departmentId)));
    }
}
