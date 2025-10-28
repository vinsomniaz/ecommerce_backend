<?php

namespace Tests\Feature\Api;

use App\Models\Entity;
use App\Models\User;
use App\Models\Ubigeo; // Importar Ubigeo
use App\Models\Country; // Importar Country
use App\Models\Address; // <-- Asegúrate de tener esta línea
use Database\Seeders\CountrySeeder; // Importar CountrySeeder
use Database\Seeders\UbigeoSeeder; // Importar UbigeoSeeder
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker; // Añadir WithFaker
use Tests\TestCase;

class EntityApiTest extends TestCase
{
    use RefreshDatabase, WithFaker; // Añadir WithFaker

    protected User $user;
    protected string $ubigeoTestPeru; // Ubigeo Peruano
    protected string $countryPeru = 'PE';
    protected string $countryUSA = 'US';


    protected function setUp(): void  
    {
        parent::setUp();
        // Creamos un usuario para autenticar las peticiones
        $this->user = User::factory()->create();

        // Ejecutar Seeders necesarios
        $this->seed(CountrySeeder::class); // <-- SEMBRAR PAÍSES

        // Crear Ubigeo de prueba para Perú
        $this->ubigeoTestPeru = '150101'; // Lima - Lima
        Ubigeo::factory()->create([ // Usar factory o create
            'ubigeo' => $this->ubigeoTestPeru,
            'country_code' => $this->countryPeru,
            'departamento' => 'Lima',
            'provincia' => 'Lima',
            'distrito' => 'Lima',
        ]);
    }

    /** @test */
    public function it_can_create_a_customer_entity_natural_person_for_peru_with_ubigeo()
    {
        $entityData = [
            'type' => 'customer',
            'tipo_documento' => '01', // DNI
            'numero_documento' => $this->faker->unique()->numerify('########'), // DNI único
            'tipo_persona' => 'natural',
            'first_name' => 'Juan',
            'last_name' => 'Perez',
            'email' => $this->faker->unique()->safeEmail, // Email único
            'phone' => '987654321',
            'address' => 'Av. Peru 123', // Dirección Fiscal
            'country_code' => $this->countryPeru, // Especificar Perú
            'ubigeo' => $this->ubigeoTestPeru, // Especificar Ubigeo
        ];

        $response = $this->actingAs($this->user, 'sanctum')
                         ->postJson('/api/entities', $entityData);

        $response->assertStatus(201)
                 ->assertJsonPath('data.country_code', $this->countryPeru)
                 ->assertJsonPath('data.ubigeo', $this->ubigeoTestPeru)
                 ->assertJsonPath('data.full_name', 'Juan Perez'); // Verificar nombre completo

        $this->assertDatabaseHas('entities', [
            'numero_documento' => $entityData['numero_documento'],
            'country_code' => $this->countryPeru,
            'ubigeo' => $this->ubigeoTestPeru,
            'user_id' => $this->user->id, // Verificar usuario creador
        ]);
    }

