<?php
// tests/Feature/ProductControllerTest.php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Supports\PurchaseBatch;
use App\Models\Inventory;
use App\Models\Ubigeo;
use App\Models\Warehouse;
use App\Models\User;
use App\Models\Category;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use App\Models\Country;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity as ActivityModel;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Storage;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Category $category;
    protected Warehouse $warehouse;
    protected Country $country;
    protected Ubigeo $ubigeo;

    protected function setUp(): void
    {
        parent::setUp();

        // Usuario autenticado
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);

        // Categoría
        $this->category = Category::factory()->create();

        // ✅ CREAR PAÍS UNA SOLA VEZ (firstOrCreate evita duplicados)
        $this->country = Country::firstOrCreate(
            ['code' => 'PE'],
            [
                'name' => 'Perú',
                'phone_code' => '+51'
            ]
        );

        // ✅ CREAR UBIGEO UNA SOLA VEZ
        $this->ubigeo = Ubigeo::firstOrCreate(
            ['ubigeo' => '150101'],
            [
                'country_code' => 'PE',
                'departamento' => 'LIMA',
                'provincia' => 'LIMA',
                'distrito' => 'LIMA',
                'codigo_sunat' => '150101',
            ]
        );

        // ✅ CREAR WAREHOUSE (ya no usa factory que crea duplicados)
        $this->warehouse = Warehouse::create([
            'name' => 'Almacén Principal Test',
            'ubigeo' => '150101',
            'address' => 'Av. Test 123',
            'phone' => '999999999',
            'is_active' => true,
            'visible_online' => true,
            'picking_priority' => 5,
            'is_main' => true,
        ]);

        Storage::fake('public');
    }

    // ================================================================
    // TESTS DE CREACIÓN DE PRODUCTOS
    // ================================================================

    #[Test]
    public function puede_crear_producto_con_datos_minimos_requeridos()
    {
        $data = [
            'primary_name' => 'Producto Test',
            'category_id' => $this->category->id,
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'sku',
                    'primary_name',
                    'min_stock',
                    'unit_measure',
                    'tax_type',
                    'average_cost',
                    'total_stock',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'primary_name' => 'Producto Test',
                    'min_stock' => 5,
                    'unit_measure' => 'NIU',
                    'tax_type' => '10',
                    'is_active' => true,
                    'is_featured' => false,
                    'visible_online' => true,
                    'average_cost' => 0.0,
                    'total_stock' => 0,
                ],
            ]);

        $this->assertDatabaseHas('products', [
            'primary_name' => 'Producto Test',
            'category_id' => $this->category->id,
        ]);
    }

    #[Test]
    public function genera_sku_automaticamente_si_no_se_proporciona()
    {
        $data = [
            'primary_name' => 'Producto Sin SKU',
            'category_id' => $this->category->id,
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(201);

        $product = Product::first();
        $this->assertNotNull($product->sku);
        $this->assertStringStartsWith('PRD-', $product->sku);
    }

    #[Test]
    public function acepta_sku_personalizado_unico()
    {
        $data = [
            'sku' => 'CUSTOM-SKU-001',
            'primary_name' => 'Producto con SKU Custom',
            'category_id' => $this->category->id,
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(201);

        $this->assertDatabaseHas('products', [
            'sku' => 'CUSTOM-SKU-001',
            'primary_name' => 'Producto con SKU Custom',
        ]);
    }

    #[Test]
    public function rechaza_sku_duplicado()
    {
        Product::factory()->create(['sku' => 'SKU-DUPLICADO']);

        $data = [
            'sku' => 'SKU-DUPLICADO',
            'primary_name' => 'Producto con SKU Duplicado',
            'category_id' => $this->category->id,
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonPath('errors.sku.0', 'Este SKU ya está registrado en el sistema');
    }

    #[Test]
    public function puede_crear_producto_con_todos_los_campos()
    {
        $data = [
            'sku' => 'FULL-PRODUCT-001',
            'primary_name' => 'Producto Completo',
            'secondary_name' => 'Nombre Secundario',
            'description' => 'Descripción detallada del producto',
            'category_id' => $this->category->id,
            'brand' => 'Marca Test',
            'min_stock' => 15,
            'unit_measure' => 'UND',
            'tax_type' => '10',
            'weight' => 2.5,
            'barcode' => '1234567890',
            'is_active' => true,
            'is_featured' => true,
            'visible_online' => true,
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(201);

        $this->assertDatabaseHas('products', [
            'sku' => 'FULL-PRODUCT-001',
            'primary_name' => 'Producto Completo',
            'secondary_name' => 'Nombre Secundario',
            'brand' => 'Marca Test',
            'min_stock' => 15,
            'weight' => 2.5,
            'barcode' => '1234567890',
        ]);
    }

    #[Test]
    public function aplica_valores_por_defecto_correctamente()
    {
        $data = [
            'primary_name' => 'Producto con Defaults',
            'category_id' => $this->category->id,
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'min_stock' => 5,
                    'unit_measure' => 'NIU',
                    'tax_type' => '10',
                    'is_active' => true,
                    'is_featured' => false,
                    'visible_online' => true,
                ],
            ]);
    }

    #[Test]
    public function puede_sobrescribir_valores_por_defecto()
    {
        $data = [
            'primary_name' => 'Producto Custom',
            'category_id' => $this->category->id,
            'min_stock' => 10,
            'unit_measure' => 'KGM',
            'tax_type' => '20',
            'is_active' => false,
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'min_stock' => 10,
                    'unit_measure' => 'KGM',
                    'tax_type' => '20',
                    'is_active' => false,
                ],
            ]);
    }

    #[Test]
    public function registra_actividad_al_crear_producto()
    {
        $data = [
            'primary_name' => 'Producto para Log',
            'category_id' => $this->category->id,
        ];

        $this->postJson('/api/products', $data);

        $this->assertDatabaseHas('activity_log', [
            'description' => 'Producto creado',
            'causer_id' => $this->user->id,
            'causer_type' => get_class($this->user),
        ]);

        $activity = ActivityModel::latest()->first();
        $this->assertEquals('Producto creado', $activity->description);
        $this->assertEquals($this->user->id, $activity->causer_id);
        $this->assertNotNull($activity->subject_id);
    }

    // ================================================================
    // TESTS DE VALIDACIÓN EN CREACIÓN
    // ================================================================

    #[Test]
    public function valida_nombre_primario_obligatorio()
    {
        $data = ['category_id' => $this->category->id];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['primary_name']);
    }

    #[Test]
    public function valida_nombre_primario_minimo_3_caracteres()
    {
        $data = [
            'primary_name' => 'AB',
            'category_id' => $this->category->id,
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['primary_name']);
    }

    #[Test]
    public function valida_nombre_primario_maximo_200_caracteres()
    {
        $data = [
            'primary_name' => str_repeat('a', 201),
            'category_id' => $this->category->id,
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['primary_name']);
    }

    #[Test]
    public function valida_categoria_obligatoria()
    {
        $data = ['primary_name' => 'Producto Test'];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category_id']);
    }

    #[Test]
    public function valida_categoria_existente_en_base_datos()
    {
        $data = [
            'primary_name' => 'Producto Test',
            'category_id' => 99999,
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category_id']);
    }

    #[Test]
    public function valida_stock_minimo_no_negativo()
    {
        $data = [
            'primary_name' => 'Producto Test',
            'category_id' => $this->category->id,
            'min_stock' => -5,
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['min_stock']);
    }

    #[Test]
    public function valida_peso_no_negativo()
    {
        $data = [
            'primary_name' => 'Producto Test',
            'category_id' => $this->category->id,
            'weight' => -1.5,
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['weight']);
    }

    // ================================================================
    // TESTS DE LECTURA/LISTADO
    // ================================================================

    #[Test]
    public function puede_listar_productos()
    {
        Product::factory()->count(5)->create();

        $response = $this->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'sku',
                        'primary_name',
                        'min_stock',
                        'is_active',
                        'average_cost',
                        'total_stock',
                    ],
                ],
                'links',
                'meta',
            ]);
    }

    #[Test]
    public function listado_retorna_paginacion_por_defecto()
    {
        Product::factory()->count(20)->create();

        $response = $this->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'from', 'last_page', 'per_page', 'to', 'total'],
            ]);

        $this->assertEquals(15, count($response->json('data')));
        $this->assertEquals(15, $response->json('meta.per_page'));
        $this->assertEquals(20, $response->json('meta.total'));
    }

    #[Test]
    public function puede_cambiar_items_por_pagina()
    {
        Product::factory()->count(30)->create();

        $response = $this->getJson('/api/products?per_page=10');

        $response->assertStatus(200);
        $this->assertEquals(10, count($response->json('data')));
        $this->assertEquals(10, $response->json('meta.per_page'));
        $this->assertEquals(3, $response->json('meta.last_page'));
    }

    #[Test]
    public function puede_navegar_entre_paginas()
    {
        Product::factory()->count(20)->create();

        $page1 = $this->getJson('/api/products?per_page=5&page=1');
        $page1->assertStatus(200);
        $this->assertEquals(1, $page1->json('meta.current_page'));

        $page2 = $this->getJson('/api/products?per_page=5&page=2');
        $page2->assertStatus(200);
        $this->assertEquals(2, $page2->json('meta.current_page'));

        $idsPage1 = collect($page1->json('data'))->pluck('id')->toArray();
        $idsPage2 = collect($page2->json('data'))->pluck('id')->toArray();
        $this->assertEmpty(array_intersect($idsPage1, $idsPage2));
    }

    #[Test]
    public function muestra_producto_individual()
    {
        $product = Product::factory()->create();

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'primary_name' => $product->primary_name,
                ],
            ]);
    }

    #[Test]
    public function retorna_404_para_producto_inexistente()
    {
        $response = $this->getJson('/api/products/99999');

        $response->assertStatus(404);
    }

    // ================================================================
    // TESTS DE BÚSQUEDA Y FILTROS
    // ================================================================

    #[Test]
    public function puede_buscar_productos_por_nombre()
    {
        Product::factory()->create(['primary_name' => 'Laptop HP']);
        Product::factory()->create(['primary_name' => 'Mouse Logitech']);
        Product::factory()->create(['primary_name' => 'Teclado HP']);

        $response = $this->getJson('/api/products?search=HP');

        $response->assertStatus(200);
        $this->assertEquals(2, count($response->json('data')));
    }

    #[Test]
    public function busqueda_encuentra_por_sku()
    {
        Product::factory()->create(['sku' => 'ABC-123', 'primary_name' => 'Producto 1']);
        Product::factory()->create(['sku' => 'XYZ-456', 'primary_name' => 'Producto 2']);

        $response = $this->getJson('/api/products?search=ABC-123');

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data')));
    }

    #[Test]
    public function busqueda_es_case_insensitive()
    {
        Product::factory()->create(['primary_name' => 'LAPTOP HP']);
        Product::factory()->create(['primary_name' => 'Mouse Logitech']);

        $response = $this->getJson('/api/products?search=laptop');

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data')));
    }

    #[Test]
    public function puede_filtrar_productos_por_categoria()
    {
        $categoria1 = Category::factory()->create();
        $categoria2 = Category::factory()->create();

        Product::factory()->count(3)->create(['category_id' => $categoria1->id]);
        Product::factory()->count(2)->create(['category_id' => $categoria2->id]);

        $response = $this->getJson("/api/products?category_id={$categoria1->id}");

        $response->assertStatus(200);
        $this->assertEquals(3, count($response->json('data')));
    }

    #[Test]
    public function puede_filtrar_productos_por_marca()
    {
        Product::factory()->count(3)->create(['brand' => 'HP']);
        Product::factory()->count(2)->create(['brand' => 'Dell']);

        $response = $this->getJson('/api/products?brand=HP');

        $response->assertStatus(200);
        $this->assertEquals(3, count($response->json('data')));
    }

    #[Test]
    public function puede_filtrar_productos_activos()
    {
        Product::factory()->count(3)->create(['is_active' => true]);
        Product::factory()->count(2)->create(['is_active' => false]);

        $response = $this->getJson('/api/products?is_active=1');

        $response->assertStatus(200);
        $this->assertEquals(3, count($response->json('data')));
    }

    #[Test]
    public function puede_filtrar_productos_destacados()
    {
        Product::factory()->count(2)->create(['is_featured' => true]);
        Product::factory()->count(3)->create(['is_featured' => false]);

        $response = $this->getJson('/api/products?is_featured=1');

        $response->assertStatus(200);
        $this->assertEquals(2, count($response->json('data')));
    }

    #[Test]
    public function puede_filtrar_productos_visibles_online()
    {
        Product::factory()->count(4)->create(['visible_online' => true]);
        Product::factory()->count(1)->create(['visible_online' => false]);

        $response = $this->getJson('/api/products?visible_online=1');

        $response->assertStatus(200);
        $this->assertEquals(4, count($response->json('data')));
    }

    #[Test]
    public function puede_filtrar_productos_con_stock()
    {
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();
        Product::factory()->create(); // Sin stock

        Inventory::create([
            'product_id' => $product1->id,
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 10,
        ]);

        Inventory::create([
            'product_id' => $product2->id,
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 5,
        ]);

        $response = $this->getJson('/api/products?with_stock=1');

        $response->assertStatus(200);
        $this->assertEquals(2, count($response->json('data')));
    }

    #[Test]
    public function puede_filtrar_productos_con_stock_bajo()
    {
        $product1 = Product::factory()->create(['min_stock' => 10]);
        $product2 = Product::factory()->create(['min_stock' => 5]);

        Inventory::create([
            'product_id' => $product1->id,
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 3, // Menor a min_stock
        ]);

        Inventory::create([
            'product_id' => $product2->id,
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 20, // Mayor a min_stock
        ]);

        $response = $this->getJson('/api/products?low_stock=1');

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data')));
    }

    #[Test]
    public function puede_filtrar_por_almacen_especifico()
    {
        $warehouse2 = Warehouse::factory()->create();
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();

        Inventory::create([
            'product_id' => $product1->id,
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 10,
        ]);

        Inventory::create([
            'product_id' => $product2->id,
            'warehouse_id' => $warehouse2->id,
            'available_stock' => 5,
        ]);

        $response = $this->getJson("/api/products?warehouse_id={$this->warehouse->id}");

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data')));
    }

    #[Test]
    public function puede_combinar_busqueda_y_filtros()
    {
        $category = Category::factory()->create();

        Product::factory()->create([
            'primary_name' => 'HP Laptop',
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        Product::factory()->create([
            'primary_name' => 'HP Mouse',
            'category_id' => $category->id,
            'is_active' => false,
        ]);

        $response = $this->getJson("/api/products?search=HP&category_id={$category->id}&is_active=1");

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data')));
    }

    #[Test]
    public function paginacion_respeta_filtros()
    {
        $category = Category::factory()->create();
        Product::factory()->count(25)->create(['category_id' => $category->id]);
        Product::factory()->count(10)->create();

        $response = $this->getJson("/api/products?category_id={$category->id}&per_page=10");

        $response->assertStatus(200);
        $this->assertEquals(25, $response->json('meta.total'));
        $this->assertEquals(10, count($response->json('data')));
    }

    // ================================================================
    // TESTS DE ORDENAMIENTO
    // ================================================================

    #[Test]
    public function puede_ordenar_por_nombre_ascendente()
    {
        Product::factory()->create(['primary_name' => 'Zebra']);
        Product::factory()->create(['primary_name' => 'Alpha']);
        Product::factory()->create(['primary_name' => 'Mega']);

        $response = $this->getJson('/api/products?sort_by=primary_name&sort_order=asc');

        $response->assertStatus(200);
        $names = collect($response->json('data'))->pluck('primary_name')->toArray();
        $this->assertEquals('Alpha', $names[0]);
        $this->assertEquals('Mega', $names[1]);
        $this->assertEquals('Zebra', $names[2]);
    }

    #[Test]
    public function puede_ordenar_por_nombre_descendente()
    {
        Product::factory()->create(['primary_name' => 'Alpha']);
        Product::factory()->create(['primary_name' => 'Zebra']);

        $response = $this->getJson('/api/products?sort_by=primary_name&sort_order=desc');

        $response->assertStatus(200);
        $names = collect($response->json('data'))->pluck('primary_name')->toArray();
        $this->assertEquals('Zebra', $names[0]);
        $this->assertEquals('Alpha', $names[1]);
    }

    #[Test]
    public function puede_ordenar_por_fecha_creacion()
    {
        $product1 = Product::factory()->create(['created_at' => now()->subDays(3)]);
        $product2 = Product::factory()->create(['created_at' => now()->subDays(1)]);
        $product3 = Product::factory()->create(['created_at' => now()]);

        $response = $this->getJson('/api/products?sort_by=created_at&sort_order=asc');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertEquals($product1->id, $ids[0]);
        $this->assertEquals($product2->id, $ids[1]);
        $this->assertEquals($product3->id, $ids[2]);
    }

    // ================================================================
    // TESTS DE ACTUALIZACIÓN
    // ================================================================

    #[Test]
    public function puede_actualizar_producto_con_put()
    {
        $product = Product::factory()->create([
            'primary_name' => 'Nombre Original',
        ]);

        $data = [
            'primary_name' => 'Nombre Actualizado',
            'category_id' => $this->category->id,
        ];

        $response = $this->putJson("/api/products/{$product->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Producto actualizado exitosamente',
            ]);

        $product->refresh();
        $this->assertEquals('Nombre Actualizado', $product->primary_name);
    }

    #[Test]
    public function puede_actualizar_producto_con_patch()
    {
        $product = Product::factory()->create([
            'primary_name' => 'Original',
            'brand' => 'Marca Original',
        ]);

        $response = $this->patchJson("/api/products/{$product->id}", [
            'primary_name' => 'Solo Nombre Actualizado',
        ]);

        $response->assertStatus(200);

        $product->refresh();
        $this->assertEquals('Solo Nombre Actualizado', $product->primary_name);
        $this->assertEquals('Marca Original', $product->brand); // No cambió
    }

    #[Test]
    public function patch_no_requiere_campos_obligatorios()
    {
        $product = Product::factory()->create();

        $response = $this->patchJson("/api/products/{$product->id}", [
            'description' => 'Nueva descripción',
        ]);

        $response->assertStatus(200);
        $product->refresh();
        $this->assertEquals('Nueva descripción', $product->description);
    }

    #[Test]
    public function put_requiere_campos_obligatorios()
    {
        $product = Product::factory()->create();

        $response = $this->putJson("/api/products/{$product->id}", [
            'primary_name' => 'Actualizado',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category_id']);
    }

    #[Test]
    public function puede_actualizar_sku_a_uno_unico()
    {
        $product = Product::factory()->create(['sku' => 'OLD-SKU']);

        $data = [
            'sku' => 'NEW-SKU',
            'primary_name' => $product->primary_name,
            'category_id' => $product->category_id,
        ];

        $response = $this->putJson("/api/products/{$product->id}", $data);

        $response->assertStatus(200);
        $product->refresh();
        $this->assertEquals('NEW-SKU', $product->sku);
    }

    #[Test]
    public function no_puede_actualizar_sku_a_uno_existente()
    {
        Product::factory()->create(['sku' => 'SKU-001']);
        $product2 = Product::factory()->create(['sku' => 'SKU-002']);

        $data = [
            'sku' => 'SKU-001',
            'primary_name' => $product2->primary_name,
            'category_id' => $product2->category_id,
        ];

        $response = $this->putJson("/api/products/{$product2->id}", $data);

        $response->assertStatus(409);
    }

    #[Test]
    public function registra_actividad_al_actualizar_producto()
    {
        $product = Product::factory()->create(['primary_name' => 'Original']);

        $data = [
            'primary_name' => 'Actualizado',
            'category_id' => $product->category_id,
        ];

        $this->putJson("/api/products/{$product->id}", $data);

        $this->assertDatabaseHas('activity_log', [
            'description' => 'Producto actualizado',
            'subject_id' => $product->id,
            'causer_id' => $this->user->id,
        ]);
    }

    // ================================================================
    // TESTS DE ACTIVACIÓN/DESACTIVACIÓN
    // ================================================================

    #[Test]
    public function puede_activar_producto_individual()
    {
        $product = Product::factory()->create(['is_active' => false]);

        $response = $this->patchJson("/api/products/{$product->id}", [
            'is_active' => true,
        ]);

        $response->assertStatus(200);
        $product->refresh();
        $this->assertTrue($product->is_active);
    }

    #[Test]
    public function puede_desactivar_producto_individual()
    {
        $product = Product::factory()->create(['is_active' => true]);

        $response = $this->patchJson("/api/products/{$product->id}", [
            'is_active' => false,
        ]);

        $response->assertStatus(200);
        $product->refresh();
        $this->assertFalse($product->is_active);
    }

    #[Test]
    public function puede_activar_multiples_productos_con_bulk_update()
    {
        $products = Product::factory()->count(3)->create(['is_active' => false]);

        $data = [
            'product_ids' => $products->pluck('id')->toArray(),
            'action' => 'activate',
        ];

        $response = $this->postJson('/api/products/bulk-update', $data);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'count' => 3,
            ]);

        foreach ($products as $product) {
            $product->refresh();
            $this->assertTrue($product->is_active);
        }
    }

    #[Test]
    public function puede_desactivar_multiples_productos_con_bulk_update()
    {
        $products = Product::factory()->count(3)->create(['is_active' => true]);

        $data = [
            'product_ids' => $products->pluck('id')->toArray(),
            'action' => 'deactivate',
        ];

        $response = $this->postJson('/api/products/bulk-update', $data);

        $response->assertStatus(200);

        foreach ($products as $product) {
            $product->refresh();
            $this->assertFalse($product->is_active);
        }
    }

    #[Test]
    public function puede_destacar_multiples_productos_con_bulk_update()
    {
        $products = Product::factory()->count(3)->create(['is_featured' => false]);

        $data = [
            'product_ids' => $products->pluck('id')->toArray(),
            'action' => 'feature',
        ];

        $response = $this->postJson('/api/products/bulk-update', $data);

        $response->assertStatus(200);

        foreach ($products as $product) {
            $product->refresh();
            $this->assertTrue($product->is_featured);
        }
    }

    #[Test]
    public function puede_quitar_destacado_multiples_productos()
    {
        $products = Product::factory()->count(2)->create(['is_featured' => true]);

        $data = [
            'product_ids' => $products->pluck('id')->toArray(),
            'action' => 'unfeature',
        ];

        $response = $this->postJson('/api/products/bulk-update', $data);

        $response->assertStatus(200);

        foreach ($products as $product) {
            $product->refresh();
            $this->assertFalse($product->is_featured);
        }
    }

    #[Test]
    public function puede_mostrar_online_multiples_productos()
    {
        $products = Product::factory()->count(3)->create(['visible_online' => false]);

        $data = [
            'product_ids' => $products->pluck('id')->toArray(),
            'action' => 'show_online',
        ];

        $response = $this->postJson('/api/products/bulk-update', $data);

        $response->assertStatus(200);

        foreach ($products as $product) {
            $product->refresh();
            $this->assertTrue($product->visible_online);
        }
    }

    #[Test]
    public function puede_ocultar_online_multiples_productos()
    {
        $products = Product::factory()->count(2)->create(['visible_online' => true]);

        $data = [
            'product_ids' => $products->pluck('id')->toArray(),
            'action' => 'hide_online',
        ];

        $response = $this->postJson('/api/products/bulk-update', $data);

        $response->assertStatus(200);

        foreach ($products as $product) {
            $product->refresh();
            $this->assertFalse($product->visible_online);
        }
    }

    #[Test]
    public function bulk_update_solo_afecta_productos_especificados()
    {
        $afectados = Product::factory()->count(3)->create(['is_active' => false]);
        $noAfectados = Product::factory()->count(2)->create(['is_active' => false]);

        $this->postJson('/api/products/bulk-update', [
            'product_ids' => $afectados->pluck('id')->toArray(),
            'action' => 'activate',
        ]);

        foreach ($afectados as $product) {
            $product->refresh();
            $this->assertTrue($product->is_active);
        }

        foreach ($noAfectados as $product) {
            $product->refresh();
            $this->assertFalse($product->is_active);
        }
    }

    #[Test]
    public function registra_actividad_al_bulk_update()
    {
        $products = Product::factory()->count(3)->create(['is_active' => false]);

        $this->postJson('/api/products/bulk-update', [
            'product_ids' => $products->pluck('id')->toArray(),
            'action' => 'activate',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'description' => 'Actualización masiva de productos: activate',
            'causer_id' => $this->user->id,
        ]);
    }

    // ================================================================
    // TESTS DE ELIMINACIÓN
    // ================================================================

    #[Test]
    public function puede_eliminar_producto_sin_transacciones()
    {
        $product = Product::factory()->create();

        $response = $this->deleteJson("/api/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Producto eliminado exitosamente',
            ]);

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    #[Test]
    public function no_puede_eliminar_producto_con_movimientos()
    {
        $product = Product::factory()->create();

        // Crear inventario con stock
        Inventory::create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 10,
        ]);

        $response = $this->deleteJson("/api/products/{$product->id}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function puede_restaurar_producto_eliminado()
    {
        $product = Product::factory()->create();
        $product->delete();

        $response = $this->postJson("/api/products/{$product->id}/restore");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Producto restaurado exitosamente',
            ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function registra_actividad_al_eliminar_producto()
    {
        $product = Product::factory()->create();

        $this->deleteJson("/api/products/{$product->id}");

        $this->assertDatabaseHas('activity_log', [
            'description' => 'Producto eliminado (soft delete)',
            'subject_id' => $product->id,
            'causer_id' => $this->user->id,
        ]);
    }

    // ================================================================
    // TESTS DE DUPLICACIÓN
    // ================================================================

    #[Test]
    public function puede_duplicar_producto()
    {
        $product = Product::factory()->create([
            'sku' => 'ORIGINAL-SKU',
            'primary_name' => 'Producto Original',
        ]);

        $response = $this->postJson("/api/products/{$product->id}/duplicate");

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Producto duplicado exitosamente',
            ]);

        $this->assertEquals(2, Product::count());

        $duplicado = Product::latest()->first();
        $this->assertNotEquals($product->sku, $duplicado->sku);
        $this->assertEquals('Producto Original (Copia)', $duplicado->primary_name);
        $this->assertFalse($duplicado->is_active);
    }

    #[Test]
    public function producto_duplicado_mantiene_categoria()
    {
        $product = Product::factory()->create();

        $response = $this->postJson("/api/products/{$product->id}/duplicate");

        $response->assertStatus(201);

        $duplicado = Product::latest()->first();
        $this->assertEquals($product->category_id, $duplicado->category_id);
    }

    #[Test]
    public function producto_duplicado_mantiene_otros_campos()
    {
        $product = Product::factory()->create([
            'brand' => 'Marca Original',
            'min_stock' => 20,
            'unit_measure' => 'KGM',
        ]);

        $response = $this->postJson("/api/products/{$product->id}/duplicate");

        $response->assertStatus(201);

        $duplicado = Product::latest()->first();
        $this->assertEquals($product->brand, $duplicado->brand);
        $this->assertEquals($product->min_stock, $duplicado->min_stock);
        $this->assertEquals($product->unit_measure, $duplicado->unit_measure);
    }

    #[Test]
    public function producto_duplicado_copia_imagenes()
    {
        $this->markTestSkipped('Test de copia de imágenes requiere configuración especial de storage');
        $product = Product::factory()->create();

        $image = UploadedFile::fake()->image('producto.jpg');
        $this->post(
            "/api/products/{$product->id}/images",
            ['images' => [$image]],
            ['Accept' => 'application/json']
        );

        $response = $this->postJson("/api/products/{$product->id}/duplicate");

        $response->assertStatus(201);

        $duplicado = Product::latest()->first();
        $this->assertCount(1, $duplicado->getMedia('images'));
    }

    // ================================================================
    // TESTS DE GESTIÓN DE IMÁGENES
    // ================================================================

    #[Test]
    public function puede_subir_una_imagen()
    {
        $product = Product::factory()->create();
        $image = UploadedFile::fake()->image('producto.jpg', 800, 600);

        $response = $this->post(
            "/api/products/{$product->id}/images",
            ['images' => [$image]],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Imágenes subidas exitosamente',
            ])
            ->assertJsonCount(1, 'images');

        $product->refresh();
        $this->assertCount(1, $product->getMedia('images'));
    }

    #[Test]
    public function puede_subir_multiples_imagenes()
    {
        $product = Product::factory()->create();

        $image1 = UploadedFile::fake()->image('img1.jpg', 800, 600);
        $image2 = UploadedFile::fake()->image('img2.jpg', 800, 600);
        $image3 = UploadedFile::fake()->image('img3.jpg', 800, 600);

        $response = $this->post(
            "/api/products/{$product->id}/images",
            ['images' => [$image1, $image2, $image3]],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(201)
            ->assertJsonCount(3, 'images');

        $product->refresh();
        $this->assertCount(3, $product->getMedia('images'));
    }

    #[Test]
    public function primera_imagen_se_marca_como_principal()
    {
        $product = Product::factory()->create();
        $image = UploadedFile::fake()->image('producto.jpg');

        $response = $this->post(
            "/api/products/{$product->id}/images",
            ['images' => [$image]],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(201)
            ->assertJsonPath('images.0.is_primary', true);
    }

    #[Test]
    public function segunda_imagen_no_es_principal()
    {
        $product = Product::factory()->create();

        $image1 = UploadedFile::fake()->image('img1.jpg');
        $image2 = UploadedFile::fake()->image('img2.jpg');

        $response = $this->post(
            "/api/products/{$product->id}/images",
            ['images' => [$image1, $image2]],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(201)
            ->assertJsonPath('images.0.is_primary', true)
            ->assertJsonPath('images.1.is_primary', false);
    }

    #[Test]
    public function imagenes_mantienen_orden_correcto()
    {
        $product = Product::factory()->create();

        $this->post(
            "/api/products/{$product->id}/images",
            [
                'images' => [
                    UploadedFile::fake()->image('img1.jpg'),
                    UploadedFile::fake()->image('img2.jpg'),
                    UploadedFile::fake()->image('img3.jpg'),
                ]
            ],
            ['Accept' => 'application/json']
        );

        $product->refresh();
        $media = $product->getMedia('images');

        $this->assertEquals(1, $media[0]->getCustomProperty('order'));
        $this->assertEquals(2, $media[1]->getCustomProperty('order'));
        $this->assertEquals(3, $media[2]->getCustomProperty('order'));
    }

    #[Test]
    public function permite_subir_hasta_5_imagenes_en_total()
    {
        $product = Product::factory()->create();

        // Primera carga: 2 imágenes
        $this->post(
            "/api/products/{$product->id}/images",
            [
                'images' => [
                    UploadedFile::fake()->image('img1.jpg'),
                    UploadedFile::fake()->image('img2.jpg'),
                ]
            ],
            ['Accept' => 'application/json']
        );

        // Segunda carga: 3 imágenes más (total 5)
        $response = $this->post(
            "/api/products/{$product->id}/images",
            [
                'images' => [
                    UploadedFile::fake()->image('img3.jpg'),
                    UploadedFile::fake()->image('img4.jpg'),
                    UploadedFile::fake()->image('img5.jpg'),
                ]
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(201);

        $product->refresh();
        $this->assertCount(5, $product->getMedia('images'));
    }

    #[Test]
    public function no_permite_exceder_limite_de_5_imagenes()
    {
        $product = Product::factory()->create();

        // Subir 4 imágenes
        $this->post(
            "/api/products/{$product->id}/images",
            [
                'images' => [
                    UploadedFile::fake()->image('img1.jpg'),
                    UploadedFile::fake()->image('img2.jpg'),
                    UploadedFile::fake()->image('img3.jpg'),
                    UploadedFile::fake()->image('img4.jpg'),
                ]
            ],
            ['Accept' => 'application/json']
        );

        // Intentar subir 2 más (excedería)
        $response = $this->post(
            "/api/products/{$product->id}/images",
            [
                'images' => [
                    UploadedFile::fake()->image('img5.jpg'),
                    UploadedFile::fake()->image('img6.jpg'),
                ]
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);

        $product->refresh();
        $this->assertCount(4, $product->getMedia('images'));
    }

    #[Test]
    public function valida_tipo_de_archivo_imagen()
    {
        $product = Product::factory()->create();
        $invalidFile = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->postJson(
            "/api/products/{$product->id}/images",
            ['images' => [$invalidFile]]
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['images.0']);
    }

    #[Test]
    public function valida_tamano_maximo_de_imagen_2mb()
    {
        $product = Product::factory()->create();
        $largeImage = UploadedFile::fake()->create('large.jpg', 3000);

        $response = $this->postJson(
            "/api/products/{$product->id}/images",
            ['images' => [$largeImage]]
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['images.0']);
    }

    #[Test]
    public function acepta_formatos_de_imagen_validos()
    {
        $product = Product::factory()->create();

        $formats = [
            UploadedFile::fake()->image('test.jpg'),
            UploadedFile::fake()->image('test.jpeg'),
            UploadedFile::fake()->image('test.png'),
            UploadedFile::fake()->image('test.webp'),
        ];

        foreach ($formats as $image) {
            $response = $this->post(
                "/api/products/{$product->id}/images",
                ['images' => [$image]],
                ['Accept' => 'application/json']
            );

            $response->assertStatus(201);
        }

        $product->refresh();
        $this->assertCount(4, $product->getMedia('images'));
    }

    #[Test]
    public function rechaza_formatos_no_validos()
    {
        $product = Product::factory()->create();

        $invalidFiles = [
            UploadedFile::fake()->create('document.pdf'),
            UploadedFile::fake()->create('video.mp4'),
            UploadedFile::fake()->create('audio.mp3'),
        ];

        foreach ($invalidFiles as $file) {
            $response = $this->postJson(
                "/api/products/{$product->id}/images",
                ['images' => [$file]]
            );

            $response->assertStatus(422);
        }
    }

    #[Test]
    public function imagenes_incluyen_todas_las_conversiones()
    {
        $product = Product::factory()->create();
        $image = UploadedFile::fake()->image('producto.jpg', 1200, 1200);

        $response = $this->post(
            "/api/products/{$product->id}/images",
            ['images' => [$image]],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(201)
            ->assertJsonStructure([
                'images' => [
                    '*' => [
                        'id',
                        'name',
                        'original_url',
                        'thumb_url',
                        'medium_url',
                        'large_url',
                        'size',
                        'order',
                        'is_primary',
                    ]
                ]
            ]);
    }

    #[Test]
    public function puede_eliminar_imagen_especifica()
    {
        $product = Product::factory()->create();
        $image = UploadedFile::fake()->image('producto.jpg', 800, 800);

        $upload = $this->post(
            "/api/products/{$product->id}/images",
            ['images' => [$image]],
            ['Accept' => 'application/json']
        )->assertCreated();

        $mediaId = $upload->json('images.0.id');

        $this->deleteJson("/api/products/{$product->id}/images/{$mediaId}")
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('media', ['id' => $mediaId]);

        $product->refresh();
        $product->unsetRelation('media');
        $this->assertCount(0, $product->getMedia('images'));
    }

    #[Test]
    public function al_eliminar_imagen_principal_otra_toma_su_lugar()
    {
        $product = Product::factory()->create();

        $image1 = UploadedFile::fake()->image('img1.jpg');
        $image2 = UploadedFile::fake()->image('img2.jpg');

        $this->post(
            "/api/products/{$product->id}/images",
            ['images' => [$image1, $image2]],
            ['Accept' => 'application/json']
        );

        $primaryMedia = $product->getMedia('images')->first();

        $this->deleteJson("/api/products/{$product->id}/images/{$primaryMedia->id}");

        $product->refresh();
        $product->unsetRelation('media');

        $remainingMedia = $product->getMedia('images')->first();
        $this->assertTrue($remainingMedia->getCustomProperty('is_primary'));
    }

    #[Test]
    public function imagenes_se_reordenan_tras_eliminar()
    {
        $product = Product::factory()->create();

        $this->post(
            "/api/products/{$product->id}/images",
            [
                'images' => [
                    UploadedFile::fake()->image('img1.jpg'),
                    UploadedFile::fake()->image('img2.jpg'),
                    UploadedFile::fake()->image('img3.jpg'),
                ]
            ],
            ['Accept' => 'application/json']
        );

        $secondMedia = $product->getMedia('images')[1];
        $this->deleteJson("/api/products/{$product->id}/images/{$secondMedia->id}");

        $product->refresh();
        $product->unsetRelation('media');
        $media = $product->getMedia('images');

        $this->assertEquals(1, $media[0]->getCustomProperty('order'));
        $this->assertEquals(2, $media[1]->getCustomProperty('order'));
    }

    #[Test]
    public function retorna_404_al_eliminar_imagen_inexistente()
    {
        $product = Product::factory()->create();

        $response = $this->deleteJson("/api/products/{$product->id}/images/99999");

        $response->assertStatus(404);
    }

    #[Test]
    public function registra_actividad_al_subir_imagenes()
    {
        $product = Product::factory()->create();
        $image = UploadedFile::fake()->image('producto.jpg');

        $this->post(
            "/api/products/{$product->id}/images",
            ['images' => [$image]],
            ['Accept' => 'application/json']
        );

        $this->assertDatabaseHas('activity_log', [
            'subject_id' => $product->id,
            'causer_id' => $this->user->id,
        ]);
    }

    #[Test]
    public function registra_actividad_al_eliminar_imagen()
    {
        $product = Product::factory()->create();
        $image = UploadedFile::fake()->image('producto.jpg');

        $upload = $this->post(
            "/api/products/{$product->id}/images",
            ['images' => [$image]],
            ['Accept' => 'application/json']
        );

        $media = $product->getMedia('images')->first();

        $this->deleteJson("/api/products/{$product->id}/images/{$media->id}");

        $this->assertDatabaseHas('activity_log', [
            'description' => 'Imagen eliminada del producto',
            'subject_id' => $product->id,
            'causer_id' => $this->user->id,
        ]);
    }

    #[Test]
    public function valida_que_imagenes_sea_array()
    {
        $product = Product::factory()->create();

        $response = $this->postJson(
            "/api/products/{$product->id}/images",
            ['images' => 'not-an-array']
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['images']);
    }

    #[Test]
    public function valida_que_imagenes_no_este_vacio()
    {
        $product = Product::factory()->create();

        $response = $this->postJson(
            "/api/products/{$product->id}/images",
            ['images' => []]
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['images']);
    }

    // ================================================================
    // TESTS DE ESTADÍSTICAS
    // ================================================================

    #[Test]
    public function puede_obtener_estadisticas_basicas()
    {
        Product::factory()->count(10)->create(['is_active' => true, 'is_featured' => false]);
        Product::factory()->count(5)->create(['is_active' => false, 'is_featured' => false]);
        Product::factory()->count(3)->create(['is_active' => true, 'is_featured' => true]);

        $response = $this->getJson('/api/products/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_products',
                    'active_products',
                    'inactive_products',
                    'featured_products',
                    'online_products',
                ],
            ]);

        $stats = $response->json('data');
        $this->assertEquals(18, $stats['total_products']);
        $this->assertEquals(13, $stats['active_products']);
        $this->assertEquals(5, $stats['inactive_products']);
        $this->assertEquals(3, $stats['featured_products']);
    }

    #[Test]
    public function estadisticas_calculan_valor_desde_lotes()
    {
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();

        PurchaseBatch::factory()->create([
            'product_id' => $product1->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_available' => 100,
            'purchase_price' => 50.00,
            'distribution_price' => 60.00,
            'status' => 'active',
        ]);

        PurchaseBatch::factory()->create([
            'product_id' => $product2->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_available' => 50,
            'purchase_price' => 80.00,
            'distribution_price' => 100.00,
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/products/statistics');

        $response->assertStatus(200);

        // Valor inventario: (100*60) + (50*100) = 11,000
        $this->assertEquals(11000.00, $response->json('data.total_inventory_value'));

        // Valor costo: (100*50) + (50*80) = 9,000
        $this->assertEquals(9000.00, $response->json('data.total_cost_value'));

        // Ganancia potencial: 11,000 - 9,000 = 2,000
        $this->assertEquals(2000.00, $response->json('data.potential_profit'));
    }

    #[Test]
    public function estadisticas_cuentan_productos_con_stock_bajo()
    {
        $product1 = Product::factory()->create(['min_stock' => 10]);
        $product2 = Product::factory()->create(['min_stock' => 5]);
        $product3 = Product::factory()->create(['min_stock' => 15]);

        Inventory::create([
            'product_id' => $product1->id,
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 3, // Bajo
        ]);

        Inventory::create([
            'product_id' => $product2->id,
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 20, // OK
        ]);

        Inventory::create([
            'product_id' => $product3->id,
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 8, // Bajo
        ]);

        $response = $this->getJson('/api/products/statistics');

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('data.low_stock_products'));
    }

    // ================================================================
    // TESTS DE ATRIBUTOS CALCULADOS
    // ================================================================

    #[Test]
    public function producto_calcula_costo_promedio_desde_lotes()
    {
        $product = Product::factory()->create();

        PurchaseBatch::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_purchased' => 100,
            'quantity_available' => 100,
            'purchase_price' => 50.00,
            'distribution_price' => 60.00,
            'status' => 'active',
        ]);

        PurchaseBatch::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_purchased' => 50,
            'quantity_available' => 50,
            'purchase_price' => 70.00,
            'distribution_price' => 80.00,
            'status' => 'active',
        ]);

        $response = $this->getJson("/api/products/{$product->id}");

        // Costo promedio: (100*60 + 50*80) / 150 = 66.67
        $response->assertStatus(200)
            ->assertJsonPath('data.average_cost', 66.67);
    }

    #[Test]
    public function producto_muestra_stock_total_de_todos_almacenes()
    {
        $product = Product::factory()->create();
        $warehouse2 = Warehouse::factory()->create();

        Inventory::create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 50,
        ]);

        Inventory::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse2->id,
            'available_stock' => 30,
        ]);

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.total_stock', 80);
    }

    #[Test]
    public function producto_muestra_precio_venta_por_almacen()
    {
        $product = Product::factory()->create();

        Inventory::create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 100,
            'reserved_stock' => 0,
            'sale_price' => 150.00,
            'profit_margin' => 50.00,
        ]);

        $response = $this->getJson("/api/products/{$product->id}?warehouse_id={$this->warehouse->id}");

        $response->assertStatus(200);

        // 💡 Usamos assertEquals (comparación no estricta)
        $this->assertEquals(150.00, $response->json('data.sale_price'));
    }

    #[Test]
    public function puede_incluir_informacion_de_almacenes()
    {
        $product = Product::factory()->create();

        Inventory::create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 100,
            'sale_price' => 150.00,
            'profit_margin' => 50.00,
        ]);

        $response = $this->getJson("/api/products/{$product->id}?include_warehouses=1");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'warehouse_prices' => [
                        '*' => [
                            'warehouse_id',
                            'warehouse_name',
                            'available_stock',
                            'reserved_stock',
                            'sale_price',
                            'profit_margin',
                        ]
                    ]
                ]
            ]);
    }

    #[Test]
    public function puede_incluir_lotes_activos()
    {
        $product = Product::factory()->create();
        PurchaseBatch::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_available' => 50,
            'status' => 'active',
        ]);

        $response = $this->getJson("/api/products/{$product->id}?include_batches=1");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'active_batches' => [
                        '*' => [
                            'id',
                            'batch_code',
                            'warehouse_id',
                            'quantity_available',
                            'purchase_price',
                            'distribution_price',
                        ]
                    ]
                ]
            ]);
    }

    #[Test]
    public function producto_con_imagenes_las_incluye_en_respuesta()
    {
        $product = Product::factory()->create();

        $image = UploadedFile::fake()->image('producto.jpg');
        $this->post(
            "/api/products/{$product->id}/images",
            ['images' => [$image]],
            ['Accept' => 'application/json']
        );

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'images' => [
                        '*' => [
                            'id',
                            'name',
                            'original_url',
                            'thumb_url',
                            'medium_url',
                            'large_url',
                            'size',
                            'order',
                            'is_primary',
                        ]
                    ]
                ]
            ])
            ->assertJsonCount(1, 'data.images');
    }

    // ================================================================
