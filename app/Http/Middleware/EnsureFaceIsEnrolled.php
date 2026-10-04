<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFaceIsEnrolled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->hasFaceEnrolled()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'code' => 'enrollment_required',
                    'message' => 'Daftarkan wajah Anda terlebih dahulu sebelum melakukan absensi.',
                    'redirect' => route('face.edit'),
                ], 403);
            }

            return redirect()->route('face.edit');
        }

        return $next($request);
    }
}
