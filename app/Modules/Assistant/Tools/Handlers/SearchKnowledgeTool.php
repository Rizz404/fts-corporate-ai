<?php

namespace App\Modules\Assistant\Tools\Handlers;

use App\Models\User;
use App\Modules\Assistant\Tools\Tool;
use App\Modules\Assistant\Tools\ToolArguments;
use App\Modules\Assistant\Tools\ToolContext;
use App\Modules\Assistant\Tools\ToolResult;
use App\Modules\Knowledge\Data\KnowledgeEvidence;
use App\Modules\Knowledge\Enums\KnowledgeCategory;
use App\Modules\Knowledge\KnowledgeRetriever;
use Illuminate\Validation\Rule;

/**
 * Plan 4 retrieval for the session user (max 5 evidence, turn locale).
 */
final class SearchKnowledgeTool implements Tool
{
    public function __construct(private readonly KnowledgeRetriever $retriever) {}

    public function name(): string
    {
        return 'search_knowledge';
    }

    public function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => 'Search official company knowledge articles (SOPs, policies) the employee may read. Returns up to 5 sources with revision_id, title, and excerpt.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'minLength' => 1, 'maxLength' => KnowledgeRetriever::MAX_QUERY_LENGTH, 'description' => 'Search words in any supported language.'],
                        'category' => ['type' => 'string', 'enum' => KnowledgeCategory::values()],
                    ],
                    'required' => ['query'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    public function validate(User $actor, array $arguments): array
    {
        return ToolArguments::validate($arguments, [
            'query' => ['required', 'string', 'min:1', 'max:'.KnowledgeRetriever::MAX_QUERY_LENGTH],
            'category' => ['sometimes', 'nullable', 'string', Rule::in(KnowledgeCategory::values())],
        ]);
    }

    public function execute(User $actor, array $arguments, ToolContext $context): ToolResult
    {
        $evidence = $this->retriever->search($actor, $arguments['query'], $context->locale, $arguments['category'] ?? null);

        return new ToolResult(
            ToolResult::OK,
            data: ['sources' => array_map(fn (KnowledgeEvidence $item) => self::forModel($item), $evidence)],
            evidence: $evidence,
            reference: ['revision_ids' => array_map(fn (KnowledgeEvidence $item) => $item->revisionId, $evidence)],
        );
    }

    public function loggableInput(array $arguments): array
    {
        return [
            'query' => mb_substr($arguments['query'], 0, 200),
            'category' => $arguments['category'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forModel(KnowledgeEvidence $item): array
    {
        return [
            'revision_id' => $item->revisionId,
            'article_id' => $item->articleId,
            'title' => $item->title,
            'version' => $item->version,
            'effective_from' => $item->effectiveFrom?->toDateString(),
            'effective_until' => $item->effectiveUntil?->toDateString(),
            'excerpt' => KnowledgeEvidence::excerpt($item->excerpt, (int) config('assistant.evidence.max_characters_per_item')),
        ];
    }
}
