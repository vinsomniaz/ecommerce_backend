<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\PriceList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistributionListTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_only_products_with_distributor_price()
    {
        // 1. Setup Price Lists
        // ID 3 es Distribuidor según lo acordado. Usamos new + save para forzar ID.
        $distributorList = new PriceList();
        $distributorList->id = 3;
        $distributorList->code = 'DISTRIBUTOR';
        $distributorList->name = 'Precio Distribuidor';
        $distributorList->is_active = true;
        $distributorList->save();

        $retailList = new PriceList();
        $retailList->id = 1;
        $retailList->code = 'RETAIL';
        $retailList->name = 'Precio Minorista';
        $retailList->is_active = true;
        $retailList->save();

        // 2. Create Products
        $category = Category::factory()->create(['is_active' => true]);

        // Product A: Has Distributor Price (Active)
        $productA = Product::factory()->create([
            'is_active' => true,
            'primary_name' => 'Product A',
            'category_id' => $category->id,
        ]);

        ProductPrice::create([
            'product_id' => $productA->id,
            'price_list_id' => $distributorList->id, // ID 3
            'price' => 100.00,
            'currency' => 'PEN',
            'min_quantity' => 1,
            'is_active' => true,
            'valid_from' => now()->subDay(),
        ]);

        // Product B: Only Retail Price (Should NOT be returned)
        $productB = Product::factory()->create([
            'is_active' => true,
            'primary_name' => 'Product B',
            'category_id' => $category->id,
        ]);

        ProductPrice::create([
            'product_id' => $productB->id,
            'price_list_id' => $retailList->id, // ID 1
            'price' => 200.00,
            'currency' => 'PEN',
            'min_quantity' => 1,
            'is_active' => true,
            'valid_from' => now()->subDay(),
        ]);

        // Product C: Has Distributor Price but INACTIVE (Should NOT be returned)
        $productC = Product::factory()->create([
            'is_active' => true,
            'primary_name' => 'Product C',
            'category_id' => $category->id,
        ]);

        ProductPrice::create([
            'product_id' => $productC->id,
            'price_list_id' => $distributorList->id, // ID 3
            'price' => 150.00,
            'currency' => 'PEN',
            'min_quantity' => 1,
            'is_active' => false, // INACTIVE
            'valid_from' => now()->subDay(),
        ]);

        // 3. Call Endpoint
        $response = $this->getJson('/api/ecommerce/distribution-list');

        // 4. Assertions
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Product A', $data[0]['primary_name']);
        $this->assertEquals(100.00, $data[0]['distribution_price']); // Verify price is present
    }

    public function test_it_sorts_by_distributor_price()
    {
        // 1. Setup Price List
        $distributorList = new PriceList();
        $distributorList->id = 3;
        $distributorList->code = 'DISTRIBUTOR';
        $distributorList->name = 'Precio Distribuidor';
        $distributorList->is_active = true;
        $distributorList->save();

        $category = Category::factory()->create(['is_active' => true]);

        // Product Cheap (50.00)
        $productCheap = Product::factory()->create([
            'is_active' => true,
            'primary_name' => 'Product Cheap',
            'category_id' => $category->id,
        ]);
        ProductPrice::create([
            'product_id' => $productCheap->id,
            'price_list_id' => $distributorList->id,
            'price' => 50.00,
            'is_active' => true,
            'valid_from' => now()->subDay(),
        ]);

        // Product Expensive (500.00)
        $productExpensive = Product::factory()->create([
            'is_active' => true,
            'primary_name' => 'Product Expensive',
            'category_id' => $category->id,
        ]);
        ProductPrice::create([
            'product_id' => $productExpensive->id,
            'price_list_id' => $distributorList->id,
            'price' => 500.00,
            'is_active' => true,
            'valid_from' => now()->subDay(),
        ]);

        // Product Middle (100.00)
        $productMiddle = Product::factory()->create([
            'is_active' => true,
            'primary_name' => 'Product Middle',
            'category_id' => $category->id,
        ]);
        ProductPrice::create([
            'product_id' => $productMiddle->id,
            'price_list_id' => $distributorList->id,
            'price' => 100.00,
            'is_active' => true,
            'valid_from' => now()->subDay(),
        ]);

        // 2. Call Endpoint with Sort
        $response = $this->getJson('/api/ecommerce/distribution-list?sort_by=price&sort_order=asc');

        // 3. Assertions (Ascending)
        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(3, $data);
        $this->assertEquals('Product Cheap', $data[0]['primary_name']);
        $this->assertEquals('Product Middle', $data[1]['primary_name']);
        $this->assertEquals('Product Expensive', $data[2]['primary_name']);

        // 4. Call Endpoint with Sort Descending
        $responseDesc = $this->getJson('/api/ecommerce/distribution-list?sort_by=price&sort_order=desc');
        $dataDesc = $responseDesc->json('data');

        $this->assertEquals('Product Expensive', $dataDesc[0]['primary_name']);
        $this->assertEquals('Product Middle', $dataDesc[1]['primary_name']);
        $this->assertEquals('Product Cheap', $dataDesc[2]['primary_name']);
    }
}
