<?php

namespace Database\Factories;

use App\Models\Entity;
use App\Models\Ubigeo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Address>
 */
class AddressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Los tests de AddressApiTest ya crean el ubigeo '150103'
        // por lo que podemos usarlo como un valor por defecto seguro.
        
        return [
            'entity_id' => Entity::factory(),
            'ubigeo' => '150103', // Ubigeo por defecto (Lima/Lima/Lince)
            'address' => $this->faker->streetAddress(), // <-- ¡Esta es la corrección principal!
            'reference' => $this->faker->secondaryAddress(),
            'phone' => $this->faker->numerify('9########'),
            'label' => $this->faker->randomElement(['Casa', 'Oficina']),
            'is_default' => false,
        ];
    }
}