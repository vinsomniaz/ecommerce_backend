<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupplierImport;
use App\Http\Resources\SupplierImportResource;
use App\Http\Resources\SupplierImportCollection;
use App\Services\SupplierImportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SupplierImportController extends Controller
{
    public function __construct(
        protected SupplierImportService $importService
    ) {}

    /**
     * Listar importaciones (usando Service y Collection)
     */
    public function index(Request $request): JsonResponse
    {
        $imports = $this->importService->getImports($request);
        $collection = new SupplierImportCollection($imports);

        return response()->json([
            'success' => true,
            'message' => 'Importaciones obtenidas correctamente',
            'data' => $collection->toArray($request)['data'],
            'meta' => $collection->with($request)['meta'],
        ]);
    }

    /**
     * Ver detalle de importación
     */
    public function show(SupplierImport $import): JsonResponse
    {
        $import->load('supplier');

        return response()->json([
            'success' => true,
            'data' => new SupplierImportResource($import),
        ]);
    }

    /**
     * Reprocesar importación fallida
     */
    public function reprocess(SupplierImport $import): JsonResponse
    {
        try {
            $this->importService->retryImport($import);

            return response()->json([
                'success' => true,
                'message' => 'Importación reencolada para reprocesamiento',
                'data' => [
                    'import_id' => $import->id,
                    'status' => 'pending',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Estadísticas de importaciones
     */
    public function statistics(Request $request): JsonResponse
    {
        $stats = $this->importService->getStatistics($request->supplier_id);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Eliminar importación
     */
    public function destroy(SupplierImport $import): JsonResponse
    {
        // Solo permitir eliminar importaciones fallidas o completadas
        if (in_array($import->status, ['pending', 'processing'])) {
            return response()->json([
                'success' => false,
                'message' => 'No se pueden eliminar importaciones en proceso',
            ], 422);
        }

        $import->delete();

        return response()->json([
            'success' => true,
            'message' => 'Importación eliminada exitosamente',
        ]);
    }

    /**
     * Ver items de una importación
     */
    public function items(SupplierImport $import): JsonResponse
    {
        $items = $import->raw_data['items'] ?? [];

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'total' => count($items),
            ],
        ]);
    }
}
