<?php

namespace App\Jobs;

use App\Models\SupplierImport;
use App\Services\SupplierImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessSupplierImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutos
    public $tries = 3;
    public $backoff = [60, 120, 300]; // Reintentos: 1min, 2min, 5min

    public function __construct(
        public int $importId
    ) {}

    public function handle(SupplierImportService $importService): void
    {
        $import = SupplierImport::find($this->importId);

        if (!$import) {
            Log::warning("SupplierImport {$this->importId} no encontrado");
            return;
        }

        try {
            $importService->processImport($import);
        } catch (\Exception $e) {
            Log::error("Error procesando SupplierImport {$this->importId}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // El servicio ya maneja el update del status a 'failed'
            throw $e;
        }
    }

    /**
     * Manejo de fallo del job
     */
    public function failed(\Throwable $exception): void
    {
        $import = SupplierImport::find($this->importId);

        if ($import) {
            $import->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'processed_at' => now(),
            ]);
        }

        Log::error("ProcessSupplierImportJob falló definitivamente", [
            'import_id' => $this->importId,
            'error' => $exception->getMessage(),
        ]);
    }
}
