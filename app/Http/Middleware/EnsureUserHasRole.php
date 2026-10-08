<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Usage: ->middleware('role:admin') or 'role:candidate,admin'. Runs after auth:sanctum. */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $allowed = array_map(fn (string $role) => UserRole::from($role), $roles);

        abort_unless($request->user()?->hasRole(...$allowed), 403);

        return $next($request);
    }
}
