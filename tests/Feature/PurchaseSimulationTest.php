<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Country;
use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\Warehouse;
use App\Models\Order;
use App\Models\PriceList;
use App\Models\ProductPrice;
use App\Models\Ubigeo;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\OrderService;
use Database\Seeders\DefaultSettingsSeeder;
use Database\Seeders\RoleSeeder;
use Faker\Factory as Faker;
use Illuminate\Support\Str;

class PurchaseSimulationTest extends TestCase
{
    // use RefreshDatabase; // DISABLE to persist state between steps

    protected static $initialized = false;
    protected static $sharedData = []; // Store data per iteration: [1 => ['user' => ...], 2 => ...]

    // CONFIGURACION: Numero de iteraciones
    const ITERATIONS = 3;

    public static function iterationsProvider(): array
    {
        $data = [];
        for ($i = 1; $i <= self::ITERATIONS; $i++) {
            $data["iteración_{$i}"] = [$i];
        }
        return $data;
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (!static::$initialized) {
            // Manual Reset & Seed ONLY ONCE
            $this->artisan('migrate:fresh');

            $this->seed([
                RoleSeeder::class,
                DefaultSettingsSeeder::class,
            ]);

            Country::factory()->create(['code' => 'PE', 'name' => 'PERU']);

            Ubigeo::factory()->create([
                'ubigeo' => '150101',
                'departamento' => 'LIMA',
                'provincia' => 'LIMA',
                'distrito' => 'LIMA',
                'country_code' => 'PE'
            ]);

            $warehouse = Warehouse::factory()->create([
                'name' => 'Almacén Principal',
                'is_main' => true,
            ]);

            // Create Products & Prices
            $category = Category::factory()->create();
            $priceList = PriceList::firstOrCreate(
                ['code' => 'MINORISTA'],
                ['name' => 'Lista Minorista', 'is_active' => true]
            );

            $products = Product::factory(10)->create([
                'category_id' => $category->id,
                'is_active' => true,
            ]);

            foreach ($products as $product) {
                Inventory::updateOrCreate(
                    ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
                    ['available_stock' => 500]
                );

                ProductPrice::create([
                    'product_id' => $product->id,
                    'price_list_id' => $priceList->id,
                    'price' => 100.00,
                    'currency' => 'PEN',
                    'min_quantity' => 1,
                    'is_active' => true,
                    'valid_from' => now()->subDay(),
                ]);
            }

            static::$initialized = true;
        }
    }

    /**
     * @dataProvider iterationsProvider
     */
    public function test_01_registro_de_cliente($iteration)
    {
        $faker = Faker::create('es_PE');
        // Unique email per iteration
        $email = 'user_' . uniqid() . "_iter{$iteration}@example.com";
        $password = 'password123';
        $dni = (string) $faker->numberBetween(10000000, 99999999);

        $userData = [
            'first_name' => $faker->firstName(),
            'last_name' => $faker->lastName(),
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $password,
            'phone' => '999888777',
            'document_type_id' => '01',
            'document_number' => $dni,
            'terms' => true,
        ];

        $response = $this->postJson('/api/register-customer', $userData);
        $response->assertStatus(200);

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user, "User $email not created");

        // Store in shared state
        static::$sharedData[$iteration]['user'] = $user;
    }

    /**
     * @dataProvider iterationsProvider
     * @depends test_01_registro_de_cliente
     */
    public function test_02_login_de_usuario($iteration)
    {
        $user = static::$sharedData[$iteration]['user'] ?? null;
        $this->assertNotNull($user, "User not found in shared state for iteration $iteration");

        $this->actingAs($user); // Login
        $this->assertAuthenticatedAs($user);
    }

    /**
     * @dataProvider iterationsProvider
     * @depends test_02_login_de_usuario
     */
    public function test_03_agregar_productos_al_carrito($iteration)
    {
        $user = static::$sharedData[$iteration]['user'] ?? null;
        $this->assertNotNull($user);

        $this->actingAs($user); // Restore session

        $products = Product::take(3)->get();
        // $this->assertCount(3, $products, "Need 3 products seeded");

        $addedCount = 0;
        foreach ($products as $product) {
            $response = $this->postJson('/api/ecommerce/cart/items', [
                'product_id' => $product->id,
                'quantity' => 1,
            ]);
            if ($response->status() === 200 || $response->status() === 201) {
                $addedCount++;
            }
        }
        $this->assertGreaterThan(0, $addedCount, "Failed to add items to cart");
    }

    /**
     * @dataProvider iterationsProvider
     * @depends test_03_agregar_productos_al_carrito
     */
    public function test_04_checkout_y_generacion_de_pedido($iteration)
    {
        $user = static::$sharedData[$iteration]['user'] ?? null;
        $this->assertNotNull($user);

        $this->actingAs($user);

        $ubigeo = Ubigeo::where('departamento', 'LIMA')->first();
        $ubigeoCode = $ubigeo ? $ubigeo->ubigeo : '150101';
        $entity = $user->entity;

        $checkoutData = [
            'customer_data' => [
                'tipo_documento' => $entity->tipo_documento ?? '01',
                'numero_documento' => $entity->numero_documento ?? '12345678',
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'phone' => '999888777',
            ],
            'address' => [
                'address' => 'Av. Test 123',
                'country_code' => 'PE',
                'ubigeo' => $ubigeoCode,
                'reference' => 'Test Auto',
                'phone' => '999888777',
            ],
            'currency' => 'PEN',
            'observations' => 'Test Check Step',
        ];

        $response = $this->postJson('/api/ecommerce/cart/checkout', $checkoutData);
        $response->assertStatus(201);

        $orderData = $response->json('data');
        $this->assertNotNull($orderData['id']);

        static::$sharedData[$iteration]['order_id'] = $orderData['id'];
    }

    /**
     * @dataProvider iterationsProvider
     * @depends test_04_checkout_y_generacion_de_pedido
     */
    public function test_05_procesamiento_de_pago($iteration)
    {
        $orderId = static::$sharedData[$iteration]['order_id'] ?? null;

        // Ensure orderId is scalar to avoid find() returning a Collection
        if (is_array($orderId)) {
            $orderId = reset($orderId);
        }
        $this->assertNotNull($orderId, "Order ID not found for iteration $iteration");

        $order = Order::find($orderId);
        $this->assertNotNull($order, "Order $orderId not found in DB");

        $orderService = app(OrderService::class);
        $sale = $orderService->confirmOrder($order, 'card', 'TXN-STEP-5-' . uniqid());

        $this->assertNotNull($sale);
        $this->assertEquals('paid', $sale->payment_status);

        static::$sharedData[$iteration]['sale'] = $sale;
    }
}
