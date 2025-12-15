<?php

namespace App\Services;

use App\Contracts\DocumentValidationInterface;
use App\Services\Adapters\ApisPeruAdapter;
use App\Services\Adapters\DecolectaAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SunatService
{
    protected ?DocumentValidationInterface $adapter;

    public function __construct()
    {
        $this->adapter = $this->resolveAdapter();
    }

    /**
     * Resolve the appropriate adapter based on configuration.
     */
    private function resolveAdapter(): DocumentValidationInterface
    {
        $provider = config('services.document_validation.provider', 'decolecta');

        return match($provider) {
            'apisperu' => new ApisPeruAdapter(),
            'decolecta' => new DecolectaAdapter(),
            default => throw new \Exception("Invalid document validation provider: {$provider}. Use 'apisperu' or 'decolecta'.")
        };
    }

    /**
     * Validate a document (DNI or RUC) using the configured provider.
     */
    public function validateDocument(string $tipo, string $numero)
    {
        $cacheKey = "sunat_validation_{$tipo}_{$numero}";

        // Intentar obtener de la caché primero
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            // Delegate to the appropriate adapter method
            if ($tipo == '01') {
                $result = $this->adapter->validateDni($numero);
            } elseif ($tipo == '06') {
                $result = $this->adapter->validateRuc($numero);
            } else {
                return ['success' => false, 'status' => 400, 'message' => 'Tipo de documento no válido.'];
            }

            // Cache successful results for 30 days
            if ($result['success'] ?? false) {
                Cache::put($cacheKey, $result, now()->addDays(30));
            }

            return $result;
        } catch (\Exception $e) {
            Log::error("Error validando documento {$tipo}-{$numero}: " . $e->getMessage());
            return ['success' => false, 'status' => 500, 'message' => 'Ocurrió un error inesperado.'];
        }
    }

    /**
     * Validate RUC with advanced information (only available with Decolecta).
     */
    public function validateRucAdvanced(string $numero)
    {
        $cacheKey = "sunat_validation_ruc_advanced_{$numero}";

        // Intentar obtener de la caché primero
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $result = $this->adapter->validateRuc($numero, true);

            // Cache successful results for 30 days
            if ($result['success'] ?? false) {
                Cache::put($cacheKey, $result, now()->addDays(30));
            }

            return $result;
        } catch (\Exception $e) {
            Log::error("Error validando RUC avanzado {$numero}: " . $e->getMessage());
            return ['success' => false, 'status' => 500, 'message' => 'Ocurrió un error inesperado.'];
        }
    }
}
