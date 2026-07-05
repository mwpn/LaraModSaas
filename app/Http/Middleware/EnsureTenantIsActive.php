<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();

        if ($tenant instanceof Tenant && $tenant->hasAccessBlock()) {
            Auth::guard('tenant')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->view('basefeature::suspended', [
                'tenant' => $tenant,
                'blockMeta' => $tenant->accessBlockMeta(),
            ], 423);
        }

        return $next($request);
    }
}
