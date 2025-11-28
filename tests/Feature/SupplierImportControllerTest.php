<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Entity;
use App\Models\SupplierImport;
use App\Jobs\ProcessSupplierImportJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;

class SupplierImportControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Entity $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);

        $this->supplier = Entity::factory()->create([
            'type' => 'supplier',
            'business_name' => 'Deltron Peru SAC',
            'trade_name' => 'deltron',
            'numero_documento' => '20123456789',
            'tipo_documento' => '06',
        ]);
    }

    #[Test]
    public function puede_recibir_importacion_desde_scraper()
    {
        // Arrange
        Queue::fake();

        $data = [
            'products' => [
                [
                    'supplier_sku' => 'DELT-001',
                    'name' => 'Laptop HP Pavilion',
                    'price' => 1299.99,
                    'stock' => 50,
                    'currency' => 'PEN',
                    'url' => 'https://deltron.com.pe/laptop-hp',
                ],
                [
                    'supplier_sku' => 'DELT-002',
                    'name' => 'Mouse Logitech',
                    'price' => 45.00,
                    'stock' => 200,
                    'currency' => 'PEN',
                ],
            ]
        ];

        // Act
        $response = $this->postJson("/api/suppliers/deltron/import", $data);

        // Assert
        $response->assertStatus(202)
            ->assertJsonStructure([
                'message',
                'import_id',
                'total_products',
                'status',
            ])
            ->assertJson([
                'total_products' => 2,
                'status' => 'pending',
            ]);

        $this->assertDatabaseHas('supplier_imports', [
            'supplier_id' => $this->supplier->id,
            'status' => 'pending',
            'total_products' => 2,
        ]);

        Queue::assertPushed(ProcessSupplierImportJob::class);
    }

    #[Test]
    public function rechaza_importacion_con_proveedor_inexistente()
    {
        // Arrange
        $data = [
            'products' => [
                [
                    'supplier_sku' => 'TEST-001',
                    'name' => 'Producto Test',
                    'price' => 100.00,
                ]
            ]
        ];

        // Act
        $response = $this->postJson('/api/suppliers/proveedor-inexistente/import', $data);

        // Assert
        $response->assertStatus(404)
            ->assertJson([
                'error' => 'supplier_not_found',
            ]);
    }

    #[Test]
    public function valida_datos_requeridos_en_importacion()
    {
        // Arrange - Data incompleta
        $data = [
            'products' => [
                [
                    'supplier_sku' => 'TEST-001',
                    // Falta 'name' y 'price'
                ]
            ]
        ];

        // Act
        $response = $this->postJson('/api/suppliers/deltron/import', $data);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['products.0.name', 'products.0.price']);
    }

    #[Test]
    public function puede_listar_importaciones()
    {
        // Arrange
        SupplierImport::factory()->count(5)->create([
            'supplier_id' => $this->supplier->id,
        ]);

        // Act
        $response = $this->getJson('/api/supplier-imports');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'supplier_id',
                        'status',
                        'total_products',
                        'processed_products',
                    ]
                ],
                'meta'
            ]);
    }

    #[Test]
    public function puede_filtrar_importaciones_por_proveedor()
    {
        // Arrange
        $supplier2 = Entity::factory()->create(['type' => 'supplier']);

        SupplierImport::factory()->count(3)->create([
            'supplier_id' => $this->supplier->id,
        ]);

        SupplierImport::factory()->count(2)->create([
            'supplier_id' => $supplier2->id,
        ]);

        // Act
        $response = $this->getJson("/api/supplier-imports?supplier_id={$this->supplier->id}");

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function puede_filtrar_importaciones_por_estado()
    {
        // Arrange
        SupplierImport::factory()->count(2)->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'completed',
        ]);

        SupplierImport::factory()->count(3)->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'pending',
        ]);

        // Act
        $response = $this->getJson('/api/supplier-imports?status=completed');

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function puede_ver_detalle_de_importacion()
    {
        // Arrange
        $import = SupplierImport::factory()->create([
            'supplier_id' => $this->supplier->id,
            'raw_data' => json_encode([
                ['supplier_sku' => 'TEST-001', 'name' => 'Producto 1', 'price' => 100],
            ]),
        ]);

        // Act
        $response = $this->getJson("/api/supplier-imports/{$import->id}");

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'supplier_id',
                    'supplier',
                    'status',
                    'raw_data',
                ]
            ]);
    }

    #[Test]
    public function puede_reprocesar_importacion_fallida()
    {
        // Arrange
        Queue::fake();

        $import = SupplierImport::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'failed',
            'error_message' => 'Error de prueba',
        ]);

        // Act
        $response = $this->postJson("/api/supplier-imports/{$import->id}/reprocess");

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Importación reencolada para reprocesamiento',
            ]);

        $this->assertDatabaseHas('supplier_imports', [
            'id' => $import->id,
            'status' => 'pending',
            'error_message' => null,
        ]);

        Queue::assertPushed(ProcessSupplierImportJob::class);
    }

    #[Test]
    public function no_puede_reprocesar_importacion_exitosa()
    {
        // Arrange
        $import = SupplierImport::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'completed',
        ]);

        // Act
        $response = $this->postJson("/api/supplier-imports/{$import->id}/reprocess");

        // Assert
        $response->assertStatus(422)
            ->assertJson([
                'error' => 'invalid_status',
            ]);
    }

    #[Test]
    public function puede_obtener_estadisticas_de_importaciones()
    {
        // Arrange
        SupplierImport::factory()->count(5)->create([
            'status' => 'completed',
            'total_products' => 10,
            'processed_products' => 10,
        ]);

        SupplierImport::factory()->count(2)->create([
            'status' => 'failed',
            'total_products' => 5,
        ]);

        SupplierImport::factory()->count(3)->create([
            'status' => 'pending',
            'total_products' => 8,
        ]);

        // Act
        $response = $this->getJson('/api/supplier-imports/statistics/summary');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total_imports',
                    'pending',
                    'processing',
                    'completed',
                    'failed',
                    'total_products_imported',
                    'total_products_processed',
                ]
            ])
            ->assertJson([
                'data' => [
                    'total_imports' => 10,
                    'completed' => 5,
                    'failed' => 2,
                    'pending' => 3,
                ]
            ]);
    }

    #[Test]
    public function endpoint_publico_tiene_rate_limit()
    {
        // Arrange - Crear 61 peticiones (límite es 60/minuto)
        $data = [
            'products' => [
                [
                    'supplier_sku' => 'TEST-001',
                    'name' => 'Test Product',
                    'price' => 100.00,
                ]
            ]
        ];

        // Act - Hacer 61 requests
        for ($i = 0; $i < 61; $i++) {
            $response = $this->postJson('/api/suppliers/deltron/import', $data);
        }

        // Assert - El último debe ser rate limited
        $response->assertStatus(429); // Too Many Requests
    }
}