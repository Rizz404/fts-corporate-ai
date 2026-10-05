<?php

namespace App\Modules\Conversation\Support;

use App\Modules\Experience\Enums\SceneCode;
use App\Modules\Knowledge\Support\TextNormalizer;

/**
 * Deterministic answers for greetings, thanks, and simple navigation, so
 * the model is not called for messages that need no company facts.
 */
final class QuickReplies
{
    private const GREETINGS = [
        'halo', 'hai', 'hi', 'hello', 'hey', 'pagi', 'selamat pagi', 'selamat siang', 'selamat sore', 'selamat malam',
        'good morning', 'good afternoon', 'good evening', 'こんにちは', 'おはよう', 'おはようございます', 'こんばんは',
    ];

    private const THANKS = [
        'terima kasih', 'makasih', 'trims', 'thanks', 'thank you', 'thx', 'ありがとう', 'ありがとうございます',
    ];

    /** Short phrases that mean "take me to this room". */
    private const NAVIGATION = [
        'reception' => ['resepsionis', 'reception', '受付'],
        'knowledge' => ['pusat pengetahuan', 'knowledge center', 'knowledge', 'ナレッジセンター'],
        'hr' => ['meja hr', 'hr desk', 'ke hr', '人事デスク'],
        'it' => ['it helpdesk', 'helpdesk', 'ke it', 'itヘルプデスク'],
        'requests' => ['permintaan saya', 'my requests', 'マイリクエスト'],
    ];

    /**
     * @return array{kind: string, text: string, actions: list<array<string, mixed>>}|null
     */
    public static function match(string $message): ?array
    {
        $text = trim((string) preg_replace('/[\p{P}\p{S}]+/u', ' ', TextNormalizer::normalize($message)));
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        if ($text === '' || mb_strlen($text) > 40) {
            return null;
        }

        if (in_array($text, self::GREETINGS, true)) {
            return ['kind' => 'greeting', 'text' => __('ui.assistant.greeting'), 'actions' => []];
        }

        if (in_array($text, self::THANKS, true)) {
            return ['kind' => 'thanks', 'text' => __('ui.assistant.thanks'), 'actions' => []];
        }

        foreach (self::NAVIGATION as $scene => $phrases) {
            if (in_array($text, $phrases, true)) {
                $code = SceneCode::from($scene);

                return [
                    'kind' => 'navigation',
                    'text' => __('ui.assistant.navigation', ['room' => $code->label()]),
                    'actions' => [TurnCards::openScene($code)],
                ];
            }
        }

        return null;
    }
}
