<?php

namespace App\Services\Adapters;

use App\Contracts\DocumentValidationInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\ConnectionException;

class DecolectaAdapter implements DocumentValidationInterface
{
    protected ?string $token;
    protected ?string $baseUrl;

    public function __construct()
    {
        $this->token = config('services.decolecta.token');
        $this->baseUrl = config('services.decolecta.base_url');
    }

    /**
     * Validate a DNI using Decolecta RENIEC API.
     */
    public function validateDni(string $numero): array
    {
        try {
            if (!$this->token) {
                Log::error('DECOLECTA_TOKEN no está configurado.');
                return ['success' => false, 'status' => 503, 'message' => 'Servicio no configurado.'];
            }

            $apiUrl = "{$this->baseUrl}/v1/reniec/dni?numero={$numero}";
            
            $response = Http::timeout(8)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->token}",
                    'Content-Type' => 'application/json',
                ])
                ->get($apiUrl);

            if ($response->successful()) {
                $data = $response->json();
                return $this->formatDniResponse($data);
            }

            if ($response->status() == 404) {
                return ['success' => false, 'status' => 404, 'message' => 'Documento no encontrado.'];
            }

            // Handle error responses
            $errorData = $response->json();
            $message = $errorData['error'] ?? $errorData['message'] ?? 'No se pudo validar el documento.';
            
            return ['success' => false, 'status' => $response->status(), 'message' => $message];
        } catch (ConnectionException $e) {
            Log::error("Error de conexión validando DNI {$numero} con Decolecta: " . $e->getMessage());
            return ['success' => false, 'status' => 503, 'message' => 'Servicio no disponible. Puede continuar con el registro manual.'];
        } catch (\Exception $e) {
            Log::error("Error general validando DNI {$numero} con Decolecta: " . $e->getMessage());
            return ['success' => false, 'status' => 500, 'message' => 'Ocurrió un error inesperado.'];
        }
    }

    /**
     * Validate a RUC using Decolecta SUNAT API.
     */
    public function validateRuc(string $numero, bool $advanced = false): array
    {
        try {
            if (!$this->token) {
                Log::error('DECOLECTA_TOKEN no está configurado.');
                return ['success' => false, 'status' => 503, 'message' => 'Servicio no configurado.'];
            }

            // Use advanced endpoint if requested
            $endpoint = $advanced ? "/v1/sunat/ruc/full" : "/v1/sunat/ruc";
            $apiUrl = "{$this->baseUrl}{$endpoint}?numero={$numero}";
            
            $response = Http::timeout(8)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->token}",
                    'Content-Type' => 'application/json',
                ])
                ->get($apiUrl);

            if ($response->successful()) {
                $data = $response->json();
                return $this->formatRucResponse($data, $advanced);
            }

            if ($response->status() == 404) {
                return ['success' => false, 'status' => 404, 'message' => 'Documento no encontrado.'];
            }

            // Handle error responses
            $errorData = $response->json();
            $message = $errorData['error'] ?? $errorData['message'] ?? 'No se pudo validar el documento.';
            
            return ['success' => false, 'status' => $response->status(), 'message' => $message];
        } catch (ConnectionException $e) {
            Log::error("Error de conexión validando RUC {$numero} con Decolecta: " . $e->getMessage());
            return ['success' => false, 'status' => 503, 'message' => 'Servicio no disponible. Puede continuar con el registro manual.'];
        } catch (\Exception $e) {
            Log::error("Error general validando RUC {$numero} con Decolecta: " . $e->getMessage());
            return ['success' => false, 'status' => 500, 'message' => 'Ocurrió un error inesperado.'];
        }
    }

    /**
     * Format DNI response from Decolecta to standard format.
     */
    private function formatDniResponse(array $data): array
    {
        return [
            'success' => true,
            'data' => [
                'tipo_documento' => '01',
                'numero_documento' => $data['document_number'] ?? null,
                'nombres' => $data['first_name'] ?? null,
                'apellido_paterno' => $data['first_last_name'] ?? null,
                'apellido_materno' => $data['second_last_name'] ?? null,
                'apellidos' => trim(($data['first_last_name'] ?? '') . ' ' . ($data['second_last_name'] ?? '')),
                'nombre_completo' => $data['full_name'] ?? trim(
                    ($data['first_name'] ?? '') . ' ' . 
                    ($data['first_last_name'] ?? '') . ' ' . 
                    ($data['second_last_name'] ?? '')
                )
            ]
        ];
    }

    /**
     * Format RUC response from Decolecta to standard format.
     */
    private function formatRucResponse(array $data, bool $advanced = false): array
    {
        $formattedData = [
            'success' => true,
            'data' => [
                'tipo_documento' => '06',
                'numero_documento' => $data['numero_documento'] ?? null,
                'razon_social' => $data['razon_social'] ?? null,
                'nombre_comercial' => $data['nombre_comercial'] ?? null,
                'direccion' => $data['direccion'] ?? null,
                'ubigeo' => $data['ubigeo'] ?? null,
                'departamento' => $data['departamento'] ?? null,
                'provincia' => $data['provincia'] ?? null,
                'distrito' => $data['distrito'] ?? null,
                'estado_sunat' => $data['estado'] ?? null,
                'condicion_sunat' => $data['condicion'] ?? null,
            ]
        ];

        // Add extra fields from Decolecta if available
        if (isset($data['es_agente_retencion'])) {
            $formattedData['data']['es_agente_retencion'] = $data['es_agente_retencion'];
        }
        if (isset($data['es_buen_contribuyente'])) {
            $formattedData['data']['es_buen_contribuyente'] = $data['es_buen_contribuyente'];
        }

        // Add advanced fields if requested
        if ($advanced) {
            if (isset($data['tipo'])) {
                $formattedData['data']['tipo_empresa'] = $data['tipo'];
            }
            if (isset($data['actividad_economica'])) {
                $formattedData['data']['actividad_economica'] = $data['actividad_economica'];
            }
            if (isset($data['numero_trabajadores'])) {
                $formattedData['data']['numero_trabajadores'] = $data['numero_trabajadores'];
            }
            if (isset($data['tipo_facturacion'])) {
                $formattedData['data']['tipo_facturacion'] = $data['tipo_facturacion'];
            }
            if (isset($data['tipo_contabilidad'])) {
                $formattedData['data']['tipo_contabilidad'] = $data['tipo_contabilidad'];
            }
            if (isset($data['comercio_exterior'])) {
                $formattedData['data']['comercio_exterior'] = $data['comercio_exterior'];
            }
        }

        return $formattedData;
    }
}
