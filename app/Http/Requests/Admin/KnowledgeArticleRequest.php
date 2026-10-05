<?php

namespace App\Http\Requests\Admin;

use App\Models\KnowledgeArticle;
use App\Modules\Knowledge\Validation\ArticleRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

/**
 * Create article (with first draft) and save draft share ArticleRules.
 */
class KnowledgeArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $article = $this->route('article');

        return $article instanceof KnowledgeArticle
            ? $this->user()->can('update', $article)
            : $this->user()->can('create', KnowledgeArticle::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('slug'))) {
            $this->merge(['slug' => Str::slug($this->input('slug'))]);
        }

        if ($this->input('audience') !== 'departments') {
            $this->merge(['department_ids' => []]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $article = $this->route('article');

        return ArticleRules::rules($article instanceof KnowledgeArticle ? $article : null);
    }

    public function after(): array
    {
        return [fn (Validator $validator) => ArticleRules::validateLists($validator)];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return ArticleRules::payload($this->validated());
    }

    public function attributes(): array
    {
        $attributes = [
            'slug' => __('ui.knowledge.fields.slug'),
            'owner_department_id' => __('ui.knowledge.fields.owner_department'),
            'category' => __('ui.knowledge.fields.category'),
            'audience' => __('ui.knowledge.fields.audience'),
            'department_ids' => __('ui.knowledge.fields.departments'),
            'effective_from' => __('ui.knowledge.fields.effective_from'),
            'effective_until' => __('ui.knowledge.fields.effective_until'),
        ];

        foreach (config('corporate.locale_names') as $locale => $name) {
            foreach (['title', 'body', 'tags', 'aliases'] as $field) {
                $attributes["translations.{$locale}.{$field}"] = __('ui.knowledge.fields.'.$field).' ('.$name.')';
            }
        }

        return $attributes;
    }
}
