<?php
// database/factories/Supports/PurchaseFactory.php

namespace Database\Factories\Supports;

use App\Models\Entity;
use App\Models\Supports\Purchase;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseFactory extends Factory
{
    protected $model = Purchase::class;

    public function definition(): array
    {
        return [
            'supplier_id' => Entity::factory()->supplier(),
            'purchase_date' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'document_type' => '01', // Factura
            'document_number' => $this->faker->numerify('F###-########'),
            'subtotal' => 0, // Se calculará con batches
            'tax_amount' => 0,
            'total_amount' => 0,
            'status' => 'completed',
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
