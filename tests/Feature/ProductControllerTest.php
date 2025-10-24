<?php
// tests/Feature/ProductTest.php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

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

    /** @test */
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
                    'min_stock' => 5, // Valor por defecto
                    'unit_measure' => 'NIU', // Valor por defecto
                    'tax_type' => '10', // Valor por defecto
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

    /** @test */
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

    /** @test */
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

    /** @test */
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
                'message' => 'Este SKU ya está registrado en el sistema',
            ])
            ->assertJsonPath('errors.sku.0', 'Este SKU ya está registrado en el sistema');
    }

    /** @test */
    public function valida_nombre_primario_obligatorio()
    {
        $data = [
            'category_id' => $this->category->id,
            'unit_price' => 100.00,
            // Sin primary_name
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['primary_name']);
    }

    /** @test */
    public function valida_nombre_primario_maximo_200_caracteres()
    {
        $data = [
            'primary_name' => str_repeat('a', 201), // 201 caracteres
            'category_id' => $this->category->id,
            'unit_price' => 100.00,
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['primary_name']);
    }

    /** @test */
    public function valida_categoria_existente_obligatoria()
    {
        $data = [
            'primary_name' => 'Producto Test',
            'unit_price' => 100.00,
            // Sin category_id
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category_id']);
    }

    /** @test */
    public function valida_categoria_existente_en_base_datos()
    {
        $data = [
            'primary_name' => 'Producto Test',
            'category_id' => 99999, // ID inexistente
            'unit_price' => 100.00,
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category_id']);
    }

    /** @test */
    public function valida_precio_unitario_obligatorio()
    {
        $data = [
            'primary_name' => 'Producto Test',
            'category_id' => $this->category->id,
            // Sin unit_price
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['unit_price']);
    }

    /** @test */
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

        // Probar con precio negativo
        $data['unit_price'] = -10;
        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['unit_price']);
    }

    /** @test */
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

    /** @test */
    public function aplica_valores_por_defecto_correctamente()
    {
        $data = [
            'primary_name' => 'Producto con Defaults',
            'category_id' => $this->category->id,
            'unit_price' => 100.00,
            // No especificar min_stock, unit_measure, tax_type
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

    /** @test */
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

    /** @test */
    public function registra_actividad_al_crear_producto()
    {
        $data = [
            'primary_name' => 'Producto para Log',
            'category_id' => $this->category->id,
            'unit_price' => 100.00,
        ];

        $this->postJson('/api/products', $data);

        $this->assertDatabaseHas('activity_log', [
            'description' => 'Producto creado',
            'causer_id' => $this->user->id,
            'causer_type' => get_class($this->user),
        ]);

        $activity = Activity::latest()->first();
        $this->assertEquals('Producto creado', $activity->description);
        $this->assertEquals($this->user->id, $activity->causer_id);
        $this->assertNotNull($activity->subject_id);
    }

    /** @test */
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
            'weight' => 2.5,
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
            'weight' => 2.5,
            'is_active' => true,
            'is_featured' => true,
            'visible_online' => true,
        ]);
    }

    /** @test */
    public function puede_subir_imagenes_al_crear_producto()
    {
         $this->markTestIncomplete('Esta prueba está pendiente de implementación.');
        // Fijar el disco que usa Media Library (public o el que tengas configurado)
        Storage::fake(config('media-library.disk_name', 'public'));

        $image1 = UploadedFile::fake()->image('producto1.jpg', 800, 600);
        $image2 = UploadedFile::fake()->image('producto2.png', 800, 600);

        $data = [
            'primary_name' => 'Producto con Imágenes',
            'category_id'  => $this->category->id,
            'unit_price'   => 100.00,
            'images'       => [$image1, $image2],
        ];

        // Usa post normal (multipart), pero mantén Accept JSON
        $response = $this->post('/api/products', $data, ['Accept' => 'application/json']);

        $response->assertStatus(201);

        $product = Product::first();
        $this->assertCount(2, $product->getMedia('images'));
    }

    /** @test */
    public function valida_maximo_5_imagenes()
    {
        $images = [];
        for ($i = 0; $i < 6; $i++) {
            $images[] = UploadedFile::fake()->image("producto{$i}.jpg");
        }

        $data = [
            'primary_name' => 'Producto con Muchas Imágenes',
            'category_id' => $this->category->id,
            'unit_price' => 100.00,
            'images' => $images,
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['images']);
    }

    /** @test */
    public function valida_tipo_de_archivo_imagen()
    {
        $invalidFile = UploadedFile::fake()->create('document.pdf', 100);

        $data = [
            'primary_name' => 'Producto Test',
            'category_id' => $this->category->id,
            'unit_price' => 100.00,
            'images' => [$invalidFile],
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['images.0']);
    }

    /** @test */
    public function valida_tamano_maximo_de_imagen_2mb()
    {
        $largeImage = UploadedFile::fake()->create('large.jpg', 3000); // 3MB

        $data = [
            'primary_name' => 'Producto Test',
            'category_id' => $this->category->id,
            'unit_price' => 100.00,
            'images' => [$largeImage],
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['images.0']);
    }

    /** @test */
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

    /** @test */
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

    /** @test */
    public function no_puede_actualizar_sku_a_uno_existente()
    {
        $product1 = Product::factory()->create(['sku' => 'SKU-001']);
        $product2 = Product::factory()->create(['sku' => 'SKU-002']);

        $data = [
            'sku' => 'SKU-001', // SKU de product1
            'primary_name' => $product2->primary_name,
            'category_id' => $product2->category_id,
            'unit_price' => $product2->unit_price,
        ];

        $response = $this->putJson("/api/products/{$product2->id}", $data);

        $response->assertStatus(409);
    }

    /** @test */
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

    /** @test */
    public function puede_eliminar_producto_sin_transacciones()
    {
        $this->markTestIncomplete('Esta prueba está pendiente de implementación.');
        $this->actingAs($this->user);
        $product = Product::factory()->create();

        $response = $this->deleteJson("/api/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Producto eliminado exitosamente',
            ]);

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    /** @test */
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

    /** @test */
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
                'meta',
                'links',
            ]);
    }

    /** @test */
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

    /** @test */
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

    /** @test */
    public function puede_filtrar_productos_activos()
    {
        Product::factory()->count(3)->create(['is_active' => true]);
        Product::factory()->count(2)->create(['is_active' => false]);

        $response = $this->getJson('/api/products?is_active=1');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(3, $data);
    }

    /** @test */
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

    /** @test */
    public function puede_obtener_estadisticas()
    {
        // Crea 15 productos base
        $activos   = Product::factory()->count(10)->create(['is_active' => true,  'is_featured' => false]);
        $inactivos = Product::factory()->count(5)->create(['is_active' => false, 'is_featured' => false]);

        // Marca 3 EXISTENTES como destacados
        $activos->take(3)->each->update(['is_featured' => true]);

        $response = $this->getJson('/api/products/statistics');

        $response->assertStatus(200);

        $stats = $response->json('data');
        $this->assertEquals(15, $stats['total_products']);
        $this->assertEquals(10, $stats['active_products']);
        $this->assertEquals(5,  $stats['inactive_products']);
        $this->assertEquals(3,  $stats['featured_products']);
    }


    /** @test */
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

    /** @test */
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

    /** @test */
    public function retorna_404_para_producto_inexistente()
    {
        $response = $this->getJson('/api/products/99999');

        $response->assertStatus(404);
    }

    /** @test */
    public function valida_todos_los_campos_en_actualizacion()
    {
        $product = Product::factory()->create();

        $data = [
            'primary_name' => '', // Vacío
            'category_id' => 99999, // No existe
            'unit_price' => -10, // Negativo
        ];

        $response = $this->putJson("/api/products/{$product->id}", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'primary_name',
                'category_id',
                'unit_price',
            ]);
    }

    /** @test */
    public function responde_con_mensajes_en_espanol()
    {
        $data = [
            'category_id' => $this->category->id,
            'unit_price' => 0, // Inválido
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertStatus(422);

        $errors = $response->json('errors');
        $this->assertStringContainsString('obligatorio', json_encode($errors));
    }
}
