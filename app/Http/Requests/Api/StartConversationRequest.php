<?php

namespace App\Http\Requests\Api;

use App\Modules\Experience\Enums\SceneCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'locale' => ['required', Rule::in(config('corporate.locales'))],
            'scene' => ['required', Rule::in(SceneCode::values())],
        ];
    }
}
