<?php

namespace App\Services\Adapters;

use App\Contracts\DocumentValidationInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\ConnectionException;

class ApisPeruAdapter implements DocumentValidationInterface
{
    protected ?string $token;
    protected ?string $baseUrl;

    public function __construct()
    {
        $this->token = config('services.apisperu.token');
        $this->baseUrl = config('services.apisperu.base_url');
    }

    /**
     * Validate a DNI using apisPeru.
     */
    public function validateDni(string $numero): array
    {
        try {
            if (!$this->token) {
                Log::error('APISPERU_TOKEN no está configurado.');
                return ['success' => false, 'status' => 503, 'message' => 'Servicio no configurado.'];
            }

            $apiUrl = "{$this->baseUrl}/dni/{$numero}?token={$this->token}";
            $response = Http::timeout(8)->get($apiUrl);

            if ($response->successful()) {
                $data = $response->json();
                return $this->formatDniResponse($data);
            }

            if ($response->status() == 404) {
                return ['success' => false, 'status' => 404, 'message' => 'Documento no encontrado.'];
            }

            return ['success' => false, 'status' => $response->status(), 'message' => 'No se pudo validar el documento.'];
        } catch (ConnectionException $e) {
            Log::error("Error de conexión validando DNI {$numero}: " . $e->getMessage());
            return ['success' => false, 'status' => 503, 'message' => 'Servicio no disponible. Puede continuar con el registro manual.'];
        } catch (\Exception $e) {
            Log::error("Error general validando DNI {$numero}: " . $e->getMessage());
            return ['success' => false, 'status' => 500, 'message' => 'Ocurrió un error inesperado.'];
        }
    }

    /**
     * Validate a RUC using apisPeru.
     */
    public function validateRuc(string $numero, bool $advanced = false): array
    {
        try {
            if (!$this->token) {
                Log::error('APISPERU_TOKEN no está configurado.');
                return ['success' => false, 'status' => 503, 'message' => 'Servicio no configurado.'];
            }

            $apiUrl = "{$this->baseUrl}/ruc/{$numero}?token={$this->token}";
            $response = Http::timeout(8)->get($apiUrl);

            if ($response->successful()) {
                $data = $response->json();
                return $this->formatRucResponse($data);
            }

            if ($response->status() == 404) {
                return ['success' => false, 'status' => 404, 'message' => 'Documento no encontrado.'];
            }

            return ['success' => false, 'status' => $response->status(), 'message' => 'No se pudo validar el documento.'];
        } catch (ConnectionException $e) {
            Log::error("Error de conexión validando RUC {$numero}: " . $e->getMessage());
            return ['success' => false, 'status' => 503, 'message' => 'Servicio no disponible. Puede continuar con el registro manual.'];
        } catch (\Exception $e) {
            Log::error("Error general validando RUC {$numero}: " . $e->getMessage());
            return ['success' => false, 'status' => 500, 'message' => 'Ocurrió un error inesperado.'];
        }
    }

    /**
     * Format DNI response to standard format.
     */
    private function formatDniResponse(array $data): array
    {
        return [
            'success' => true,
            'data' => [
                'tipo_documento' => '01',
                'numero_documento' => $data['dni'] ?? null,
                'nombres' => $data['nombres'] ?? null,
                'apellido_paterno' => $data['apellidoPaterno'] ?? null,
                'apellido_materno' => $data['apellidoMaterno'] ?? null,
                'apellidos' => trim(($data['apellidoPaterno'] ?? '') . ' ' . ($data['apellidoMaterno'] ?? '')),
                'nombre_completo' => trim(($data['nombres'] ?? '') . ' ' . ($data['apellidoPaterno'] ?? '') . ' ' . ($data['apellidoMaterno'] ?? ''))
            ]
        ];
    }

    /**
     * Format RUC response to standard format.
     */
    private function formatRucResponse(array $data): array
    {
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
                'estado_sunat' => $data['estado'] ?? null,
                'condicion_sunat' => $data['condicion'] ?? null,
            ]
        ];
    }
}
