<?php

namespace Tests\Feature\Api;

use App\Models\Address;
use App\Models\Entity;
use App\Models\Ubigeo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AddressApiTest extends TestCase
{
    // Esto limpia y migra la base de datos por cada test.
    // ¡Es fundamental!
    use RefreshDatabase;
    
    protected User $user;
    protected Entity $entity;
    protected string $ubigeoTest;

    /**
     * Configuración inicial para todos los tests de esta clase
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 1. Creamos un usuario de prueba para autenticarnos
        $this->user = User::factory()->create();

        // 2. Creamos una entidad (cliente) asociada a ese usuario
        $this->entity = Entity::factory()->create([
            'user_id' => $this->user->id
        ]);

        // 3. ¡IMPORTANTE! Creamos un Ubigeo de prueba
        // Si no hacemos esto, la validación 'exists:ubigeos' fallará.
        $this->ubigeoTest = '150103'; // Lince
        Ubigeo::create([
            'ubigeo' => $this->ubigeoTest,
            'departamento' => 'Lima',
            'provincia' => 'Lima',
            'distrito' => 'Lince',
        ]);
    }

    /** @test */
    public function it_can_create_an_address_for_an_entity()
    {
        $addressData = [
            'address' => 'Av. Arequipa 2545 Dpto. 302',
            'ubigeo' => $this->ubigeoTest,
            'reference' => 'Edificio blanco, portón negro',
            'phone' => '987654321',
            'label' => 'Casa',
        ];

        // Actuamos como el usuario autenticado (Sanctum)
        $response = $this->actingAs($this->user, 'sanctum')
                         ->postJson("/api/entities/{$this->entity->id}/addresses", $addressData);

        // Verificamos que la respuesta fue exitosa (201 Creado)
        $response->assertStatus(201);

        // Verificamos que la respuesta JSON tiene la estructura esperada
        $response->assertJson([
            'message' => 'Dirección creada exitosamente',
            'data' => [
                'address' => 'Av. Arequipa 2545 Dpto. 302',
                'label' => 'Casa',
                'ubigeo' => $this->ubigeoTest,
                'distrito' => 'Lince' // Verifica que trae los datos del ubigeo
            ]
        ]);

        // Verificamos que los datos se guardaron en la base de datos
        $this->assertDatabaseHas('addresses', [
            'entity_id' => $this->entity->id,
            'address' => 'Av. Arequipa 2545 Dpto. 302',
            'phone' => '987654321',
        ]);
    }

    /** @test */
    public function first_address_created_is_set_as_default()
    {
        $addressData = [
            'address' => 'Av. Arequipa 2545',
            'ubigeo' => $this->ubigeoTest,
            'is_default' => false // Enviamos 'false' a propósito
        ];

        $this->actingAs($this->user, 'sanctum')
             ->postJson("/api/entities/{$this->entity->id}/addresses", $addressData);

        // Verificamos que, aunque enviamos 'false', se marcó como 'true'
        // porque era la primera dirección.
        $this->assertDatabaseHas('addresses', [
            'entity_id' => $this->entity->id,
            'address' => 'Av. Arequipa 2545',
            'is_default' => true,
        ]);
    }

    /** @test */
    public function it_can_list_addresses_for_an_entity()
    {
        // Creamos 2 direcciones de prueba
        Address::factory()->create(['entity_id' => $this->entity->id, 'ubigeo' => $this->ubigeoTest]);
        Address::factory()->create(['entity_id' => $this->entity->id, 'ubigeo' => $this->ubigeoTest]);

        $response = $this->actingAs($this->user, 'sanctum')
                         ->getJson("/api/entities/{$this->entity->id}/addresses");
        
        $response->assertStatus(200);
        
        // Verificamos que la respuesta JSON contiene 2 items en 'data'
        $response->assertJsonCount(2, 'data');
    }

    /** @test */
    public function it_can_update_an_address()
    {
        $address = Address::factory()->create([
            'entity_id' => $this->entity->id,
            'ubigeo' => $this->ubigeoTest,
            'label' => 'Casa'
        ]);

        $updateData = [
            'label' => 'Oficina',
            'phone' => '111222333'
        ];

        $response = $this->actingAs($this->user, 'sanctum')
                         ->putJson("/api/addresses/{$address->id}", $updateData);

        $response->assertStatus(200);
        $response->assertJsonPath('data.label', 'Oficina');

        $this->assertDatabaseHas('addresses', [
            'id' => $address->id,
            'label' => 'Oficina',
            'phone' => '111222333'
        ]);
    }

    /** @test */
    public function it_can_delete_an_address()
    {
        $address = Address::factory()->create([
            'entity_id' => $this->entity->id,
            'ubigeo' => $this->ubigeoTest
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
    public function it_can_set_a_new_default_address()
    {
        // La primera se crea como default (lógica del Service)
        $address1 = Address::factory()->create([
            'entity_id' => $this->entity->id,
            'ubigeo' => $this->ubigeoTest
        ]);
        
        $address2 = Address::factory()->create([
            'entity_id' => $this->entity->id,
            'ubigeo' => $this->ubigeoTest,
            'is_default' => false
        ]);

        $this->assertTrue($address1->fresh()->is_default);
        $this->assertFalse($address2->fresh()->is_default);

        // Marcamos la segunda como predeterminada
        $response = $this->actingAs($this->user, 'sanctum')
                         ->patchJson("/api/addresses/{$address2->id}/set-default");
        
        $response->assertStatus(200);
        $response->assertJsonPath('data.is_default', true);

        // Verificamos que la primera se desmarcó
        $this->assertDatabaseHas('addresses', [
            'id' => $address1->id,
            'is_default' => false
        ]);
        
        // Verificamos que la segunda se marcó
        $this->assertDatabaseHas('addresses', [
            'id' => $address2->id,
            'is_default' => true
        ]);
    }

    /** @test */
    public function it_validates_required_fields_on_create()
    {
        $response = $this->actingAs($this->user, 'sanctum')
                         ->postJson("/api/entities/{$this->entity->id}/addresses", []);

        $response->assertStatus(422); // Error de validación
        
        // Verificamos que los campos requeridos están en los errores
        $response->assertJsonValidationErrors(['address', 'ubigeo']);
    }

    /** @test */
    public function it_validates_if_ubigeo_exists()
    {
        $addressData = [
            'address' => 'Av. Falsa 123',
            'ubigeo' => '999999', // Ubigeo que no existe
        ];
        
        $response = $this->actingAs($this->user, 'sanctum')
                         ->postJson("/api/entities/{$this->entity->id}/addresses", $addressData);
            
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('ubigeo');
        $response->assertJsonPath('errors.ubigeo.0', 'El ubigeo no existe en nuestra base de datos.');
    }
}