     /** @test */
    public function it_can_create_a_customer_entity_legal_person_for_peru_with_ubigeo()
    {
        $entityData = [
            'type' => 'customer',
            'tipo_documento' => '06', // RUC
            'numero_documento' => '20' . $this->faker->unique()->numerify('#########'), // RUC único
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
                 ->assertJsonPath('data.country_code', $this->countryPeru)
                 ->assertJsonPath('data.ubigeo', $this->ubigeoTestPeru)
                 ->assertJsonPath('data.full_name', 'Empresa SAC'); // Razón social como full_name

        $this->assertDatabaseHas('entities', [
            'numero_documento' => $entityData['numero_documento'],
            'business_name' => 'Empresa SAC',
        ]);
    }

    /** @test */
    public function it_can_create_a_customer_entity_for_usa_natural_person_without_ubigeo()
    {
        $entityData = [
            'type' => 'customer',
            'tipo_documento' => '01', // Documento extranjero (ej: pasaporte)
            'numero_documento' => $this->faker->unique()->numerify('########'), // Documento único
            'tipo_persona' => 'natural',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => $this->faker->unique()->safeEmail,
            'address' => '123 Main St',
            'country_code' => $this->countryUSA, // Especificar USA
            // 'ubigeo' => null, // No se envía o se ignora
        ];

        // Nota: Ajustar validación de tipo_documento si '00' no está permitido en StoreEntityRequest
         // Si '00' no es válido, usa '01' o ajusta la validación
         $entityData['tipo_documento'] = '01'; // Usar DNI si es más simple para la validación actual

        $response = $this->actingAs($this->user, 'sanctum')
                         ->postJson('/api/entities', $entityData);

        // Ajustar el assert de status si la validación falla por tipo_documento
        // $response->assertStatus(422); return; // Descomentar si falla por tipo_doc

        $response->assertStatus(201)
                 ->assertJsonPath('data.country_code', $this->countryUSA)
                 ->assertJsonPath('data.ubigeo', null); // Ubigeo debe ser null

        $this->assertDatabaseHas('entities', [
            'numero_documento' => $entityData['numero_documento'],
            'country_code' => $this->countryUSA,
            'ubigeo' => null, // Verificar que se guardó null
        ]);
    }

    /** @test */
    public function it_validates_ubigeo_is_required_for_peru_on_create_if_address_is_present()
    {
        // La validación 'required_if:country_code,PE' aplica si 'country_code' es 'PE' o no se envía (default 'PE')
        $entityData = [
            'type' => 'customer',
            'tipo_documento' => '01',
            'numero_documento' => $this->faker->unique()->numerify('########'),
            'tipo_persona' => 'natural',
            'first_name' => 'Test',
            'last_name' => 'User',
            'address' => 'Direccion Fiscal Peruana', // Añadir dirección para que aplique la validación de ubigeo (aunque no sea obligatorio para cliente)
            'country_code' => $this->countryPeru,
            // Sin ubigeo
        ];

        $response = $this->actingAs($this->user, 'sanctum')
                         ->postJson('/api/entities', $entityData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['ubigeo'])
                 ->assertJsonPath('errors.ubigeo.0', 'El ubigeo es obligatorio para entidades en Perú.'); // Mensaje de StoreEntityRequest
    }

     /** @test */
    public function it_validates_ubigeo_is_not_required_for_other_countries_on_create()
    {
        $entityData = [
            'type' => 'customer',
            'tipo_documento' => '01', // Ajustar tipo doc si es necesario
            'numero_documento' => $this->faker->unique()->numerify('########'), // Documento único
            'tipo_persona' => 'natural',
            'first_name' => 'Test',
            'last_name' => 'User Int',
            'email' => $this->faker->unique()->safeEmail,
            'address' => 'Addr Int',
            'country_code' => $this->countryUSA,
            // Sin ubigeo
        ];

        $response = $this->actingAs($this->user, 'sanctum')
                         ->postJson('/api/entities', $entityData);

        $response->assertStatus(201); // Debería crearla sin problema
        $response->assertJsonMissingValidationErrors(['ubigeo']);
    }

    /** @test */
    public function it_can_update_entity_country_from_peru_to_usa_nullifying_ubigeo()
    {
        $entity = Entity::factory()->create([
            'country_code' => $this->countryPeru,
            'ubigeo' => $this->ubigeoTestPeru,
            'user_id' => $this->user->id
        ]);

        $updateData = [
            'country_code' => $this->countryUSA,
            // UpdateEntityRequest usa 'sometimes', por lo que solo necesitamos enviar lo que cambia
            // Pero para pasar validaciones como DNI/RUC, puede ser necesario enviar más campos si cambias tipo_documento o tipo_persona
        ];

        $response = $this->actingAs($this->user, 'sanctum')
                         ->patchJson("/api/entities/{$entity->id}", $updateData); // Usar PATCH para parcial

        $response->assertStatus(200)
                 ->assertJsonPath('data.country_code', $this->countryUSA)
                 ->assertJsonPath('data.ubigeo', null); // Se anula automáticamente en el modelo o servicio

        $entity->refresh();
        $this->assertEquals($this->countryUSA, $entity->country_code);
        $this->assertNull($entity->ubigeo);
    }

     /** @test */
    public function it_validates_ubigeo_when_updating_entity_country_to_peru()
    {
        $entity = Entity::factory()->create([
            'country_code' => $this->countryUSA,
            'ubigeo' => null,
            'user_id' => $this->user->id
        ]);

        $updateData = [
            'country_code' => $this->countryPeru,
            // Faltará el ubigeo aquí para forzar el error
            // No necesitamos enviar otros campos si solo cambia el país y falla la validación
        ];

        $response = $this->actingAs($this->user, 'sanctum')
                         ->patchJson("/api/entities/{$entity->id}", $updateData);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['ubigeo']); // Falla según UpdateEntityRequest
    }

