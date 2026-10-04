<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerRole
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next, string $mode = 'deny'): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->hasRole('customer')) {
            return $next($request);
        }

        if ($mode === 'redirect' && $user instanceof User && $user->hasAnyRole(['manager', 'leisure-assistant'])) {
            return Inertia::location($user->workspaceUrl());
        }

        abort(403);
    }
}
