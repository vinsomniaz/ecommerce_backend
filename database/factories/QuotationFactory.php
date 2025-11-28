<?php

namespace Database\Factories;

use App\Models\Quotation;
use App\Models\User;
use App\Models\Entity;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Quotation>
 */
class QuotationFactory extends Factory
{
    protected $model = Quotation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = $this->faker->randomFloat(2, 500, 5000);
        $tax = $subtotal * 0.18;
        $total = $subtotal + $tax;
        
        $totalCost = $subtotal * 0.70; // Asumiendo 30% de margen
        $totalMargin = $total - $totalCost;
        $marginPercentage = ($totalMargin / $totalCost) * 100;

        return [
            'user_id' => User::factory(),
            'customer_id' => Entity::factory()->customer(),
            'warehouse_id' => Warehouse::factory(),
            
            'quotation_code' => $this->generateQuotationCode(),
            'quotation_date' => now(),
            'valid_until' => now()->addDays(15),
            
            'status' => $this->faker->randomElement(['draft', 'sent', 'accepted', 'rejected', 'expired']),
            
            // Snapshot del cliente
            'customer_name' => $this->faker->company(),
            'customer_document' => $this->faker->numerify('20#########'),
            'customer_email' => $this->faker->companyEmail(),
            'customer_phone' => $this->faker->phoneNumber(),
            
            // Moneda
            'currency' => 'PEN',
            'exchange_rate' => 1.0000,
            
            // Montos
            'subtotal' => $subtotal,
            'discount' => 0,
            'tax' => $tax,
            'total' => $total,
            
            // Costos adicionales
            'shipping_cost' => 0,
            'packaging_cost' => 0,
            'assembly_cost' => 0,
            
            // Márgenes
            'total_margin' => $totalMargin,
            'margin_percentage' => $marginPercentage,
            
            // Comisiones
            'commission_percentage' => $this->faker->randomFloat(2, 2, 5),
            'commission_amount' => $totalMargin * 0.03, // 3% de comisión
            'commission_paid' => false,
            
            // Observaciones
            'observations' => $this->faker->optional()->sentence(),
            'internal_notes' => $this->faker->optional()->sentence(),
            
            // PDF
            'pdf_path' => null,
            'sent_at' => null,
            'sent_to_email' => null,
            
            // Conversión
            'converted_at' => null,
            'converted_sale_id' => null,
        ];
    }

    /**
     * Indicate that the quotation is in draft status.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'sent_at' => null,
            'sent_to_email' => null,
        ]);
    }

    /**
     * Indicate that the quotation has been sent.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'sent',
            'sent_at' => now()->subDays($this->faker->numberBetween(1, 7)),
            'sent_to_email' => $this->faker->email(),
        ]);
    }

    /**
     * Indicate that the quotation has been accepted.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'accepted',
            'sent_at' => now()->subDays($this->faker->numberBetween(3, 10)),
            'sent_to_email' => $this->faker->email(),
        ]);
    }

    /**
     * Indicate that the quotation has been rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
        ]);
    }

    /**
     * Indicate that the quotation has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'valid_until' => now()->subDays($this->faker->numberBetween(1, 30)),
        ]);
    }

    /**
     * Indicate that the quotation has been converted to sale.
     */
    public function converted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'converted',
            'converted_at' => now()->subDays($this->faker->numberBetween(1, 5)),
            'converted_sale_id' => $this->faker->numberBetween(1, 1000),
        ]);
    }

    /**
     * Indicate that commission has been paid.
     */
    public function commissionPaid(): static
    {
        return $this->state(fn (array $attributes) => [
            'commission_paid' => true,
        ]);
    }

    /**
     * Generate a unique quotation code.
     */
    private function generateQuotationCode(): string
    {
        $year = now()->year;
        $number = $this->faker->unique()->numberBetween(1, 999999);
        return 'COT-' . $year . '-' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }
}