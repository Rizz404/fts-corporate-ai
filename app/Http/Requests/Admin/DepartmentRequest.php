<?php

namespace App\Http\Requests\Admin;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Slug is set on create and immutable afterwards (other modules and the
 * reference seed refer to it).
 */
class DepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $department = $this->route('department');

        return $department instanceof Department
            ? $this->user()->can('update', $department)
            : $this->user()->can('create', Department::class);
    }

    protected function prepareForValidation(): void
    {
        if ($this->route('department') instanceof Department) {
            $this->merge(['is_active' => $this->boolean('is_active')]);

            return;
        }

        if (is_string($this->input('slug'))) {
            $this->merge(['slug' => Str::slug($this->input('slug'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if ($this->route('department') instanceof Department) {
            return [
                'name' => ['required', 'string', 'max:160'],
                'is_active' => ['required', 'boolean'],
            ];
        }

        return [
            'slug' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:departments,slug'],
            'name' => ['required', 'string', 'max:160'],
        ];
    }

    public function attributes(): array
    {
        return [
            'slug' => __('ui.departments.slug'),
            'name' => __('ui.name'),
            'is_active' => __('ui.status'),
        ];
    }
}
