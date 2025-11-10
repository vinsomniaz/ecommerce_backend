<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GeminiController extends Controller
{
    /**
     * Genera info de producto - ULTRA OPTIMIZADO para hosting compartido
     */
    public function generateProductInfo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_name' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $productName = trim($request->product_name);
        $cacheKey = 'gemini_product_' . md5(strtolower($productName));
        
        // Cache HIT - respuesta instantánea
        $cachedData = Cache::get($cacheKey);
        if ($cachedData) {
            return response()->json([
                'success' => true,
                'data' => $cachedData,
                'cached' => true
            ]);
        }
        
        // Cache MISS - generar ahora (sin locks complicados)
        try {
            $result = $this->callGeminiAPI($productName);
            
            // Cachear 30 días
            Cache::put($cacheKey, $result, now()->addDays(30));
            
            return response()->json([
                'success' => true,
                'data' => $result,
                'cached' => false
            ]);

        } catch (\Exception $e) {
            Log::error('Gemini API Error', [
                'product' => $productName,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al generar contenido',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno'
            ], 500);
        }
    }

    /**
     * Batch PARALELO usando Guzzle Promises (MUCHO más rápido)
     * Procesa múltiples productos SIMULTÁNEAMENTE
     */
    public function generateBatch(Request $request)
    {
        set_time_limit(120);
        
        $validator = Validator::make($request->all(), [
            'products' => 'required|array|min:1|max:10',
            'products.*.name' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $products = collect($request->products)->map(fn($p) => trim($p['name']));
        $results = [];
        $promises = [];
        $client = new Client(['timeout' => 25, 'connect_timeout' => 5]);
        
        foreach ($products as $productName) {
            $cacheKey = 'gemini_product_' . md5(strtolower($productName));
            
            // Si está en caché, agregar directamente
            $cachedData = Cache::get($cacheKey);
            if ($cachedData) {
                $results[$productName] = [
                    'product_name' => $productName,
                    'data' => $cachedData,
                    'cached' => true
                ];
                continue;
            }
            
            // Si NO está en caché, crear promesa para request paralelo
            $promises[$productName] = $this->createGeminiPromise($client, $productName);
        }
        
        // Ejecutar TODAS las promesas en PARALELO
        if (count($promises) > 0) {
            $responses = Promise\Utils::settle($promises)->wait();
            
            foreach ($responses as $productName => $response) {
                $cacheKey = 'gemini_product_' . md5(strtolower($productName));
                
                if ($response['state'] === 'fulfilled') {
                    try {
                        $data = $this->parseGeminiResponse($response['value']);
                        Cache::put($cacheKey, $data, now()->addDays(30));
                        
                        $results[$productName] = [
                            'product_name' => $productName,
                            'data' => $data,
                            'cached' => false
                        ];
                    } catch (\Exception $e) {
                        $results[$productName] = [
                            'product_name' => $productName,
                            'error' => $e->getMessage()
                        ];
                    }
                } else {
                    $results[$productName] = [
                        'product_name' => $productName,
                        'error' => 'Request falló: ' . ($response['reason']->getMessage() ?? 'Unknown')
                    ];
                }
            }
        }
        
        $stats = [
            'total' => count($results),
            'from_cache' => collect($results)->where('cached', true)->count(),
            'from_api' => collect($results)->where('cached', false)->count(),
            'errors' => collect($results)->whereNotNull('error')->count()
        ];

        return response()->json([
            'success' => true,
            'results' => array_values($results),
            'stats' => $stats
        ]);
    }

    /**
     * Crear promesa de Guzzle para request paralelo
     */
    private function createGeminiPromise(Client $client, string $productName)
    {
        $apiKey = config('services.gemini.api_key');
        
        $prompt = "Producto: {$productName}

Responde SOLO JSON sin markdown:
{\"description\":\"Descripción comercial 3-4 líneas\",\"specifications\":[{\"name\":\"Spec\",\"value\":\"Val\"}]}

6-8 specs técnicas.";

        return $client->postAsync(
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent',
            [
                'headers' => ['Content-Type' => 'application/json'],
                'query' => ['key' => $apiKey],
                'json' => [
                    'contents' => [[
                        'parts' => [['text' => $prompt]]
                    ]],
                    'generationConfig' => [
                        'temperature' => 0.3,
                        'maxOutputTokens' => 600
                    ]
                ]
            ]
        );
    }

    /**
     * Parsear respuesta de Gemini
     */
    private function parseGeminiResponse($response): array
    {
        $data = json_decode($response->getBody(), true);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        
        if (!$text) {
            throw new \Exception('Respuesta vacía');
        }
        
        // Limpiar markdown
        $text = preg_replace('/```(?:json)?\s*|\s*```/', '', $text);
        $text = trim($text);
        
        $parsed = json_decode($text, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('JSON inválido');
        }
        
        return [
            'description' => $parsed['description'] ?? '',
            'specifications' => $parsed['specifications'] ?? []
        ];
    }

    /**
     * Llamada síncrona simple a Gemini
     */
    private function callGeminiAPI(string $productName): array
    {
        $client = new Client([
            'timeout' => 25,
            'connect_timeout' => 5
        ]);
        
        $apiKey = config('services.gemini.api_key');
        
        if (!$apiKey) {
            throw new \Exception('API Key no configurada');
        }

        $prompt = "Producto: {$productName}

Responde SOLO JSON sin markdown ni explicaciones:
{\"description\":\"Descripción comercial de 3-4 líneas con características y beneficios\",\"specifications\":[{\"name\":\"Especificación\",\"value\":\"Valor\"}]}

Incluye 6-8 especificaciones técnicas relevantes.";

        try {
            $response = $client->post(
                'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent',
                [
                    'headers' => ['Content-Type' => 'application/json'],
                    'query' => ['key' => $apiKey],
                    'json' => [
                        'contents' => [[
                            'parts' => [['text' => $prompt]]
                        ]],
                        'generationConfig' => [
                            'temperature' => 0.3,
                            'maxOutputTokens' => 600
                        ]
                    ]
                ]
            );
            
            return $this->parseGeminiResponse($response);
            
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            if ($e->hasResponse()) {
                $status = $e->getResponse()->getStatusCode();
                
                if ($status === 429) {
                    throw new \Exception('Rate limit alcanzado. Intenta más tarde.');
                }
                
                throw new \Exception("Error API Gemini (HTTP {$status})");
            }
            
            throw new \Exception('Error de conexión con Gemini');
        }
    }

    /**
     * Pre-calentar caché con productos más usados
     */
    public function warmCache(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'products' => 'required|array|max:50',
            'products.*' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $products = collect($request->products)->unique();
        $warmed = 0;
        $skipped = 0;

        foreach ($products as $productName) {
            $cacheKey = 'gemini_product_' . md5(strtolower($productName));
            
            if (Cache::has($cacheKey)) {
                $skipped++;
                continue;
            }
            
            try {
                $result = $this->callGeminiAPI($productName);
                Cache::put($cacheKey, $result, now()->addDays(30));
                $warmed++;
                
                // Esperar 1.5s entre llamadas
                if ($warmed < $products->count() - $skipped) {
                    sleep(2);
                }
            } catch (\Exception $e) {
                Log::warning("Error warming cache for: {$productName}", [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'warmed' => $warmed,
            'skipped' => $skipped,
            'total' => $products->count()
        ]);
    }

    /**
     * Limpiar caché
     */
    public function clearCache(Request $request)
    {
        if ($request->product_name) {
            $cacheKey = 'gemini_product_' . md5(strtolower($request->product_name));
            Cache::forget($cacheKey);
            
            return response()->json([
                'success' => true,
                'message' => 'Caché del producto limpiado'
            ]);
        }

        if ($request->clear_all) {
            Cache::flush();
            
            return response()->json([
                'success' => true,
                'message' => 'Todo el caché limpiado'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Proporciona product_name o clear_all'
        ], 400);
    }
}