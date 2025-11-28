<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupplierImport;
use App\Models\Entity;
use App\Jobs\ProcessSupplierImportJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SupplierImportController extends Controller
{
    /**
     * Endpoint público para scrapers
     */
    public function import(Request $request, string $slug): JsonResponse
    {
        $supplier = Entity::where('type', 'supplier')
            ->where(function($q) use ($slug) {
                $q->whereRaw("LOWER(trade_name) = ?", [strtolower($slug)])
                  ->orWhereRaw("LOWER(business_name) = ?", [strtolower($slug)]);
            })
            ->first();

        if (!$supplier) {
            return response()->json([
                'error' => 'supplier_not_found',
                'message' => "Proveedor '{$slug}' no encontrado",
            ], 404);
        }

        $validated = $request->validate([
            'products' => 'required|array|min:1',
            'products.*.supplier_sku' => 'required|string',
            'products.*.name' => 'required|string',
            'products.*.price' => 'required|numeric|min:0',
            'products.*.stock' => 'nullable|integer|min:0',
            'products.*.currency' => 'nullable|string|in:PEN,USD',
            'products.*.url' => 'nullable|url',
        ]);

        $import = SupplierImport::create([
            'supplier_id' => $supplier->id,
            'raw_data' => json_encode($validated['products']),
            'status' => 'pending',
            'total_products' => count($validated['products']),
        ]);

        // Despachar job para procesar en background
        ProcessSupplierImportJob::dispatch($import->id);

        return response()->json([
            'message' => 'Importación recibida exitosamente',
            'import_id' => $import->id,
            'total_products' => count($validated['products']),
            'status' => 'pending',
        ], 202);
    }

    public function index(Request $request): JsonResponse
    {
        $query = SupplierImport::with('supplier')
            ->when($request->supplier_id, fn($q) => $q->where('supplier_id', $request->supplier_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->latest();

        $imports = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'data' => $imports->items(),
            'meta' => [
                'current_page' => $imports->currentPage(),
                'last_page' => $imports->lastPage(),
                'per_page' => $imports->perPage(),
                'total' => $imports->total(),
            ],
        ]);
    }

    public function show(SupplierImport $import): JsonResponse
    {
        $import->load('supplier');

        return response()->json([
            'data' => $import,
        ]);
    }

    public function reprocess(SupplierImport $import): JsonResponse
    {
        if ($import->status !== 'failed') {
            return response()->json([
                'error' => 'invalid_status',
                'message' => 'Solo se pueden reprocesar importaciones fallidas',
            ], 422);
        }

        $import->update(['status' => 'pending', 'error_message' => null]);
        ProcessSupplierImportJob::dispatch($import->id);

        return response()->json([
            'message' => 'Importación reencolada para reprocesamiento',
            'import_id' => $import->id,
        ]);
    }

    public function statistics(): JsonResponse
    {
        $stats = [
            'total_imports' => SupplierImport::count(),
            'pending' => SupplierImport::where('status', 'pending')->count(),
            'processing' => SupplierImport::where('status', 'processing')->count(),
            'completed' => SupplierImport::where('status', 'completed')->count(),
            'failed' => SupplierImport::where('status', 'failed')->count(),
            'total_products_imported' => SupplierImport::sum('total_products'),
            'total_products_processed' => SupplierImport::sum('processed_products'),
        ];

        return response()->json(['data' => $stats]);
    }
}