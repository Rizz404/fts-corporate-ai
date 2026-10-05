<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketDraft;
use App\Modules\Experience\Enums\SceneCode;
use App\Modules\Identity\Enums\SupportTeamCode;
use App\Modules\Support\Enums\TicketCategory;
use App\Modules\Support\Presenters\TicketPresenter;
use App\Modules\Experience\Queries\BuildScene;
use App\Modules\Identity\CurrentActor;
use App\Modules\Knowledge\Enums\KnowledgeCategory;
use App\Modules\Knowledge\KnowledgeRetriever;
use App\Modules\Knowledge\Queries\VisibleRevisions;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Office scenes. Each scene is rendered from SceneData built server-side.
 */
class OfficeController extends Controller
{
    public function __construct(private readonly BuildScene $buildScene) {}

    public function reception(): View
    {
        return $this->scene(SceneCode::Reception, 'office.reception');
    }

    /**
     * Manual knowledge search (no AI). Invalid input is ignored on pages.
     */
    public function knowledge(Request $request, KnowledgeRetriever $retriever, CurrentActor $actors): View
    {
        $query = trim(mb_substr((string) $request->query('q', ''), 0, KnowledgeRetriever::MAX_QUERY_LENGTH));
        $category = in_array($request->query('category'), KnowledgeCategory::values(), true) ? $request->query('category') : null;
        $locale = app()->getLocale();

        $results = null;
        $latest = null;

        if ($query !== '') {
            $results = $retriever->search($request->user(), $query, $locale, $category);
        } else {
            $latest = VisibleRevisions::for($actors->for($request->user()))
                ->when($category, fn ($builder) => $builder->where('knowledge_revisions.category', $category))
                ->with(['article', 'translations'])
                ->orderByDesc('knowledge_revisions.published_at')
                ->orderBy('knowledge_revisions.article_id')
                ->paginate(10)
                ->withQueryString();
        }

        return $this->scene(SceneCode::Knowledge, 'office.knowledge', [
            'query' => $query,
            'category' => $category,
            'results' => $results,
            'latest' => $latest,
            'categoryOptions' => KnowledgeCategory::options(),
        ]);
    }

    public function knowledgeShow(Request $request, string $articleSlug, KnowledgeRetriever $retriever): View
    {
        $revision = $retriever->find($request->user(), $articleSlug);
        abort_if($revision === null, 404);

        return $this->scene(SceneCode::Knowledge, 'office.knowledge-show', [
            'revision' => $revision,
            'translation' => $revision->translation(app()->getLocale()),
        ], ['article_id' => $revision->article_id]);
    }

    public function hr(): View
    {
        return $this->scene(SceneCode::Hr, 'office.hr');
    }

    public function it(): View
    {
        return $this->scene(SceneCode::It, 'office.it');
    }

    public function requests(Request $request): View
    {
        $tickets = Ticket::query()
            ->with('team:id,code')
            ->where('requester_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(20);

        return $this->scene(SceneCode::Requests, 'office.requests', ['tickets' => $tickets]);
    }

    /**
     * Three-step wizard; drafts are saved server-side through the API.
     * ?draft=<uuid> opens an own draft (e.g. prepared by the assistant) at
     * the review step; a confirmed draft redirects to its ticket.
     */
    public function requestsCreate(Request $request): View|RedirectResponse
    {
        $team = in_array($request->query('team'), SupportTeamCode::values(), true) ? $request->query('team') : null;
        $initialDraft = null;

        if ($request->filled('draft')) {
            $draftId = (string) $request->query('draft');
            abort_unless(Str::isUuid($draftId), 404);

            $draft = TicketDraft::query()
                ->with(['team:id,code', 'consumedTicket'])
                ->where('public_id', strtolower($draftId))
                ->where('requester_id', $request->user()->id)
                ->firstOrFail();

            if ($draft->consumedTicket !== null) {
                return redirect()->route('office.requests.show', $draft->consumedTicket);
            }

            $initialDraft = TicketPresenter::draft($draft);
        }

        return $this->scene(SceneCode::Requests, 'office.requests-create', [
            'initialTeam' => $team,
            'initialDraft' => $initialDraft,
            'categoryOptions' => TicketCategory::options(),
        ]);
    }

    public function requestsShow(Request $request, Ticket $ticket): View
    {
        abort_unless($ticket->requester_id === $request->user()->id, 404);
        $ticket->load(['team:id,code', 'assignee:id,name']);

        // The assistant may use this own ticket as context (Plan 7).
        return $this->scene(SceneCode::Requests, 'office.requests-show', ['ticket' => $ticket], ['ticket_public_id' => $ticket->public_id]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{article_id?: ?int, ticket_public_id?: ?string}  $context
     */
    protected function scene(SceneCode $scene, string $view, array $data = [], array $context = []): View
    {
        return view($view, [
            'scene' => $this->buildScene->handle($scene, $context),
            'adminHref' => Gate::allows('admin.access') ? route('admin.dashboard') : null,
        ] + $data);
    }
}
