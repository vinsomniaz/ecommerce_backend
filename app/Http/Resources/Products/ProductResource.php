<?php
// app/Http/Resources/Products/ProductResource.php

namespace App\Http\Resources\Products;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Obtener warehouse_id del request si existe
        $warehouseId = $request->input('warehouse_id');

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'primary_name' => $this->primary_name,
            'secondary_name' => $this->secondary_name,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'brand' => $this->brand,
            'unit_measure' => $this->unit_measure,
            'tax_type' => $this->tax_type,
            'min_stock' => $this->min_stock,
            'weight' => $this->weight,
            'barcode' => $this->barcode,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'visible_online' => $this->visible_online,

            // ✅ Atributos calculados (vienen del modelo)
            'average_cost' => $this->average_cost,
            'total_stock' => $this->total_stock,

            // ✅ Precio de venta específico si se proporciona warehouse_id
            'sale_price' => $warehouseId
                ? $this->getSalePriceForWarehouse($warehouseId)
                : null,

            // ✅ Información de inventario por almacén
            'warehouse_prices' => $this->when(
                $request->input('include_warehouses'),
                fn() => $this->getWarehousePricesFormatted()
            ),

            // ✅ Lotes activos
            'active_batches' => $this->when(
                $request->input('include_batches'),
                fn() => $this->getActiveBatchesFormatted()
            ),

            // ✅ Imágenes con todas las conversiones
            'images' => $this->getImagesFormatted(),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }

    /**
     * Obtener precios por almacén formateados
     */
    private function getWarehousePricesFormatted(): array
    {
        return $this->inventory()
            ->with('warehouse:id,name')
            ->get()
            ->map(fn($inv) => [
                'warehouse_id' => $inv->warehouse_id,
                'warehouse_name' => $inv->warehouse->name,
                'available_stock' => $inv->available_stock,
                'reserved_stock' => $inv->reserved_stock,
                'sale_price' => $inv->sale_price,
                'profit_margin' => $inv->profit_margin,
            ])
            ->toArray();
    }

    /**
     * Obtener lotes activos formateados
     */
    private function getActiveBatchesFormatted(): array
    {
        return $this->purchaseBatches()
            ->where('status', 'active')
            ->where('quantity_available', '>', 0)
            ->get()
            ->map(fn($batch) => [
                'id' => $batch->id,
                'batch_code' => $batch->batch_code,
                'warehouse_id' => $batch->warehouse_id,
                'quantity_available' => $batch->quantity_available,
                'purchase_price' => $batch->purchase_price,
                'distribution_price' => $batch->distribution_price,
                'purchase_date' => $batch->purchase_date?->format('Y-m-d'),
                'expiry_date' => $batch->expiry_date?->format('Y-m-d'),
            ])
            ->toArray();
    }

    /**
     * Obtener imágenes formateadas
     */
    private function getImagesFormatted(): array
    {
        return $this->getMedia('images')->map(function ($media) {
            return [
                'id' => $media->id,
                'name' => $media->file_name,
                'original_url' => $media->getUrl(),
                'thumb_url' => $media->getUrl('thumb'),
                'medium_url' => $media->getUrl('medium'),
                'large_url' => $media->getUrl('large'),
                'size' => $this->formatBytes($media->size),
                'order' => $media->getCustomProperty('order', 0),
                'is_primary' => $media->getCustomProperty('is_primary', false),
            ];
        })->toArray();
    }

    /**
     * Formatear bytes en formato legible
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