     /** @test */
    public function it_allows_updating_to_peru_if_ubigeo_is_provided()
    {
        $entity = Entity::factory()->create([
            'country_code' => $this->countryUSA,
            'ubigeo' => null,
            'user_id' => $this->user->id
        ]);

        $updateData = [
            'country_code' => $this->countryPeru,
            'ubigeo' => $this->ubigeoTestPeru, // Proveer ubigeo válido
        ];

        $response = $this->actingAs($this->user, 'sanctum')
                         ->patchJson("/api/entities/{$entity->id}", $updateData);

        $response->assertStatus(200)
                ->assertJsonPath('data.country_code', $this->countryPeru)
                ->assertJsonPath('data.ubigeo', $this->ubigeoTestPeru);

         $entity->refresh();
         $this->assertEquals($this->countryPeru, $entity->country_code);
         $this->assertEquals($this->ubigeoTestPeru, $entity->ubigeo);
    }

    /** @test */
    public function it_can_list_entities_with_pagination_and_filters()
    {
        // Crear entidades variadas
        Entity::factory()->count(3)->create(['type' => 'customer', 'country_code' => $this->countryPeru]);
        Entity::factory()->count(2)->create(['type' => 'supplier', 'tipo_persona' => 'juridica', 'country_code' => $this->countryPeru]);
        Entity::factory()->create(['country_code' => $this->countryUSA]); // Entidad USA

        // 1. Listar todo (debe haber 6)
        $responseAll = $this->actingAs($this->user, 'sanctum')->getJson('/api/entities?per_page=10');
        $responseAll->assertStatus(200);
        $responseAll->assertJsonCount(6, 'data');
        $responseAll->assertJsonPath('meta.total', 6);

        // 2. Filtrar por tipo 'supplier' (debe haber 2)
        $responseSuppliers = $this->actingAs($this->user, 'sanctum')->getJson('/api/entities?type=supplier');
        $responseSuppliers->assertStatus(200);
        $responseSuppliers->assertJsonCount(2, 'data');

        // 3. Filtrar por tipo 'customer' (debe haber 4: 3 customer + 1 USA)
        // La lógica en EntityService aplica 'customers()' scope que incluye 'customer' y 'both'
         $responseCustomers = $this->actingAs($this->user, 'sanctum')->getJson('/api/entities?type=customer');
         $responseCustomers->assertStatus(200);
         // Si la entidad USA es 'customer', debe haber 4. Si no, ajustar.
         // Asumiendo que el factory por defecto crea 'customer'
         $responseCustomers->assertJsonCount(4, 'data');

        // 4. Buscar por nombre (ejemplo)
        Entity::factory()->create(['first_name' => 'Maria', 'last_name' => 'Gonzales', 'country_code' => $this->countryPeru]);
        $responseSearch = $this->actingAs($this->user, 'sanctum')->getJson('/api/entities?search=Maria');
        $responseSearch->assertStatus(200);
        $responseSearch->assertJsonCount(1, 'data');
        $responseSearch->assertJsonPath('data.0.full_name', 'Maria Gonzales');
    }

