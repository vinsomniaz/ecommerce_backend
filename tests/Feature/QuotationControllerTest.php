<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Inventory;
use App\Models\Category;
use App\Models\Entity;
use App\Models\Quotation;
use App\Models\QuotationDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;

class QuotationControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Product $product;
    protected Warehouse $warehouse;
    protected Category $category;
    protected Entity $customer;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear usuario y autenticar con Sanctum
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);

        // Crear categoría con márgenes
        $this->category = Category::factory()->create([
            'name' => 'Procesadores',
            'min_margin_percentage' => 10,
            'normal_margin_percentage' => 20,
        ]);

        // Crear producto de prueba
        $this->product = Product::factory()->create([
            'sku' => 'CPU-TEST-001',
            'primary_name' => 'Procesador Test',
            'category_id' => $this->category->id,
            'distribution_price' => 500.00,
            'is_active' => true,
        ]);

        // Crear almacén de prueba
        $this->warehouse = Warehouse::factory()->create([
            'name' => 'Almacén Principal',
            'is_main' => true,
            'is_active' => true,
        ]);

        // Crear inventario
        Inventory::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'available_stock' => 100,
            'reserved_stock' => 0,
            'sale_price' => 750.00,
            'last_movement_at' => now(),
        ]);

        // Crear cliente
        $this->customer = Entity::factory()->create([
            'type' => 'customer',
            'business_name' => 'Cliente Test SAC',
            'numero_documento' => '20123456789',
            'tipo_documento' => '06',
        ]);
    }

    #[Test]
    public function puede_listar_cotizaciones()
    {
        // Arrange
        Quotation::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
        ]);

        // Act
        $response = $this->getJson('/api/quotations');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'quotation_code',
                        'quotation_date',
                        'valid_until',
                        'status',
                        'customer',
                        'seller',
                        'total',
                    ]
                ],
                'meta' => ['current_page', 'per_page', 'total', 'last_page']
            ]);
    }

    #[Test]
    public function puede_crear_cotizacion_con_items()
    {
        // Arrange
        $data = [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'customer_name' => $this->customer->business_name,
            'customer_document' => $this->customer->numero_documento,
            'currency' => 'PEN',
            'valid_days' => 15,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'product_name' => $this->product->primary_name,
                    'product_sku' => $this->product->sku,
                    'quantity' => 2,
                    'unit_price' => 750.00,
                    'discount' => 0,
                    'source_type' => 'warehouse',
                    'warehouse_id' => $this->warehouse->id,
                    'purchase_price' => 500.00,
                ]
            ],
        ];

        // Act
        $response = $this->postJson('/api/quotations', $data);

        // Assert
        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'quotation_code',
                    'status',
                    'total',
                    'items',
                ]
            ])
            ->assertJson([
                'data' => [
                    'status' => 'draft',
                ]
            ]);

        $this->assertDatabaseHas('quotations', [
            'customer_id' => $this->customer->id,
            'status' => 'draft',
        ]);
    }

    #[Test]
    public function puede_ver_detalle_de_cotizacion()
    {
        // Arrange
        $quotation = Quotation::factory()->create([
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
        ]);

        QuotationDetail::create([
            'quotation_id' => $quotation->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->primary_name,
            'quantity' => 2,
            'unit_price' => 750.00,
            'subtotal' => 1500.00,
            'tax_amount' => 270.00,
            'total' => 1770.00,
        ]);

        // Act
        $response = $this->getJson("/api/quotations/{$quotation->id}");

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'quotation_code',
                    'items' => [
                        '*' => [
                            'product',
                            'quantity',
                            'unit_price',
                        ]
                    ]
                ]
            ]);
    }

    #[Test]
    public function puede_agregar_item_a_cotizacion()
    {
        // Arrange
        $quotation = Quotation::factory()->create([
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'draft',
        ]);

        $itemData = [
            'product_id' => $this->product->id,
            'product_name' => $this->product->primary_name,
            'product_sku' => $this->product->sku,
            'quantity' => 1,
            'unit_price' => 750.00,
            'source_type' => 'warehouse',
            'warehouse_id' => $this->warehouse->id,
            'purchase_price' => 500.00,
        ];

        // Act
        $response = $this->postJson("/api/quotations/{$quotation->id}/items", $itemData);

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Producto agregado exitosamente',
            ]);

        $this->assertDatabaseHas('quotation_details', [
            'quotation_id' => $quotation->id,
            'product_id' => $this->product->id,
        ]);
    }

    #[Test]
    public function puede_eliminar_item_de_cotizacion()
    {
        // Arrange
        $quotation = Quotation::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'draft',
        ]);

        $detail = QuotationDetail::create([
            'quotation_id' => $quotation->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->primary_name,
            'quantity' => 1,
            'unit_price' => 750.00,
        ]);

        // Act
        $response = $this->deleteJson("/api/quotations/{$quotation->id}/items/{$detail->id}");

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Producto eliminado exitosamente',
            ]);

        $this->assertDatabaseMissing('quotation_details', [
            'id' => $detail->id,
        ]);
    }

    #[Test]
    public function puede_actualizar_cantidad_de_item()
    {
        // Arrange
        $quotation = Quotation::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'draft',
        ]);

        $detail = QuotationDetail::create([
            'quotation_id' => $quotation->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->primary_name,
            'quantity' => 1,
            'unit_price' => 750.00,
            'subtotal' => 750.00,
        ]);

        // Act
        $response = $this->patchJson(
            "/api/quotations/{$quotation->id}/items/{$detail->id}/quantity",
            ['quantity' => 5]
        );

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Cantidad actualizada exitosamente',
            ]);

        $this->assertDatabaseHas('quotation_details', [
            'id' => $detail->id,
            'quantity' => 5,
        ]);
    }

    #[Test]
    public function no_puede_editar_cotizacion_que_no_esta_en_borrador()
    {
        // Arrange
        $quotation = Quotation::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'sent', // No es draft
        ]);

        // Act
        $response = $this->patchJson("/api/quotations/{$quotation->id}", [
            'observations' => 'Nuevo texto',
        ]);

        // Assert
        $response->assertStatus(422)
            ->assertJson([
                'error' => 'invalid_status',
            ]);
    }

    #[Test]
    public function puede_cambiar_estado_de_cotizacion()
    {
        // Arrange
        $quotation = Quotation::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'draft',
        ]);

        // Act
        $response = $this->postJson("/api/quotations/{$quotation->id}/status", [
            'status' => 'sent',
            'notes' => 'Enviada al cliente',
        ]);

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Estado actualizado exitosamente',
            ]);

        $this->assertDatabaseHas('quotations', [
            'id' => $quotation->id,
            'status' => 'sent',
        ]);
    }

    #[Test]
    public function puede_duplicar_cotizacion()
    {
        // Arrange
        $quotation = Quotation::factory()->create([
            'user_id' => $this->user->id,
        ]);

        QuotationDetail::create([
            'quotation_id' => $quotation->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->primary_name,
            'quantity' => 2,
            'unit_price' => 750.00,
        ]);

        // Act
        $response = $this->postJson("/api/quotations/{$quotation->id}/duplicate");

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Cotización duplicada exitosamente',
            ]);

        $this->assertEquals(2, Quotation::count());
        $this->assertEquals(2, QuotationDetail::count());
    }

    #[Test]
    public function puede_validar_disponibilidad_de_cotizacion()
    {
        // Arrange
        $quotation = Quotation::factory()->create([
            'user_id' => $this->user->id,
            'warehouse_id' => $this->warehouse->id,
            'valid_until' => now()->subDays(5), // Vencida
        ]);

        QuotationDetail::create([
            'quotation_id' => $quotation->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'product_name' => $this->product->primary_name,
            'quantity' => 2,
            'unit_price' => 750.00,
            'source_type' => 'warehouse',
        ]);

        // Act
        $response = $this->getJson("/api/quotations/{$quotation->id}/validate-availability");

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'quotation_id',
                'is_expired',
                'can_be_used',
                'validation_summary',
                'unavailable_items',
                'price_changes',
                'stock_warnings',
            ]);
    }

    #[Test]
    public function puede_verificar_stock_de_productos()
    {
        // Arrange
        $data = [
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'warehouse_id' => $this->warehouse->id,
                    'quantity' => 50,
                ]
            ]
        ];

        // Act
        $response = $this->postJson('/api/quotations/check-stock', $data);

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'product_id',
                        'warehouse_id',
                        'requested_quantity',
                        'available_stock',
                        'sufficient',
                    ]
                ]
            ]);
    }

    #[Test]
    public function puede_calcular_totales_sin_guardar()
    {
        // Arrange
        $data = [
            'items' => [
                [
                    'unit_price' => 750.00,
                    'quantity' => 2,
                    'discount' => 100.00,
                ],
                [
                    'unit_price' => 500.00,
                    'quantity' => 1,
                    'discount' => 0,
                ],
            ],
            'shipping_cost' => 50.00,
        ];

        // Act
        $response = $this->postJson('/api/quotations/calculate-totals', $data);

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'subtotal',
                'tax',
                'total',
            ]);
    }

    #[Test]
    public function puede_obtener_estadisticas_de_cotizaciones()
    {
        // Arrange
        Quotation::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'status' => 'sent',
        ]);

        Quotation::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'status' => 'accepted',
        ]);

        // Act
        $response = $this->getJson('/api/quotations/statistics/general');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total',
                    'draft',
                    'sent',
                    'accepted',
                    'total_amount',
                    'total_margin',
                ]
            ]);
    }

    #[Test]
    public function rechaza_cotizacion_con_margen_bajo()
    {
        // Arrange - Precio muy bajo que no cumple margen mínimo
        $data = [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'customer_name' => $this->customer->business_name,
            'customer_document' => $this->customer->numero_documento,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'product_name' => $this->product->primary_name,
                    'quantity' => 1,
                    'unit_price' => 520.00, // Solo 4% de margen, menos del 10% mínimo
                    'source_type' => 'warehouse',
                    'warehouse_id' => $this->warehouse->id,
                    'purchase_price' => 500.00,
                ]
            ],
        ];

        // Act
        $response = $this->postJson('/api/quotations', $data);

        // Assert
        $response->assertStatus(422)
            ->assertJson([
                'error' => 'low_margin',
            ]);
    }
}