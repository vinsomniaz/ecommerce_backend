<?php

namespace App\Services;

use App\Models\Quotation;
use App\Models\QuotationDetail;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class QuotationService
{
    public function __construct(
        private SettingService $settingService,
        private MarginCalculatorService $marginCalculator,
        private CommissionCalculatorService $commissionCalculator
    ) {}
    
    public function create(array $data, User $user): Quotation
    {
        return DB::transaction(function() use ($data, $user) {
            // Crear cotización
            $quotation = Quotation::create([
                'user_id' => $user->id,
                'customer_id' => $data['customer_id'],
                'warehouse_id' => $data['warehouse_id'],
                'quotation_code' => $this->generateCode(),
                'quotation_date' => now(),
                'valid_until' => $this->calculateValidUntil($data['valid_days'] ?? null),
                'status' => 'draft',
                'currency' => $data['currency'] ?? 'PEN',
                'exchange_rate' => $data['exchange_rate'] ?? 1.0000,
                'commission_percentage' => $user->commission_percentage ?? 0,
                // Datos del cliente (snapshot)
                'customer_name' => $data['customer_name'],
                'customer_document' => $data['customer_document'],
                'customer_email' => $data['customer_email'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
            ]);
            
            // Agregar items
            if (isset($data['items'])) {
                foreach ($data['items'] as $item) {
                    $this->addItem($quotation, $item);
                }
            }
            
            // Calcular totales
            $this->recalculateTotals($quotation);
            
            return $quotation->fresh(['details', 'customer', 'user']);
        });
    }
    
    public function addItem(Quotation $quotation, array $itemData): QuotationDetail
    {
        $detail = $quotation->details()->create([
            'product_id' => $itemData['product_id'],
            'product_name' => $itemData['product_name'],
            'product_sku' => $itemData['product_sku'] ?? null,
            'product_brand' => $itemData['product_brand'] ?? null,
            'quantity' => $itemData['quantity'],
            'unit_price' => $itemData['unit_price'],
            'discount' => $itemData['discount'] ?? 0,
            'source_type' => $itemData['source_type'] ?? 'warehouse',
            'warehouse_id' => $itemData['warehouse_id'] ?? null,
            'supplier_id' => $itemData['supplier_id'] ?? null,
            'supplier_product_id' => $itemData['supplier_product_id'] ?? null,
            'is_requested_from_supplier' => $itemData['is_requested_from_supplier'] ?? false,
            'purchase_price' => $itemData['purchase_price'] ?? 0,
        ]);
        
        // Calcular márgenes
        $margins = $this->marginCalculator->calculate($detail);
        $detail->update($margins);
        
        // Calcular subtotal, tax, total del item
        $this->calculateItemTotals($detail);
        
        return $detail;
    }
    
    private function calculateItemTotals(QuotationDetail $detail): void
    {
        $subtotal = ($detail->unit_price * $detail->quantity) - $detail->discount;
        $taxAmount = $subtotal * 0.18; // IGV 18%
        $total = $subtotal + $taxAmount;
        
        $detail->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total,
        ]);
    }
    
    public function recalculateTotals(Quotation $quotation): void
    {
        $quotation->load('details');
        
        // Sumar totales de items
        $subtotal = $quotation->details->sum('subtotal');
        $tax = $quotation->details->sum('tax_amount');
        $total = $subtotal + $tax + 
                 ($quotation->shipping_cost ?? 0) + 
                 ($quotation->packaging_cost ?? 0) + 
                 ($quotation->assembly_cost ?? 0);
        
        // Calcular márgenes totales
        $margins = $this->marginCalculator->calculateQuotationTotalMargin($quotation->details);
        
        // Calcular comisiones
        $quotation->update([
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total,
            'total_margin' => $margins['total_margin'],
            'margin_percentage' => $margins['margin_percentage'],
        ]);
        
        $commission = $this->commissionCalculator->calculate($quotation->fresh());
        $quotation->update([
            'commission_amount' => $commission['commission_amount'],
        ]);
    }
    
    private function generateCode(): string
    {
        $prefix = 'COT';
        $year = now()->year;
        $lastNumber = Quotation::whereYear('created_at', $year)
            ->max('quotation_code');
        
        if ($lastNumber) {
            $number = (int) substr($lastNumber, -6) + 1;
        } else {
            $number = 1;
        }
        
        return $prefix . '-' . $year . '-' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }
    
    private function calculateValidUntil(?int $days): string
    {
        $validDays = $days ?? $this->settingService->get('quotations', 'default_validity_days', 15);
        return now()->addDays($validDays)->toDateString();
    }
}