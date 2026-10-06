<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Only users holding a staff role (admin, super admin, managers) may reach admin APIs. */
class EnsureUserIsStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->isStaff()) {
            return response()->json(['success' => false, 'message' => 'You do not have access to the admin area.'], 403);
        }

        return $next($request);
    }
}
