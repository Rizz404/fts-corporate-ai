<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Company-wide branding (singleton row id = 1). Text only: no HTML, remote
 * URLs, or scripts. Palette stays in theme.css; assets and timezone in config.
 */
#[Fillable(['brand_name', 'welcome_text', 'updated_by'])]
class CompanySetting extends Model
{
    public const SINGLETON_ID = 1;

    protected function casts(): array
    {
        return [
            'welcome_text' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function welcomeFor(string $locale): ?string
    {
        $text = $this->welcome_text[$locale] ?? null;

        return is_string($text) && trim($text) !== '' ? $text : null;
    }

    /**
     * @return array{brand_name: string, welcome_text: array<string, string>}
     */
    public static function defaults(): array
    {
        return [
            'brand_name' => 'FTS Corporate AI',
            'welcome_text' => [
                'id' => 'Selamat datang di kantor virtual. Pilih ruang untuk mencari informasi resmi atau meminta bantuan HR dan IT.',
                'en' => 'Welcome to the virtual office. Choose a room to find official information or ask HR and IT for help.',
                'ja' => 'バーチャルオフィスへようこそ。部屋を選んで、公式情報を探したり人事・IT にサポートを依頼したりできます。',
            ],
        ];
    }
}
