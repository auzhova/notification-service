<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireIdempotencyKey
{
    public function handle(Request $request, Closure $next)
    {
        $key = $request->header('Idempotency-Key');

        if (!$key) {
            return response()->json([
                'message' => 'Idempotency-Key header is required',
            ], 400);
        }

        if (strlen($key) < 8) {
            return response()->json([
                'message' => 'Invalid Idempotency-Key',
            ], 400);
        }

        return $next($request);
    }
}
