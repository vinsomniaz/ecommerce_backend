<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        
        // Los productos requieren una categoría
        $this->category = Category::factory()->create(['level' => 1]);
    }

    /** @test */
    public function it_can_create_a_product()
    {
        $productData = [
            'primary_name' => 'Procesador Ryzen 5',
            'category_id' => $this->category->id,
            'unit_price' => 599.99,
            'cost_price' => 450.00,
            'min_stock' => 10,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
                         ->postJson('/api/products', $productData);

        $response->assertStatus(201);
        
        $response->assertJson([
            'message' => 'Producto creado exitosamente',
            'data' => [
                'primary_name' => 'Procesador Ryzen 5',
                'sku' => fn ($sku) => !empty($sku) // Verifica que el SKU se autogeneró
            ]
        ]);

        $this->assertDatabaseHas('products', [
            'primary_name' => 'Procesador Ryzen 5',
            'unit_price' => 599.99,
            'category_id' => $this->category->id,
        ]);
    }

    /** @test */
    public function it_validates_required_fields_for_product()
    {
        $response = $this->actingAs($this->user, 'sanctum')
                         ->postJson('/api/products', []); // Petición vacía

        $response->assertStatus(422);
        
        // Verifica los mensajes de error de tu StoreProductRequest
        $response->assertJsonValidationErrors(['primary_name', 'category_id', 'unit_price']);
        $response->assertJsonPath('errors.primary_name.0', 'El nombre del producto es obligatorio');
    }
}