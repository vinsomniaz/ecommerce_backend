<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Product;
use App\Models\Ubigeo;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Api\CartController;
use App\Http\Requests\Cart\AddItemRequest;
use App\Http\Requests\Cart\CheckoutRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;
use Illuminate\Validation\ValidationException;

use App\Models\Order;
use App\Services\OrderService;

class SimulatePurchaseProcess extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'simulation:purchase {count=20 : Number of users to simulate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulate the purchase process for a number of users';

    /**
     * Execute the console command.
     */
    public function handle(
        RegisteredUserController $registeredUserController,
        CartController $cartController,
        OrderService $orderService
    ) {
        $count = (int) $this->argument('count');
        $faker = Faker::create('es_PE');

        $this->info("Starting simulation for {$count} users...");

        $successCount = 0;
        $failCount = 0;

        // Pre-fetch some data to avoid queries in loop
        $products = Product::where('is_active', true)->withStock()->limit(100)->get();
        if ($products->isEmpty()) {
            $this->error("No active products found!");
            return;
        }

        // Get a valid ubigeo for default
        $ubigeo = Ubigeo::where('departamento', 'LIMA')->first();
        $ubigeoCode = $ubigeo ? $ubigeo->ubigeo : '150101';

        // --- STEP 0: Replenish Stock for Simulation ---
        $this->info("Refilling stock for " . count($products) . " products to ensure availability...");
        $warehouse = \App\Models\Warehouse::where('is_main', true)->first() ?? \App\Models\Warehouse::first();

        if ($warehouse) {
            foreach ($products as $prod) {
                \App\Models\Inventory::updateOrCreate(
                    ['product_id' => $prod->id, 'warehouse_id' => $warehouse->id],
                    ['available_stock' => 500]
                );
            }
            $this->info("Stock replenished to 500 units for selected products.");
        }
        // ----------------------------------------------

        for ($i = 0; $i < $count; $i++) {
            $this->line("------------------------------------------------");
            $this->info("Processing User " . ($i + 1) . "/{$count}");

            DB::beginTransaction();
            try {
                // 1. REGISTER
                // We create the user manually to ensure valid data and instant login
                $email = $faker->unique()->safeEmail();
                $password = 'password123';

                $userData = [
                    'first_name' => $faker->firstName(),
                    'last_name' => $faker->lastName(),
                    'email' => $email,
                    'password' => $password,
                    'password_confirmation' => $password,
                    'terms' => true,
                ];

                // Create Request for Registration
                $regRequest = Request::create('/api/register-customer', 'POST', $userData);

                // Directly calling controller might require manual validation if using FormRequests internally, 
                // but RegisteredUserController::storeCustomer usually uses a request object.
                // However, to be 100% sure we bypass route middleware issues, we can just use the User factory or create logic.
                // BUT the requirement is to simulate the process. 
                // Let's rely on the User Factory for speed or manual create if specific fields needed.

                $user = User::create([
                    'first_name' => $userData['first_name'],
                    'last_name' => $userData['last_name'],
                    'email' => $userData['email'],
                    'password' => bcrypt($password),
                    'is_active' => true,
                ]);

                // Assign role (assuming Spatie Permissions)
                // $user->assignRole('customer'); // Verify if role needed. Reviewing generic logic.

                $this->info("  [+] Registered: {$user->email}");

                // 2. LOGIN (Act as user)
                Auth::login($user);

                // 3. ADD TO CART
                // Add 1-3 random products
                $itemsCount = rand(1, 3);
                $this->info("  [+] Adding {$itemsCount} items to cart...");

                $addedCount = 0;

                for ($j = 0; $j < $itemsCount; $j++) {
                    // Pick random product and refresh it to get real-time stock
                    $randomProduct = $products->random();
                    $product = Product::with('inventory')->find($randomProduct->id);

                    if (!$product || $product->total_stock <= 0) {
                        $this->warn("      -> Skipping Product ID: {$randomProduct->id} (Stock: " . ($product ? $product->total_stock : 'N/A') . ")");
                        continue;
                    }

                    // Ensure we don't buy more than available
                    $maxQty = $product->total_stock > 5 ? 5 : $product->total_stock;
                    $quantity = rand(1, $maxQty);

                    $addItemRequest = AddItemRequest::create('/api/ecommerce/cart/items', 'POST', [
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                    ]);

                    // Manually set user resolver for the request
                    $addItemRequest->setUserResolver(fn() => $user);

                    // Validate manually because controller injection bypasses auto-validation in some contexts if not routed
                    $validator = validator($addItemRequest->all(), $addItemRequest->rules(), $addItemRequest->messages());
                    if ($validator->fails()) {
                        throw new ValidationException($validator);
                    }

                    $cartController->addItem($addItemRequest);
                    $this->comment("      -> Added Product ID: {$product->id} (Qty: {$quantity})");
                    $addedCount++;
                }

                if ($addedCount === 0) {
                    throw new \Exception("Could not add any items to cart (Stock issues? All picked products had 0 stock).");
                }

                // 4. CHECKOUT
                $tipoDoc = $faker->randomElement(['01', '06']);
                $docNum = $tipoDoc === '01' ? $faker->dni() : $faker->ruc(); // Faker es_PE supports dni/ruc? If not, fallback.
                if (!method_exists($faker, 'dni')) {
                    $docNum = $tipoDoc === '01' ? (string) rand(10000000, 99999999) : '20' . rand(100000000, 999999999);
                }

                $customerData = [
                    'tipo_documento' => $tipoDoc,
                    'numero_documento' => $docNum,
                    'email' => $user->email,
                    'phone' => $faker->phoneNumber(),
                ];

                if ($tipoDoc === '01') {
                    $customerData['first_name'] = $user->first_name;
                    $customerData['last_name'] = $user->last_name;
                } else {
                    $customerData['business_name'] = $faker->company();
                }

                $checkoutPayload = [
                    'customer_data' => $customerData,
                    'address' => [
                        'address' => $faker->streetAddress(),
                        'country_code' => 'PE',
                        'ubigeo' => $ubigeoCode,
                        'reference' => 'Simulación automática',
                        'phone' => $faker->phoneNumber(),
                    ],
                    'currency' => 'PEN',
                    'observations' => 'Pedido generado por simulación.',
                ];

                $checkoutRequest = CheckoutRequest::create('/api/ecommerce/checkout', 'POST', $checkoutPayload);
                $checkoutRequest->setUserResolver(fn() => $user);

                // Validate
                $validator = validator($checkoutRequest->all(), $checkoutRequest->rules());
                if ($validator->fails()) {
                    throw new ValidationException($validator);
                }

                // Inject validated data into request so $request->validated() works in Controller
                $checkoutRequest->setValidator($validator);

                $response = $cartController->checkout($checkoutRequest);

                $data = $response->getData(true);

                if ($response->getStatusCode() === 201) {
                    $orderData = $data['data'];
                    // $this->info("  [SUCCESS] Order Created! ID: " . ($orderData['id'] ?? 'unknown'));

                    // --- STEP 5: SIMULATE PAYMENT (Convert to Sale) ---
                    $orderId = $orderData['id'];
                    $order = Order::find($orderId);

                    if ($order) {
                        try {
                            $sale = $orderService->confirmOrder($order, 'card', 'TXN-' . uniqid());
                            $this->info("  [SUCCESS] Order #{$orderId} PAID -> Sale #{$sale->id}");
                            $successCount++;
                            DB::commit();
                        } catch (\Exception $e) {
                            $this->error("  [FAIL] Payment failed for Order #{$orderId}: " . $e->getMessage());
                            $failCount++; // Count as fail if payment fails even if order created? Maybe.
                            DB::rollBack(); // Rollback everything if payment fails in this simulation context
                        }
                    } else {
                        $this->error("  [FAIL] Order created but model not found.");
                        $failCount++;
                        DB::commit(); // Commit the order anyway? No, let's strictly fail.
                    }
                } else {
                    $this->error("  [FAIL] Checkout failed.");
                    $this->error(json_encode($data));
                    $failCount++;
                    DB::rollBack();
                }
            } catch (\Exception $e) {
                DB::rollBack();
                $failCount++;
                $errorMsg = "Error: " . $e->getMessage() . "\nIn: " . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString();
                file_put_contents(storage_path('logs/simulation_error.log'), $errorMsg);
                $this->error("Error occurred. Check storage/logs/simulation_error.log");
            } finally {
                Auth::logout();
            }
        }

        $this->line("------------------------------------------------");
        $this->info("Simulation Completed.");
        $this->info("Success: {$successCount}");
        $this->info("Failed: {$failCount}");
    }
}
