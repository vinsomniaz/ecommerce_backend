<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRate; //
use App\Services\OpenExchangeRateService; // <- USAMOS EL NUEVO SERVICIO
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ExchangeRateController extends Controller
{
    public function __construct(
        private OpenExchangeRateService $exchangeRateService
    ) {}

    /**
     * Endpoint para JALAR, GUARDAR USD/PEN y EUR/PEN y MOSTRAR las tasas.
     * GET /api/rates/fetch-and-show
     */
    public function fetchAndShow(): JsonResponse
    {
        // 1. Jalar tasas (Base USD)
        $usdRates = $this->exchangeRateService->fetchUsdBasedRates();

        if (empty($usdRates)) {
            return response()->json([
                'message' => 'Fallo al obtener las tasas de Open Exchange Rates.',
                'status' => 'fetch_failed',
            ], 500);
        }

        $usdToPenRate = $usdRates['PEN'];
        $usdToEurRate = $usdRates['EUR'];

        // 2. Calcular la tasa EUR/PEN
        // Conversión requerida: 1 EUR = X PEN.
        // Cálculo: Rate_EUR_PEN = Rate_USD_PEN / Rate_USD_EUR
        $eurToPenRate = $usdToPenRate / $usdToEurRate;

        try {
            $updatedRates = DB::transaction(function () use ($usdToPenRate, $eurToPenRate) {

                // 3. Guardar USD/PEN (1 USD = X PEN)
                ExchangeRate::updateOrCreate(
                    ['currency' => 'USD'],
                    ['exchange_rate' => $usdToPenRate, 'updated_at' => now()]
                );

                // 4. Guardar EUR/PEN (1 EUR = X/Y PEN)
                ExchangeRate::updateOrCreate(
                    ['currency' => 'EUR'],
                    ['exchange_rate' => round($eurToPenRate, 4), 'updated_at' => now()]
                );

                // 5. Limpiar caché para que Ecommerce use la nueva tasa inmediatamente
                Cache::forget('exchange_rate:USD');
                Cache::forget('exchange_rate:EUR');

                return [
                    'USD_PEN' => $usdToPenRate,
                    'EUR_PEN' => round($eurToPenRate, 4),
                ];
            });

            // 6. Retornar el resultado
            return response()->json([
                'message' => 'Tasas USD/PEN y EUR/PEN actualizadas exitosamente con Open Exchange Rates.',
                'rates' => [
                    'USD_PEN' => $updatedRates['USD_PEN'],
                    'EUR_PEN' => $updatedRates['EUR_PEN'],
                ],
                'updated_at' => now()->toDateTimeString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Fallo al actualizar la base de datos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint para ver las tasas actuales en la base de datos (verificación).
     * GET /api/rates/current
     */
    public function showCurrentRates(): JsonResponse
    {
        // Lee directamente de la BD (o caché)
        $usdRate = ExchangeRate::getRate('USD');
        $eurRate = ExchangeRate::getRate('EUR');

        return response()->json([
            'message' => 'Tasas de cambio actuales leídas de la base de datos.',
            'rates' => [
                'USD_PEN' => $usdRate,
                'EUR_PEN' => $eurRate,
            ],
            'updated_at' => ExchangeRate::where('currency', 'USD')->value('updated_at')?->toDateTimeString(),
            'note' => 'Tasa: 1 unidad de esta moneda = X PEN (valor en soles)',
        ]);
    }
}
