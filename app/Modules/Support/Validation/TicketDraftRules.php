<?php

namespace App\Modules\Support\Validation;

use App\Models\SupportTeam;
use App\Modules\Identity\Enums\SupportTeamCode;
use App\Modules\Support\Enums\TicketCategory;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Domain validation for ticket drafts, shared by Form Requests and the
 * Plan 7 prepare_ticket tool. The requester always comes from the session.
 */
final class TicketDraftRules
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'team_code' => ['required', 'string', Rule::in(SupportTeamCode::values()), Rule::exists('support_teams', 'code')->where('is_active', true)],
            'category' => ['required', 'string', 'max:32'],
            'subject' => ['required', 'string', 'min:1', 'max:160'],
            'description' => ['required', 'string', 'min:1', 'max:5000'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    public static function categoryErrors(array $data): array
    {
        $team = $data['team_code'] ?? null;
        $category = $data['category'] ?? null;

        return is_string($team) && ! in_array($category, TicketCategory::for($team), true)
            ? ['category' => __('ui.tickets.errors.invalid_category')]
            : [];
    }

    /**
     * Trims text fields; normalizes line endings.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalize(array $input): array
    {
        foreach (['team_code', 'category', 'subject'] as $field) {
            if (isset($input[$field]) && is_string($input[$field])) {
                $input[$field] = trim($input[$field]);
            }
        }
        if (isset($input['description']) && is_string($input['description'])) {
            $input['description'] = trim(str_replace("\r\n", "\n", $input['description']));
        }

        return $input;
    }

    /**
     * Full validation for non-HTTP callers (AI tool in Plan 7).
     *
     * @param  array<string, mixed>  $input
     * @return array{team_code: string, category: string, subject: string, description: string}
     *
     * @throws ValidationException
     */
    public static function validate(array $input): array
    {
        $validator = Validator::make(self::normalize($input), self::rules());
        $validator->after(function ($validator) {
            foreach (self::categoryErrors($validator->getData()) as $field => $message) {
                $validator->errors()->add($field, $message);
            }
        });

        return $validator->validate();
    }

    public static function team(string $code): SupportTeam
    {
        return SupportTeam::query()->active()->where('code', $code)->firstOrFail();
    }
}
