<?php

namespace App\Observers;

use App\Models\QuotationDetail;
use App\Exceptions\InsufficientStockException;

class QuotationDetailObserver
{
    public function creating(QuotationDetail $detail): void
    {
        $this->validateStock($detail);
    }
    
    public function updating(QuotationDetail $detail): void
    {
        if ($detail->isDirty('quantity')) {
            $this->validateStock($detail);
        }
    }
    
    private function validateStock(QuotationDetail $detail): void
    {
        if ($detail->source_type !== 'warehouse') {
            return;
        }
        
        $inventory = $detail->product->inventory()
            ->where('warehouse_id', $detail->warehouse_id)
            ->first();
        
        $availableStock = $inventory?->available_stock ?? 0;
        
        if ($detail->quantity > $availableStock) {
            throw new InsufficientStockException(
                "Stock insuficiente para {$detail->product_name}. Disponible: {$availableStock}",
                $detail->quantity,
                $availableStock
            );
        }
    }
}