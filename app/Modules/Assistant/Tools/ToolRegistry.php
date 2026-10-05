<?php

namespace App\Modules\Assistant\Tools;

/**
 * Explicit registry of AI tools. Empty until Plan 7; while empty, the
 * gateway sends no tool definitions and any tool call from the model is
 * treated as an invalid response.
 */
final class ToolRegistry
{
    /**
     * @return list<array<string, mixed>> Function definitions for the provider.
     */
    public function definitions(): array
    {
        return [];
    }

    public function isEmpty(): bool
    {
        return $this->definitions() === [];
    }
}
