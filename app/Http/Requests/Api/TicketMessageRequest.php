<?php

namespace App\Http\Requests\Api;

use App\Modules\Support\Enums\MessageVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ticket message. `visibility` is accepted on staff routes only; requester
 * messages are always public.
 */
class TicketMessageRequest extends FormRequest
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
            'text' => ['required', 'string', 'min:1', 'max:5000'],
            'idempotency_key' => ['required', 'uuid'],
            'visibility' => [$this->routeIs('api.staff.*') ? 'nullable' : 'prohibited', Rule::in(array_column(MessageVisibility::cases(), 'value'))],
        ];
    }

    public function visibility(): MessageVisibility
    {
        return MessageVisibility::tryFrom((string) $this->validated('visibility')) ?? MessageVisibility::Public;
    }
}
