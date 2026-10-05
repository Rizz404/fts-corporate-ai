<?php

namespace App\Modules\Experience\Support;

use App\Modules\Experience\Enums\SceneCode;

/**
 * Builds scene and character image URLs from config/corporate.php.
 *
 * Placeholder URLs use a fixed seed per image so they do not change between
 * requests, and never include user, query, or session data.
 */
final class SceneImages
{
    public const SOURCE_PLACEHOLDER = 'placeholder';

    public const SOURCE_LOCAL = 'local';

    public function backdrop(SceneCode $scene): string
    {
        return $this->url($scene->value, 1600, 900);
    }

    public function character(): string
    {
        return $this->url('assistant', 600, 800);
    }

    public function source(): string
    {
        return config('corporate.scenes.image_source') === self::SOURCE_LOCAL ? self::SOURCE_LOCAL : self::SOURCE_PLACEHOLDER;
    }

    private function url(string $name, int $width, int $height): string
    {
        if ($this->source() === self::SOURCE_LOCAL) {
            $path = trim(config('corporate.scenes.asset_path'), '/');
            $extension = ltrim((string) config('corporate.scenes.local_extension', 'svg'), '.');

            return asset("{$path}/{$name}.{$extension}");
        }

        $base = rtrim((string) config('corporate.scenes.placeholder_base_url'), '/');

        return "{$base}/seed/corporate-{$name}/{$width}/{$height}";
    }
}
