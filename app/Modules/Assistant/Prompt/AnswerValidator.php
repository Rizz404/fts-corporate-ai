<?php

namespace App\Modules\Assistant\Prompt;

use App\Modules\Assistant\Gateway\ChatCompletionResult;

/**
 * Strict parser for the project answer format:
 *   {"text": "...", "source_revision_ids": [1, 2]}
 *
 * Rejected (→ fallback): tool calls while no tools are registered,
 * finish_reason other than "stop", code fences or extra text, malformed or
 * extra keys, empty text, ids not given to this turn, and answers without
 * any source. A leading <think>…</think> block from reasoning models is
 * removed before parsing.
 */
final class AnswerValidator
{
    public const TRUNCATED = 'ai_truncated';

    public const MALFORMED = 'ai_bad_response';

    public const UNEXPECTED_TOOL_CALL = 'ai_unexpected_tool_call';

    public const INVALID_SOURCE = 'ai_invalid_source';

    public const NO_SOURCE = 'ai_no_source';

    /**
     * @param  list<int>  $allowedRevisionIds
     * @return array{ok: true, text: string, source_revision_ids: list<int>}|array{ok: false, code: string}
     */
    public function validate(ChatCompletionResult $result, array $allowedRevisionIds, bool $toolsEnabled = false): array
    {
        if ($result->toolCalls !== [] && ! $toolsEnabled) {
            return $this->fail(self::UNEXPECTED_TOOL_CALL);
        }

        if ($result->finishReason !== 'stop') {
            return $this->fail($result->finishReason === 'length' ? self::TRUNCATED : self::MALFORMED);
        }

        $content = trim((string) preg_replace('/^\s*<think>.*?<\/think>/s', '', (string) $result->text));
        if ($content === '' || ! str_starts_with($content, '{') || ! str_ends_with($content, '}')) {
            return $this->fail(self::MALFORMED);
        }

        $data = json_decode($content, true);
        if (! is_array($data) || array_is_list($data) || array_diff(array_keys($data), ['text', 'source_revision_ids']) !== []) {
            return $this->fail(self::MALFORMED);
        }

        $text = $data['text'] ?? null;
        $ids = $data['source_revision_ids'] ?? null;
        if (! is_string($text) || ! is_array($ids) || ! array_is_list($ids)) {
            return $this->fail(self::MALFORMED);
        }

        $text = trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text));
        if ($text === '' || mb_strlen($text) > (int) config('assistant.max_answer_characters')) {
            return $this->fail(self::MALFORMED);
        }

        $clean = [];
        foreach ($ids as $id) {
            if (! is_int($id) || ! in_array($id, $allowedRevisionIds, true)) {
                return $this->fail(self::INVALID_SOURCE);
            }
            $clean[] = $id;
        }

        if ($clean === []) {
            return $this->fail(self::NO_SOURCE);
        }

        return ['ok' => true, 'text' => $text, 'source_revision_ids' => array_values(array_unique($clean))];
    }

    /**
     * @return array{ok: false, code: string}
     */
    private function fail(string $code): array
    {
        return ['ok' => false, 'code' => $code];
    }
}
