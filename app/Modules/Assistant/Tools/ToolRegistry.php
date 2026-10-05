<?php

namespace App\Modules\Assistant\Tools;

use App\Modules\Assistant\Tools\Handlers\GetArticleTool;
use App\Modules\Assistant\Tools\Handlers\GetTicketTool;
use App\Modules\Assistant\Tools\Handlers\ListMyTicketsTool;
use App\Modules\Assistant\Tools\Handlers\PrepareTicketTool;
use App\Modules\Assistant\Tools\Handlers\SearchKnowledgeTool;
use Illuminate\Contracts\Container\Container;

/**
 * Explicit whitelist of AI tools. A model-supplied name is only looked up in
 * this map; it is never turned into a class or method name. There is no
 * create/close/approve/internal-note or HTTP tool.
 *
 * Empty when assistant.tools.enabled is false (provider without tool
 * calling): the gateway then sends no tools and the turn works like Plan 5.
 */
class ToolRegistry
{
    /** @var list<class-string<Tool>> */
    public const HANDLERS = [
        SearchKnowledgeTool::class,
        GetArticleTool::class,
        ListMyTicketsTool::class,
        GetTicketTool::class,
        PrepareTicketTool::class,
    ];

    /** @var array<string, Tool>|null */
    private ?array $tools = null;

    public function __construct(private readonly Container $container) {}

    /**
     * @return array<string, Tool>
     */
    public function all(): array
    {
        if ($this->tools !== null) {
            return $this->tools;
        }

        $this->tools = [];
        if (! config('assistant.tools.enabled')) {
            return $this->tools;
        }

        foreach (self::HANDLERS as $class) {
            /** @var Tool $tool */
            $tool = $this->container->make($class);
            $this->tools[$tool->name()] = $tool;
        }

        return $this->tools;
    }

    public function get(string $name): ?Tool
    {
        return $this->all()[$name] ?? null;
    }

    /**
     * @return list<array<string, mixed>> Function definitions for the provider.
     */
    public function definitions(): array
    {
        return array_values(array_map(fn (Tool $tool) => $tool->definition(), $this->all()));
    }

    public function isEmpty(): bool
    {
        return $this->all() === [];
    }
}
