<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

        // Debug logging (temporal)
        Log::info('🔍 Scraper Token Verification', [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'received_token' => $token ? substr($token, 0, 20) . '...' : 'NULL',
            'expected_token' => $expectedToken ? substr($expectedToken, 0, 20) . '...' : 'NULL',
            'token_match' => $token && $expectedToken ? hash_equals($expectedToken, $token) : false,
            'all_headers' => $request->headers->keys(),
        ]);

        // Si no hay token configurado, rechazar
        if (empty($expectedToken)) {
            Log::error('❌ Scraper token not configured in .env');
            return response()->json([
                'success' => false,
                'message' => 'Scraper token not configured',
            ], 500);
        }

        // Si no se envió token
        if (empty($token)) {
            Log::warning('⚠️ No scraper token received in request');
            return response()->json([
                'success' => false,
                'message' => 'Scraper token required',
            ], 401);
        }

        // Si el token no coincide
        if (!hash_equals($expectedToken, $token)) {
            Log::warning('⚠️ Invalid scraper token received');
            return response()->json([
                'success' => false,
                'message' => 'Invalid scraper token',
            ], 403);
        }

        Log::info('✅ Scraper token verified successfully');
        return $next($request);
    }
}
