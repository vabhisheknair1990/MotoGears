<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Usage: ->middleware('permission:products.manage') — passes if the user has ANY listed permission. */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        if (! $user || ! collect($permissions)->contains(fn ($p) => $user->hasPermission($p))) {
            return response()->json(['success' => false, 'message' => 'You do not have permission to perform this action.'], 403);
        }

        return $next($request);
    }
}
