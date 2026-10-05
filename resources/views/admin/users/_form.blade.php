{{-- Shared create/edit fields. $user is null on create. --}}
@php
    $roles = old('roles', $user?->roles->map(fn ($role) => $role->code->value)->all() ?? ['employee']);
    $teams = old('support_teams', $user?->supportTeams->map(fn ($team) => $team->code->value)->all() ?? []);
    $editorDepartments = array_map('intval', old('editor_departments', $user?->editorDepartments->pluck('id')->all() ?? []));
@endphp

<div class="grid gap-4 md:grid-cols-2">
    <x-ui.field name="name" id="user-name" :label="__('ui.name')" required>
        <x-ui.input name="name" id="user-name" :value="$user?->name" autocomplete="off" maxlength="160" required />
    </x-ui.field>

    <x-ui.field name="email" id="user-email" :label="__('ui.email')" required>
        <x-ui.input name="email" id="user-email" type="email" :value="$user?->email" autocomplete="off" maxlength="255" required />
    </x-ui.field>

    <x-ui.field name="department_id" id="user-department" :label="__('ui.department')" required>
        <x-ui.select name="department_id" id="user-department" :options="$departmentOptions" :value="$user?->department_id" :placeholder="__('ui.choose')" required />
    </x-ui.field>

    <x-ui.field name="preferred_locale" id="user-locale" :label="__('ui.users.preferred_locale')" required>
        <x-ui.select name="preferred_locale" id="user-locale" :options="$localeOptions" :value="$user?->preferred_locale ?? config('corporate.default_locale')" required />
    </x-ui.field>
</div>

<fieldset class="mt-6 space-y-2">
    <legend class="text-body font-medium text-text">{{ __('ui.users.roles') }}</legend>
    <div class="flex flex-wrap gap-x-6 gap-y-2">
        @foreach ($roleOptions as $value => $label)
            <x-ui.checkbox name="roles[]" id="user-role-{{ $value }}" :value="$value" :checked="in_array($value, $roles, true)" :describedby="$errors->has('roles') ? 'user-roles-error' : null">
                {{ $label }}
            </x-ui.checkbox>
        @endforeach
    </div>
    <p class="text-caption text-muted">{{ __('ui.users.roles_help') }}</p>
    @error('roles')
        <p id="user-roles-error" class="text-caption text-danger">{{ $message }}</p>
    @enderror
</fieldset>

<div class="mt-6 grid gap-6 md:grid-cols-2">
    <fieldset class="space-y-2">
        <legend class="text-body font-medium text-text">{{ __('ui.users.support_teams') }}</legend>
        <div class="flex flex-wrap gap-x-6 gap-y-2">
            @foreach ($teamOptions as $value => $label)
                <x-ui.checkbox name="support_teams[]" id="user-team-{{ $value }}" :value="$value" :checked="in_array($value, $teams, true)">
                    {{ $label }}
                </x-ui.checkbox>
            @endforeach
        </div>
        <p class="text-caption text-muted">{{ __('ui.users.support_teams_help') }}</p>
        @error('support_teams')
            <p class="text-caption text-danger">{{ $message }}</p>
        @enderror
    </fieldset>

    <fieldset class="space-y-2">
        <legend class="text-body font-medium text-text">{{ __('ui.users.editor_departments') }}</legend>
        <div class="flex flex-wrap gap-x-6 gap-y-2">
            @foreach ($editorDepartmentOptions as $value => $label)
                <x-ui.checkbox name="editor_departments[]" id="user-editor-department-{{ $value }}" :value="$value" :checked="in_array((int) $value, $editorDepartments, true)">
                    {{ $label }}
                </x-ui.checkbox>
            @endforeach
        </div>
        <p class="text-caption text-muted">{{ __('ui.users.editor_departments_help') }}</p>
        @error('editor_departments')
            <p class="text-caption text-danger">{{ $message }}</p>
        @enderror
    </fieldset>
</div>
