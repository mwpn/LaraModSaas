<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCentralPermission
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        /** @var User|null $user */
        $user = Auth::guard('central')->user();

        if (! $user instanceof User) {
            abort(403);
        }

        if (! $user->canAccessCentral($ability)) {
            abort(403, 'Akses role ini tidak diizinkan untuk area central tersebut.');
        }

        return $next($request);
    }
}
