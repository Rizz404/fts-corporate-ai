<?php

namespace App\Modules\Assistant\Tools\Handlers;

use App\Models\User;
use App\Modules\Assistant\Tools\Tool;
use App\Modules\Assistant\Tools\ToolArguments;
use App\Modules\Assistant\Tools\ToolContext;
use App\Modules\Assistant\Tools\ToolResult;
use App\Modules\Knowledge\KnowledgeRetriever;

/**
 * Current published revision of one article, only when it is in effect and
 * its audience includes the session user. Otherwise not_found.
 */
final class GetArticleTool implements Tool
{
    public function __construct(private readonly KnowledgeRetriever $retriever) {}

    public function name(): string
    {
        return 'get_article';
    }

    public function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => 'Read one official article by its article_id (from search results). Returns the source with revision_id and text.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'article_id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => ['article_id'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    public function validate(User $actor, array $arguments): array
    {
        return ToolArguments::validate($arguments, [
            'article_id' => ['required', 'integer:strict', 'min:1'],
        ]);
    }

    public function execute(User $actor, array $arguments, ToolContext $context): ToolResult
    {
        $revision = $this->retriever->find($actor, (int) $arguments['article_id']);
        if ($revision === null) {
            return ToolResult::error(ToolResult::NOT_FOUND);
        }

        $evidence = KnowledgeRetriever::toEvidence($revision, $context->locale);

        return new ToolResult(
            ToolResult::OK,
            data: ['source' => SearchKnowledgeTool::forModel($evidence)],
            evidence: [$evidence],
            reference: ['revision_ids' => [$evidence->revisionId]],
        );
    }

    public function loggableInput(array $arguments): array
    {
        return ['article_id' => (int) $arguments['article_id']];
    }
}
