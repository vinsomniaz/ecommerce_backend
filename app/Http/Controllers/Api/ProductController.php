<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductService;
use App\Http\Requests\Products\StoreProductRequest;
use App\Http\Requests\Products\UpdateProductRequest;
use App\Http\Resources\Products\ProductResource;
use App\Http\Resources\Products\ProductListResource;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    protected $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    /**
     * Display a listing of products with filters and pagination
     */
    public function index(Request $request)
    {
        $query = Product::with(['category', 'inventory']);

        // Search
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        // Filters
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('brand')) {
            $query->where('brand', $request->brand);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('is_featured')) {
            $query->where('is_featured', $request->boolean('is_featured'));
        }

        if ($request->filled('visible_online')) {
            $query->where('visible_online', $request->boolean('visible_online'));
        }

        if ($request->filled('min_stock')) {
            $query->lowStock();
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 50);
        $products = $query->paginate($perPage);

        return ProductListResource::collection($products);
    }

    /**
     * Store a newly created product
     */
    public function store(StoreProductRequest $request)
    {
        try {
            $product = $this->productService->createProduct($request->validated());
            
            return response()->json([
                'message' => 'Producto creado exitosamente',
                'data' => new ProductResource($product),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear producto',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Display the specified product
     */
    public function show(Product $product)
    {
        $product->load(['category', 'attributes', 'inventory.warehouse', 'media']);
        
        return new ProductResource($product);
    }

    /**
     * Update the specified product
     */
    public function update(UpdateProductRequest $request, Product $product)
    {
        try {
            $product = $this->productService->updateProduct($product, $request->validated());
            
            return response()->json([
                'message' => 'Producto actualizado exitosamente',
                'data' => new ProductResource($product),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar producto',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Remove the specified product
     */
    public function destroy(Product $product)
    {
        try {
            $result = $this->productService->deleteProduct($product);
            
            if ($result['type'] === 'soft') {
                return response()->json([
                    'message' => $result['message'],
                ]);
            }
            
            return response()->noContent();
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar producto',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Upload images for product
     */
    public function uploadImages(Request $request, Product $product)
    {
        $request->validate([
            'images' => 'required|array|max:5',
            'images.*' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        try {
            $images = $this->productService->uploadImages($product, $request->file('images'));
            
            return response()->json([
                'message' => 'Imágenes subidas exitosamente',
                'images' => $images,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al subir imágenes',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Reorder product images
     */
    public function reorderImages(Request $request, Product $product)
    {
        $request->validate([
            'order' => 'required|array',
            'order.*' => 'required|integer|exists:media,id',
        ]);

        try {
            $this->productService->reorderImages($product, $request->order);
            
            return response()->json([
                'message' => 'Orden actualizado exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al reordenar imágenes',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Delete product image
     */
    public function deleteImage(Product $product, $mediaId)
    {
        try {
            $this->productService->deleteImage($product, $mediaId);
            
            return response()->json([
                'message' => 'Imagen eliminada exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar imagen',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Toggle product visibility
     */
    public function toggleVisibility(Request $request, Product $product)
    {
        $request->validate([
            'visible_online' => 'required|boolean',
        ]);

        try {
            $product = $this->productService->toggleVisibility($product, $request->visible_online);
            
            return response()->json([
                'message' => 'Visibilidad actualizada exitosamente',
                'data' => new ProductResource($product),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar visibilidad',
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}