<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DepartmentRequest;
use App\Models\Department;
use App\Modules\Identity\Actions\WriteAuditEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Departments are deactivated, never deleted.
 */
class DepartmentController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Department::class);

        return view('admin.departments.index', [
            'departments' => Department::query()->withCount('users')->orderBy('name')->paginate(20),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Department::class);

        return view('admin.departments.create');
    }

    public function store(DepartmentRequest $request, WriteAuditEvent $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $audit) {
            $department = Department::create($request->validated() + ['is_active' => true]);
            $audit->handle($request->user(), 'department.created', $department, $department->only('slug', 'name'));
        });

        return redirect()->route('admin.departments.index')->with('status', __('ui.departments.created'));
    }

    public function edit(Department $department): View
    {
        Gate::authorize('update', $department);

        return view('admin.departments.edit', ['department' => $department]);
    }

    public function update(DepartmentRequest $request, Department $department, WriteAuditEvent $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $department, $audit) {
            $department->fill($request->validated());
            $changes = array_intersect_key($department->getDirty(), array_flip(['name', 'is_active']));
            $department->save();

            if ($changes !== []) {
                $audit->handle($request->user(), 'department.updated', $department, ['changes' => $changes]);
            }
        });

        return redirect()->route('admin.departments.edit', $department)->with('status', __('ui.departments.updated'));
    }
}
