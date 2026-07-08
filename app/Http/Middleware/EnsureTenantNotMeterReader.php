<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantNotMeterReader
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = Auth::guard('tenant')->user();

        if ($user instanceof User && $user->isMeterReader()) {
            abort(403, 'Akun petugas catat meter tidak punya akses ke fitur ini.');
        }

        return $next($request);
    }
}

