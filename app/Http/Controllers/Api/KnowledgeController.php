<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\KnowledgeSearchRequest;
use App\Modules\Knowledge\Data\KnowledgeEvidence;
use App\Modules\Knowledge\KnowledgeRetriever;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KnowledgeController extends Controller
{
    public function __construct(private readonly KnowledgeRetriever $retriever) {}

    public function index(KnowledgeSearchRequest $request): JsonResponse
    {
        $results = $this->retriever->search(
            $request->user(),
            $request->validated('q'),
            $request->searchLocale(),
            $request->validated('category'),
        );

        return ApiResponse::success(
            array_map(fn (KnowledgeEvidence $evidence) => $evidence->toArray(KnowledgeEvidence::UI_EXCERPT), $results),
            ['count' => count($results)],
        );
    }

    public function show(Request $request, int $article): JsonResponse
    {
        $locale = $request->validate(['locale' => ['nullable', Rule::in(config('corporate.locales'))]])['locale'] ?? app()->getLocale();

        $revision = $this->retriever->find($request->user(), $article);
        abort_if($revision === null, 404);

        $translation = $revision->translation($locale);

        return ApiResponse::success(KnowledgeRetriever::toEvidence($revision, $locale)->toArray() + [
            'body' => $translation?->body ?? '',
            'tags' => $translation?->tags ?? [],
        ]);
    }
}
