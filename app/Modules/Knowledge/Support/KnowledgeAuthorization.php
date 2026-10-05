<?php

namespace App\Modules\Knowledge\Support;

use App\Models\KnowledgeArticle;
use App\Models\User;
use App\Modules\Identity\ActorContext;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * CMS management rules, used by KnowledgeArticlePolicy and re-checked inside
 * action transactions with a fresh ActorContext. Management is separate
 * from reading published knowledge (which follows audience, with no admin
 * bypass).
 */
final class KnowledgeAuthorization
{
    public static function canManageCms(ActorContext $actor): bool
    {
        return $actor->isAdmin() || ($actor->isEditor() && $actor->editorDepartmentIds !== []);
    }

    public static function canAuthorFor(ActorContext $actor, int $departmentId): bool
    {
        return $actor->isAdmin() || $actor->canAuthorFor($departmentId);
    }

    public static function canEdit(ActorContext $actor, KnowledgeArticle $article): bool
    {
        return self::canAuthorFor($actor, (int) $article->owner_department_id);
    }

    public static function canPublish(ActorContext $actor): bool
    {
        return $actor->isAdmin();
    }

    /**
     * @throws AuthorizationException
     */
    public static function assertCanEdit(User $user, KnowledgeArticle $article): void
    {
        if (! self::canEdit(ActorContext::load($user), $article)) {
            throw new AuthorizationException;
        }
    }

    /**
     * @throws AuthorizationException
     */
    public static function assertCanPublish(User $user): void
    {
        if (! self::canPublish(ActorContext::load($user))) {
            throw new AuthorizationException;
        }
    }
}
