<?php

namespace App\Modules\Identity\Presenters;

use App\Modules\Identity\ActorContext;
use App\Modules\Knowledge\Support\KnowledgeAuthorization;
use App\Modules\Support\Support\TicketAccess;

/**
 * Builds the admin sidebar from server-side abilities. Items for modules
 * that later plans deliver are shown disabled (href null) to the roles that
 * will use them.
 */
final class AdminNavigation
{
    /**
     * @return list<array{label: string, href: ?string, icon: string, active: bool}>
     */
    public static function items(ActorContext $actor, ?string $currentRoute): array
    {
        $is = fn (string $pattern) => $currentRoute !== null && fnmatch($pattern, $currentRoute);
        $items = [];

        $items[] = ['label' => __('ui.nav.overview'), 'href' => route('admin.dashboard'), 'icon' => 'squares-2x2', 'active' => $is('admin.dashboard')];

        if ($actor->isAdmin()) {
            $items[] = ['label' => __('ui.nav.employees'), 'href' => route('admin.users.index'), 'icon' => 'users', 'active' => $is('admin.users.*')];
            $items[] = ['label' => __('ui.nav.departments'), 'href' => route('admin.departments.index'), 'icon' => 'building-office-2', 'active' => $is('admin.departments.*')];
        }

        if (KnowledgeAuthorization::canManageCms($actor)) {
            $items[] = ['label' => __('ui.nav.knowledge'), 'href' => route('admin.knowledge.index'), 'icon' => 'book-open', 'active' => $is('admin.knowledge.*')];
        }

        if (TicketAccess::handledTeamIds($actor) !== []) {
            $items[] = ['label' => __('ui.nav.tickets'), 'href' => route('admin.tickets.index'), 'icon' => 'ticket', 'active' => $is('admin.tickets.*')];
        }

        if ($actor->isAdmin()) {
            $items[] = ['label' => __('ui.nav.operations'), 'href' => route('admin.operations'), 'icon' => 'chart-bar', 'active' => $is('admin.operations')];
            $items[] = ['label' => __('ui.nav.settings'), 'href' => route('admin.settings.edit'), 'icon' => 'cog-6-tooth', 'active' => $is('admin.settings.*')];
        }

        return $items;
    }
}
