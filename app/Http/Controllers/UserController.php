<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        if (! $request->expectsJson()) {
            return view('settings.users', [
                'roles' => Role::orderBy('id')->get(),
                'employees' => Employee::where('is_active', true)->orderBy('full_name')->get(['id', 'full_name', 'employee_no', 'user_id']),
            ]);
        }

        return response()->json(User::with(['role', 'employee'])
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$request->q}%")->orWhere('email', 'like', "%{$request->q}%")))
            ->when($request->filled('role_id'), fn ($q) => $q->where('role_id', $request->role_id))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 10))
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role_id' => $u->role_id,
                'role' => $u->role?->name,
                'employee_id' => $u->employee?->id,
                'employee' => $u->employee ? $u->employee->full_name.' ('.$u->employee->employee_no.')' : null,
                'is_active' => $u->is_active,
                'last_login_at' => $u->last_login_at?->translatedFormat('d M Y H:i'),
            ]));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $user = DB::transaction(function () use ($data) {
            $user = User::create($data);
            $this->linkEmployee($user, $data['employee_id'] ?? null);

            return $user;
        });
        AuditLog::record('user.create', $user, null, ['email' => $user->email, 'role_id' => $user->role_id]);

        return response()->json(['message' => "User {$user->email} dibuat."]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $this->validated($request, $user);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        if ($user->is($request->user()) && (! ($data['is_active'] ?? true) || (int) $data['role_id'] !== $user->role_id)) {
            abort(422, 'Anda tidak dapat menonaktifkan atau mengganti role akun sendiri.');
        }

        $old = $user->only(['name', 'email', 'role_id', 'is_active']);
        DB::transaction(function () use ($user, $data) {
            $user->update($data);
            $this->linkEmployee($user, $data['employee_id'] ?? null);
        });
        AuditLog::record('user.update', $user, $old, $user->only(array_keys($old)));

        return response()->json(['message' => "User {$user->email} diperbarui."]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        abort_if($user->is($request->user()), 422, 'Tidak dapat menghapus akun sendiri.');
        $user->delete();
        AuditLog::record('user.delete', $user, ['email' => $user->email]);

        return response()->json(['message' => 'User dihapus.']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', Rule::unique('users')->ignore($user?->id)],
            'role_id' => ['required', 'exists:roles,id'],
            'employee_id' => ['nullable', 'exists:employees,id'],
            'password' => [$user ? 'nullable' : 'required', Password::min(8)],
            'is_active' => ['boolean'],
        ]);
    }

    protected function linkEmployee(User $user, ?int $employeeId): void
    {
        Employee::where('user_id', $user->id)->when($employeeId, fn ($q) => $q->where('id', '!=', $employeeId))->update(['user_id' => null]);
        if ($employeeId) {
            Employee::whereKey($employeeId)->update(['user_id' => $user->id]);
        }
    }
}
