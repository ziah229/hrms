<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function __invoke(Request $request): View|JsonResponse
    {
        if (! $request->expectsJson()) {
            return view('settings.audit-logs', [
                'entities' => AuditLog::whereNotNull('entity')->distinct()->orderBy('entity')->pluck('entity'),
            ]);
        }

        return response()->json(AuditLog::with('user')
            ->when($request->filled('q'), fn ($q) => $q->where('action', 'like', "%{$request->q}%"))
            ->when($request->filled('entity'), fn ($q) => $q->where('entity', $request->entity))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('created_at', $request->date))
            ->when($request->boolean('denied'), fn ($q) => $q->where('action', 'access.denied'))
            ->latest('id')
            ->paginate($request->integer('per_page', 25))
            ->through(fn (AuditLog $a) => [
                'id' => $a->id,
                'created_at' => $a->created_at?->translatedFormat('d M Y H:i:s'),
                'user' => $a->user?->name ?? 'Sistem',
                'action' => $a->action,
                'entity' => $a->entity ? $a->entity.' #'.$a->entity_id : '-',
                'old_values' => $a->old_values,
                'new_values' => $a->new_values,
                'ip_address' => $a->ip_address,
            ]));
    }
}
