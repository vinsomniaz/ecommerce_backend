<?php
// database/factories/ProductFactory.php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'sku' => 'PRD-' . $this->faker->unique()->numerify('######'),
            'primary_name' => $this->faker->words(3, true),
            'secondary_name' => $this->faker->optional()->words(2, true),
            'description' => $this->faker->optional()->paragraph(),
            'category_id' => Category::factory(),
            'brand' => $this->faker->optional()->company(),

            // ❌ REMOVIDOS: unit_price y cost_price (ahora vienen de lotes)

            'min_stock' => $this->faker->numberBetween(5, 50),
            'unit_measure' => $this->faker->randomElement(['NIU', 'KGM', 'UND', 'MTR', 'LTR']),
            'tax_type' => $this->faker->randomElement(['10', '18', '20']),
            'weight' => $this->faker->optional()->randomFloat(2, 0.1, 100),
            'barcode' => $this->faker->optional()->ean13(),

            // Estados
            'is_active' => $this->faker->boolean(80), // 80% activos
            'is_featured' => $this->faker->boolean(20), // 20% destacados
            'visible_online' => $this->faker->boolean(90), // 90% visibles online
        ];
    }

    /**
     * Producto activo
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Producto inactivo
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Producto destacado
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }

    /**
     * Producto no destacado
     */
    public function notFeatured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => false,
        ]);
    }

    /**
     * Producto visible online
     */
    public function visibleOnline(): static
    {
        return $this->state(fn (array $attributes) => [
            'visible_online' => true,
        ]);
    }

    /**
     * Producto oculto online
     */
    public function hiddenOnline(): static
    {
        return $this->state(fn (array $attributes) => [
            'visible_online' => false,
        ]);
    }

    /**
     * Producto con stock bajo
     */
    public function lowStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'min_stock' => 50,
        ]);
    }

    /**
     * Producto con todos los campos
     */
    public function complete(): static
    {
        return $this->state(fn (array $attributes) => [
            'secondary_name' => $this->faker->words(2, true),
            'description' => $this->faker->paragraph(),
            'brand' => $this->faker->company(),
            'weight' => $this->faker->randomFloat(2, 0.1, 100),
            'barcode' => $this->faker->ean13(),
        ]);
    }

    /**
     * Producto con SKU específico
     */
    public function withSku(string $sku): static
    {
        return $this->state(fn (array $attributes) => [
            'sku' => $sku,
        ]);
    }

    /**
     * Producto con nombre específico
     */
    public function withName(string $name): static
    {
        return $this->state(fn (array $attributes) => [
            'primary_name' => $name,
        ]);
    }

    /**
     * Producto con marca específica
     */
    public function withBrand(string $brand): static
    {
        return $this->state(fn (array $attributes) => [
            'brand' => $brand,
        ]);
    }

    /**
     * Producto con categoría específica
     */
    public function forCategory(Category $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $category->id,
        ]);
    }
}
