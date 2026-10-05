<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\KnowledgeArticleRequest;
use App\Models\Department;
use App\Models\KnowledgeArticle;
use App\Modules\Identity\CurrentActor;
use App\Modules\Knowledge\Actions\ArchiveRevision;
use App\Modules\Knowledge\Actions\CloneRevision;
use App\Modules\Knowledge\Actions\CreateArticle;
use App\Modules\Knowledge\Actions\PublishRevision;
use App\Modules\Knowledge\Actions\SaveDraft;
use App\Modules\Knowledge\Enums\Audience;
use App\Modules\Knowledge\Enums\KnowledgeCategory;
use App\Modules\Knowledge\Validation\PublicationRules;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Knowledge CMS: plain-text articles in id/en/ja with draft → published →
 * archived revisions.
 */
class KnowledgeController extends Controller
{
    public function __construct(private readonly CurrentActor $actors) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', KnowledgeArticle::class);
        $actor = $this->actors->for($request->user());

        $articles = KnowledgeArticle::query()
            ->with(['ownerDepartment:id,name', 'latestRevision.translations', 'draft:id,article_id,version', 'published:id,article_id,version'])
            ->when(! $actor->isAdmin(), fn ($query) => $query->whereIn('owner_department_id', $actor->editorDepartmentIds))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(20);

        return view('admin.knowledge.index', ['articles' => $articles]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', KnowledgeArticle::class);

        return view('admin.knowledge.create', $this->formOptions($request));
    }

    public function store(KnowledgeArticleRequest $request, CreateArticle $create): RedirectResponse
    {
        $article = $create->handle($request->user(), $request->payload());

        return redirect()->route('admin.knowledge.edit', $article)->with('status', __('ui.knowledge.created'));
    }

    public function edit(Request $request, KnowledgeArticle $article): View
    {
        Gate::authorize('update', $article);

        $article->load(['ownerDepartment', 'draft.translations', 'draft.departments', 'published.translations', 'published.departments', 'published.publisher:id,name']);

        return view('admin.knowledge.edit', [
            'article' => $article,
            'draft' => $article->draft,
            'published' => $article->published,
            'slugLocked' => $article->slugIsLocked(),
            'completeness' => PublicationRules::completeness($article->draft),
            'canPublish' => Gate::allows('publish', $article),
        ] + $this->formOptions($request));
    }

    public function update(KnowledgeArticleRequest $request, KnowledgeArticle $article, SaveDraft $save): RedirectResponse
    {
        $save->handle($request->user(), $article, $request->payload());

        return redirect()->route('admin.knowledge.edit', $article)->with('status', __('ui.knowledge.draft_saved'));
    }

    public function revisions(KnowledgeArticle $article): View
    {
        Gate::authorize('update', $article);

        return view('admin.knowledge.revisions', [
            'article' => $article,
            'revisions' => $article->revisions()->with(['translations', 'creator:id,name', 'publisher:id,name'])->orderByDesc('version')->get(),
        ]);
    }

    public function createDraft(Request $request, KnowledgeArticle $article, CloneRevision $clone): RedirectResponse
    {
        Gate::authorize('update', $article);
        $clone->handle($request->user(), $article);

        return redirect()->route('admin.knowledge.edit', $article)->with('status', __('ui.knowledge.draft_created'));
    }

    public function publish(Request $request, KnowledgeArticle $article, PublishRevision $publish): RedirectResponse
    {
        Gate::authorize('publish', $article);
        $publish->handle($request->user(), $article);

        return redirect()->route('admin.knowledge.edit', $article)->with('status', __('ui.knowledge.published'));
    }

    public function archive(Request $request, KnowledgeArticle $article, ArchiveRevision $archive): RedirectResponse
    {
        Gate::authorize('publish', $article);
        $archive->handle($request->user(), $article);

        return redirect()->route('admin.knowledge.edit', $article)->with('status', __('ui.knowledge.archived'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(Request $request): array
    {
        $actor = $this->actors->for($request->user());
        $departments = Department::query()->active()->orderBy('name')->pluck('name', 'id');

        return [
            'ownerDepartmentOptions' => $actor->isAdmin()
                ? $departments->all()
                : $departments->only($actor->editorDepartmentIds)->all(),
            'departmentOptions' => $departments->all(),
            'categoryOptions' => KnowledgeCategory::options(),
            'audienceOptions' => collect(Audience::cases())->mapWithKeys(fn (Audience $audience) => [$audience->value => $audience->label()])->all(),
        ];
    }
}
