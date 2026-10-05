<?php

namespace App\Http\Requests\Api;

use App\Models\TicketDraft;
use App\Modules\Support\Validation\TicketDraftRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Create (POST) or edit (PATCH, with expected_revision) a ticket draft.
 * The requester is always the session user.
 */
class TicketDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->replace(TicketDraftRules::normalize($this->all()));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = TicketDraftRules::rules();

        if ($this->route('draft') instanceof TicketDraft) {
            $rules['expected_revision'] = ['required', 'integer', 'min:1'];
        } else {
            $rules['conversation_public_id'] = ['nullable', 'uuid'];
        }

        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            foreach (TicketDraftRules::categoryErrors($validator->getData()) as $field => $message) {
                $validator->errors()->add($field, $message);
            }
        }];
    }

    /**
     * @return array{team_code: string, category: string, subject: string, description: string}
     */
    public function draftData(): array
    {
        return $this->safe()->only(['team_code', 'category', 'subject', 'description']);
    }

    public function attributes(): array
    {
        return [
            'team_code' => __('ui.tickets.fields.team'),
            'category' => __('ui.tickets.fields.category'),
            'subject' => __('ui.tickets.fields.subject'),
            'description' => __('ui.tickets.fields.description'),
        ];
    }
}
