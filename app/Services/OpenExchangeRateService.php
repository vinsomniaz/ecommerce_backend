<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenExchangeRateService
{
    private const API_BASE_URL = 'https://openexchangerates.org/api/latest.json';
    // 🔥 USANDO EL APP ID PROPORCIONADO POR EL USUARIO
    private const APP_ID = '63ce6480cde54240b8eb2283083422ee';

    /**
     * Consulta las tasas de cambio de Open Exchange Rates (Base: USD).
     * @return array|null [PEN => float, EUR => float] o null en caso de error.
     */
    public function fetchUsdBasedRates(): ?array
    {
        try {
            $response = Http::get(self::API_BASE_URL, [
                'app_id' => self::APP_ID,
                // Solicitamos PEN y EUR para realizar el cálculo localmente
                'symbols' => 'PEN,EUR' 
            ]);

            if ($response->failed() || !isset($response->json()['rates'])) {
                $error = $response->json()['description'] ?? 'API no devolvió tasas.';
                Log::error('Open Exchange Rates API Error', ['status' => $response->status(), 'error' => $error]);
                return null;
            }

            $rates = $response->json()['rates'];
            
            if (!isset($rates['PEN']) || !isset($rates['EUR'])) {
                 Log::error('Open Exchange Rates: Faltan símbolos requeridos PEN o EUR en la respuesta.');
                 return null;
            }
            
            return [
                'PEN' => (float) $rates['PEN'], // 1 USD = X PEN
                'EUR' => (float) $rates['EUR'], // 1 USD = Y EUR
            ];

        } catch (\Exception $e) {
            Log::error('Open Exchange Rates Connection Error: ' . $e->getMessage());
            return null;
        }
    }
}