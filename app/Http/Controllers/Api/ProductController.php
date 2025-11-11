<?php
// app/Http/Controllers/Api/ProductController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Products\StoreProductRequest;
use App\Http\Requests\Products\UpdateProductRequest;
use App\Http\Requests\Products\BulkUpdateProductsRequest;
use App\Http\Requests\Products\UploadProductImagesRequest;
use App\Http\Resources\Products\ProductResource;
use App\Services\ProductService;
use App\Models\Product;
use App\Exceptions\Products\ProductNotFoundException;
use App\Exceptions\Products\ProductAlreadyExistsException;
use App\Exceptions\Products\ProductInUseException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function __construct(
        private ProductService $productService
    ) {
    }

    /**
     * Listar productos
     */
    public function index(Request $request)
    {
        $filters = $request->only([
            'search',
            'category_id',
            'brand',
            'is_active',
            'is_featured',
            'visible_online',
            'warehouse_id',
            'with_stock',
            'low_stock',
            'sort_by',
            'sort_order',
            'with_trashed'
        ]);

        $perPage = $request->input('per_page', 15);
        $products = $this->productService->getFiltered($filters, $perPage);

        return ProductResource::collection($products);
    }

    /**
     * Crear un nuevo producto (SIN precios - se asignarán con compras)
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        try {
            $product = $this->productService->create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Producto creado exitosamente. Los precios se asignarán al registrar compras.',
                'data' => new ProductResource($product->fresh(['attributes', 'category', 'media'])), // ✅ AGREGADO
            ], 201);

        } catch (ProductAlreadyExistsException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => ['sku' => [$e->getMessage()]],
            ], 409);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el producto',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ver detalles de un producto
     */
    public function show(Product $product, Request $request): JsonResponse
    {
        $product->load(['media', 'category', 'attributes']); // ✅ AGREGADO 'attributes'

        return response()->json([
            'success' => true,
            'data' => new ProductResource($product),
        ]);
    }

    /**
     * Actualizar un producto
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        try {
            $updatedProduct = $this->productService->updateProduct($product->id, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Producto actualizado exitosamente',
                'data' => new ProductResource($updatedProduct),
            ]);

        } catch (ProductAlreadyExistsException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el producto',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar un producto (soft delete)
     */
    public function destroy(Product $product): JsonResponse
    {
        try {
            $this->productService->delete($product);

            return response()->json([
                'success' => true,
                'message' => 'Producto eliminado exitosamente',
            ]);

        } catch (ProductInUseException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el producto',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Restaurar un producto eliminado
     */
    public function restore(int $id): JsonResponse
    {
        try {
            $product = $this->productService->restore($id);

            return response()->json([
                'success' => true,
                'message' => 'Producto restaurado exitosamente',
                'data' => new ProductResource($product),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al restaurar el producto',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualización masiva
     */
    public function bulkUpdate(BulkUpdateProductsRequest $request): JsonResponse
    {
        try {
            $count = $this->productService->bulkUpdate(
                $request->product_ids,
                $request->action
            );

            return response()->json([
                'success' => true,
                'message' => "{$count} productos actualizados exitosamente",
                'count' => $count,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la actualización masiva',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Estadísticas
     */
    public function statistics(): JsonResponse
    {
        $stats = $this->productService->getStatistics();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Duplicar producto
     */
    public function duplicate(Product $product): JsonResponse
    {
        try {
            $newProduct = $this->productService->duplicate($product);

            return response()->json([
                'success' => true,
                'message' => 'Producto duplicado exitosamente',
                'data' => new ProductResource($newProduct),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al duplicar el producto',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Subir imágenes a un producto
     */
    public function uploadImages(UploadProductImagesRequest $request, Product $product): JsonResponse
    {
        try {
            $files = $request->file('images');

            if (!$files || !is_array($files)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se recibieron archivos válidos',
                ], 422);
            }

            $images = $this->productService->uploadImages($product, $files);

            return response()->json([
                'success' => true,
                'message' => 'Imágenes subidas exitosamente',
                'images' => $images,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Eliminar una imagen específica
     */
    public function deleteImage(Product $product, int $mediaId): JsonResponse
    {
        try {
            $this->productService->deleteImage($product, $mediaId);

            return response()->json([
                'success' => true,
                'message' => 'Imagen eliminada exitosamente',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }
}
