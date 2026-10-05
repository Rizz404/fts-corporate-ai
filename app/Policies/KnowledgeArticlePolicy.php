<?php

namespace App\Policies;

use App\Models\KnowledgeArticle;
use App\Models\User;
use App\Modules\Identity\CurrentActor;
use App\Modules\Knowledge\Support\KnowledgeAuthorization;

/**
 * CMS management. Admin manages all articles; editors manage articles of
 * their authoring departments; only admin publishes/archives. Reading
 * published knowledge is governed by VisibleRevisions, not this policy.
 */
class KnowledgeArticlePolicy
{
    public function __construct(private readonly CurrentActor $actors) {}

    public function viewAny(User $actor): bool
    {
        return KnowledgeAuthorization::canManageCms($this->actors->for($actor));
    }

    public function create(User $actor): bool
    {
        return KnowledgeAuthorization::canManageCms($this->actors->for($actor));
    }

    public function update(User $actor, KnowledgeArticle $article): bool
    {
        return KnowledgeAuthorization::canEdit($this->actors->for($actor), $article);
    }

    public function publish(User $actor, KnowledgeArticle $article): bool
    {
        return KnowledgeAuthorization::canPublish($this->actors->for($actor));
    }
}
