<?php

namespace Tests\Feature\Api;

use App\Models\Entity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntityApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        // Creamos un usuario para autenticar las peticiones
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_can_create_a_customer_entity()
    {
        $entityData = [
            'type' => 'customer',
            'tipo_documento' => '01', // DNI
            'numero_documento' => '12345678',
            'tipo_persona' => 'natural',
            'first_name' => 'Juan',
            'last_name' => 'Perez',
            'email' => 'juan@perez.com',
            'phone' => '987654321',
        ];

        // Actuamos como el usuario autenticado (Sanctum)
        $response = $this->actingAs($this->user, 'sanctum')
                         ->postJson('/api/entities', $entityData);

        // Verificamos que fue exitoso (201 Creado)
        $response->assertStatus(201);
        
        // Verificamos la estructura de la respuesta JSON
        $response->assertJson([
            'message' => 'Cliente creado exitosamente',
            'data' => [
                'tipo_documento' => '01',
                'numero_documento' => '12345678',
                'full_name' => 'Juan Perez',
            ]
        ]);

        // Verificamos que se guardó en la base de datos
        $this->assertDatabaseHas('entities', [
            'numero_documento' => '12345678',
            'email' => 'juan@perez.com',
            'user_id' => $this->user->id, // Verifica que se asignó el usuario
        ]);
    }

    /** @test */
    public function it_can_list_entities()
    {
        // Creamos 3 clientes y 2 proveedores
        Entity::factory()->count(3)->create(['type' => 'customer']);
        Entity::factory()->count(2)->create(['type' => 'supplier', 'tipo_persona' => 'juridica']);

        $response = $this->actingAs($this->user, 'sanctum')
                         ->getJson('/api/entities');

        $response->assertStatus(200);
        
        // Debe listar los 5
        $response->assertJsonCount(5, 'data');
        
        // Probamos el filtro de 'supplier'
        $responseSuppliers = $this->actingAs($this->user, 'sanctum')
                                  ->getJson('/api/entities?type=supplier');
        
        $responseSuppliers->assertStatus(200);
        $responseSuppliers->assertJsonCount(2, 'data');
    }

    /** @test */
    public function it_validates_required_fields_for_supplier()
    {
        // Los proveedores requieren RUC (06) y son 'juridica'
        $supplierData = [
            'type' => 'supplier',
            'tipo_documento' => '01', // DNI (Incorrecto para proveedor)
            'numero_documento' => '12345678',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
                         ->postJson('/api/entities', $supplierData);
        
        $response->assertStatus(422); // Error de validación
        
        // Verifica los mensajes de error de tu StoreEntityRequest
        $response->assertJsonValidationErrors(['tipo_documento', 'business_name', 'email', 'phone', 'address']);
        $response->assertJsonPath('errors.tipo_documento.0', 'Los proveedores deben tener RUC (tipo_documento = 06).');
    }
}