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
}