<?php
namespace App\Services;

use App\Models\Quotation;

class CommissionCalculatorService
{
    public function __construct(
        private SettingService $settingService
    ) {}
    
    public function calculate(Quotation $quotation): array
    {
        // Base de cálculo configurada en settings
        $calculateOn = $this->settingService->get('commissions', 'calculate_on', 'margin');
        
        $baseAmount = match($calculateOn) {
            'margin' => $quotation->total_margin,
            'subtotal' => $quotation->subtotal,
            'total' => $quotation->total,
            default => $quotation->total_margin,
        };
        
        $commissionAmount = $baseAmount * ($quotation->commission_percentage / 100);
        
        return [
            'commission_amount' => round($commissionAmount, 2),
            'commission_percentage' => $quotation->commission_percentage,
            'calculated_on' => $calculateOn,
        ];
    }
}