<?php

namespace Tests\Feature\Api;

use App\Models\Address;
use App\Models\Entity;
use App\Models\Ubigeo;
use App\Models\User;
use App\Models\Country; // Importar Country
use Database\Seeders\CountrySeeder; // Importar CountrySeeder
use Database\Seeders\UbigeoSeeder; // Importar UbigeoSeeder
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AddressApiTest extends TestCase
{
    // Limpia y migra la BD por cada test.
    use RefreshDatabase, WithFaker; // Añadir WithFaker

    protected User $user;
    protected Entity $entityPeru; // Entidad Peruana
    protected Entity $entityUSA; // Entidad USA
    protected string $ubigeoTestPeru;
    protected string $anotherUbigeoPeru;
    protected string $countryPeru = 'PE';
    protected string $countryUSA = 'US';

    /**
     * Configuración inicial para todos los tests.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 1. Creamos un usuario de prueba
        $this->user = User::factory()->create();

        // 2. Ejecutar Seeder de Países y Ubigeos (para tener datos reales)
        $this->seed(CountrySeeder::class);
        // Usar UbigeoSeeder puede ser pesado, crearemos solo los necesarios
        // $this->seed(UbigeoSeeder::class);

        // 3. Creamos Ubigeos Peruanos de prueba manualmente
        $this->ubigeoTestPeru = '150103'; // Lima - Lince
        Ubigeo::factory()->create([ // Usar factory o create directo
            'ubigeo' => $this->ubigeoTestPeru,
            'country_code' => $this->countryPeru,
            'departamento' => 'Lima',
            'provincia' => 'Lima',
            'distrito' => 'Lince',
        ]);
        $this->anotherUbigeoPeru = '150101'; // Lima - Lima
        Ubigeo::factory()->create([ // Usar factory o create directo
            'ubigeo' => $this->anotherUbigeoPeru,
            'country_code' => $this->countryPeru,
            'departamento' => 'Lima',
            'provincia' => 'Lima',
            'distrito' => 'Lima',
        ]);

        // 4. Creamos una entidad Peruana y una Estadounidense
        $this->entityPeru = Entity::factory()->create([
            'user_id' => $this->user->id,
            'country_code' => $this->countryPeru,
            'ubigeo' => $this->ubigeoTestPeru, // Dirección fiscal en Lince
        ]);
        $this->entityUSA = Entity::factory()->create([
            'user_id' => $this->user->id,
            'country_code' => $this->countryUSA,
            'ubigeo' => null, // Dirección fiscal en USA, sin ubigeo
            'tipo_documento' => '00', // Ejemplo: Pasaporte u otro tipo
            'numero_documento' => $this->faker->unique()->numerify('##########') // Asegurar unicidad
        ]);
    }

    // ===========================================
    // NUEVOS TESTS CON LÓGICA DE PAÍSES
    // ===========================================

    /** @test */
    public function it_can_create_an_address_for_peru_entity_with_ubigeo()
    {
        $addressData = [
            'address' => 'Av. Arequipa 2545 Dpto. 302',
            'country_code' => $this->countryPeru,
            'ubigeo' => $this->ubigeoTestPeru,
            'reference' => 'Edificio blanco',
            'phone' => '987654321',
            'label' => 'Casa',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/entities/{$this->entityPeru->id}/addresses", $addressData);

        $response->assertStatus(201)
            ->assertJsonPath('data.country_code', $this->countryPeru)
            ->assertJsonPath('data.ubigeo', $this->ubigeoTestPeru)
            ->assertJsonPath('data.distrito', 'Lince'); // Verifica datos del ubigeo

        $this->assertDatabaseHas('addresses', [
            'entity_id' => $this->entityPeru->id,
            'address' => 'Av. Arequipa 2545 Dpto. 302',
            'country_code' => $this->countryPeru,
            'ubigeo' => $this->ubigeoTestPeru,
        ]);
    }

    /** @test */
    public function it_can_create_an_address_for_usa_entity_without_ubigeo()
    {
        $addressData = [
            'address' => '456 Freedom Ave',
            'country_code' => $this->countryUSA,
            // 'ubigeo' => 'IGNORADO', // No se envía o se ignora
            'reference' => 'Near Central Park',
            'phone' => '5551234',
            'label' => 'Work',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/entities/{$this->entityUSA->id}/addresses", $addressData);

        $response->assertStatus(201)
            ->assertJsonPath('data.country_code', $this->countryUSA)
            ->assertJsonPath('data.ubigeo', null) // Ubigeo debe ser null
            ->assertJsonPath('data.distrito', null); // Datos de ubigeo deben ser null

        $this->assertDatabaseHas('addresses', [
            'entity_id' => $this->entityUSA->id,
            'address' => '456 Freedom Ave',
            'country_code' => $this->countryUSA,
            'ubigeo' => null, // Verificar que se guardó null
        ]);
    }

    /** @test */
    public function it_validates_ubigeo_required_when_country_is_peru_on_create()
    {
        $addressData = [
            'address' => 'Calle Falsa 123',
            'country_code' => $this->countryPeru,
            // Falta ubigeo
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/entities/{$this->entityPeru->id}/addresses", $addressData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ubigeo'])
            ->assertJsonPath('errors.ubigeo.0', 'El ubigeo es obligatorio para direcciones en Perú.');
    }

    /** @test */
    public function it_validates_ubigeo_not_required_when_country_is_not_peru_on_create()
    {
        $addressData = [
            'address' => 'Another St 789',
            'country_code' => $this->countryUSA,
            // Sin ubigeo
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/entities/{$this->entityUSA->id}/addresses", $addressData);

        $response->assertStatus(201); // Debe crearla
        $response->assertJsonMissingValidationErrors(['ubigeo']);
    }

    /** @test */
    public function it_validates_invalid_country_code_on_create()
    {
        $addressData = [
            'address' => 'Invalid Country Addr',
            'country_code' => 'XX', // Código inválido
            'ubigeo' => $this->ubigeoTestPeru, // Añadimos ubigeo para aislar el error de country
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/entities/{$this->entityPeru->id}/addresses", $addressData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['country_code']);
    }

    /** @test */
    public function it_can_update_address_country_from_peru_to_usa_nullifying_ubigeo()
    {
        // Crear una dirección existente para la entidad peruana
        $address = Address::factory()->create([
            'entity_id' => $this->entityPeru->id,
            'country_code' => $this->countryPeru,
            'ubigeo' => $this->ubigeoTestPeru,
        ]);

        $updateData = [
            'country_code' => $this->countryUSA,
            // Otros campos obligatorios para el Update Request (según UpdateAddressRequest.php)
            'address' => $address->address, // Mantener la dirección
            // No necesitamos enviar ubigeo, se anulará
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/addresses/{$address->id}", $updateData);


        $response->assertStatus(200)
            ->assertJsonPath('data.country_code', $this->countryUSA)
            ->assertJsonPath('data.ubigeo', null); // Se debe haber anulado

        $address->refresh(); // Recargar datos desde la BD
        $this->assertEquals($this->countryUSA, $address->country_code);
        $this->assertNull($address->ubigeo);
    }

    /** @test */
    public function it_validates_ubigeo_when_updating_address_country_to_peru()
    {
        // Crear una dirección existente para la entidad USA
        $address = Address::factory()->create([
            'entity_id' => $this->entityUSA->id,
            'country_code' => $this->countryUSA,
            'ubigeo' => null,
        ]);

        $updateData = [
            'country_code' => $this->countryPeru,
            // Faltará el ubigeo aquí
            'address' => $address->address, // Campo obligatorio del Update Request
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/addresses/{$address->id}", $updateData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ubigeo']); // Debe fallar por falta de ubigeo
    }

    /** @test */
    public function it_allows_updating_ubigeo_within_peru()
    {
        $address = Address::factory()->create([
            'entity_id' => $this->entityPeru->id,
            'country_code' => $this->countryPeru,
            'ubigeo' => $this->ubigeoTestPeru, // Lince
        ]);

        $updateData = [
            'ubigeo' => $this->anotherUbigeoPeru, // Cambiar a Lima
            'address' => $address->address, // Campo obligatorio del Update Request
            // country_code se mantiene PE implicitamente o se puede enviar
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/addresses/{$address->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonPath('data.ubigeo', $this->anotherUbigeoPeru)
            ->assertJsonPath('data.country_code', $this->countryPeru); // Sigue siendo Perú

        $address->refresh();
        $this->assertEquals($this->anotherUbigeoPeru, $address->ubigeo);
    }

    // ===========================================
    // TESTS EXISTENTES ADAPTADOS O MANTENIDOS
    // ===========================================

    /** @test */
    public function first_address_created_is_set_as_default_for_peru_entity()
    {
        // Asegurarse que la entidad NO tenga direcciones previas
        $entityPeruFresh = Entity::factory()->create([
            'user_id' => $this->user->id,
            'country_code' => $this->countryPeru,
            'ubigeo' => $this->ubigeoTestPeru,
        ]);

        $addressData = [
            'address' => 'Av. Arequipa 2545',
            'country_code' => $this->countryPeru,
            'ubigeo' => $this->ubigeoTestPeru,
            'is_default' => false // Enviamos 'false' a propósito
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/entities/{$entityPeruFresh->id}/addresses", $addressData);

        $response->assertStatus(201);

        // Verificamos que se marcó como 'true' en la base de datos
        $this->assertDatabaseHas('addresses', [
            'entity_id' => $entityPeruFresh->id,
            'address' => 'Av. Arequipa 2545',
            'is_default' => true, // El service debe forzarlo a true
        ]);
    }

    /** @test */
    public function it_can_list_addresses_for_peru_and_usa_entities()
    {
        // Creamos direcciones peruanas (asegúrate que el factory use un ubigeo válido)
        Address::factory()->count(2)->create([
            'entity_id' => $this->entityPeru->id,
            'country_code' => $this->countryPeru,
            'ubigeo' => $this->ubigeoTestPeru // Asegurar ubigeo válido
        ]);
        // Creamos dirección USA
        Address::factory()->create([
            'entity_id' => $this->entityUSA->id,
            'country_code' => $this->countryUSA,
            'ubigeo' => null
        ]);

        // Listar para entidad Peruana
        $responsePeru = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/entities/{$this->entityPeru->id}/addresses");

        $responsePeru->assertStatus(200);
        $responsePeru->assertJsonCount(2, 'data'); // Debe tener 2

        // Listar para entidad USA
        $responseUSA = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/entities/{$this->entityUSA->id}/addresses");

        $responseUSA->assertStatus(200);
        $responseUSA->assertJsonCount(1, 'data'); // Debe tener 1
    }

    /** @test */
    public function it_can_update_an_address_details_for_peru_entity()
    {
        $address = Address::factory()->create([
            'entity_id' => $this->entityPeru->id,
            'country_code' => $this->countryPeru,
            'ubigeo' => $this->ubigeoTestPeru,
            'label' => 'Casa'
        ]);

        $updateData = [
            'label' => 'Oficina',
            'phone' => '111222333',
            // Asegúrate de incluir campos obligatorios si UpdateAddressRequest los requiere siempre
            'address' => $address->address, // 'address' es required en UpdateRequest
            // No necesitamos enviar 'ubigeo' ni 'country_code' si no cambian
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/addresses/{$address->id}", $updateData);

        $response->assertStatus(200);
        $response->assertJsonPath('data.label', 'Oficina');
        $response->assertJsonPath('data.phone', '111222333');

        $this->assertDatabaseHas('addresses', [
            'id' => $address->id,
            'label' => 'Oficina',
            'phone' => '111222333'
        ]);
    }

    /** @test */
    public function it_can_delete_an_address_for_peru_entity()
    {
        $address = Address::factory()->create([
            'entity_id' => $this->entityPeru->id,
            'country_code' => $this->countryPeru,
            'ubigeo' => $this->ubigeoTestPeru
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/addresses/{$address->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Dirección eliminada exitosamente');

        // Verificamos que fue borrada de la base de datos
        $this->assertDatabaseMissing('addresses', [
            'id' => $address->id
        ]);
    }

    /** @test */
    public function it_can_set_a_new_default_address_for_peru_entity()
    {
        // Creamos la entidad sin direcciones iniciales para controlar el proceso
        $entityPeruFresh = Entity::factory()->create([
            'user_id' => $this->user->id,
            'country_code' => $this->countryPeru,
            'ubigeo' => $this->ubigeoTestPeru,
        ]);

        // Create two addresses using the factory, explicitly setting defaults
        $address1 = Address::factory()->create([
            'entity_id' => $entityPeruFresh->id,
            'country_code' => $this->countryPeru,
            'ubigeo' => $this->ubigeoTestPeru,
            'is_default' => true // << Explicitly set first as default
        ]);
        $address2 = Address::factory()->create([
            'entity_id' => $entityPeruFresh->id,
            'country_code' => $this->countryPeru,
            'ubigeo' => $this->anotherUbigeoPeru,
            'is_default' => false // << Explicitly set second as non-default
        ]);

        // Verificamos estado inicial
        $this->assertTrue($address1->fresh()->is_default);
        $this->assertFalse($address2->fresh()->is_default);

        // Marcamos la segunda como predeterminada usando el endpoint
        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/addresses/{$address2->id}/set-default");

        $response->assertStatus(200);
        $response->assertJsonPath('data.is_default', true); // La respuesta debe confirmar

        // Verificamos que la primera se desmarcó en la BD
        $this->assertDatabaseHas('addresses', [
            'id' => $address1->id,
            'is_default' => false
        ]);

        // Verificamos que la segunda se marcó en la BD
        $this->assertDatabaseHas('addresses', [
            'id' => $address2->id,
            'is_default' => true
        ]);
    }

    /** @test */
    public function it_validates_required_fields_on_create_for_peru_entity()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/entities/{$this->entityPeru->id}/addresses", [
                // No enviar 'address' ni 'ubigeo', country_code default es 'PE'
            ]);

        $response->assertStatus(422); // Error de validación

        // Verificamos que los campos requeridos están en los errores
        // Como 'country_code' no se envió, se asume 'PE', por lo tanto 'ubigeo' es requerido.
        $response->assertJsonValidationErrors(['address', 'ubigeo']);
        $response->assertJsonPath('errors.address.0', 'La dirección es obligatoria.'); // Mensaje de StoreAddressRequest
        $response->assertJsonPath('errors.ubigeo.0', 'El ubigeo es obligatorio para direcciones en Perú.'); // Mensaje de StoreAddressRequest
    }

    /** @test */
    public function it_validates_if_ubigeo_exists_for_peru_entity()
    {
        $addressData = [
            'address' => 'Av. Falsa 123',
            'country_code' => $this->countryPeru,
            'ubigeo' => '999999', // Ubigeo que no existe
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/entities/{$this->entityPeru->id}/addresses", $addressData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('ubigeo');
        $response->assertJsonPath('errors.ubigeo.0', 'El ubigeo no existe en nuestra base de datos.'); // Mensaje de StoreAddressRequest
    }

    /** @test */
    public function it_can_show_a_single_address_with_country_and_ubigeo_data()
    {
        $address = Address::factory()->create([
            'entity_id' => $this->entityPeru->id,
            'country_code' => $this->countryPeru,
            'ubigeo' => $this->ubigeoTestPeru, // Lince
            'label' => 'Principal'
        ]);

        // Asegúrate de que el modelo Address cargue la relación 'country'
        $address->load('country', 'ubigeoData');

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/addresses/{$address->id}");

        $response->assertStatus(200);
        // Validar estructura incluyendo 'country' y datos de 'ubigeoData'
        $response->assertJsonStructure([ // Basado en AddressResource
            'data' => [
                'id',
                'address',
                'country_code',
                'country' => ['code', 'name'], // Verifica relación país
                'ubigeo',
                'departamento', // Verifica relación ubigeo
                'provincia',
                'distrito',
                'label',
                'is_default'
            ]
        ]);
        $response->assertJsonPath('data.country_code', $this->countryPeru);
        $response->assertJsonPath('data.ubigeo', $this->ubigeoTestPeru);
        $response->assertJsonPath('data.label', 'Principal');
        $response->assertJsonPath('data.distrito', 'Lince'); // Dato de ubigeoData
        $response->assertJsonPath('data.country.name', 'Perú'); // Dato de country
    }

    /** @test */
    public function it_returns_404_when_showing_non_existent_address()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/addresses/99999"); // ID que no existe

        $response->assertStatus(404); // El route model binding debe fallar
    }
}
