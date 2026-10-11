<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Department;
use App\Models\Holiday;
use App\Models\LeaveType;
use App\Models\Position;
use App\Models\Shift;
use App\Models\WorkLocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * CRUD generik untuk tabel master sederhana. Definisi tiap tipe ada di definitions().
 */
class MasterDataController extends Controller
{
    /**
     * @return array<string, array<string, mixed>>
     */
    protected function definitions(): array
    {
        return [
            'departments' => [
                'model' => Department::class, 'title' => 'Master Departemen', 'icon' => 'apartment', 'permission' => 'employee.update',
                'section' => 'Kepegawaian', 'company' => true, 'order' => 'code',
                'fields' => [
                    'code' => ['label' => 'Kode', 'type' => 'text', 'rules' => ['required', 'string', 'max:20'], 'unique' => true],
                    'name' => ['label' => 'Nama Departemen', 'type' => 'text', 'rules' => ['required', 'string', 'max:100']],
                ],
                'count' => 'employees',
            ],
            'positions' => [
                'model' => Position::class, 'title' => 'Master Jabatan', 'icon' => 'work', 'permission' => 'employee.update',
                'section' => 'Kepegawaian', 'order' => 'level',
                'fields' => [
                    'code' => ['label' => 'Kode', 'type' => 'text', 'rules' => ['required', 'string', 'max:20'], 'unique' => true],
                    'name' => ['label' => 'Nama Jabatan', 'type' => 'text', 'rules' => ['required', 'string', 'max:100']],
                    'level' => ['label' => 'Level', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:10']],
                ],
                'count' => 'employees',
            ],
            'work-locations' => [
                'model' => WorkLocation::class, 'title' => 'Lokasi Kerja', 'icon' => 'location_on', 'permission' => 'employee.update',
                'section' => 'Kepegawaian', 'company' => true, 'order' => 'name',
                'fields' => [
                    'name' => ['label' => 'Nama Lokasi', 'type' => 'text', 'rules' => ['required', 'string', 'max:100']],
                    'address' => ['label' => 'Alamat', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:500']],
                ],
                'count' => 'employees',
            ],
            'shifts' => [
                'model' => Shift::class, 'title' => 'Jadwal / Shift Kerja', 'icon' => 'schedule', 'permission' => 'attendance.update',
                'section' => 'Absensi', 'order' => 'start_time',
                'fields' => [
                    'name' => ['label' => 'Nama Shift', 'type' => 'text', 'rules' => ['required', 'string', 'max:100']],
                    'start_time' => ['label' => 'Jam Masuk', 'type' => 'time', 'rules' => ['required', 'date_format:H:i']],
                    'end_time' => ['label' => 'Jam Pulang', 'type' => 'time', 'rules' => ['required', 'date_format:H:i']],
                    'late_tolerance_min' => ['label' => 'Toleransi Terlambat (menit)', 'type' => 'number', 'rules' => ['required', 'integer', 'between:0,120']],
                ],
                'used_by' => ['attendances', 'schedules'],
            ],
            'leave-types' => [
                'model' => LeaveType::class, 'title' => 'Jenis Cuti & Izin', 'icon' => 'flight_takeoff', 'permission' => 'attendance.update',
                'section' => 'Absensi', 'order' => 'name',
                'fields' => [
                    'name' => ['label' => 'Nama', 'type' => 'text', 'rules' => ['required', 'string', 'max:100']],
                    'default_quota' => ['label' => 'Kuota Default (hari)', 'type' => 'number', 'rules' => ['required', 'integer', 'between:0,365']],
                    'is_paid' => ['label' => 'Dibayar', 'type' => 'switch', 'rules' => ['boolean']],
                ],
                'count' => 'requests',
            ],
            'holidays' => [
                'model' => Holiday::class, 'title' => 'Hari Libur', 'icon' => 'event', 'permission' => 'setting.manage',
                'section' => 'Pengaturan', 'company' => true, 'order' => 'holiday_date',
                'fields' => [
                    'holiday_date' => ['label' => 'Tanggal', 'type' => 'date', 'rules' => ['required', 'date'], 'unique' => 'company',
                        'message' => 'Tanggal tersebut sudah terdaftar sebagai hari libur.'],
                    'name' => ['label' => 'Keterangan', 'type' => 'text', 'rules' => ['required', 'string', 'max:150']],
                ],
            ],
        ];
    }

    public function index(Request $request, string $type): View|JsonResponse
    {
        $def = $this->definition($request, $type);

        if (! $request->expectsJson()) {
            return view('master.index', ['type' => $type, 'def' => $def]);
        }

        $query = $def['model']::query()
            ->when(isset($def['count']), fn ($q) => $q->withCount($def['count']))
            ->when($request->filled('q'), function ($q) use ($request, $def) {
                $q->where(function ($w) use ($request, $def) {
                    foreach ($def['fields'] as $name => $f) {
                        if (in_array($f['type'], ['text', 'textarea'])) {
                            $w->orWhere($name, 'like', '%'.$request->q.'%');
                        }
                    }
                });
            })
            ->orderBy($def['order']);

        return response()->json($query->paginate($request->integer('per_page', 10))->through(function (Model $m) use ($def) {
            $row = $m->only(array_merge(['id'], array_keys($def['fields'])));
            foreach ($def['fields'] as $name => $f) {
                if ($f['type'] === 'time') {
                    $row[$name] = substr((string) $m->{$name}, 0, 5);
                } elseif ($f['type'] === 'date') {
                    $row[$name] = $m->{$name}?->toDateString();
                }
            }
            $row['usage'] = isset($def['count']) ? $m->{$def['count'].'_count'} : null;

            return $row;
        }));
    }

    public function store(Request $request, string $type): JsonResponse
    {
        $def = $this->definition($request, $type);
        $data = $request->validate($this->rules($def), $this->messages($def));
        if (! empty($def['company'])) {
            $data['company_id'] = Company::value('id');
        }

        $model = $def['model']::create($data);
        AuditLog::record("master.{$type}.create", $model, null, $data);

        return response()->json(['message' => $def['title'].' berhasil ditambahkan.']);
    }

    public function update(Request $request, string $type, int $id): JsonResponse
    {
        $def = $this->definition($request, $type);
        $model = $def['model']::findOrFail($id);
        $data = $request->validate($this->rules($def, $model), $this->messages($def));
        $old = $model->only(array_keys($data));

        $model->update($data);
        AuditLog::record("master.{$type}.update", $model, $old, $data);

        return response()->json(['message' => $def['title'].' berhasil diperbarui.']);
    }

    public function destroy(Request $request, string $type, int $id): JsonResponse
    {
        $def = $this->definition($request, $type);
        $model = $def['model']::findOrFail($id);

        // FK master memakai nullOnDelete/cascadeOnDelete, jadi pemakaian harus dicek manual agar data terkait tidak ikut hilang.
        if ($this->isUsed($def, $model)) {
            return response()->json(['message' => 'Data masih dipakai oleh data lain dan tidak dapat dihapus.'], 422);
        }

        try {
            $model->delete();
        } catch (QueryException) {
            return response()->json(['message' => 'Data masih dipakai oleh data lain dan tidak dapat dihapus.'], 422);
        }
        AuditLog::record("master.{$type}.delete", $model, $model->toArray());

        return response()->json(['message' => 'Data berhasil dihapus.']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function definition(Request $request, string $type): array
    {
        $def = $this->definitions()[$type] ?? abort(404);

        if (! $request->user()->hasPermission($def['permission'])) {
            AuditLog::record('access.denied', null, null, ['permissions' => [$def['permission']], 'url' => $request->fullUrl()]);
            abort(403, 'Anda tidak memiliki akses ke master data ini.');
        }

        return $def;
    }

    /**
     * @param  array<string, mixed>  $def
     * @return array<string, mixed>
     */
    protected function rules(array $def, ?Model $model = null): array
    {
        $table = (new $def['model'])->getTable();

        return collect($def['fields'])->map(function ($f, $name) use ($table, $model) {
            $rules = $f['rules'];
            if (! empty($f['unique'])) {
                $rule = Rule::unique($table, $name)->ignore($model?->id);
                $rules[] = $f['unique'] === 'company' ? $rule->where('company_id', $model->company_id ?? Company::value('id')) : $rule;
            }

            return $rules;
        })->all();
    }

    /**
     * @param  array<string, mixed>  $def
     * @return array<string, string>
     */
    protected function messages(array $def): array
    {
        return collect($def['fields'])->filter(fn ($f) => isset($f['message']))
            ->mapWithKeys(fn ($f, $name) => ["{$name}.unique" => $f['message']])->all();
    }

    /**
     * @param  array<string, mixed>  $def
     */
    protected function isUsed(array $def, Model $model): bool
    {
        $relations = $def['used_by'] ?? (isset($def['count']) ? [$def['count']] : []);

        foreach ($relations as $relation) {
            $query = $model->{$relation}();
            // Karyawan yang sudah di-soft-delete tetap dihitung agar riwayatnya tidak kehilangan referensi.
            if (in_array(SoftDeletes::class, class_uses_recursive($query->getModel()))) {
                $query->withTrashed();
            }
            if ($query->exists()) {
                return true;
            }
        }

        return false;
    }
}
