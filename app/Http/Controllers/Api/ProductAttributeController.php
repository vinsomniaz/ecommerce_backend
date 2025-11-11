<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Supports\ProductAttribute;
use DB;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductAttributeController extends Controller
{
    /**
     * Listar atributos de un producto
     */
    public function index(Product $product): JsonResponse
    {
        $attributes = $product->attributes;

        return response()->json([
            'success' => true,
            'data' => $attributes,
        ]);
    }

    /**
     * Agregar un atributo a un producto
     */
    public function store(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'value' => 'required|string|max:200',
        ]);

        $attribute = $product->attributes()->create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Atributo agregado exitosamente',
            'data' => $attribute,
        ], 201);
    }

    /**
     * Actualizar un atributo
     */
    public function update(Request $request, Product $product, ProductAttribute $attribute): JsonResponse
    {
        // Verificar que el atributo pertenece al producto
        if ($attribute->product_id !== $product->id) {
            return response()->json([
                'success' => false,
                'message' => 'El atributo no pertenece a este producto',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'value' => 'required|string|max:200',
        ]);

        $attribute->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Atributo actualizado exitosamente',
            'data' => $attribute,
        ]);
    }

    /**
     * Eliminar un atributo
     */
    public function destroy(Product $product, ProductAttribute $attribute): JsonResponse
    {
        if ($attribute->product_id !== $product->id) {
            return response()->json([
                'success' => false,
                'message' => 'El atributo no pertenece a este producto',
            ], 404);
        }

        $attribute->delete();

        return response()->json([
            'success' => true,
            'message' => 'Atributo eliminado exitosamente',
        ]);
    }

    /**
     * Actualizar múltiples atributos a la vez
     */
    public function bulkUpdate(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'attributes' => 'required|array',
            'attributes.*.id' => 'nullable|exists:product_attributes,id',
            'attributes.*.name' => 'required|string|max:50',
            'attributes.*.value' => 'required|string|max:200',
        ]);

        DB::beginTransaction();
        try {
            foreach ($validated['attributes'] as $attrData) {
                if (!empty($attrData['id'])) {
                    // Actualizar existente
                    $attr = ProductAttribute::find($attrData['id']);
                    if ($attr && $attr->product_id === $product->id) {
                        $attr->update([
                            'name' => $attrData['name'],
                            'value' => $attrData['value'],
                        ]);
                    }
                } else {
                    // Crear nuevo
                    $product->attributes()->create([
                        'name' => $attrData['name'],
                        'value' => $attrData['value'],
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Atributos actualizados exitosamente',
                'data' => $product->fresh(['attributes']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar atributos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
