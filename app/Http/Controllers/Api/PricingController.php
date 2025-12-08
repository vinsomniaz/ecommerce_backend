<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\PricingService;
use Illuminate\Http\Request;

class PricingController extends Controller
{
    public function __construct(
        private PricingService $pricingService
    ) {}

    public function updatePrices(Request $request, Product $product)
    {
        $validated = $request->validate([
            'new_sale_price' => 'required|numeric|min:0|max:999999.99',
        ]);

        $pricingStatus = $this->pricingService->recalculateProductAllWarehouses(
            $product->id,
            $validated['new_sale_price']
        );

        return response()->json([
            'success' => true,
            'message' => 'Precios actualizados correctamente en todos los almacenes.',
            'product' => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->primary_name,
            ],
            'pricing_status' => $pricingStatus,
        ], 200);
    }
}
