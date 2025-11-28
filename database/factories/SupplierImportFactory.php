<?php

namespace Database\Factories;

use App\Models\SupplierImport;
use App\Models\Entity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SupplierImport>
 */
class SupplierImportFactory extends Factory
{
    protected $model = SupplierImport::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalProducts = $this->faker->numberBetween(10, 500);
        $processedProducts = $this->faker->numberBetween(0, $totalProducts);
        $updatedProducts = $this->faker->numberBetween(0, $processedProducts);
        $newProducts = $processedProducts - $updatedProducts;

        return [
            'supplier_id' => Entity::factory()->supplier(),
            
            // Datos crudos de la importación
            'raw_data' => json_encode($this->generateSampleProducts($totalProducts)),
            
            // Estado
            'status' => $this->faker->randomElement(['pending', 'processing', 'completed', 'failed']),
            
            // Contadores
            'total_products' => $totalProducts,
            'processed_products' => $processedProducts,
            'updated_products' => $updatedProducts,
            'new_products' => $newProducts,
            'failed_products' => 0,
            
            // Errores
            'error_message' => null,
            
            // Timestamps
            'processed_at' => $this->faker->optional(0.7)->dateTimeBetween('-1 month', 'now'),
            'created_at' => now()->subDays($this->faker->numberBetween(0, 30)),
            'updated_at' => now()->subDays($this->faker->numberBetween(0, 30)),
        ];
    }

    /**
     * Indicate that the import is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'processed_products' => 0,
            'updated_products' => 0,
            'new_products' => 0,
            'processed_at' => null,
            'error_message' => null,
        ]);
    }

    /**
     * Indicate that the import is processing.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'processed_products' => $this->faker->numberBetween(1, $attributes['total_products'] / 2),
            'processed_at' => null,
            'error_message' => null,
        ]);
    }

    /**
     * Indicate that the import is completed.
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $totalProducts = $attributes['total_products'];
            $updatedProducts = $this->faker->numberBetween(0, $totalProducts);
            $newProducts = $totalProducts - $updatedProducts;
            
            return [
                'status' => 'completed',
                'processed_products' => $totalProducts,
                'updated_products' => $updatedProducts,
                'new_products' => $newProducts,
                'failed_products' => 0,
                'processed_at' => now()->subHours($this->faker->numberBetween(1, 48)),
                'error_message' => null,
            ];
        });
    }

    /**
     * Indicate that the import has failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'processed_products' => $this->faker->numberBetween(0, $attributes['total_products'] / 2),
            'processed_at' => now()->subHours($this->faker->numberBetween(1, 24)),
            'error_message' => $this->faker->randomElement([
                'Connection timeout',
                'Invalid JSON format',
                'Database connection lost',
                'Memory limit exceeded',
                'Product not found',
            ]),
        ]);
    }

    /**
     * Set a specific supplier by name.
     */
    public function forSupplier(string $supplierName): static
    {
        return $this->state(function (array $attributes) use ($supplierName) {
            $supplier = Entity::factory()->supplier()->create([
                'business_name' => $supplierName,
                'trade_name' => strtolower($supplierName),
            ]);
            
            return [
                'supplier_id' => $supplier->id,
            ];
        });
    }

    /**
     * Set specific product count.
     */
    public function withProductCount(int $count): static
    {
        return $this->state(fn (array $attributes) => [
            'total_products' => $count,
            'raw_data' => json_encode($this->generateSampleProducts($count)),
        ]);
    }

    /**
     * Generate sample products data for raw_data.
     */
    private function generateSampleProducts(int $count): array
    {
        $products = [];
        
        for ($i = 0; $i < min($count, 100); $i++) { // Limitar a 100 en el JSON
            $products[] = [
                'supplier_sku' => strtoupper($this->faker->bothify('???-####')),
                'name' => $this->faker->words(3, true),
                'price' => $this->faker->randomFloat(2, 10, 5000),
                'stock' => $this->faker->numberBetween(0, 500),
                'currency' => $this->faker->randomElement(['PEN', 'USD']),
                'url' => $this->faker->optional(0.7)->url(),
                'brand' => $this->faker->optional(0.8)->company(),
                'category' => $this->faker->optional(0.6)->word(),
            ];
        }
        
        return $products;
    }

    /**
     * Create import with custom raw data.
     */
    public function withRawData(array $products): static
    {
        return $this->state(fn (array $attributes) => [
            'raw_data' => json_encode($products),
            'total_products' => count($products),
        ]);
    }

    /**
     * Create import with all products processed successfully.
     */
    public function fullyProcessed(): static
    {
        return $this->state(function (array $attributes) {
            $totalProducts = $attributes['total_products'];
            
            return [
                'status' => 'completed',
                'processed_products' => $totalProducts,
                'updated_products' => (int)($totalProducts * 0.7), // 70% actualizados
                'new_products' => (int)($totalProducts * 0.3), // 30% nuevos
                'failed_products' => 0,
                'processed_at' => now()->subHours($this->faker->numberBetween(1, 12)),
                'error_message' => null,
            ];
        });
    }

    /**
     * Create import with partial processing.
     */
    public function partiallyProcessed(): static
    {
        return $this->state(function (array $attributes) {
            $totalProducts = $attributes['total_products'];
            $processedProducts = (int)($totalProducts * 0.5); // Solo 50% procesado
            
            return [
                'status' => 'processing',
                'processed_products' => $processedProducts,
                'updated_products' => (int)($processedProducts * 0.6),
                'new_products' => (int)($processedProducts * 0.4),
                'failed_products' => 0,
                'processed_at' => null,
                'error_message' => null,
            ];
        });
    }
}