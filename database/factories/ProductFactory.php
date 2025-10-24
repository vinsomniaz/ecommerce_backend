<?php
// database/factories/ProductFactory.php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sku' => 'PRD-' . $this->faker->unique()->numerify('######'),
            'primary_name' => $this->faker->words(3, true),
            'secondary_name' => $this->faker->optional()->words(2, true),
            'description' => $this->faker->optional()->paragraph(),
            'category_id' => Category::factory(),
            'brand' => $this->faker->optional()->company(),
            'unit_price' => $this->faker->randomFloat(2, 10, 1000),
            'cost_price' => $this->faker->randomFloat(2, 5, 500),
            'min_stock' => $this->faker->numberBetween(5, 50),
            'unit_measure' => 'NIU',
            'tax_type' => '10',
            'weight' => $this->faker->optional()->randomFloat(2, 0.1, 100),
            'is_active' => $this->faker->boolean(80),
            'is_featured' => $this->faker->boolean(20),
            'visible_online' => $this->faker->boolean(90),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }
}
