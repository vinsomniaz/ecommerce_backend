<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\PriceList;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EcommerceOptimizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ecommerce_product_list_response_structure_optimized()
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'visible_online' => true,
            'is_active' => true,
        ]);

        $warehouse = Warehouse::factory()->create(['is_active' => true]);
        // Manually create PriceList if factory is missing
        $priceList = PriceList::create([
            'name' => 'Retail',
            'code' => 'RETAIL',
            'is_active' => true,
            'currency' => 'PEN'
        ]);

        // Aseguramos que tenga precio
        ProductPrice::create([
            'product_id' => $product->id,
            'price_list_id' => $priceList->id,
            'warehouse_id' => $warehouse->id,
            'price' => 100,
            'min_price' => 90,
            'currency' => 'PEN',
            'is_active' => true,
            'valid_from' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/ecommerce/products');

        $response->assertOk();

        $data = $response->json('data.0');

        // Verificamos campos presentes (Públicos)
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('primary_name', $data);
        $this->assertArrayHasKey('sale_price', $data);
        $this->assertArrayHasKey('is_in_stock', $data);

        // Verificamos campos ELIMINADOS (Privados/Sensibles)
        $this->assertArrayNotHasKey('average_cost', $data, 'average_cost should NOT be present');
        $this->assertArrayNotHasKey('initial_cost', $data, 'initial_cost should NOT be present');
        $this->assertArrayNotHasKey('profit_margin', $data, 'profit_margin should NOT be present');
        $this->assertArrayNotHasKey('warehouse_prices', $data, 'warehouse_prices should NOT be present');
        $this->assertArrayNotHasKey('created_at', $data, 'created_at should NOT be present');
    }

    public function test_ecommerce_category_list_response_uses_optimized_resource()
    {
        Category::factory()->create(['is_active' => true, 'name' => 'Public Cat']);

        // Updated URL
        $response = $this->getJson('/api/ecommerce/categories');

        $response->assertOk();

        $data = $response->json('data.0');

        // Verificamos campos presentes
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('slug', $data);

        // Verificamos campos ELIMINADOS
        $this->assertArrayNotHasKey('min_margin_percentage', $data);
        $this->assertArrayNotHasKey('normal_margin_percentage', $data);
        $this->assertArrayNotHasKey('inherits_margins', $data);
        $this->assertArrayNotHasKey('created_at', $data);
    }
}
