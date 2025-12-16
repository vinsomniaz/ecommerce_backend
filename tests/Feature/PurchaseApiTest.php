<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Entity;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\Purchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Laravel\Sanctum\Sanctum;

class PurchaseApiTest extends TestCase
{
    // use RefreshDatabase; // Disabled to allow state persistence between steps

    protected static $initialized = false;
    protected static $user;
    protected static $supplier;
    protected static $warehouse;
    protected static $product;
    protected static $purchaseId;

    protected function setUp(): void
    {
        parent::setUp();

        if (!static::$initialized) {
            $this->artisan('migrate:fresh');
            static::$initialized = true;
            $this->setupEnvironment();
        }

        // Re-authenticate for each request if needed, or just ensure actingAs is called
        if (static::$user) {
            Sanctum::actingAs(static::$user, ['*']);
        }
    }

    protected function setupEnvironment()
    {
        // 1. Auth & Permissions
        $user = User::factory()->create();
        $permissions = ['purchases.index', 'purchases.store', 'purchases.show', 'purchases.update', 'purchases.destroy', 'purchases.statistics', 'purchases.payments.create'];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'api']);
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'sanctum']);
        }

        $roleSanctum = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'sanctum']);
        $roleSanctum->givePermissionTo(Permission::where('guard_name', 'sanctum')->get());
        $user->assignRole($roleSanctum);

        // Clear cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        static::$user = $user;

        // 2. Base Data
        static::$supplier = Entity::factory()->create(['type' => 'supplier']);
        static::$warehouse = Warehouse::factory()->create();
        static::$product = Product::factory()->create();
    }

    public function test_01_authentication_and_setup()
    {
        $this->assertNotNull(static::$user);
        $this->assertNotNull(static::$supplier);
        $this->assertTrue(static::$user->hasRole('super-admin'));
    }

    public function test_02_create_purchase()
    {
        $purchaseData = [
            'supplier_id' => static::$supplier->id,
            'warehouse_id' => static::$warehouse->id,
            'series' => 'TEST',
            'number' => '001-' . uniqid(),
            'date' => now()->format('Y-m-d'),
            'currency' => 'PEN',
            'exchange_rate' => 1,
            'notes' => 'Test Purchase',
            'products' => [
                [
                    'product_id' => static::$product->id,
                    'quantity' => 10,
                    'price' => 100
                ]
            ]
        ];

        $response = $this->postJson('/api/purchases', $purchaseData);
        $response->assertStatus(201)->assertJsonPath('success', true);

        static::$purchaseId = $response->json('data.id');
        $this->assertNotNull(static::$purchaseId);
    }

    public function test_03_list_purchases()
    {
        $listResponse = $this->getJson('/api/purchases?per_page=10');
        $listResponse->assertStatus(200)->assertJsonPath('success', true);

        // Verify our purchase is in the list
        $data = $listResponse->json('data');
        $ids = array_column($data, 'id');
        $this->assertContains(static::$purchaseId, $ids);
    }

    public function test_04_get_purchase_statistics()
    {
        $statsResponse = $this->getJson('/api/purchases/statistics/global');
        $statsResponse->assertStatus(200)->assertJsonPath('success', true);
    }

    public function test_05_update_purchase()
    {
        $updateData = ['notes' => 'Updated Notes'];
        $updateResponse = $this->putJson("/api/purchases/" . static::$purchaseId, $updateData);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.notes', 'Updated Notes');
    }

    public function test_06_register_payment()
    {
        $paymentData = [
            'amount' => 500,
            'payment_method' => 'cash',
            'paid_at' => now()->format('Y-m-d H:i:s'),
            'reference' => 'REF001'
        ];

        $paymentResponse = $this->postJson("/api/purchases/" . static::$purchaseId . "/payments", $paymentData);
        $paymentResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.amount', '500.00');

        $this->assertDatabaseHas('purchases', [
            'id' => static::$purchaseId,
            'payment_status' => 'partial'
        ]);
    }

    public function test_07_delete_purchase()
    {
        $deleteResponse = $this->deleteJson("/api/purchases/" . static::$purchaseId);
        $deleteResponse->assertStatus(200)->assertJsonPath('success', true);

        // Final verification
        $this->assertDatabaseMissing('purchases', ['id' => static::$purchaseId]);
    }
}
