<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        return view('settings.roles', [
            'roles' => Role::with('permissions:id')->withCount('users')->orderBy('id')->get(),
            'permissions' => Permission::orderBy('id')->get()->groupBy('module'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $role = Role::create($data + ['is_system' => false]);
        $role->permissions()->sync($request->input('permissions', []));
        AuditLog::record('role.create', $role, null, $data + ['permissions' => $request->input('permissions', [])]);

        return response()->json(['message' => "Role {$role->name} dibuat."]);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $data = $this->validated($request, $role);
        if ($role->is_system) {
            $data['name'] = $role->name; // nama role bawaan tidak diubah
        }

        $old = ['role' => $role->only(['name', 'description', 'data_scope']), 'permissions' => $role->permissions()->pluck('code')->all()];
        $role->update($data);
        if (! $role->isSuperAdmin()) {
            $role->permissions()->sync($request->input('permissions', []));
        }
        AuditLog::record('role.update', $role, $old, ['role' => $data, 'permissions' => $role->permissions()->pluck('code')->all()]);

        return response()->json(['message' => "Role {$role->name} diperbarui."]);
    }

    public function destroy(Role $role): JsonResponse
    {
        abort_if($role->is_system, 422, 'Role bawaan sistem tidak dapat dihapus.');
        abort_if($role->users()->exists(), 422, 'Role masih dipakai oleh user.');
        $role->delete();
        AuditLog::record('role.delete', $role, $role->toArray());

        return response()->json(['message' => 'Role dihapus.']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('roles')->ignore($role?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'data_scope' => ['required', Rule::in(['all', 'department', 'subordinate', 'self'])],
            'permissions' => ['array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);
    }
}
