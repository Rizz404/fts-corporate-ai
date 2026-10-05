<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserAccessRequest;
use App\Models\Department;
use App\Models\User;
use App\Modules\Identity\Actions\ProvisionUser;
use App\Modules\Identity\Actions\UpdateUserAccess;
use App\Modules\Identity\Enums\RoleCode;
use App\Modules\Identity\Enums\SupportTeamCode;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $search = trim((string) $request->query('q', ''));
        $departmentId = $request->integer('department') ?: null;
        $status = in_array($request->query('status'), ['active', 'inactive'], true) ? $request->query('status') : null;

        $users = User::query()
            ->with(['department:id,name', 'roles:id,code'])
            ->when($search !== '', function (Builder $query) use ($search) {
                $like = '%'.addcslashes(mb_strtolower($search), '%_\\').'%';
                $query->where(fn (Builder $query) => $query
                    ->whereRaw("lower(name) like ? escape '\'", [$like])
                    ->orWhereRaw("lower(email) like ? escape '\'", [$like]));
            })
            ->when($departmentId, fn (Builder $query) => $query->where('department_id', $departmentId))
            ->when($status, fn (Builder $query) => $query->where('is_active', $status === 'active'))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'filters' => ['q' => $search, 'department' => $departmentId, 'status' => $status],
            'departmentOptions' => Department::query()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('admin.users.create', $this->formOptions(null));
    }

    public function store(UserAccessRequest $request, ProvisionUser $provision): RedirectResponse
    {
        $provision->handle($request->user(), $request->validated());

        return redirect()->route('admin.users.index')->with('status', __('ui.users.created'));
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        $user->load(['roles:id,code', 'supportTeams:id,code', 'editorDepartments:id']);

        return view('admin.users.edit', ['user' => $user] + $this->formOptions($user));
    }

    public function update(UserAccessRequest $request, User $user, UpdateUserAccess $update): RedirectResponse
    {
        $update->handle($request->user(), $user, $request->validated());

        return redirect()->route('admin.users.edit', $user)->with('status', __('ui.users.updated'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(?User $user): array
    {
        $departments = Department::query()
            ->where(fn (Builder $query) => $query->where('is_active', true)->when($user, fn (Builder $query) => $query->orWhere('id', $user->department_id)))
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);

        return [
            'departmentOptions' => $departments->pluck('name', 'id')->all(),
            'editorDepartmentOptions' => $departments->where('is_active', true)->pluck('name', 'id')->all(),
            'roleOptions' => collect(RoleCode::cases())->mapWithKeys(fn (RoleCode $role) => [$role->value => $role->label()])->all(),
            'teamOptions' => collect(SupportTeamCode::cases())->mapWithKeys(fn (SupportTeamCode $team) => [$team->value => $team->label()])->all(),
            'localeOptions' => config('corporate.locale_names'),
        ];
    }
}
