<?php

namespace App\Http\Requests\Admin;

use App\Modules\Experience\Support\CompanySettings;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Plain text only: no HTML tags, URLs, or script-like content.
 */
class CompanySettingsRequest extends FormRequest
{
    private const NO_MARKUP = 'not_regex:/[<>]|javascript:|https?:\/\/|www\./i';

    public function authorize(): bool
    {
        return $this->user()->can('update', app(CompanySettings::class)->get());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'brand_name' => ['required', 'string', 'min:1', 'max:160', self::NO_MARKUP],
            'welcome_text' => ['required', 'array'],
        ];

        foreach (config('corporate.locales') as $locale) {
            $rules["welcome_text.{$locale}"] = ['nullable', 'string', 'max:1000', self::NO_MARKUP];
        }

        return $rules;
    }

    public function attributes(): array
    {
        $attributes = ['brand_name' => __('ui.settings.brand_name')];

        foreach (config('corporate.locale_names') as $locale => $name) {
            $attributes["welcome_text.{$locale}"] = __('ui.settings.welcome_text').' ('.$name.')';
        }

        return $attributes;
    }
}
