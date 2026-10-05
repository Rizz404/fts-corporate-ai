<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Assistant
    |--------------------------------------------------------------------------
    |
    | Budgets for one AI turn. Provider connection values live in
    | config/services.php under "local_llm".
    |
    */

    'enabled' => (bool) env('ASSISTANT_ENABLED', false),

    // corporate-v2 adds the tool rules; corporate-v1 is used when tools are off.
    'prompt_version' => env('ASSISTANT_TOOLS_ENABLED', true) ? 'corporate-v2' : 'corporate-v1',

    // Tool calling (Plan 7). Turn off for providers without function calling;
    // chat then answers from retrieval only and tickets use the manual form.
    'tools' => [
        'enabled' => (bool) env('ASSISTANT_TOOLS_ENABLED', true),
    ],

    'deadline_seconds' => 60,
    'connect_timeout_seconds' => 5,
    // Conversation/user lease; longer than the deadline so a slow turn can finish.
    'lease_seconds' => 75,
    // Browser gives up waiting after this many seconds and polls the turn.
    'client_timeout_seconds' => 70,

    'max_completion_calls' => 7,
    'max_tool_rounds' => 6,
    'max_tool_calls_per_response' => 5,
    'max_total_invocations' => 12,

    'history' => [
        'max_messages' => 20,
        'max_characters' => 12000,
    ],

    'evidence' => [
        'max_items' => 5,
        'max_characters_per_item' => 2000,
    ],

    'max_tokens' => 2048,
    'temperature' => 0.3,

    // Provider capability: only sent when configured. Thinking models such as
    // Qwen3 on LM Studio need "none" so the answer is not spent on reasoning.
    'reasoning_effort' => env('ASSISTANT_REASONING_EFFORT') ?: null,

    // Longest assistant text accepted from the model.
    'max_answer_characters' => 4000,

];
