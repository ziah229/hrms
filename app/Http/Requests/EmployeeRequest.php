<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('employee')?->id;

        return [
            'full_name' => ['required', 'string', 'max:150'],
            'nik_ktp' => ['required', 'digits:16'],
            'npwp' => ['nullable', 'regex:/^[0-9]{15,16}$/'],
            'gender' => ['required', Rule::in(['L', 'P'])],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'religion' => ['nullable', 'string', 'max:30'],
            'marital_status' => ['nullable', 'string', 'max:30'],
            'dependents' => ['nullable', 'integer', 'between:0,10'],
            'ptkp_status' => ['required', Rule::in(array_keys(config('payroll.ter_category')))],
            'email' => ['required', 'email', 'max:150', Rule::unique('employees', 'email')->ignore($id)->whereNull('deleted_at')],
            'phone' => ['required', 'regex:/^(\+62|0)8[0-9]{7,12}$/'],
            'address' => ['nullable', 'string', 'max:500'],
            'department_id' => ['required', 'exists:departments,id'],
            'position_id' => ['required', 'exists:positions,id'],
            'work_location_id' => ['nullable', 'exists:work_locations,id'],
            'manager_id' => ['nullable', 'exists:employees,id', Rule::notIn(array_filter([$id]))],
            'employment_status' => ['required', Rule::in(array_keys(Employee::EMPLOYMENT_STATUSES))],
            'join_date' => ['required', 'date'],
            'contract_end_date' => ['nullable', 'required_if:employment_status,kontrak,magang', 'date', 'after:join_date'],
            'bpjs_kesehatan_no' => ['nullable', 'string', 'max:30'],
            'bpjs_tk_no' => ['nullable', 'string', 'max:30'],
            'bank_name' => ['required', 'string', 'max:50'],
            'account_no' => ['required', 'regex:/^[0-9]{6,20}$/'],
            'account_name' => ['nullable', 'string', 'max:150'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'full_name' => 'nama lengkap', 'nik_ktp' => 'NIK KTP', 'npwp' => 'NPWP', 'gender' => 'jenis kelamin',
            'ptkp_status' => 'status PTKP', 'phone' => 'nomor telepon', 'department_id' => 'departemen',
            'position_id' => 'jabatan', 'employment_status' => 'status hubungan kerja', 'join_date' => 'tanggal bergabung',
            'contract_end_date' => 'tanggal akhir kontrak', 'bank_name' => 'bank', 'account_no' => 'nomor rekening',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nik_ktp.digits' => 'NIK KTP harus 16 digit angka.',
            'npwp.regex' => 'NPWP harus 15/16 digit angka.',
            'phone.regex' => 'Format nomor telepon tidak valid (contoh: 081234567890).',
            'account_no.regex' => 'Nomor rekening hanya angka (6–20 digit).',
            'contract_end_date.required_if' => 'Tanggal akhir kontrak wajib untuk status kontrak/magang.',
        ];
    }
}
