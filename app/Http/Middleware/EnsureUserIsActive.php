<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Keep users whose registration hasn't been approved by HRD yet
     * out of the app, even though Fortify logs them in right after
     * they submit the registration form.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActive()) {
            if ($request->expectsJson()) {
                return response()->json(['code' => 'account_inactive', 'message' => 'Akun Anda belum aktif.'], 403);
            }

            return redirect()->route('pending-approval');
        }

        return $next($request);
    }
}
