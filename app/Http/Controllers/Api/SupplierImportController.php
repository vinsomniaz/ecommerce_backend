<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupplierImport;
use App\Http\Requests\SupplierSyncRequest;
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
     * Listar importaciones
     */
    public function index(Request $request): JsonResponse
    {
        $query = SupplierImport::with('supplier')
            ->when($request->supplier_id, fn($q) => $q->where('supplier_id', $request->supplier_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->from_date, fn($q) => $q->whereDate('created_at', '>=', $request->from_date))
            ->when($request->to_date, fn($q) => $q->whereDate('created_at', '<=', $request->to_date))
            ->latest();

        $imports = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => SupplierImportResource::collection($imports),
            'meta' => [
                'current_page' => $imports->currentPage(),
                'last_page' => $imports->lastPage(),
                'per_page' => $imports->perPage(),
                'total' => $imports->total(),
            ],
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
        $rawData = is_string($import->raw_data)
            ? json_decode($import->raw_data, true)
            : $import->raw_data;

        $items = $rawData['items'] ?? [];

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'total' => count($items),
            ],
        ]);
    }
}
