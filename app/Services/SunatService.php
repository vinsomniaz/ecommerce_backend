<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\ConnectionException;

class SunatService
{
    public function validateDocument(string $tipo, string $numero)
    {
        $cacheKey = "sunat_validation_{$tipo}_{$numero}";

        // Intentar obtener de la caché primero
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $token = config('services.apisperu.token');
            if (!$token) {
                Log::error('APISPERU_TOKEN no está configurado.');
                return ['success' => false, 'status' => 503, 'message' => 'Servicio no configurado.'];
            }

            $apiUrl = $tipo == '01'
                ? "https://dniruc.apisperu.com/api/v1/dni/{$numero}?token={$token}"
                : "https://dniruc.apisperu.com/api/v1/ruc/{$numero}?token={$token}";

            $response = Http::timeout(8)->get($apiUrl);

            if ($response->successful()) {
                $data = $response->json();

                // Formatear la respuesta para que coincida con lo que el frontend espera
                $formattedData = $this->formatResponse($tipo, $data);

                // Guardar en caché por 30 días
                Cache::put($cacheKey, $formattedData, now()->addDays(30));

                return $formattedData;
            }

            // Manejo de errores comunes de la API
            if ($response->status() == 404) {
                return ['success' => false, 'status' => 404, 'message' => 'Documento no encontrado.'];
            }

            return ['success' => false, 'status' => $response->status(), 'message' => 'No se pudo validar el documento.'];
        } catch (ConnectionException $e) {
            Log::error("Error de conexión validando documento {$tipo}-{$numero}: " . $e->getMessage());
            return ['success' => false, 'status' => 503, 'message' => 'Servicio no disponible. Puede continuar con el registro manual.'];
        } catch (\Exception $e) {
            Log::error("Error general validando documento {$tipo}-{$numero}: " . $e->getMessage());
            return ['success' => false, 'status' => 500, 'message' => 'Ocurrió un error inesperado.'];
        }
    }

    private function formatResponse(string $tipo, array $data): array
    {
        if ($tipo == '01') { // DNI
            return [
                'success' => true,
                'data' => [
                    'tipo_documento' => '01',
                    'numero_documento' => $data['dni'] ?? null,
                    'nombres' => $data['nombres'] ?? null,
                    'apellido_paterno' => $data['apellidoPaterno'] ?? null,
                    'apellido_materno' => $data['apellidoMaterno'] ?? null,
                    'nombre_completo' => trim(($data['nombres'] ?? '') . ' ' . ($data['apellidoPaterno'] ?? '') . ' ' . ($data['apellidoMaterno'] ?? ''))
                ]
            ];
        }

        // RUC
        return [
            'success' => true,
            'data' => [
                'tipo_documento' => '06',
                'numero_documento' => $data['ruc'] ?? null,
                'razon_social' => $data['razonSocial'] ?? null,
                'nombre_comercial' => $data['nombreComercial'] ?? null,
                'direccion' => $data['direccion'] ?? null,
                'ubigeo' => $data['ubigeo'] ?? null,
                'departamento' => $data['departamento'] ?? null,
                'provincia' => $data['provincia'] ?? null,
                'distrito' => $data['distrito'] ?? null,
                'estado' => $data['estado'] ?? null,
                'condicion' => $data['condicion'] ?? null,
            ]
        ];
    }
}
