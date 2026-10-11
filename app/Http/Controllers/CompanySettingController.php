<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanySettingController extends Controller
{
    public function edit(): View
    {
        return view('settings.company', ['company' => Company::firstOrFail()]);
    }

    public function update(Request $request): JsonResponse
    {
        $company = Company::firstOrFail();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'npwp' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'logo' => ['nullable', 'image', 'max:1024'],
        ]);
        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('company', 'public');
        }

        $old = $company->only(array_keys($data));
        $company->update($data);
        AuditLog::record('setting.company', $company, $old, $data);

        return response()->json(['message' => 'Pengaturan perusahaan disimpan.']);
    }
}
