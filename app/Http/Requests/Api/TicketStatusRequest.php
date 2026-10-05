<?php

namespace App\Http\Requests\Api;

use App\Modules\Support\Enums\TicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Status change. Requesters may only request "closed" (resolved → closed);
 * the transition matrix is enforced by TransitionTicket.
 */
class TicketStatusRequest extends FormRequest
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
        $allowed = $this->routeIs('api.staff.*') ? TicketStatus::values() : [TicketStatus::Closed->value];

        return [
            'status' => ['required', Rule::in($allowed)],
            'expected_version' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:5000'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
