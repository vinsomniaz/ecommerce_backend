<?php

namespace Tests\Feature\Api;

use App\Models\Entity;
use App\Models\User;
use App\Models\Ubigeo;
use App\Models\Country;
use App\Models\Address;
use Database\Seeders\CountrySeeder;
use Database\Seeders\UbigeoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class EntityApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected string $ubigeoTestPeru;
    protected string $countryPeru = 'PE';
    protected string $countryUSA = 'US';

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->seed(CountrySeeder::class);

        $this->ubigeoTestPeru = '150101'; // Lima - Lima
        Ubigeo::factory()->create([
            'ubigeo' => $this->ubigeoTestPeru,
            'country_code' => $this->countryPeru,
            'departamento' => 'Lima',
            'provincia' => 'Lima',
            'distrito' => 'Lima',
        ]);
    }

    #[Test]
    public function puede_crear_una_entidad_cliente_persona_natural_para_peru_con_ubigeo()
    {
        $entityData = [
            'type' => 'customer',
            'tipo_documento' => '01',
            'numero_documento' => $this->faker->unique()->numerify('########'),
            'tipo_persona' => 'natural',
            'first_name' => 'Juan',
            'last_name' => 'Perez',
            'email' => $this->faker->unique()->safeEmail,
            'phone' => '987654321',
            'address' => 'Av. Peru 123',
            'country_code' => $this->countryPeru,
            'ubigeo' => $this->ubigeoTestPeru,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/entities', $entityData);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Cliente creado exitosamente')
            ->assertJsonPath('data.country_code', $this->countryPeru)
            ->assertJsonPath('data.ubigeo', $this->ubigeoTestPeru)
            ->assertJsonPath('data.full_name', 'Juan Perez');

        $this->assertDatabaseHas('entities', [
            'numero_documento' => $entityData['numero_documento'],
            'country_code' => $this->countryPeru,
            'ubigeo' => $this->ubigeoTestPeru,
            'user_id' => $this->user->id,
        ]);
    }

    #[Test]
    public function puede_crear_una_entidad_cliente_persona_juridica_para_peru_con_ubigeo()
    {
        $entityData = [
            'type' => 'customer',
            'tipo_documento' => '06',
            'numero_documento' => '20' . $this->faker->unique()->numerify('#########'),
            'tipo_persona' => 'juridica',
            'business_name' => 'Empresa SAC',
            'trade_name' => 'Nombre Comercial',
            'email' => $this->faker->unique()->safeEmail,
            'phone' => '999888777',
            'address' => 'Jr. Huallaga 456',
            'country_code' => $this->countryPeru,
            'ubigeo' => $this->ubigeoTestPeru,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/entities', $entityData);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Cliente creado exitosamente')
            ->assertJsonPath('data.country_code', $this->countryPeru)
            ->assertJsonPath('data.ubigeo', $this->ubigeoTestPeru)
            ->assertJsonPath('data.full_name', 'Empresa SAC');

        $this->assertDatabaseHas('entities', [
            'numero_documento' => $entityData['numero_documento'],
            'business_name' => 'Empresa SAC',
        ]);
    }

    #[Test]
    public function puede_crear_una_entidad_cliente_para_usa_persona_natural_sin_ubigeo()
    {
        $entityData = [
            'type' => 'customer',
            'tipo_documento' => '01',
            'numero_documento' => $this->faker->unique()->numerify('########'),
            'tipo_persona' => 'natural',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => $this->faker->unique()->safeEmail,
            'address' => '123 Main St',
            'country_code' => $this->countryUSA,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/entities', $entityData);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Cliente creado exitosamente')
            ->assertJsonPath('data.country_code', $this->countryUSA)
            ->assertJsonPath('data.ubigeo', null);

        $this->assertDatabaseHas('entities', [
            'numero_documento' => $entityData['numero_documento'],
            'country_code' => $this->countryUSA,
            'ubigeo' => null,
        ]);
    }

    #[Test]
    public function valida_que_ubigeo_es_obligatorio_para_peru_al_crear_si_hay_direccion()
    {
        $entityData = [
            'type' => 'customer',
            'tipo_documento' => '01',
            'numero_documento' => $this->faker->unique()->numerify('########'),
            'tipo_persona' => 'natural',
            'first_name' => 'Test',
            'last_name' => 'User',
            'address' => 'Direccion Fiscal Peruana',
            'country_code' => $this->countryPeru,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/entities', $entityData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ubigeo'])
            ->assertJsonPath('errors.ubigeo.0', 'El ubigeo es obligatorio para entidades en Perú.');
    }

    #[Test]
    public function valida_que_ubigeo_no_es_obligatorio_para_otros_paises_al_crear()
    {
        $entityData = [
            'type' => 'customer',
            'tipo_documento' => '01',
            'numero_documento' => $this->faker->unique()->numerify('########'),
            'tipo_persona' => 'natural',
            'first_name' => 'Test',
            'last_name' => 'User Int',
            'email' => $this->faker->unique()->safeEmail,
            'address' => 'Addr Int',
            'country_code' => $this->countryUSA,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/entities', $entityData);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Cliente creado exitosamente');
        $response->assertJsonMissingValidationErrors(['ubigeo']);
    }

    #[Test]
    public function puede_actualizar_pais_de_entidad_de_peru_a_usa_anulando_ubigeo()
    {
        $entity = Entity::factory()->create([
            'country_code' => $this->countryPeru,
            'ubigeo' => $this->ubigeoTestPeru,
            'user_id' => $this->user->id
        ]);

        $updateData = [
            'country_code' => $this->countryUSA,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/entities/{$entity->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Cliente actualizado exitosamente')
            ->assertJsonPath('data.country_code', $this->countryUSA)
            ->assertJsonPath('data.ubigeo', null);

        $entity->refresh();
        $this->assertEquals($this->countryUSA, $entity->country_code);
        $this->assertNull($entity->ubigeo);
    }

    #[Test]
    public function valida_ubigeo_al_actualizar_pais_de_entidad_a_peru()
    {
        $entity = Entity::factory()->create([
            'country_code' => $this->countryUSA,
            'ubigeo' => null,
            'user_id' => $this->user->id
        ]);

        $updateData = [
            'country_code' => $this->countryPeru,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/entities/{$entity->id}", $updateData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ubigeo']);
    }

    #[Test]
    public function permite_actualizar_a_peru_si_se_provee_ubigeo()
    {
        $entity = Entity::factory()->create([
            'country_code' => $this->countryUSA,
            'ubigeo' => null,
            'user_id' => $this->user->id
        ]);

        $updateData = [
            'country_code' => $this->countryPeru,
            'ubigeo' => $this->ubigeoTestPeru,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/entities/{$entity->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Cliente actualizado exitosamente')
            ->assertJsonPath('data.country_code', $this->countryPeru)
            ->assertJsonPath('data.ubigeo', $this->ubigeoTestPeru);

        $entity->refresh();
        $this->assertEquals($this->countryPeru, $entity->country_code);
        $this->assertEquals($this->ubigeoTestPeru, $entity->ubigeo);
    }

    #[Test]
    public function puede_listar_entidades_con_paginacion_y_filtros()
    {
        Entity::factory()->count(3)->create(['type' => 'customer', 'country_code' => $this->countryPeru]);
        Entity::factory()->count(2)->create(['type' => 'supplier', 'tipo_persona' => 'juridica', 'country_code' => $this->countryPeru]);
        Entity::factory()->create(['country_code' => $this->countryUSA]);

        // 1. Listar todo
        $responseAll = $this->actingAs($this->user, 'sanctum')->getJson('/api/entities?per_page=10');
        $responseAll->assertStatus(200);
        $responseAll->assertJsonCount(6, 'data');
        $responseAll->assertJsonPath('meta.total', 6);

        // 2. Filtrar por tipo 'supplier'
        $responseSuppliers = $this->actingAs($this->user, 'sanctum')->getJson('/api/entities?type=supplier');
        $responseSuppliers->assertStatus(200);
        $responseSuppliers->assertJsonCount(2, 'data');

        // 3. Filtrar por tipo 'customer'
        $responseCustomers = $this->actingAs($this->user, 'sanctum')->getJson('/api/entities?type=customer');
        $responseCustomers->assertStatus(200);
        $responseCustomers->assertJsonCount(4, 'data');

        // 4. Buscar por nombre
        Entity::factory()->create([
            'first_name' => 'Maria',
            'last_name' => 'Gonzales',
            'country_code' => $this->countryPeru,
            'user_id' => $this->user->id,
            'tipo_persona' => 'natural',
            'tipo_documento' => '01',
            'numero_documento' => $this->faker->unique()->numerify('########')
        ]);
        $responseSearch = $this->actingAs($this->user, 'sanctum')->getJson('/api/entities?search=Maria');
        $responseSearch->assertStatus(200);
        $responseSearch->assertJsonCount(1, 'data');

        $results = $responseSearch->json('data');
        $foundMaria = false;
        foreach ($results as $result) {
            if ($result['full_name'] === 'Maria Gonzales') {
                $foundMaria = true;
                break;
            }
        }
        $this->assertTrue($foundMaria, "Entidad 'Maria Gonzales' no encontrada en los resultados de búsqueda.");
    }

    #[Test]
    public function valida_campos_obligatorios_para_proveedor_al_crear()
    {
        $supplierData = [
            'type' => 'supplier',
            'tipo_documento' => '06',
            'numero_documento' => '20' . $this->faker->unique()->numerify('#########'),
            'country_code' => $this->countryPeru,
            'ubigeo' => $this->ubigeoTestPeru,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/entities', $supplierData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['business_name', 'email', 'phone', 'address']);
        $response->assertJsonPath('errors.email.0', 'El email es obligatorio para proveedores.');
    }

    #[Test]
    public function puede_mostrar_una_entidad_individual_con_relaciones()
    {
        $ubigeoForAddress = $this->ubigeoTestPeru;

        $entity = Entity::factory()
            ->has(Address::factory()->count(1)->state(function (array $attributes, Entity $entity) use ($ubigeoForAddress) {
                return [
                    'entity_id' => $entity->id,
                    'is_default' => true,
                    'country_code' => $entity->country_code,
                    'ubigeo' => $ubigeoForAddress
                ];
            }))
            ->create([
                'user_id' => $this->user->id,
                'country_code' => $this->countryPeru,
                'ubigeo' => $this->ubigeoTestPeru
            ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/entities/{$entity->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'type',
                'tipo_documento',
                'numero_documento',
                'tipo_persona',
                'full_name',
                'email',
                'phone',
                'country_code',
                'country_name',
                'ubigeo',
                'ubigeo_name',
                'is_active',
                'registered_at',
                'default_address',
                'user_id',
            ]
        ]);
        $response->assertJsonPath('data.id', $entity->id);
        $response->assertJsonPath('data.ubigeo_name', 'Lima');
        $response->assertJsonPath('data.default_address.id', fn($id) => !is_null($id) && is_int($id));
        $response->assertJsonPath('data.country_name', 'Perú');
    }

    #[Test]
    public function puede_desactivar_una_entidad()
    {
        $entity = Entity::factory()->create(['is_active' => true, 'user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/entities/{$entity->id}/deactivate");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Cliente desactivado exitosamente')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('entities', ['id' => $entity->id, 'is_active' => false]);
    }

    #[Test]
    public function puede_activar_una_entidad()
    {
        $entity = Entity::factory()->create(['is_active' => false, 'user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/entities/{$entity->id}/activate");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Cliente activado exitosamente')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('entities', ['id' => $entity->id, 'is_active' => true]);
    }

    #[Test]
    public function puede_eliminar_una_entidad_logicamente()
    {
        $entity = Entity::factory()->create(['is_active' => true, 'user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/entities/{$entity->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Cliente eliminado (desactivado) exitosamente');

        $this->assertDatabaseHas('entities', ['id' => $entity->id, 'is_active' => false]);
        $this->assertDatabaseCount('entities', 1);
    }
}
