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

    'deadline_seconds' => 60,
    'connect_timeout_seconds' => 5,

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

    // Not sent unless explicitly configured for a provider that supports it.
    'reasoning_effort' => null,

];
