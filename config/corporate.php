<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Brand
    |--------------------------------------------------------------------------
    |
    | Default brand shown in layouts. Plan 3 adds admin-managed branding on
    | top of this baseline.
    |
    */

    'brand' => [
        'name' => env('APP_NAME', 'FTS Corporate AI'),
        'short_name' => 'FTS',
    ],

    /*
    |--------------------------------------------------------------------------
    | Display timezone
    |--------------------------------------------------------------------------
    |
    | Timestamps are stored in UTC (config/app.php). This timezone is used only
    | when presenting dates to people.
    |
    */

    'timezone' => env('APP_TIMEZONE', 'Asia/Jakarta'),

    /*
    |--------------------------------------------------------------------------
    | Locales
    |--------------------------------------------------------------------------
    */

    'locales' => ['id', 'en', 'ja'],

    'default_locale' => 'id',

    // Native names, shown the same in every UI language.
    'locale_names' => [
        'id' => 'Bahasa Indonesia',
        'en' => 'English',
        'ja' => '日本語',
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention (days)
    |--------------------------------------------------------------------------
    */

    'retention' => [
        'conversations_days' => (int) env('RETENTION_CONVERSATIONS_DAYS', 90),
        'tickets_after_close_days' => (int) env('RETENTION_TICKETS_DAYS', 365),
        'audit_days' => (int) env('RETENTION_AUDIT_DAYS', 365),
    ],

    /*
    |--------------------------------------------------------------------------
    | Scene images
    |--------------------------------------------------------------------------
    |
    | "placeholder": random images with a fixed seed per scene (development and
    | internal pilot only). "local": files in public/{asset_path}/{name}.{ext}
    | once the final illustrations exist (Plan 8). URLs are always built by
    | App\Modules\Experience\Support\SceneImages and never contain user data.
    |
    */

    'scenes' => [
        'image_source' => env('SCENE_IMAGE_SOURCE', 'placeholder'),
        'placeholder_base_url' => env('SCENE_PLACEHOLDER_BASE_URL', 'https://picsum.photos'),
        'asset_path' => 'assets/corporate',
        'local_extension' => env('SCENE_IMAGE_EXTENSION', 'svg'),
    ],

];
