<?php

namespace App\Http\Controllers;

use App\Exports\ArrayExport;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\PayrollPeriod;
use App\Models\ReportDefinition;
use App\Models\ReportExport;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $reports = ReportDefinition::where('is_active', true)->orderBy('id')->get()
            ->filter(fn ($r) => ! $r->permission || $request->user()->hasPermission($r->permission))
            ->groupBy('category');

        return view('reports.index', [
            'reports' => $reports,
            'departments' => Department::orderBy('name')->get(),
            'periods' => PayrollPeriod::whereNot('status', 'draft')->orderByDesc('year')->orderByDesc('month')->get(),
            'recent' => ReportExport::with('definition')->where('user_id', $request->user()->id)->latest('id')->limit(5)->get(),
        ]);
    }

    public function preview(Request $request, string $code): JsonResponse
    {
        [$definition, $data] = $this->build($request, $code);

        return response()->json([
            'title' => $definition->name,
            'subtitle' => $data['subtitle'],
            'columns' => $data['columns'],
            'rows' => array_slice($data['rows'], 0, 200),
            'total' => count($data['rows']),
            'money' => $data['money'],
        ]);
    }

    public function export(Request $request, string $code): Response
    {
        $format = $request->input('format') === 'pdf' ? 'pdf' : 'xlsx';
        [$definition, $data] = $this->build($request, $code);
        $filename = str($definition->name)->slug().'-'.now()->format('YmdHis').'.'.$format;

        ReportExport::create([
            'report_definition_id' => $definition->id,
            'user_id' => $request->user()->id,
            'filters' => $this->filters($request),
            'format' => $format,
            'file_path' => $filename,
            'created_at' => now(),
        ]);
        AuditLog::record('report.export', $definition, null, ['format' => $format] + $this->filters($request));

        if ($format === 'pdf') {
            return Pdf::loadView('reports.pdf', ['definition' => $definition, 'data' => $data])
                ->setPaper('a4', count($data['columns']) > 6 ? 'landscape' : 'portrait')
                ->download($filename);
        }

        return Excel::download(new ArrayExport($data['columns'], $data['rows'], $definition->name), $filename);
    }

    /**
     * @return array{0: ReportDefinition, 1: array<string, mixed>}
     */
    protected function build(Request $request, string $code): array
    {
        $definition = ReportDefinition::where('code', $code)->where('is_active', true)->firstOrFail();
        if ($definition->permission && ! $request->user()->hasPermission($definition->permission)) {
            AuditLog::record('access.denied', $definition);
            abort(403, 'Anda tidak memiliki akses ke report ini.');
        }

        return [$definition, (new ReportService($request->user(), $this->filters($request)))->run($code)];
    }

    /**
     * @return array<string, mixed>
     */
    protected function filters(Request $request): array
    {
        return array_filter($request->only(['month', 'period', 'department_id', 'status']), fn ($v) => filled($v));
    }
}
