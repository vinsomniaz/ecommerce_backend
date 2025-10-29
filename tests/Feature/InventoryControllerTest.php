<?php
// tests/Feature/InventoryControllerTest.php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Inventory;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;

class InventoryControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Product $product;
    protected Warehouse $warehouse;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear usuario y autenticar con Sanctum
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);

        // Crear categoría
        $this->category = Category::factory()->create(['name' => 'Electrónica']);

        // Crear productos de prueba
        $this->product = Product::factory()->create([
            'sku' => 'TEST-001',
            'primary_name' => 'Laptop Test',
            'category_id' => $this->category->id,
            'min_stock' => 10,
            'is_active' => true,
        ]);

        // Crear almacenes de prueba
        $this->warehouse = Warehouse::factory()->create([
            'name' => 'Almacén Principal',
            'is_main' => true,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function puede_listar_todo_el_inventario()
    {
        // Arrange: Crear inventario
        Inventory::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 50,
            'reserved_stock' => 5,
            'sale_price' => 999.99,
            'profit_margin' => 30,
            'last_movement_at' => now(),
        ]);

        // Act: Llamar al endpoint
        $response = $this->getJson('/api/inventory');

        // Assert: Verificar respuesta
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'product_id',
                        'warehouse_id',
                        'product',
                        'warehouse',
                        'available_stock',
                        'reserved_stock',
                        'total_stock',
                        'sale_price',
                    ]
                ],
                'meta' => ['current_page', 'per_page', 'total', 'last_page']
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    #[Test]
    public function puede_filtrar_inventario_por_almacen()
    {
        // Arrange
        $warehouse2 = Warehouse::factory()->create(['name' => 'Almacén Secundario']);

        Inventory::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 50,
            'reserved_stock' => 0,
            'last_movement_at' => now(),
        ]);

        Inventory::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $warehouse2->id,
            'available_stock' => 30,
            'reserved_stock' => 0,
            'last_movement_at' => now(),
        ]);

        // Act
        $response = $this->getJson("/api/inventory?warehouse_id={$this->warehouse->id}");

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.warehouse_id', $this->warehouse->id);
    }

    #[Test]
    public function puede_filtrar_inventario_con_stock()
    {
        // Arrange: Crear inventario con y sin stock
        Inventory::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 50,
            'reserved_stock' => 0,
            'last_movement_at' => now(),
        ]);

        $product2 = Product::factory()->create(['sku' => 'TEST-002', 'category_id' => $this->category->id]);
        Inventory::create([
            'product_id' => $product2->id,
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 0,
            'reserved_stock' => 0,
            'last_movement_at' => now(),
        ]);

        // Act
        $response = $this->getJson('/api/inventory?with_stock=true');

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function puede_filtrar_inventario_sin_stock()
    {
        // Arrange
        Inventory::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 0,
            'reserved_stock' => 0,
            'last_movement_at' => now(),
        ]);

        $product2 = Product::factory()->create(['sku' => 'TEST-002', 'category_id' => $this->category->id]);
        Inventory::create([
            'product_id' => $product2->id,
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 50,
            'reserved_stock' => 0,
            'last_movement_at' => now(),
        ]);

        // Act
        $response = $this->getJson('/api/inventory?out_of_stock=true');

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.available_stock', 0);
    }

    #[Test]
    public function puede_filtrar_inventario_con_stock_bajo()
    {
        // Arrange: Producto con stock bajo
        Inventory::create([
            'product_id' => $this->product->id, // min_stock = 10
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 5, // Menor al mínimo
            'reserved_stock' => 0,
            'last_movement_at' => now(),
        ]);

        // Producto con stock normal
        $product2 = Product::factory()->create([
            'sku' => 'TEST-002',
            'category_id' => $this->category->id,
            'min_stock' => 10
        ]);
        Inventory::create([
            'product_id' => $product2->id,
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 50,
            'reserved_stock' => 0,
            'last_movement_at' => now(),
        ]);

        // Act
        $response = $this->getJson('/api/inventory?low_stock=true');

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function puede_buscar_productos_en_inventario()
    {
        // Arrange
        Inventory::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 50,
            'reserved_stock' => 0,
            'last_movement_at' => now(),
        ]);

        // Act
        $response = $this->getJson('/api/inventory?search=Laptop');

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.product.primary_name', 'Laptop Test');
    }

    #[Test]
    public function puede_obtener_inventario_por_producto()
    {
        // Arrange: Crear inventario en múltiples almacenes
        $warehouse2 = Warehouse::factory()->create(['name' => 'Almacén Norte']);

        Inventory::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 50,
            'reserved_stock' => 5,
            'sale_price' => 999.99,
            'last_movement_at' => now(),
        ]);

        Inventory::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $warehouse2->id,
            'available_stock' => 30,
            'reserved_stock' => 0,
            'sale_price' => 950.00,
            'last_movement_at' => now(),
        ]);

        // Act
        $response = $this->getJson("/api/products/{$this->product->id}/inventory");

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'product_id',
                        'warehouse_id',
                        'warehouse',
                        'available_stock',
                    ]
                ],
                'meta' => ['total_warehouses', 'product_id', 'product_name']
            ])
            ->assertJsonPath('meta.total_warehouses', 2)
            ->assertJsonPath('meta.product_id', $this->product->id);
    }

    #[Test]
    public function puede_obtener_inventario_por_almacen()
    {
        // Arrange: Crear múltiples productos en un almacén
        $product2 = Product::factory()->create([
            'sku' => 'TEST-002',
            'category_id' => $this->category->id
        ]);

        Inventory::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 50,
            'reserved_stock' => 0,
            'last_movement_at' => now(),
        ]);

        Inventory::create([
            'product_id' => $product2->id,
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 30,
            'reserved_stock' => 0,
            'last_movement_at' => now(),
        ]);

        // Act
        $response = $this->getJson("/api/warehouses/{$this->warehouse->id}/inventory");

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => ['current_page', 'total', 'warehouse_id', 'warehouse_name']
            ])
            ->assertJsonPath('meta.warehouse_id', $this->warehouse->id);
    }

    #[Test]
    public function puede_obtener_inventario_especifico()
    {
        // Arrange
        Inventory::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 50,
            'reserved_stock' => 5,
            'sale_price' => 999.99,
            'profit_margin' => 30,
            'last_movement_at' => now(),
        ]);

        // Act
        $response = $this->getJson("/api/inventory/{$this->product->id}/{$this->warehouse->id}");

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'product_id' => $this->product->id,
                    'warehouse_id' => $this->warehouse->id,
                    'available_stock' => 50,
                    'reserved_stock' => 5,
                ]
            ]);
    }

    #[Test]
    public function retorna_404_si_inventario_no_existe()
    {
        // Act
        $response = $this->getJson("/api/inventory/{$this->product->id}/{$this->warehouse->id}");

        // Assert
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
            ]);
    }

    #[Test]
    public function puede_asignar_producto_a_un_almacen()
    {
        // Arrange
        $data = [
            'product_id' => $this->product->id,
            'warehouse_ids' => [$this->warehouse->id],
            'sale_price' => 999.99,
            'profit_margin' => 30,
            'min_sale_price' => 850.00
        ];

        // Act
        $response = $this->postJson('/api/inventory', $data);

        // Assert
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Producto asignado a 1 almacén(es) exitosamente',
                'data' => [
                    'assigned' => 1,
                    'skipped' => 0,
                    'total' => 1,
                ]
            ]);

        $this->assertDatabaseHas('inventory', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 0,
            'sale_price' => 999.99,
        ]);
    }

    #[Test]
    public function puede_asignar_producto_a_multiples_almacenes()
    {
        // Arrange
        $warehouse2 = Warehouse::factory()->create(['name' => 'Almacén Norte']);
        $warehouse3 = Warehouse::factory()->create(['name' => 'Almacén Sur']);

        $data = [
            'product_id' => $this->product->id,
            'warehouse_ids' => [$this->warehouse->id, $warehouse2->id, $warehouse3->id],
            'sale_price' => 999.99
        ];

        // Act
        $response = $this->postJson('/api/inventory', $data);

        // Assert
        $response->assertStatus(201)
            ->assertJsonPath('data.assigned', 3)
            ->assertJsonPath('data.total', 3);

        $this->assertDatabaseCount('inventory', 3);
    }

    #[Test]
    public function omite_asignacion_si_inventario_ya_existe()
    {
        // Arrange: Crear inventario existente
        Inventory::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 10,
            'reserved_stock' => 0,
            'last_movement_at' => now()
        ]);

        $warehouse2 = Warehouse::factory()->create();

        $data = [
            'product_id' => $this->product->id,
            'warehouse_ids' => [$this->warehouse->id, $warehouse2->id],
        ];

        // Act
        $response = $this->postJson('/api/inventory', $data);

        // Assert
        $response->assertStatus(201)
            ->assertJsonPath('data.assigned', 1)
            ->assertJsonPath('data.skipped', 1)
            ->assertJsonPath('data.total', 2);
    }

    #[Test]
    public function valida_que_producto_sea_requerido_al_asignar()
    {
        // Arrange
        $data = [
            'warehouse_ids' => [$this->warehouse->id],
        ];

        // Act
        $response = $this->postJson('/api/inventory', $data);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['product_id']);
    }

    #[Test]
    public function valida_que_almacenes_sean_requeridos_al_asignar()
    {
        // Arrange
        $data = [
            'product_id' => $this->product->id,
        ];

        // Act
        $response = $this->postJson('/api/inventory', $data);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['warehouse_ids']);
    }

    #[Test]
    public function valida_que_producto_exista_al_asignar()
    {
        // Arrange
        $data = [
            'product_id' => 99999,
            'warehouse_ids' => [$this->warehouse->id],
        ];

        // Act
        $response = $this->postJson('/api/inventory', $data);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['product_id']);
    }

    #[Test]
    public function puede_hacer_asignacion_masiva()
    {
        // Arrange
        $product2 = Product::factory()->create(['sku' => 'TEST-002', 'category_id' => $this->category->id]);
        $warehouse2 = Warehouse::factory()->create();

        $data = [
            'product_ids' => [$this->product->id, $product2->id],
            'warehouse_ids' => [$this->warehouse->id, $warehouse2->id],
            'sale_price' => 500.00
        ];

        // Act
        $response = $this->postJson('/api/inventory/bulk-assign', $data);

        // Assert
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_assigned' => 4, // 2 productos x 2 almacenes
                    'products_processed' => 2,
                    'warehouses_targeted' => 2,
                ]
            ]);

        $this->assertDatabaseCount('inventory', 4);
    }

    #[Test]
    public function puede_actualizar_precios_de_inventario()
    {
        // Arrange
        Inventory::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 50,
            'reserved_stock' => 0,
            'sale_price' => 999.99,
            'profit_margin' => 30,
            'last_movement_at' => now()
        ]);

        $data = [
            'sale_price' => 1199.99,
            'profit_margin' => 35,
            'min_sale_price' => 1000.00,
        ];

        // Act
        $response = $this->patchJson(
            "/api/inventory/{$this->product->id}/{$this->warehouse->id}",
            $data
        );

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Inventario actualizado exitosamente',
            ]);

        $this->assertDatabaseHas('inventory', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'sale_price' => 1199.99,
            'profit_margin' => 35,
        ]);
    }

    #[Test]
    public function puede_eliminar_inventario_sin_stock()
    {
        // Arrange
        Inventory::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 0,
            'reserved_stock' => 0,
            'last_movement_at' => now()
        ]);

        // Act
        $response = $this->deleteJson("/api/inventory/{$this->product->id}/{$this->warehouse->id}");

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Producto desasignado del almacén exitosamente',
            ]);

        $this->assertDatabaseMissing('inventory', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
        ]);
    }

    #[Test]
    public function no_puede_eliminar_inventario_con_stock()
    {
        // Arrange
        Inventory::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 50,
            'reserved_stock' => 0,
            'last_movement_at' => now(),
        ]);

        // Act
        $response = $this->deleteJson("/api/inventory/{$this->product->id}/{$this->warehouse->id}");

        // Assert
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertStringContainsString(
            'No se puede eliminar el inventario mientras tenga stock',
            $response->json('message')
        );
    }

    #[Test]
    public function puede_obtener_estadisticas_de_producto()
    {
        // Arrange
        $warehouse2 = Warehouse::factory()->create();

        Inventory::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 50,
            'reserved_stock' => 5,
            'sale_price' => 999.99,
            'last_movement_at' => now(),
        ]);

        Inventory::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $warehouse2->id,
            'available_stock' => 30,
            'reserved_stock' => 0,
            'sale_price' => 950.00,
            'last_movement_at' => now(),
        ]);

        // Act
        $response = $this->getJson("/api/products/{$this->product->id}/inventory/statistics");

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_warehouses',
                    'total_available_stock',
                    'total_reserved_stock',
                    'warehouses_with_stock',
                    'average_sale_price',
                ]
            ])
            ->assertJsonPath('data.total_warehouses', 2)
            ->assertJsonPath('data.total_available_stock', 80);
    }

    #[Test]
    public function puede_obtener_estadisticas_de_almacen()
    {
        // Arrange
        $product2 = Product::factory()->create(['sku' => 'TEST-002', 'category_id' => $this->category->id]);

        Inventory::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 50,
            'reserved_stock' => 0,
            'last_movement_at' => now(),
        ]);

        Inventory::create([
            'product_id' => $product2->id,
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 0,
            'reserved_stock' => 0,
            'last_movement_at' => now(),
        ]);

        // Act
        $response = $this->getJson("/api/warehouses/{$this->warehouse->id}/inventory/statistics");

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_products',
                    'products_with_stock',
                    'products_out_of_stock',
                    'total_available_stock',
                ]
            ])
            ->assertJsonPath('data.total_products', 2)
            ->assertJsonPath('data.products_with_stock', 1)
            ->assertJsonPath('data.products_out_of_stock', 1);
    }

    #[Test]
    public function puede_obtener_estadisticas_globales()
    {
        // Arrange
        Inventory::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 50,
            'reserved_stock' => 5,
            'last_movement_at' => now(),
        ]);

        // Act
        $response = $this->getJson('/api/inventory/statistics/global');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_inventory_records',
                    'records_with_stock',
                    'records_out_of_stock',
                    'total_available_stock',
                    'unique_products',
                    'unique_warehouses',
                ]
            ]);
    }

    #[Test]
    public function puede_obtener_alerta_de_stock_bajo()
    {
        // Arrange
        Inventory::create([
            'product_id' => $this->product->id, // min_stock = 10
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 5, // Menor al mínimo
            'reserved_stock' => 0,
            'last_movement_at' => now(),
        ]);

        // Act
        $response = $this->getJson('/api/inventory/alerts/low-stock');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => ['current_page', 'total']
            ]);
    }

    #[Test]
    public function puede_obtener_alerta_de_sin_stock()
    {
        // Arrange
        Inventory::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 0,
            'reserved_stock' => 0,
            'last_movement_at' => now(),
        ]);

        // Act
        $response = $this->getJson('/api/inventory/alerts/out-of-stock');

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.available_stock', 0);
    }

    #[Test]
    public function requiere_autenticacion_para_acceder_al_inventario()
    {
        // Arrange: Eliminar autenticación
        $this->app->get('auth')->forgetGuards();

        // Act
        $response = $this->getJson('/api/inventory');

        // Assert
        $response->assertStatus(401);
    }

    #[Test]
    public function valida_precios_no_negativos_al_asignar()
    {
        // Arrange
        $data = [
            'product_id' => $this->product->id,
            'warehouse_ids' => [$this->warehouse->id],
            'sale_price' => -100,
        ];

        // Act
        $response = $this->postJson('/api/inventory', $data);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sale_price']);
    }

    #[Test]
    public function valida_margen_entre_0_y_100()
    {
        // Arrange
        $data = [
            'product_id' => $this->product->id,
            'warehouse_ids' => [$this->warehouse->id],
            'profit_margin' => 150,
        ];

        // Act
        $response = $this->postJson('/api/inventory', $data);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['profit_margin']);
    }
}
