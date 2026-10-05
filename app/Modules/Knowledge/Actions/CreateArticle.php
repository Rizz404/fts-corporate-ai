<?php

namespace App\Modules\Knowledge\Actions;

use App\Models\KnowledgeArticle;
use App\Models\KnowledgeRevision;
use App\Models\User;
use App\Modules\Identity\Actions\WriteAuditEvent;
use App\Modules\Identity\ActorContext;
use App\Modules\Knowledge\Enums\RevisionStatus;
use App\Modules\Knowledge\Support\DraftWriter;
use App\Modules\Knowledge\Support\KnowledgeAuthorization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Creates an article with its first draft (version 1).
 */
class CreateArticle
{
    public function __construct(private readonly WriteAuditEvent $audit) {}

    /**
     * @param  array<string, mixed>  $payload  From ArticleRules::payload().
     */
    public function handle(User $actor, array $payload): KnowledgeArticle
    {
        return DB::transaction(function () use ($actor, $payload) {
            if (! KnowledgeAuthorization::canAuthorFor(ActorContext::load($actor), $payload['owner_department_id'])) {
                throw new AuthorizationException;
            }

            $article = KnowledgeArticle::create([
                'slug' => $payload['slug'],
                'owner_department_id' => $payload['owner_department_id'],
                'created_by' => $actor->id,
            ]);

            $draft = KnowledgeRevision::create([
                'article_id' => $article->id,
                'version' => 1,
                'status' => RevisionStatus::Draft,
                'audience' => $payload['audience'],
                'category' => $payload['category'],
                'created_by' => $actor->id,
            ]);

            DraftWriter::write($draft, $payload);

            $this->audit->handle($actor, 'knowledge.article_created', $article, [
                'slug' => $article->slug,
                'owner_department_id' => $article->owner_department_id,
            ]);

            return $article;
        });
    }
}