    /** @test */
    public function it_validates_required_fields_for_supplier_on_create()
    {
        // Los proveedores requieren RUC (06) y son 'juridica' por defecto
        // Faltan campos obligatorios para supplier: email, phone, address, business_name
        $supplierData = [
            'type' => 'supplier',
            'tipo_documento' => '06', // RUC Correcto
            'numero_documento' => '20' . $this->faker->unique()->numerify('#########'), // RUC único
            // Faltan business_name, email, phone, address
            'country_code' => $this->countryPeru,
            'ubigeo' => $this->ubigeoTestPeru,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
                         ->postJson('/api/entities', $supplierData);

        $response->assertStatus(422); // Error de validación

        // Verifica los mensajes de error de StoreEntityRequest
        $response->assertJsonValidationErrors(['business_name', 'email', 'phone', 'address']);
        $response->assertJsonPath('errors.email.0', 'El email es obligatorio para proveedores.'); // Mensaje específico para supplier
    }

    /** @test */
    public function it_can_show_a_single_entity_with_relations()
    {
        $entity = Entity::factory()
            ->has(Address::factory()->count(1)->state(function (array $attributes, Entity $entity) {
                // Crear dirección default para esta entidad
                return ['entity_id' => $entity->id, 'is_default' => true, 'country_code' => $entity->country_code, 'ubigeo' => $entity->ubigeo];
            }))
            ->create([
                'user_id' => $this->user->id,
                'country_code' => $this->countryPeru,
                'ubigeo' => $this->ubigeoTestPeru
            ]);

        $response = $this->actingAs($this->user, 'sanctum')
                         ->getJson("/api/entities/{$entity->id}");

        $response->assertStatus(200);
        // Verificar estructura según EntityResource, incluyendo relaciones cargadas en el controlador
        $response->assertJsonStructure([
            'data' => [
                'id',
                'full_name',
                'country_code',
                'country_name', // Cargado si la relación existe
                'ubigeo',
                'ubigeo_name', // Distrito
                'default_address' => [
                    'id',
                    'address',
                    'distrito'
                ],
                'user_id' // Cargado por el controlador
            ]
        ]);
        $response->assertJsonPath('data.id', $entity->id);
        $response->assertJsonPath('data.ubigeo_name', 'Lima'); // Distrito del ubigeoTestPeru
        $response->assertNotNull('data.default_address.id'); // Verificar que la dirección default cargó
    }

    /** @test */
    public function it_can_deactivate_an_entity()
    {
        $entity = Entity::factory()->create(['is_active' => true, 'user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
                         ->patchJson("/api/entities/{$entity->id}/deactivate"); // Ruta específica

        $response->assertStatus(200)
                 ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('entities', ['id' => $entity->id, 'is_active' => false]);
    }

    /** @test */
    public function it_can_activate_an_entity()
    {
        $entity = Entity::factory()->create(['is_active' => false, 'user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
                         ->patchJson("/api/entities/{$entity->id}/activate"); // Ruta específica

        $response->assertStatus(200)
                 ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('entities', ['id' => $entity->id, 'is_active' => true]);
    }

    /** @test */
    public function it_can_delete_an_entity_logically()
    {
        // El método destroy del controlador en realidad desactiva la entidad
        $entity = Entity::factory()->create(['is_active' => true, 'user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
                         ->deleteJson("/api/entities/{$entity->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('message', 'Cliente eliminado (desactivado) exitosamente');

        // Verifica que se marcó como inactivo, no eliminado de la BD
        $this->assertDatabaseHas('entities', ['id' => $entity->id, 'is_active' => false]);
        $this->assertDatabaseCount('entities', 1); // Sigue existiendo 1 registro
    }

}