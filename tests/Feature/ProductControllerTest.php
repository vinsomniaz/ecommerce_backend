<?php
// tests/Feature/ProductTest.php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity as ActivityModel;
use Storage;
use Tests\TestCase;
use App\Models\User;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user, 'sanctum');

        $this->category = Category::factory()->create();

        Storage::fake('public');
    }

    #[Test]
    public function puede_crear_producto_con_datos_minimos_requeridos()
    {
        $data = [
            'primary_name' => 'Producto Test',
            'category_id' => $this->category->id,
            'unit_price' => 100.50,
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
                    'unit_price',
                    'cost_price',
                    'min_stock',
                    'unit_measure',
                    'tax_type',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'primary_name' => 'Producto Test',
                    'unit_price' => 100.50,
                    'min_stock' => 5,
                    'unit_measure' => 'NIU',
                    'tax_type' => '10',
                    'is_active' => true,
                ],
            ]);

        $this->assertDatabaseHas('products', [
            'primary_name' => 'Producto Test',
            'unit_price' => 100.50,
            'min_stock' => 5,
            'unit_measure' => 'NIU',
            'tax_type' => '10',
        ]);
    }

    #[Test]
    public function genera_sku_automaticamente_si_no_se_proporciona()
    {
        $data = [
            'primary_name' => 'Producto Sin SKU',
            'category_id' => $this->category->id,
            'unit_price' => 50.00,
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(201);

        $product = Product::first();
        $this->assertNotNull($product->sku);
        $this->assertStringStartsWith('PRD-', $product->sku);
        $this->assertEquals(50.00, (float) $product->unit_price);
    }

    #[Test]
    public function acepta_sku_personalizado_unico()
    {
        $data = [
            'sku' => 'CUSTOM-SKU-001',
            'primary_name' => 'Producto con SKU Custom',
            'category_id' => $this->category->id,
            'unit_price' => 75.00,
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
            'unit_price' => 100.00,
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonPath('errors.sku.0', 'Este SKU ya está registrado en el sistema');
    }

    #[Test]
    public function valida_nombre_primario_obligatorio()
    {
        $data = [
            'category_id' => $this->category->id,
            'unit_price' => 100.00,
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
            'unit_price' => 100.00,
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['primary_name']);
    }

    #[Test]
    public function valida_categoria_existente_obligatoria()
    {
        $data = [
            'primary_name' => 'Producto Test',
            'unit_price' => 100.00,
        ];

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
            'unit_price' => 100.00,
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category_id']);
    }

    #[Test]
    public function valida_precio_unitario_obligatorio()
    {
        $data = [
            'primary_name' => 'Producto Test',
            'category_id' => $this->category->id,
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['unit_price']);
    }

    #[Test]
    public function valida_precio_unitario_mayor_a_cero()
    {
        $data = [
            'primary_name' => 'Producto Test',
            'category_id' => $this->category->id,
            'unit_price' => 0,
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['unit_price']);

        $data['unit_price'] = -10;
        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['unit_price']);
    }

    #[Test]
    public function acepta_precio_unitario_con_decimales()
    {
        $data = [
            'primary_name' => 'Producto Test',
            'category_id' => $this->category->id,
            'unit_price' => 99.99,
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(201);

        $this->assertDatabaseHas('products', [
            'primary_name' => 'Producto Test',
            'unit_price' => 99.99,
        ]);
    }

    #[Test]
    public function aplica_valores_por_defecto_correctamente()
    {
        $data = [
            'primary_name' => 'Producto con Defaults',
            'category_id' => $this->category->id,
            'unit_price' => 100.00,
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
                    'cost_price' => 0.00,
                ],
            ]);
    }

    #[Test]
    public function puede_sobrescribir_valores_por_defecto()
    {
        $data = [
            'primary_name' => 'Producto Custom',
            'category_id' => $this->category->id,
            'unit_price' => 100.00,
            'min_stock' => 10,
            'unit_measure' => 'KGM',
            'tax_type' => '20',
            'cost_price' => 50.00,
            'is_active' => false,
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'min_stock' => 10,
                    'unit_measure' => 'KGM',
                    'tax_type' => '20',
                    'cost_price' => 50.00,
                    'is_active' => false,
                ],
            ]);
    }

    #[Test]
    public function registra_actividad_al_crear_producto()
    {
        // Autenticación para que exista causer_id
        $this->actingAs($this->user); // o Sanctum::actingAs($this->user);

        $data = [
            'primary_name' => 'Producto para Log',
            'category_id' => $this->category->id,
            'unit_price' => 100.00,
        ];

        $this->postJson('/api/products', $data)->assertCreated();

        $this->assertDatabaseHas('activity_log', [
            'description' => 'Producto creado',
            'causer_id' => $this->user->id,
            'causer_type' => get_class($this->user),
        ]);

        // 👇 ahora sí sobre el modelo
        $activity = ActivityModel::query()->latest()->first();

        $this->assertEquals('Producto creado', $activity->description);
        $this->assertEquals($this->user->id, $activity->causer_id);
        $this->assertNotNull($activity->subject_id);
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
            'unit_price' => 150.00,
            'cost_price' => 90.00,
            'min_stock' => 15,
            'unit_measure' => 'UND',
            'tax_type' => '18',
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
            'unit_price' => 150.00,
            'cost_price' => 90.00,
            'min_stock' => 15,
            'unit_measure' => 'UND',
            'tax_type' => '18',
            'is_active' => true,
            'is_featured' => true,
            'visible_online' => true,
        ]);
    }

    // ========================================
    // TESTS DE IMÁGENES - CORREGIDOS Y AMPLIADOS
    // ========================================

    #[Test]
    public function puede_subir_imagenes_a_producto_existente()
    {
        Storage::fake('public');

        $product = Product::factory()->create([
            'primary_name' => 'Producto con Imágenes',
            'category_id' => $this->category->id,
            'unit_price' => 100.00,
        ]);

        $image1 = UploadedFile::fake()->image('producto1.jpg', 800, 600);
        $image2 = UploadedFile::fake()->image('producto2.png', 800, 600);

        // Usar post() con Accept header para multipart/form-data
        $response = $this->post(
            "/api/products/{$product->id}/images",
            ['images' => [$image1, $image2]],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Imágenes subidas exitosamente',
            ])
            ->assertJsonCount(2, 'images');

        $product->refresh();
        $this->assertCount(2, $product->getMedia('images'));
    }

    #[Test]
    public function primera_imagen_se_marca_como_principal_automaticamente()
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

        $image1 = UploadedFile::fake()->image('producto1.jpg');
        $image2 = UploadedFile::fake()->image('producto2.jpg');

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
    public function valida_maximo_5_imagenes_totales()
    {
        $product = Product::factory()->create();

        // Subir 3 imágenes primero
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

        // Intentar subir 3 más (excedería el límite)
        $response = $this->post(
            "/api/products/{$product->id}/images",
            [
                'images' => [
                    UploadedFile::fake()->image('img4.jpg'),
                    UploadedFile::fake()->image('img5.jpg'),
                    UploadedFile::fake()->image('img6.jpg'),
                ]
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
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
    public function puede_eliminar_imagen_especifica()
    {
        Sanctum::actingAs($this->user);
        Storage::fake('public'); // mismo disk que en tu colección

        $product = Product::factory()->create();

        $image = UploadedFile::fake()->image('producto.jpg', 800, 800)->size(800);

        $upload = $this->post(
            "/api/products/{$product->id}/images",
            ['images' => [$image]],
            ['Accept' => 'application/json']
        )->assertCreated();

        $mediaId = $upload->json('images.0.id');

        $this->deleteJson("/api/products/{$product->id}/images/{$mediaId}")
            ->assertOk()
            ->assertJson(['success' => true]);

        // Verifica en BD que se borró el registro
        $this->assertDatabaseMissing('media', ['id' => $mediaId]);

        // Refresca y limpia relación cacheada por si acaso
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
        $this->post(
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

    // ========================================
    // TESTS ORIGINALES DE ACTUALIZACIÓN Y OTROS
    // ========================================

    #[Test]
    public function puede_actualizar_producto()
    {
        $product = Product::factory()->create([
            'primary_name' => 'Nombre Original',
            'unit_price' => 100.00,
        ]);

        $data = [
            'primary_name' => 'Nombre Actualizado',
            'category_id' => $this->category->id,
            'unit_price' => 150.00,
        ];

        $response = $this->putJson("/api/products/{$product->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Producto actualizado exitosamente',
            ]);

        $product->refresh();
        $this->assertEquals('Nombre Actualizado', $product->primary_name);
        $this->assertEquals(150.00, (float) $product->unit_price);
    }

    #[Test]
    public function puede_actualizar_sku_a_uno_unico()
    {
        $product = Product::factory()->create(['sku' => 'OLD-SKU']);

        $data = [
            'sku' => 'NEW-SKU',
            'primary_name' => $product->primary_name,
            'category_id' => $product->category_id,
            'unit_price' => $product->unit_price,
        ];

        $response = $this->putJson("/api/products/{$product->id}", $data);

        $response->assertStatus(200);

        $product->refresh();
        $this->assertEquals('NEW-SKU', $product->sku);
    }

    #[Test]
    public function no_puede_actualizar_sku_a_uno_existente()
    {
        $product1 = Product::factory()->create(['sku' => 'SKU-001']);
        $product2 = Product::factory()->create(['sku' => 'SKU-002']);

        $data = [
            'sku' => 'SKU-001',
            'primary_name' => $product2->primary_name,
            'category_id' => $product2->category_id,
            'unit_price' => $product2->unit_price,
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
            'unit_price' => $product->unit_price,
        ];

        $this->putJson("/api/products/{$product->id}", $data);

        $this->assertDatabaseHas('activity_log', [
            'description' => 'Producto actualizado',
            'subject_id' => $product->id,
            'causer_id' => $this->user->id,
        ]);
    }

    #[Test]
    public function puede_eliminar_producto_sin_transacciones()
    {
        $this->markTestIncomplete('Pendiente: falta definir la lógica de eliminación segura.');

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
                        'unit_price',
                        'cost_price',
                        'min_stock',
                        'is_active',
                    ],
                ],
            ]);
    }

    #[Test]
    public function puede_buscar_productos_por_nombre()
    {
        Product::factory()->create(['primary_name' => 'Laptop HP']);
        Product::factory()->create(['primary_name' => 'Mouse Logitech']);
        Product::factory()->create(['primary_name' => 'Teclado HP']);

        $response = $this->getJson('/api/products?search=HP');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(2, $data);
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

        $data = $response->json('data');
        $this->assertCount(3, $data);
    }

    #[Test]
    public function puede_filtrar_productos_activos()
    {
        Product::factory()->count(3)->create(['is_active' => true]);
        Product::factory()->count(2)->create(['is_active' => false]);

        $response = $this->getJson('/api/products?is_active=1');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(3, $data);
    }

    #[Test]
    public function puede_actualizar_productos_masivamente()
    {
        $products = Product::factory()->count(3)->create(['is_active' => true]);

        $data = [
            'product_ids' => $products->pluck('id')->toArray(),
            'action' => 'deactivate',
        ];

        $response = $this->postJson('/api/products/bulk-update', $data);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'count' => 3,
            ]);

        foreach ($products as $product) {
            $product->refresh();
            $this->assertFalse($product->is_active);
        }
    }

    #[Test]
    public function puede_obtener_estadisticas()
    {
        $activos = Product::factory()->count(10)->create(['is_active' => true, 'is_featured' => false]);
        $inactivos = Product::factory()->count(5)->create(['is_active' => false, 'is_featured' => false]);

        $activos->take(3)->each->update(['is_featured' => true]);

        $response = $this->getJson('/api/products/statistics');

        $response->assertStatus(200);

        $stats = $response->json('data');
        $this->assertEquals(15, $stats['total_products']);
        $this->assertEquals(10, $stats['active_products']);
        $this->assertEquals(5, $stats['inactive_products']);
        $this->assertEquals(3, $stats['featured_products']);
    }

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

        $duplicated = Product::latest()->first();
        $this->assertNotEquals($product->sku, $duplicated->sku);
        $this->assertEquals('Producto Original (Copia)', $duplicated->primary_name);
        $this->assertFalse($duplicated->is_active);
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

    #[Test]
    public function valida_todos_los_campos_en_actualizacion()
    {
        $product = Product::factory()->create();

        $data = [
            'primary_name' => '',
            'category_id' => 99999,
            'unit_price' => -10,
        ];

        $response = $this->putJson("/api/products/{$product->id}", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'primary_name',
                'category_id',
                'unit_price',
            ]);
    }

    #[Test]
    public function responde_con_mensajes_en_espanol()
    {
        $data = [
            'category_id' => $this->category->id,
            'unit_price' => 0,
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(422);

        $errors = $response->json('errors');
        $this->assertStringContainsString('obligatorio', json_encode($errors));
    }

    #[Test]
    public function producto_duplicado_mantiene_relacion_con_categoria()
    {
        $product = Product::factory()->create();

        $response = $this->postJson("/api/products/{$product->id}/duplicate");

        $response->assertStatus(201);

        $duplicated = Product::latest()->first();
        $this->assertEquals($product->category_id, $duplicated->category_id);
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

        // Intentar subir 2 más (excedería el límite)
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

        foreach ($formats as $index => $image) {
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
    public function rechaza_formatos_de_archivo_no_validos()
    {
        $product = Product::factory()->create();

        $invalidFiles = [
            UploadedFile::fake()->create('document.pdf'),
            UploadedFile::fake()->create('video.mp4'),
            UploadedFile::fake()->create('audio.mp3'),
            UploadedFile::fake()->create('document.docx'),
        ];

        foreach ($invalidFiles as $file) {
            $response = $this->postJson(
                "/api/products/{$product->id}/images",
                ['images' => [$file]]
            );

            $response->assertStatus(422);
        }

        $product->refresh();
        $this->assertCount(0, $product->getMedia('images'));
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
        $this->assertCount(0, $product->getMedia('images'));
    }

    #[Test]
    public function puede_subir_nueva_imagen_como_principal_despues_de_eliminar_todas()
    {
        $product = Product::factory()->create();

        // Subir y eliminar
        $this->post(
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

    #[Test]
    public function muestra_tamano_de_archivo_en_formato_legible()
    {
        // Si usas Spatie Media Library en 'public'
        Storage::fake('public');

        // Autentica si la ruta está protegida
        Sanctum::actingAs($this->user);

        $product = Product::factory()->create();

        // ⬇️ Genera una imagen >= 500x500 y de ~1.5 MB
        $image = UploadedFile::fake()
            ->image('producto.jpg', 800, 800) // dimensiones válidas
            ->size(1500);                      // KB (≈1.5 MB)

        $response = $this->post(
            "/api/products/{$product->id}/images",
            ['images' => [$image]],
            ['Accept' => 'application/json'] // no uses postJson para archivos
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
}
