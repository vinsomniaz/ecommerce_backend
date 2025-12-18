<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyScraperToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Scraper-Token');
        $expectedToken = config('scraper.token');

        // Si no hay token configurado, rechazar
        if (empty($expectedToken)) {
            return response()->json([
                'success' => false,
                'message' => 'Scraper token not configured',
            ], 500);
        }

        // Si no se envió token
        if (empty($token)) {
            return response()->json([
                'success' => false,
                'message' => 'Scraper token required',
            ], 401);
        }

        // Si el token no coincide
        if (!hash_equals($expectedToken, $token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid scraper token',
            ], 403);
        }

        return $next($request);
    }
}
