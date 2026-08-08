<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Money is owner/manager business. Staff logins (farm workers given a sign-in)
 * can record their day-to-day work but must not see farm totals, sales or
 * costs — so every route that reads or writes money sits behind this.
 *
 * Enforced server-side rather than only hidden in the UI: hiding a menu is a
 * courtesy, this is the actual boundary.
 */
class EnsureCanViewFinances
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->canViewFinances()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have access to financial information.',
            ], 403);
        }

        return $next($request);
    }
}
