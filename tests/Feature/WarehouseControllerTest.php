<?php

namespace Tests\Feature;

use App\Models\Warehouse;
use App\Models\Ubigeo;
use App\Models\Inventory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear usuario autenticado
        $this->user = User::factory()->create();

        // Crear ubigeos de prueba
        Ubigeo::create([
            'ubigeo' => '150101',
            'departamento' => 'Lima',
            'provincia' => 'Lima',
            'distrito' => 'Lima',
        ]);

        Ubigeo::create([
            'ubigeo' => '150102',
            'departamento' => 'Lima',
            'provincia' => 'Lima',
            'distrito' => 'Ancón',
        ]);

        Ubigeo::create([
            'ubigeo' => '150103',
            'departamento' => 'Lima',
            'provincia' => 'Lima',
            'distrito' => 'Ate',
        ]);
    }

    /** @test */
    public function puede_crear_almacen_exitosamente()
    {
        $data = [
            'name' => 'Almacén Principal',
            'ubigeo' => '150101',
            'address' => 'Av. Ejemplo 123, Lima',
            'phone' => '987654321',
            'is_main' => true,
            'visible_online' => true,
            'picking_priority' => 1,
        ];

        $response = $this->actingAs($this->user)->postJson('/api/warehouses', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'ubigeo',
                    'address',
                    'phone',
                    'is_main',
                    'is_active',
                    'visible_online',
                    'picking_priority',
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Almacén creado exitosamente',
                'data' => [
                    'name' => 'Almacén Principal',
                    'ubigeo' => '150101',
                    'is_main' => true,
                ],
            ]);

        $this->assertDatabaseHas('warehouses', [
            'name' => 'Almacén Principal',
            'ubigeo' => '150101',
            'is_main' => true,
        ]);
    }

    /** @test */
    public function puede_crear_almacen_con_campos_minimos_requeridos()
    {
        $data = [
            'name' => 'Almacén Simple',
            'ubigeo' => '150101',
            'address' => 'Av. Test 123',
        ];

        $response = $this->actingAs($this->user)->postJson('/api/warehouses', $data);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Almacén Simple',
                    'is_active' => true,
                    'visible_online' => true,
                    'picking_priority' => 0,
                    'is_main' => false,
                ],
            ]);
    }

    /** @test */
    public function valida_que_nombre_sea_requerido()
    {
        $data = [
            'ubigeo' => '150101',
            'address' => 'Av. Test 123',
        ];

        $response = $this->actingAs($this->user)->postJson('/api/warehouses', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name'])
            ->assertJson([
                'errors' => [
                    'name' => ['El nombre del almacén es obligatorio'],
                ],
            ]);
    }

    /** @test */
    public function valida_que_ubigeo_sea_requerido()
    {
        $data = [
            'name' => 'Almacén Test',
            'address' => 'Av. Test 123',
        ];

        $response = $this->actingAs($this->user)->postJson('/api/warehouses', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ubigeo']);
    }

    /** @test */
    public function valida_que_direccion_sea_requerida()
    {
        $data = [
            'name' => 'Almacén Test',
            'ubigeo' => '150101',
        ];

        $response = $this->actingAs($this->user)->postJson('/api/warehouses', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['address']);
    }

    /** @test */
    public function valida_nombre_unico_de_almacen()
    {
        Warehouse::factory()->create([
            'name' => 'Almacén Existente',
            'ubigeo' => '150101',
        ]);

        $data = [
            'name' => 'Almacén Existente',
            'ubigeo' => '150102',
            'address' => 'Av. Test 123',
        ];

        $response = $this->actingAs($this->user)->postJson('/api/warehouses', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name'])
            ->assertJson([
                'errors' => [
                    'name' => ['Ya existe un almacén con este nombre'],
                ],
            ]);
    }

    /** @test */
    public function valida_que_ubigeo_exista_en_base_de_datos()
    {
        $data = [
            'name' => 'Almacén Test',
            'ubigeo' => '999999',
            'address' => 'Av. Test 123',
        ];

        $response = $this->actingAs($this->user)->postJson('/api/warehouses', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ubigeo'])
            ->assertJson([
                'errors' => [
                    'ubigeo' => ['El ubigeo ingresado no es válido'],
                ],
            ]);
    }

    /** @test */
    public function valida_que_ubigeo_tenga_6_digitos()
    {
        $data = [
            'name' => 'Almacén Test',
            'ubigeo' => '1501',
            'address' => 'Av. Test 123',
        ];

        $response = $this->actingAs($this->user)->postJson('/api/warehouses', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ubigeo']);
    }

    /** @test */
    public function valida_longitud_maxima_de_nombre()
    {
        $data = [
            'name' => str_repeat('A', 256),
            'ubigeo' => '150101',
            'address' => 'Av. Test 123',
        ];

        $response = $this->actingAs($this->user)->postJson('/api/warehouses', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function valida_longitud_maxima_de_direccion()
    {
        $data = [
            'name' => 'Almacén Test',
            'ubigeo' => '150101',
            'address' => str_repeat('A', 501),
        ];

        $response = $this->actingAs($this->user)->postJson('/api/warehouses', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['address']);
    }

    /** @test */
    public function valida_longitud_maxima_de_telefono()
    {
        $data = [
            'name' => 'Almacén Test',
            'ubigeo' => '150101',
            'address' => 'Av. Test 123',
            'phone' => str_repeat('9', 21),
        ];

        $response = $this->actingAs($this->user)->postJson('/api/warehouses', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    /** @test */
    public function valida_que_prioridad_sea_numero_entero()
    {
        $data = [
            'name' => 'Almacén Test',
            'ubigeo' => '150101',
            'address' => 'Av. Test 123',
            'picking_priority' => 'abc',
        ];

        $response = $this->actingAs($this->user)->postJson('/api/warehouses', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['picking_priority']);
    }

    /** @test */
    public function valida_que_prioridad_este_entre_0_y_100()
    {
        $data = [
            'name' => 'Almacén Test',
            'ubigeo' => '150101',
            'address' => 'Av. Test 123',
            'picking_priority' => 101,
        ];

        $response = $this->actingAs($this->user)->postJson('/api/warehouses', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['picking_priority']);

        $data['picking_priority'] = -1;
        $response = $this->actingAs($this->user)->postJson('/api/warehouses', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['picking_priority']);
    }

    /** @test */
    public function desmarca_almacen_principal_anterior_al_crear_uno_nuevo()
    {
        $oldMain = Warehouse::factory()->create([
            'name' => 'Almacén Anterior',
            'ubigeo' => '150101',
            'is_main' => true,
        ]);

        $data = [
            'name' => 'Nuevo Almacén Principal',
            'ubigeo' => '150102',
            'address' => 'Av. Nueva 456',
            'is_main' => true,
        ];

        $response = $this->actingAs($this->user)->postJson('/api/warehouses', $data);

        $response->assertStatus(201);

        $oldMain->refresh();
        $this->assertFalse($oldMain->is_main);

        $this->assertDatabaseHas('warehouses', [
            'name' => 'Nuevo Almacén Principal',
            'is_main' => true,
        ]);
    }

    /** @test */
    public function puede_listar_todos_los_almacenes()
    {
        Warehouse::factory()->count(3)->create(['ubigeo' => '150101']);

        $response = $this->actingAs($this->user)->getJson('/api/warehouses');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'ubigeo',
                        'address',
                        'is_main',
                        'is_active',
                        'visible_online',
                        'picking_priority',
                    ],
                ],
                'meta' => ['total'],
            ])
            ->assertJson([
                'success' => true,
            ]);

        $this->assertEquals(3, $response->json('meta.total'));
    }

    /** @test */
    public function puede_filtrar_almacenes_por_estado_activo()
    {
        Warehouse::factory()->create([
            'name' => 'Activo',
            'ubigeo' => '150101',
            'is_active' => true,
        ]);

        Warehouse::factory()->create([
            'name' => 'Inactivo',
            'ubigeo' => '150101',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/warehouses?is_active=1');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals('Activo', $response->json('data.0.name'));
    }

    /** @test */
    public function puede_filtrar_almacenes_por_visibilidad_online()
    {
        Warehouse::factory()->create([
            'name' => 'Visible',
            'ubigeo' => '150101',
            'visible_online' => true,
        ]);

        Warehouse::factory()->create([
            'name' => 'No Visible',
            'ubigeo' => '150101',
            'visible_online' => false,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/warehouses?visible_online=1');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals('Visible', $response->json('data.0.name'));
    }

    /** @test */
    public function puede_filtrar_almacen_principal()
    {
        Warehouse::factory()->create([
            'name' => 'Principal',
            'ubigeo' => '150101',
            'is_main' => true,
        ]);

        Warehouse::factory()->create([
            'name' => 'Secundario',
            'ubigeo' => '150101',
            'is_main' => false,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/warehouses?is_main=1');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals('Principal', $response->json('data.0.name'));
    }

    /** @test */
    public function ordena_almacenes_por_prioridad_descendente()
    {
        Warehouse::factory()->create([
            'name' => 'Baja',
            'ubigeo' => '150101',
            'picking_priority' => 1,
        ]);

        Warehouse::factory()->create([
            'name' => 'Alta',
            'ubigeo' => '150101',
            'picking_priority' => 10,
        ]);

        Warehouse::factory()->create([
            'name' => 'Media',
            'ubigeo' => '150101',
            'picking_priority' => 5,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/warehouses');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals('Alta', $data[0]['name']);
        $this->assertEquals('Media', $data[1]['name']);
        $this->assertEquals('Baja', $data[2]['name']);
    }

    /** @test */
    public function puede_obtener_detalle_de_almacen()
    {
        $warehouse = Warehouse::factory()->create([
            'name' => 'Almacén Detalle',
            'ubigeo' => '150101',
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/warehouses/{$warehouse->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $warehouse->id,
                    'name' => 'Almacén Detalle',
                    'ubigeo' => '150101',
                ],
            ]);
    }

    /** @test */
    public function incluye_datos_de_ubigeo_en_detalle()
    {
        $warehouse = Warehouse::factory()->create(['ubigeo' => '150101']);

        $response = $this->actingAs($this->user)->getJson("/api/warehouses/{$warehouse->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'ubigeo_data' => [
                        'ubigeo',
                        'departamento',
                        'provincia',
                        'distrito',
                    ],
                ],
            ]);
    }

    /** @test */
    public function retorna_404_si_almacen_no_existe()
    {
        $response = $this->actingAs($this->user)->getJson('/api/warehouses/999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'WAREHOUSE_NOT_FOUND',
                    'message' => 'Almacén con ID 999 no encontrado',
                ],
            ]);
    }

    /** @test */
    public function puede_actualizar_almacen_completamente_con_put()
    {
        $warehouse = Warehouse::factory()->create([
            'name' => 'Nombre Original',
            'ubigeo' => '150101',
            'address' => 'Dirección Original',
            'picking_priority' => 1,
        ]);

        $data = [
            'name' => 'Nombre Actualizado',
            'ubigeo' => '150102',
            'address' => 'Nueva Dirección',
            'picking_priority' => 5,
        ];

        $response = $this->actingAs($this->user)->putJson("/api/warehouses/{$warehouse->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Almacén actualizado exitosamente',
                'data' => [
                    'name' => 'Nombre Actualizado',
                    'ubigeo' => '150102',
                    'address' => 'Nueva Dirección',
                    'picking_priority' => 5,
                ],
            ]);

        $this->assertDatabaseHas('warehouses', [
            'id' => $warehouse->id,
            'name' => 'Nombre Actualizado',
        ]);
    }

    /** @test */
    public function puede_actualizar_almacen_parcialmente_con_patch()
    {
        $warehouse = Warehouse::factory()->create([
            'name' => 'Nombre Original',
            'ubigeo' => '150101',
            'address' => 'Dirección Original',
            'picking_priority' => 1,
        ]);

        $data = [
            'picking_priority' => 10,
        ];

        $response = $this->actingAs($this->user)->patchJson("/api/warehouses/{$warehouse->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Nombre Original',
                    'picking_priority' => 10,
                ],
            ]);

        $this->assertDatabaseHas('warehouses', [
            'id' => $warehouse->id,
            'name' => 'Nombre Original',
            'picking_priority' => 10,
        ]);
    }

    /** @test */
    public function puede_actualizar_solo_el_nombre()
    {
        $warehouse = Warehouse::factory()->create([
            'name' => 'Nombre Viejo',
            'ubigeo' => '150101',
        ]);

        $response = $this->actingAs($this->user)->patchJson("/api/warehouses/{$warehouse->id}", [
            'name' => 'Nombre Nuevo',
        ]);

        $response->assertStatus(200);
        $warehouse->refresh();
        $this->assertEquals('Nombre Nuevo', $warehouse->name);
    }

    /** @test */
    public function valida_nombre_unico_al_actualizar_excluyendo_el_mismo_almacen()
    {
        $warehouse1 = Warehouse::factory()->create([
            'name' => 'Almacén 1',
            'ubigeo' => '150101',
        ]);

        $warehouse2 = Warehouse::factory()->create([
            'name' => 'Almacén 2',
            'ubigeo' => '150101',
        ]);

        $response = $this->actingAs($this->user)->patchJson("/api/warehouses/{$warehouse2->id}", [
            'name' => 'Almacén 1',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function permite_actualizar_con_el_mismo_nombre()
    {
        $warehouse = Warehouse::factory()->create([
            'name' => 'Mi Almacén',
            'ubigeo' => '150101',
        ]);

        $response = $this->actingAs($this->user)->patchJson("/api/warehouses/{$warehouse->id}", [
            'name' => 'Mi Almacén',
            'picking_priority' => 5,
        ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function desmarca_almacen_principal_anterior_al_actualizar()
    {
        $oldMain = Warehouse::factory()->create([
            'name' => 'Antiguo Principal',
            'ubigeo' => '150101',
            'is_main' => true,
        ]);

        $newMain = Warehouse::factory()->create([
            'name' => 'Será Principal',
            'ubigeo' => '150102',
            'is_main' => false,
        ]);

        $response = $this->actingAs($this->user)->patchJson("/api/warehouses/{$newMain->id}", [
            'is_main' => true,
        ]);

        $response->assertStatus(200);

        $oldMain->refresh();
        $this->assertFalse($oldMain->is_main);

        $newMain->refresh();
        $this->assertTrue($newMain->is_main);
    }

    /** @test */
    public function puede_desactivar_almacen()
    {
        $warehouse = Warehouse::factory()->create([
            'ubigeo' => '150101',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->patchJson("/api/warehouses/{$warehouse->id}", [
            'is_active' => false,
        ]);

        $response->assertStatus(200);
        $warehouse->refresh();
        $this->assertFalse($warehouse->is_active);
    }

    /** @test */
    public function puede_ocultar_almacen_de_ecommerce()
    {
        $warehouse = Warehouse::factory()->create([
            'ubigeo' => '150101',
            'visible_online' => true,
        ]);

        $response = $this->actingAs($this->user)->patchJson("/api/warehouses/{$warehouse->id}", [
            'visible_online' => false,
        ]);

        $response->assertStatus(200);
        $warehouse->refresh();
        $this->assertFalse($warehouse->visible_online);
    }

    /** @test */
    public function puede_eliminar_almacen_sin_inventario()
    {
        $warehouse = Warehouse::factory()->create(['ubigeo' => '150101']);

        $response = $this->actingAs($this->user)->deleteJson("/api/warehouses/{$warehouse->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Almacén eliminado exitosamente',
            ]);

        // ✅ Cambiar a assertSoftDeleted en lugar de assertDatabaseMissing
        $this->assertSoftDeleted('warehouses', [
            'id' => $warehouse->id,
        ]);
    }

    /** @test */
    /** @test */
    public function no_puede_eliminar_almacen_con_inventario()
    {

        $this->markTestSkipped('Se omite temporalmente esta prueba hasta tener producto listo.');

        $warehouse = Warehouse::factory()->create(['ubigeo' => '150101']);

        Inventory::factory()->create(['warehouse_id' => $warehouse->id]);

        $response = $this->actingAs($this->user)->deleteJson("/api/warehouses/{$warehouse->id}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'WAREHOUSE_HAS_INVENTORY',
                ],
            ]);

        // ✅ Verificar que NO fue eliminado (ni soft delete)
        $this->assertDatabaseHas('warehouses', [
            'id' => $warehouse->id,
            'deleted_at' => null, // ✅ Asegurar que no fue soft deleted
        ]);
    }

    /** @test */
    public function retorna_404_al_intentar_eliminar_almacen_inexistente()
    {
        $response = $this->actingAs($this->user)->deleteJson('/api/warehouses/999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'WAREHOUSE_NOT_FOUND',
                ],
            ]);
    }

    /** @test */
    public function establece_valores_por_defecto_al_crear()
    {
        $data = [
            'name' => 'Almacén Simple',
            'ubigeo' => '150101',
            'address' => 'Av. Test 123',
        ];

        $response = $this->actingAs($this->user)->postJson('/api/warehouses', $data);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_active' => true,
                    'visible_online' => true,
                    'picking_priority' => 0,
                    'is_main' => false,
                ],
            ]);
    }

    /** @test */
    public function permite_crear_almacen_sin_telefono()
    {
        $data = [
            'name' => 'Sin Teléfono',
            'ubigeo' => '150101',
            'address' => 'Av. Test 123',
        ];

        $response = $this->actingAs($this->user)->postJson('/api/warehouses', $data);

        $response->assertStatus(201);
        $this->assertDatabaseHas('warehouses', [
            'name' => 'Sin Teléfono',
            'phone' => null,
        ]);
    }

    /** @test */
    public function puede_crear_multiples_almacenes_no_principales()
    {
        $data1 = [
            'name' => 'Almacén 1',
            'ubigeo' => '150101',
            'address' => 'Dirección 1',
            'is_main' => false,
        ];

        $data2 = [
            'name' => 'Almacén 2',
            'ubigeo' => '150102',
            'address' => 'Dirección 2',
            'is_main' => false,
        ];

        $response1 = $this->actingAs($this->user)->postJson('/api/warehouses', $data1);
        $response2 = $this->actingAs($this->user)->postJson('/api/warehouses', $data2);

        $response1->assertStatus(201);
        $response2->assertStatus(201);

        $this->assertEquals(2, Warehouse::where('is_main', false)->count());
    }

    /** @test */
    public function solo_permite_un_almacen_principal_a_la_vez()
    {
        $principal1 = Warehouse::factory()->create([
            'name' => 'Principal 1',
            'ubigeo' => '150101',
            'is_main' => true,
        ]);

        // ✅ Cuando se crea el segundo principal, el primero debe desmarcarse
        $data = [
            'name' => 'Principal 2',
            'ubigeo' => '150102',
            'address' => 'Dirección 2',
            'is_main' => true,
        ];

        $response = $this->actingAs($this->user)->postJson('/api/warehouses', $data);

        $response->assertStatus(201);

        // ✅ Verificar que solo hay uno principal
        $this->assertEquals(1, Warehouse::where('is_main', true)->count());

        // ✅ Verificar que el nuevo es el principal
        $this->assertTrue(Warehouse::where('name', 'Principal 2')->first()->is_main);

        // ✅ Verificar que el anterior ya no es principal
        $principal1->refresh();
        $this->assertFalse($principal1->is_main);
    }

    /** @test */
    public function formato_de_respuesta_incluye_fechas_iso()
    {
        $warehouse = Warehouse::factory()->create(['ubigeo' => '150101']);

        $response = $this->actingAs($this->user)->getJson("/api/warehouses/{$warehouse->id}");

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertNotNull($data['created_at']);
        $this->assertNotNull($data['updated_at']);
        $this->assertStringContainsString('T', $data['created_at']);
    }
}
