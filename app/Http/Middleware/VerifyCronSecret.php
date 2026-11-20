<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyCronSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = (string) config('liwaas.payment_cron_secret', '');

        if ($configured === '') {
            return response()->json([
                'success' => false,
                'message' => 'Payment cron is not configured.',
            ], 503);
        }

        $provided = (string) (
            $request->header('X-Cron-Secret')
            ?: $request->bearerToken()
            ?: $request->query('secret')
            ?: ''
        );

        if ($provided === '' || !hash_equals($configured, $provided)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        return $next($request);
    }
}
