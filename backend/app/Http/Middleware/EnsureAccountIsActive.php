<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && $user->is_active === false) {
            $user->currentAccessToken()?->delete();

            return response()->json(['success' => false, 'message' => 'Your account has been disabled. Please contact support.'], 403);
        }

        return $next($request);
    }
}
