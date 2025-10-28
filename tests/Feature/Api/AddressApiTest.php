<?php

namespace Tests\Feature\Api;

use App\Models\Address;
use App\Models\Entity;
use App\Models\Ubigeo;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AddressApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Entity $entityPeru;
    protected Entity $entityUSA;
    protected string $ubigeoTestPeru;
    protected string $anotherUbigeoPeru;
    protected string $countryPeru = 'PE';
    protected string $countryUSA = 'US';

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->seed(CountrySeeder::class);

        // Crear Ubigeos Peruanos
        $this->ubigeoTestPeru = '150103'; // Lima - Lince
        Ubigeo::factory()->create([
            'ubigeo' => $this->ubigeoTestPeru,
            'country_code' => $this->countryPeru,
            'departamento' => 'Lima',
            'provincia' => 'Lima',
            'distrito' => 'Lince',
        ]);

        $this->anotherUbigeoPeru = '150101'; // Lima - Lima
        Ubigeo::factory()->create([
            'ubigeo' => $this->anotherUbigeoPeru,
            'country_code' => $this->countryPeru,
            'departamento' => 'Lima',
            'provincia' => 'Lima',
            'distrito' => 'Lima',
        ]);

        // Crear entidades
        $this->entityPeru = Entity::factory()->create([
            'user_id' => $this->user->id,
            'country_code' => $this->countryPeru,
            'ubigeo' => $this->ubigeoTestPeru,
        ]);

        $this->entityUSA = Entity::factory()->create([
            'user_id' => $this->user->id,
            'country_code' => $this->countryUSA,
            'ubigeo' => null,
            'tipo_documento' => '00',
            'numero_documento' => $this->faker->unique()->numerify('##########')
        ]);
    }

    #[Test]
    public function puede_crear_direccion_para_entidad_peruana_con_ubigeo()
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
            ->assertJsonPath('data.ubigeo', $this->ubigeoTestPeru);

        $this->assertDatabaseHas('addresses', [
            'entity_id' => $this->entityPeru->id,
            'address' => 'Av. Arequipa 2545 Dpto. 302',
            'country_code' => $this->countryPeru,
            'ubigeo' => $this->ubigeoTestPeru,
        ]);
    }

    #[Test]
    public function puede_crear_direccion_para_entidad_usa_sin_ubigeo()
    {
        $addressData = [
            'address' => '456 Freedom Ave',
            'country_code' => $this->countryUSA,
            'reference' => 'Near Central Park',
            'phone' => '5551234',
            'label' => 'Work',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/entities/{$this->entityUSA->id}/addresses", $addressData);

        $response->assertStatus(201)
            ->assertJsonPath('data.country_code', $this->countryUSA)
            ->assertJsonPath('data.ubigeo', null);

        $this->assertDatabaseHas('addresses', [
            'entity_id' => $this->entityUSA->id,
            'address' => '456 Freedom Ave',
            'country_code' => $this->countryUSA,
            'ubigeo' => null,
        ]);
    }

    #[Test]
    public function valida_ubigeo_requerido_cuando_pais_es_peru_al_crear()
    {
        $addressData = [
            'address' => 'Calle Falsa 123',
            'country_code' => $this->countryPeru,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/entities/{$this->entityPeru->id}/addresses", $addressData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ubigeo']);
    }

    #[Test]
    public function valida_ubigeo_no_requerido_cuando_pais_no_es_peru_al_crear()
    {
        $addressData = [
            'address' => 'Another St 789',
            'country_code' => $this->countryUSA,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/entities/{$this->entityUSA->id}/addresses", $addressData);

        $response->assertStatus(201);
        $response->assertJsonMissingValidationErrors(['ubigeo']);
    }

    #[Test]
    public function valida_codigo_pais_invalido_al_crear()
    {
        $addressData = [
            'address' => 'Invalid Country Addr',
            'country_code' => 'XX',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/entities/{$this->entityPeru->id}/addresses", $addressData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['country_code']);
    }

    #[Test]
    public function puede_actualizar_pais_de_direccion_de_peru_a_usa_anulando_ubigeo()
    {
        $address = Address::factory()->create([
            'entity_id' => $this->entityPeru->id,
            'country_code' => $this->countryPeru,
            'ubigeo' => $this->ubigeoTestPeru,
        ]);

        $updateData = [
            'country_code' => $this->countryUSA,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/addresses/{$address->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonPath('data.country_code', $this->countryUSA)
            ->assertJsonPath('data.ubigeo', null);

        $this->assertDatabaseHas('addresses', [
            'id' => $address->id,
            'country_code' => $this->countryUSA,
            'ubigeo' => null,
        ]);
    }

    #[Test]
    public function valida_ubigeo_al_actualizar_pais_de_direccion_a_peru()
    {
        $address = Address::factory()->create([
            'entity_id' => $this->entityUSA->id,
            'country_code' => $this->countryUSA,
            'ubigeo' => null,
        ]);

        $updateData = [
            'country_code' => $this->countryPeru,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/addresses/{$address->id}", $updateData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ubigeo']);
    }

    #[Test]
    public function permite_actualizar_ubigeo_dentro_de_peru()
    {
        $address = Address::factory()->create([
            'entity_id' => $this->entityPeru->id,
            'country_code' => $this->countryPeru,
            'ubigeo' => $this->ubigeoTestPeru,
        ]);

        $updateData = [
            'ubigeo' => $this->anotherUbigeoPeru,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/addresses/{$address->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonPath('data.ubigeo', $this->anotherUbigeoPeru)
            ->assertJsonPath('data.country_code', $this->countryPeru);

        $this->assertDatabaseHas('addresses', [
            'id' => $address->id,
            'ubigeo' => $this->anotherUbigeoPeru,
        ]);
    }

    #[Test]
    public function primera_direccion_creada_se_marca_como_predeterminada_para_entidad_peruana()
    {
        $entityPeruFresh = Entity::factory()->create([
            'user_id' => $this->user->id,
            'country_code' => $this->countryPeru,
            'ubigeo' => $this->ubigeoTestPeru,
        ]);

        $addressData = [
            'address' => 'Av. Arequipa 2545',
            'country_code' => $this->countryPeru,
            'ubigeo' => $this->ubigeoTestPeru,
            'is_default' => false
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/entities/{$entityPeruFresh->id}/addresses", $addressData);

        $response->assertStatus(201);

        $this->assertDatabaseHas('addresses', [
            'entity_id' => $entityPeruFresh->id,
            'address' => 'Av. Arequipa 2545',
            'is_default' => true,
        ]);
    }

    #[Test]
    public function puede_listar_direcciones_para_entidades_peru_y_usa()
    {
        Address::factory()->count(2)->create([
            'entity_id' => $this->entityPeru->id,
            'country_code' => $this->countryPeru,
            'ubigeo' => $this->ubigeoTestPeru
        ]);

        Address::factory()->create([
            'entity_id' => $this->entityUSA->id,
            'country_code' => $this->countryUSA,
            'ubigeo' => null
        ]);

        $responsePeru = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/entities/{$this->entityPeru->id}/addresses");

        $responsePeru->assertStatus(200)
            ->assertJsonCount(2, 'data');

        $responseUSA = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/entities/{$this->entityUSA->id}/addresses");

        $responseUSA->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function puede_actualizar_detalles_de_direccion_para_entidad_peruana()
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
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/addresses/{$address->id}", $updateData);

        $response->assertStatus(200); // ✅ Ahora pasa
    }

    #[Test]
    public function puede_eliminar_direccion_para_entidad_peruana()
    {
        $address = Address::factory()->create([
            'entity_id' => $this->entityPeru->id,
            'country_code' => $this->countryPeru,
            'ubigeo' => $this->ubigeoTestPeru
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/addresses/{$address->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Dirección eliminada exitosamente');

        $this->assertDatabaseMissing('addresses', [
            'id' => $address->id
        ]);
    }

    #[Test]
    public function puede_establecer_nueva_direccion_predeterminada_para_entidad_peruana()
    {
        $entityPeruFresh = Entity::factory()->create([
            'user_id' => $this->user->id,
            'country_code' => $this->countryPeru,
            'ubigeo' => $this->ubigeoTestPeru,
        ]);

        $address1 = Address::factory()->create([
            'entity_id' => $entityPeruFresh->id,
            'country_code' => $this->countryPeru,
            'ubigeo' => $this->ubigeoTestPeru,
            'is_default' => true
        ]);

        $address2 = Address::factory()->create([
            'entity_id' => $entityPeruFresh->id,
            'country_code' => $this->countryPeru,
            'ubigeo' => $this->anotherUbigeoPeru,
            'is_default' => false
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/addresses/{$address2->id}/set-default");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_default', true);

        $this->assertDatabaseHas('addresses', [
            'id' => $address1->id,
            'is_default' => false
        ]);

        $this->assertDatabaseHas('addresses', [
            'id' => $address2->id,
            'is_default' => true
        ]);
    }

    #[Test]
    public function valida_campos_requeridos_al_crear_para_entidad_peruana()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/entities/{$this->entityPeru->id}/addresses", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['address']);
    }

    #[Test]
    public function valida_si_ubigeo_existe_para_entidad_peruana()
    {
        $addressData = [
            'address' => 'Av. Falsa 123',
            'country_code' => $this->countryPeru,
            'ubigeo' => '999999',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/entities/{$this->entityPeru->id}/addresses", $addressData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('ubigeo');
    }

    #[Test]
    public function puede_mostrar_direccion_individual_con_datos_pais_y_ubigeo()
    {
        $address = Address::factory()->create([
            'entity_id' => $this->entityPeru->id,
            'country_code' => $this->countryPeru,
            'ubigeo' => $this->ubigeoTestPeru,
            'label' => 'Principal'
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/addresses/{$address->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.country_code', $this->countryPeru)
            ->assertJsonPath('data.ubigeo', $this->ubigeoTestPeru)
            ->assertJsonPath('data.label', 'Principal');
    }

    #[Test]
    public function retorna_404_al_mostrar_direccion_inexistente()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/addresses/99999");

        $response->assertStatus(404);
    }
}
