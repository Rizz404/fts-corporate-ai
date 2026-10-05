<?php

namespace App\Modules\Identity\Validation;

use App\Models\User;
use App\Modules\Identity\Enums\RoleCode;
use App\Modules\Identity\Enums\SupportTeamCode;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validation shared by admin forms, CLI commands, and actions so every path
 * that creates or changes an account applies the same rules.
 */
final class UserAccessRules
{
    public const PASSWORD_MIN = 12;

    /**
     * @return array<string, mixed>
     */
    public static function rules(?User $user = null): array
    {
        $creating = $user === null;

        return [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->getKey())],
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')->where(
                // An existing user may keep a department that was later deactivated.
                fn (Builder $query) => $query->where('is_active', true)->when(
                    $user !== null,
                    fn (Builder $query) => $query->orWhere('id', $user->department_id),
                ),
            )],
            'preferred_locale' => ['required', Rule::in(config('corporate.locales'))],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'distinct', Rule::in(RoleCode::values())],
            'support_teams' => ['array'],
            'support_teams.*' => ['string', 'distinct', Rule::in(SupportTeamCode::values())],
            'editor_departments' => ['array'],
            'editor_departments.*' => ['integer', 'distinct', Rule::exists('departments', 'id')->where('is_active', true)],
            'is_active' => [$creating ? 'exclude' : 'required', 'boolean'],
            'password' => $creating ? self::passwordRules() : ['exclude'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function passwordRules(): array
    {
        return ['required', 'string', 'min:'.self::PASSWORD_MIN, 'max:255', 'confirmed'];
    }

    /**
     * Normalizes raw input (forms send absent checkboxes as missing keys).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalize(array $input): array
    {
        if (isset($input['email']) && is_string($input['email'])) {
            $input['email'] = User::normalizeEmail($input['email']);
        }

        foreach (['roles', 'support_teams', 'editor_departments'] as $key) {
            $input[$key] = array_values(array_filter((array) ($input[$key] ?? []), fn ($value) => $value !== null && $value !== ''));
        }

        $input['editor_departments'] = array_map(fn ($id) => is_numeric($id) ? (int) $id : $id, $input['editor_departments']);

        return $input;
    }

    /**
     * Staff team and editor department assignments require their role.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    public static function consistencyErrors(array $data): array
    {
        $roles = (array) ($data['roles'] ?? []);
        $errors = [];

        if (($data['support_teams'] ?? []) !== [] && ! in_array(RoleCode::Staff->value, $roles, true)) {
            $errors['support_teams'] = __('ui.users.errors.teams_require_staff');
        }

        if (($data['editor_departments'] ?? []) !== [] && ! in_array(RoleCode::Editor->value, $roles, true)) {
            $errors['editor_departments'] = __('ui.users.errors.departments_require_editor');
        }

        return $errors;
    }

    /**
     * Full validation for non-HTTP callers (CLI, actions).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function validate(array $input, ?User $user = null): array
    {
        $validator = Validator::make(self::normalize($input), self::rules($user));
        $validator->after(function ($validator) {
            foreach (self::consistencyErrors($validator->getData()) as $field => $message) {
                $validator->errors()->add($field, $message);
            }
        });

        return $validator->validate();
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public static function assertConsistent(array $data): void
    {
        $errors = self::consistencyErrors($data);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
