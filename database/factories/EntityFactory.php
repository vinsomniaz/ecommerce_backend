<?php

namespace Database\Factories;

use App\Models\Entity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Entity>
 */
class EntityFactory extends Factory
{
    protected $model = Entity::class;

    public function definition(): array
    {
        $tipoPersona = $this->faker->randomElement(['natural', 'juridica']);
        $isNatural = $tipoPersona === 'natural';

        // Simular DNI o RUC
        $tipoDoc = $isNatural ? '01' : '06';
        $numDoc = $isNatural ? $this->faker->numerify('########') : '20' . $this->faker->numerify('#########');

        return [
            'type' => 'customer',
            'tipo_documento' => $tipoDoc,
            'numero_documento' => $numDoc,
            'tipo_persona' => $tipoPersona,
            'first_name' => $isNatural ? $this->faker->firstName() : null,
            'last_name' => $isNatural ? $this->faker->lastName() : null,
            'business_name' => $isNatural ? null : $this->faker->company() . ' S.A.C.',
            'trade_name' => $isNatural ? null : $this->faker->company(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->numerify('9########'),
            'address' => $this->faker->address(),
            'ubigeo' => null, // Dejamos null para no depender de ubigeos
            'user_id' => User::factory(), // Crea un usuario asociado
            'is_active' => true,
            'registered_at' => now(),
        ];
    }

    /**
     * Indicate that the entity is a customer.
     */
    public function customer(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => 'customer',
        ]);
    }

    /**
     * Indicate that the entity is a supplier.
     */
    public function supplier(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => 'supplier',
            'tipo_persona' => 'juridica',
            'tipo_documento' => '06',
            'numero_documento' => $this->faker->numerify('20#########'),
            'business_name' => $this->faker->company() . ' ' . $this->faker->randomElement(['SAC', 'SRL', 'SA']),
            'trade_name' => $this->faker->company(),
            'first_name' => null,
            'last_name' => null,
        ]);
    }

    /**
     * Indicate that the entity is both customer and supplier.
     */
    public function both(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => 'both',
        ]);
    }

    /**
     * Indicate that the entity is a natural person.
     */
    public function naturalPerson(): static
    {
        return $this->state(fn(array $attributes) => [
            'tipo_persona' => 'natural',
            'tipo_documento' => '01',
            'numero_documento' => $this->faker->numerify('########'),
            'business_name' => null,
            'trade_name' => null,
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
        ]);
    }

    /**
     * Indicate that the entity is a legal person (company).
     */
    public function legalPerson(): static
    {
        return $this->state(fn(array $attributes) => [
            'tipo_persona' => 'juridica',
            'tipo_documento' => '06',
            'numero_documento' => $this->faker->numerify('20#########'),
            'business_name' => $this->faker->company() . ' ' . $this->faker->randomElement(['SAC', 'SRL', 'SA', 'EIRL']),
            'trade_name' => $this->faker->optional(0.7)->company(),
            'first_name' => null,
            'last_name' => null,
        ]);
    }

    /**
     * Indicate that the entity is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a specific supplier by name (for tests).
     */
    public function named(string $name): static
    {
        return $this->state(fn(array $attributes) => [
            'business_name' => $name,
            'trade_name' => strtolower($name),
        ]);
    }
}
