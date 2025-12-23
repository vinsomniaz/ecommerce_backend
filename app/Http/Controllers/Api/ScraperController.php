<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SupplierSyncRequest;
use App\Services\SupplierImportService;
use App\Models\Entity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScraperController extends Controller
{
    public function __construct(
        protected SupplierImportService $importService
    ) {}

    /**
     * Endpoint legacy para scrapers: POST /api/scraper/import
     * 
     * Este endpoint permite identificar al supplier por slug en lugar de ID.
     * Útil para scrapers que no conocen el ID del supplier.
     */
    public function import(Request $request): JsonResponse
    {
        // Validar que venga el slug del supplier
        $request->validate([
            'supplier_slug' => 'required|string',
        ]);

        // Buscar supplier por slug (trade_name o business_name)
        $slug = $request->input('supplier_slug');

        $supplier = Entity::where('type', 'supplier')
            ->where(function ($q) use ($slug) {
                $q->whereRaw("LOWER(trade_name) = ?", [strtolower($slug)])
                    ->orWhereRaw("LOWER(business_name) = ?", [strtolower($slug)]);
            })
            ->first();

        if (!$supplier) {
            return response()->json([
                'success' => false,
                'message' => "Proveedor '{$slug}' no encontrado",
            ], 404);
        }

        // Agregar supplier_id al payload
        $payload = $request->all();
        $payload['supplier_id'] = $supplier->id;

        // Validar el payload completo
        $validator = validator($payload, (new SupplierSyncRequest())->rules());

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Procesar la sincronización
        try {
            $result = $this->importService->processSync($validator->validated());

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'import_id' => $result['import_id'],
                    'changed' => $result['changed'],
                    'stats' => $result['stats'] ?? null,
                ],
            ], $result['status']);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error procesando importación',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint moderno para scrapers: POST /api/scraper/sync/{supplierId}
     * 
     * Este endpoint permite identificar al supplier directamente por ID.
     * Más rápido y directo que buscar por slug.
     */
    public function syncById(int $supplierId, SupplierSyncRequest $request): JsonResponse
    {
        // Verificar que el supplier existe
        $supplier = Entity::where('type', 'supplier')
            ->where('id', $supplierId)
            ->first();

        if (!$supplier) {
            return response()->json([
                'success' => false,
                'message' => "Proveedor con ID {$supplierId} no encontrado",
            ], 404);
        }

        // Procesar la sincronización
        try {
            $result = $this->importService->processSync($request->validated());

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'import_id' => $result['import_id'],
                    'changed' => $result['changed'],
                    'stats' => $result['stats'] ?? null,
                ],
            ], $result['status']);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error procesando importación',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
