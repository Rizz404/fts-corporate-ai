<?php

namespace App\Http\Requests\Api;

use App\Modules\Experience\Enums\SceneCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Turn input (TECHNICAL_CONTRACTS). Context is limited to scene, article_id,
 * and ticket_public_id; identity never comes from the request. Ownership of
 * the context article/ticket is checked by AppendTurn (422 invalid_context).
 */
class SendMessageRequest extends FormRequest
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
            'client_turn_id' => ['required', 'uuid'],
            'message' => ['required', 'string', 'min:1', 'max:2000'],
            'locale' => ['required', Rule::in(config('corporate.locales'))],
            'context' => ['required', 'array:scene,article_id,ticket_public_id'],
            'context.scene' => ['required', Rule::in(SceneCode::values())],
            'context.article_id' => ['present', 'nullable', 'integer', 'min:1'],
            'context.ticket_public_id' => ['present', 'nullable', 'string', 'uuid'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('message'))) {
            $this->merge(['message' => trim($this->input('message'))]);
        }
    }

    /**
     * @return array{client_turn_id: string, message: string, locale: string, context: array{scene: string, article_id: ?int, ticket_public_id: ?string}}
     */
    public function turnInput(): array
    {
        $context = $this->validated('context');

        return [
            'client_turn_id' => strtolower($this->validated('client_turn_id')),
            'message' => $this->validated('message'),
            'locale' => $this->validated('locale'),
            'context' => [
                'scene' => $context['scene'],
                'article_id' => isset($context['article_id']) ? (int) $context['article_id'] : null,
                'ticket_public_id' => isset($context['ticket_public_id']) ? strtolower($context['ticket_public_id']) : null,
            ],
        ];
    }
}
