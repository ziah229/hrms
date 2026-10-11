<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pemakaian di route: ->middleware('permission:employee.view,employee.create')
 * Lolos bila user memiliki salah satu permission yang disebutkan.
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasPermission(...$permissions)) {
            AuditLog::record('access.denied', null, null, [
                'permissions' => $permissions,
                'method' => $request->method(),
                'url' => $request->fullUrl(),
            ]);

            abort(403, 'Anda tidak memiliki akses ke fitur ini.');
        }

        return $next($request);
    }
}
