<?php

namespace App\Modules\Knowledge\Validation;

use App\Models\KnowledgeArticle;
use App\Modules\Knowledge\Enums\Audience;
use App\Modules\Knowledge\Enums\KnowledgeCategory;
use App\Modules\Knowledge\Support\TextNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Draft input rules shared by create and save. Drafts may be incomplete;
 * PublicationRules decides whether a draft can be published.
 */
final class ArticleRules
{
    public const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public const MAX_LIST_ITEMS = 50;

    public const MAX_LIST_ITEM_LENGTH = 80;

    /**
     * @return array<string, mixed>
     */
    public static function rules(?KnowledgeArticle $article): array
    {
        $creating = $article === null;
        $slugEditable = $creating || ! $article->slugIsLocked();

        $rules = [
            'slug' => $slugEditable
                ? ['required', 'string', 'max:120', 'regex:'.self::SLUG_PATTERN, Rule::unique('knowledge_articles', 'slug')->ignore($article?->getKey())]
                : ['exclude'],
            'owner_department_id' => $creating
                ? ['required', 'integer', Rule::exists('departments', 'id')->where('is_active', true)]
                : ['exclude'],
            'category' => ['required', Rule::in(KnowledgeCategory::values())],
            'audience' => ['required', Rule::in(Audience::values())],
            'department_ids' => ['array'],
            'department_ids.*' => ['integer', 'distinct', Rule::exists('departments', 'id')],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', Rule::when(fn ($input) => filled($input['effective_from'] ?? null), 'after:effective_from')],
            'translations' => ['required', 'array'],
        ];

        foreach (config('corporate.locales') as $locale) {
            $rules["translations.{$locale}.title"] = ['nullable', 'string', 'max:180'];
            $rules["translations.{$locale}.body"] = ['nullable', 'string', 'max:20000'];
            $rules["translations.{$locale}.tags"] = ['nullable', 'string', 'max:6000'];
            $rules["translations.{$locale}.aliases"] = ['nullable', 'string', 'max:6000'];
        }

        return $rules;
    }

    /**
     * Tag/alias lists: at most 50 entries of 1–80 characters after normalization.
     */
    public static function validateLists(Validator $validator): void
    {
        $data = $validator->getData();

        foreach (config('corporate.locales') as $locale) {
            foreach (['tags', 'aliases'] as $field) {
                $items = TextNormalizer::splitList($data['translations'][$locale][$field] ?? '');
                $tooLong = array_filter($items, fn (string $item) => mb_strlen($item) > self::MAX_LIST_ITEM_LENGTH);

                if (count($items) > self::MAX_LIST_ITEMS || $tooLong !== []) {
                    $validator->errors()->add("translations.{$locale}.{$field}", __('ui.knowledge.errors.list_limits', [
                        'max' => self::MAX_LIST_ITEMS,
                        'length' => self::MAX_LIST_ITEM_LENGTH,
                    ]));
                }
            }
        }
    }

    /**
     * Converts validated form data into the action payload. Dates entered in
     * the company display timezone are stored as UTC.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function payload(array $validated): array
    {
        $timezone = config('corporate.timezone');
        $date = fn ($value) => $value === null || $value === ''
            ? null
            : CarbonImmutable::parse($value, $timezone)->utc();

        $translations = [];
        foreach (config('corporate.locales') as $locale) {
            $input = $validated['translations'][$locale] ?? [];
            $translations[$locale] = [
                'title' => trim((string) ($input['title'] ?? '')),
                'body' => trim(str_replace("\r\n", "\n", (string) ($input['body'] ?? ''))),
                'tags' => TextNormalizer::splitList($input['tags'] ?? ''),
                'aliases' => TextNormalizer::splitList($input['aliases'] ?? ''),
            ];
        }

        return array_filter([
            'slug' => $validated['slug'] ?? null,
            'owner_department_id' => isset($validated['owner_department_id']) ? (int) $validated['owner_department_id'] : null,
        ], fn ($value) => $value !== null) + [
            'category' => $validated['category'],
            'audience' => $validated['audience'],
            'department_ids' => array_values(array_unique(array_map('intval', $validated['department_ids'] ?? []))),
            'effective_from' => $date($validated['effective_from'] ?? null),
            'effective_until' => $date($validated['effective_until'] ?? null),
            'translations' => $translations,
        ];
    }
}