// TESTS DE CASOS ESPECIALES
// ================================================================

    #[Test]
    public function producto_sin_lotes_muestra_costo_cero()
    {
        $product = Product::factory()->create();

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(200);

        // 💡 Usamos assertEquals (comparación no estricta)
        $this->assertEqualsWithDelta(0.00, $response->json('data.average_cost'), 0.001);
    }

    #[Test]
    public function producto_sin_inventario_muestra_stock_cero()
    {
        $product = Product::factory()->create();

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.total_stock', 0);
    }

    #[Test]
    public function lotes_inactivos_no_afectan_costo_promedio()
    {
        $product = Product::factory()->create();

        // Lote activo
        PurchaseBatch::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_available' => 100,
            'distribution_price' => 60.00,
            'status' => 'active',
        ]);

        // Lote inactivo (no debe contar)
        PurchaseBatch::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_available' => 50,
            'distribution_price' => 200.00,
            'status' => 'inactive',
        ]);

        $response = $this->getJson("/api/products/{$product->id}");

        // Solo debe considerar el lote activo
        $response->assertStatus(200);

        $this->assertEqualsWithDelta(60.00, $response->json('data.average_cost'), 0.001);
    }

    #[Test]
    public function lotes_sin_stock_no_afectan_costo_promedio()
    {
        $product = Product::factory()->create();

        // Lote con stock
        PurchaseBatch::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_available' => 100,
            'distribution_price' => 60.00,
            'status' => 'active',
        ]);

        // Lote sin stock (no debe contar)
        PurchaseBatch::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_available' => 0,
            'distribution_price' => 200.00,
            'status' => 'active',
        ]);

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(200);

        $this->assertEqualsWithDelta(60.00, $response->json('data.average_cost'), 0.001);
    }

    #[Test]
    public function puede_agregar_imagenes_despues_de_crear_producto()
    {
        $product = Product::factory()->create();

        $this->assertCount(0, $product->getMedia('images'));

        $image = UploadedFile::fake()->image('nueva-imagen.jpg');

        $response = $this->post(
            "/api/products/{$product->id}/images",
            ['images' => [$image]],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(201);

        $product->refresh();
        $this->assertCount(1, $product->getMedia('images'));
    }

    #[Test]
    public function eliminar_todas_las_imagenes_deja_producto_sin_imagen_principal()
    {
        $product = Product::factory()->create();

        $this->post(
            "/api/products/{$product->id}/images",
            ['images' => [UploadedFile::fake()->image('img.jpg')]],
            ['Accept' => 'application/json']
        );

        $media = $product->getMedia('images')->first();
        $this->deleteJson("/api/products/{$product->id}/images/{$media->id}");

        $product->refresh();
        $product->unsetRelation('media');
        $this->assertCount(0, $product->getMedia('images'));
    }

    #[Test]
    public function puede_subir_nueva_imagen_como_principal_despues_de_eliminar_todas()
    {
        $product = Product::factory()->create();

        // Subir y eliminar
        $upload = $this->post(
            "/api/products/{$product->id}/images",
            ['images' => [UploadedFile::fake()->image('img1.jpg')]],
            ['Accept' => 'application/json']
        );

        $media = $product->getMedia('images')->first();
        $this->deleteJson("/api/products/{$product->id}/images/{$media->id}");

        // Subir nueva
        $response = $this->post(
            "/api/products/{$product->id}/images",
            ['images' => [UploadedFile::fake()->image('img2.jpg')]],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(201)
            ->assertJsonPath('images.0.is_primary', true);
    }

    #[Test]
    public function muestra_tamano_de_archivo_en_formato_legible()
    {
        $product = Product::factory()->create();

        $image = UploadedFile::fake()
            ->image('producto.jpg', 800, 800)
            ->size(1500);

        $response = $this->post(
            "/api/products/{$product->id}/images",
            ['images' => [$image]],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(201)
            ->assertJsonStructure([
                'images' => [
                    '*' => ['size']
                ]
            ]);

        $size = $response->json('images.0.size');
        $this->assertIsString($size);
        $this->assertMatchesRegularExpression('/(B|KB|MB|GB)$/', $size);
    }

    // ================================================================
// TESTS DE VALIDACIÓN DE MENSAJES EN ESPAÑOL
// ================================================================

    #[Test]
    public function mensajes_de_validacion_estan_en_espanol()
    {
        $data = [
            'category_id' => $this->category->id,
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(422);

        $errors = $response->json('errors');
        $errorMessage = json_encode($errors);

        $this->assertStringContainsString('obligatorio', strtolower($errorMessage));
    }

    #[Test]
    public function mensaje_sku_duplicado_en_espanol()
    {
        Product::factory()->create(['sku' => 'TEST-SKU']);

        $data = [
            'sku' => 'TEST-SKU',
            'primary_name' => 'Producto Test',
            'category_id' => $this->category->id,
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(409);

        $message = $response->json('message');
        $this->assertStringContainsString('ya está registrado', $message);
    }

    // ================================================================
// TESTS DE PAGINACIÓN VACÍA
// ================================================================

    #[Test]
    public function paginacion_vacia_retorna_estructura_correcta()
    {
        $response = $this->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [],
                'meta' => [
                    'total' => 0,
                    'current_page' => 1,
                ],
            ]);
    }

    // ================================================================
// TESTS DE SCOPES Y QUERIES AVANZADAS
// ================================================================

    #[Test]
    public function scope_active_filtra_productos_activos()
    {
        Product::factory()->count(3)->create(['is_active' => true]);
        Product::factory()->count(2)->create(['is_active' => false]);

        $activeProducts = Product::active()->get();

        $this->assertCount(3, $activeProducts);
    }

    #[Test]
    public function scope_featured_filtra_productos_destacados()
    {
        Product::factory()->count(2)->create(['is_featured' => true]);
        Product::factory()->count(3)->create(['is_featured' => false]);

        $featuredProducts = Product::featured()->get();

        $this->assertCount(2, $featuredProducts);
    }

    #[Test]
    public function scope_visible_online_filtra_correctamente()
    {
        Product::factory()->create(['visible_online' => true, 'is_active' => true]);
        Product::factory()->create(['visible_online' => true, 'is_active' => false]); // No debería contar
        Product::factory()->create(['visible_online' => false, 'is_active' => true]);

        $visibleProducts = Product::visibleOnline()->get();

        $this->assertCount(1, $visibleProducts);
    }

    #[Test]
    public function scope_with_stock_filtra_productos_con_inventario()
    {
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();
        Product::factory()->create(); // Sin stock

        Inventory::create([
            'product_id' => $product1->id,
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 10,
        ]);

        Inventory::create([
            'product_id' => $product2->id,
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 5,
        ]);

        $productsWithStock = Product::withStock()->get();

        $this->assertCount(2, $productsWithStock);
    }

    #[Test]
    public function scope_low_stock_filtra_productos_bajo_stock()
    {
        $product1 = Product::factory()->create(['min_stock' => 10]);
        $product2 = Product::factory()->create(['min_stock' => 5]);
        $product3 = Product::factory()->create(['min_stock' => 20]);

        Inventory::create([
            'product_id' => $product1->id,
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 3, // Bajo
        ]);

        Inventory::create([
            'product_id' => $product2->id,
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 20, // OK
        ]);

        Inventory::create([
            'product_id' => $product3->id,
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 15, // Bajo
        ]);

        $lowStockProducts = Product::lowStock()->get();

        $this->assertCount(2, $lowStockProducts);
    }

    // ================================================================
// TESTS DE SOFT DELETE
// ================================================================

    #[Test]
    public function productos_eliminados_no_aparecen_en_listado_normal()
    {
        Product::factory()->count(3)->create();
        $deleted = Product::factory()->create();
        $deleted->delete();

        $response = $this->getJson('/api/products');

        $response->assertStatus(200);
        $this->assertEquals(3, count($response->json('data')));
    }

    #[Test]
    public function puede_incluir_productos_eliminados_con_parametro()
    {
        Product::factory()->count(3)->create();
        $deleted = Product::factory()->create();
        $deleted->delete();

        $response = $this->getJson('/api/products?with_trashed=1');

        $response->assertStatus(200);
        $this->assertEquals(4, count($response->json('data')));
    }

    // TESTS DE CAMPOS OPCIONALES
// ================================================================

    #[Test]
    public function acepta_secondary_name_opcional()
    {
        $data = [
            'primary_name' => 'Producto Test',
            'secondary_name' => 'Nombre Secundario',
            'category_id' => $this->category->id,
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(201);
        $this->assertDatabaseHas('products', [
            'primary_name' => 'Producto Test',
            'secondary_name' => 'Nombre Secundario',
        ]);
    }

    #[Test]
    public function acepta_description_opcional()
    {
        $data = [
            'primary_name' => 'Producto Test',
            'description' => 'Descripción detallada del producto',
            'category_id' => $this->category->id,
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(201);
        $this->assertDatabaseHas('products', [
            'description' => 'Descripción detallada del producto',
        ]);
    }

    #[Test]
    public function acepta_barcode_opcional()
    {
        $data = [
            'primary_name' => 'Producto Test',
            'barcode' => '7501234567890',
            'category_id' => $this->category->id,
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(201);
        $this->assertDatabaseHas('products', [
            'barcode' => '7501234567890',
        ]);
    }

    #[Test]
    public function acepta_weight_opcional()
    {
        $data = [
            'primary_name' => 'Producto Test',
            'weight' => 2.5,
            'category_id' => $this->category->id,
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(201);
        $this->assertDatabaseHas('products', [
            'weight' => 2.5,
        ]);
    }

    // ================================================================
// TESTS DE LÍMITES Y CASOS EXTREMOS
// ================================================================

    #[Test]
    public function valida_descripcion_maximo_5000_caracteres()
    {
        $data = [
            'primary_name' => 'Producto Test',
            'description' => str_repeat('a', 5001),
            'category_id' => $this->category->id,
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['description']);
    }

    #[Test]
    public function valida_secondary_name_maximo_100_caracteres()
    {
        $data = [
            'primary_name' => 'Producto Test',
            'secondary_name' => str_repeat('a', 101),
            'category_id' => $this->category->id,
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['secondary_name']);
    }

    #[Test]
    public function valida_sku_maximo_50_caracteres()
    {
        $data = [
            'sku' => str_repeat('A', 51),
            'primary_name' => 'Producto Test',
            'category_id' => $this->category->id,
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(409)
            ->assertJsonValidationErrors(['sku']);
    }

    #[Test]
    public function valida_brand_maximo_100_caracteres()
    {
        $data = [
            'primary_name' => 'Producto Test',
            'brand' => str_repeat('A', 101),
            'category_id' => $this->category->id,
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['brand']);
    }
}
