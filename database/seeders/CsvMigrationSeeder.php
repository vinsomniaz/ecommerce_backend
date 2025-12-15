<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Category;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Purchase;
use App\Models\User;
use App\Models\Entity;
use Illuminate\Support\Arr;

class CsvMigrationSeeder extends Seeder
{
    /**
     * Almacena los mapeos de IDs antiguos a IDs nuevos.
     * Formato: [id_antiguo => id_nuevo]
     */
    private $mapCategorias = [];
    private $mapFamilias = [];
    private $mapSubfamilias = [];
    private $mapProductos = [];
    private $mapAlmacenes = [];

    /**
     * Almacena el ID de la compra ficticia para asociar lotes
     */
    private $dummyPurchaseId;

    /**
     * Almacena los precios de costo por ID de producto nuevo
     * Formato: [nuevo_product_id => precio_compra]
     */
    private $mapProductCosts = [];

    /**
     * Almacena la categoría de cada producto
     * Formato: [nuevo_product_id => category_id]
     */
    private $mapProductCategories = [];

    /**
     * Almacena el precio de distribución (mayorista) por producto
     * Formato: [nuevo_product_id => precio_distribucion]
     */
    private $mapProductDistributionPrices = [];

    /**
     * Almacena los precios minoristas por producto y almacén
     * Formato: [nuevo_product_id][nuevo_warehouse_id] => precio_venta
     */
    private $mapRetailPrices = [];

    /**
     * Función helper para leer un CSV y devolverlo como un array asociativo
     */
    private function readCsv(string $filename): array
    {
        $path = database_path('data/' . $filename);
        if (!file_exists($path) || !is_readable($path)) {
            $this->command->error("Archivo no encontrado o no se puede leer: $filename");
            return [];
        }

        $header = null;
        $data = [];

        if (($handle = fopen($path, 'r')) !== false) {
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                if (!$header) {
                    $header = $row;
                } else {
                    if (count($header) == count($row)) {
                        $data[] = array_combine($header, $row);
                    }
                }
            }
            fclose($handle);
        }

        return $data;
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        Category::truncate();
        Product::truncate();
        Warehouse::truncate();
        Purchase::truncate();
        DB::table('purchase_details')->truncate();
        DB::table('purchase_batches')->truncate();
        DB::table('inventory')->truncate();
        DB::table('product_prices')->truncate();

        $this->importCategorias();
        $this->importFamilias();
        $this->importSubfamilias();
        $this->importAlmacenes();

        $this->createDummyPurchase();
        $this->importProductos('productos.csv');
        $this->importInventarioYPrecios(); // ← Renombrado para mayor claridad

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    private function createDummyPurchase(): void
    {
        $this->command->info('Creando Compra Ficticia...');

        $firstUser = User::first();
        if (!$firstUser) {
            $this->command->error('No se encontró un usuario. Ejecuta UserSeeder primero.');
            throw new \Exception('No se encontró usuario para la compra ficticia.');
        }

        $firstWarehouseId = Arr::first($this->mapAlmacenes);
        if (!$firstWarehouseId) {
            $this->command->error('No se encontró un almacén. El seeder de almacenes debe correr primero.');
            throw new \Exception('No se encontró almacén para la compra ficticia.');
        }

        $supplier = Entity::firstOrCreate(
            ['tipo_documento' => '06', 'numero_documento' => '99999999999'],
            [
                'type' => 'supplier',
                'tipo_persona' => 'juridica',
                'business_name' => 'PROVEEDOR GENÉRICO DE MIGRACIÓN',
                'user_id' => $firstUser->id,
            ]
        );

        // Crear dirección principal si no existe
        if (!$supplier->primaryAddress) {
            $supplier->addresses()->create([
                'address' => 'Dirección de Migración S/N',
                'ubigeo' => '150101',
                'country_code' => 'PE',
                'phone' => '999999999',
                'is_default' => true,
                'label' => 'Oficina Principal',
            ]);
        }

        // Crear contacto principal si no existe
        if (!$supplier->primaryContact) {
            $supplier->contacts()->create([
                'full_name' => 'Contacto de Migración',
                'position' => 'Administrador',
                'email' => 'migracion@proveedor.com',
                'phone' => '999999999',
                'is_primary' => true,
            ]);
        }

        $dummyPurchase = Purchase::create([
            'warehouse_id' => $firstWarehouseId,
            'user_id' => $firstUser->id,
            'supplier_id' => $supplier->id,
            'series' => 'MIG',
            'number' => '0001',
            'date' => now(),
            'tax' => 0,
            'subtotal' => 0.00,
            'total' => 0.00,
            'payment_status' => 'paid',
        ]);

        $this->dummyPurchaseId = $dummyPurchase->id;
        $this->command->info('Compra Ficticia creada con ID: ' . $this->dummyPurchaseId);
    }

    private function importCategorias()
    {
        $this->command->info('Importando Categorías...');
        $data = $this->readCsv('categorias.csv');

        foreach ($data as $row) {
            $normalMargin = isset($row['normal_margin_percentage']) && $row['normal_margin_percentage'] !== ''
                ? (float) $row['normal_margin_percentage']
                : 0.00;

            $minMargin = isset($row['min_margin_percentage']) && $row['min_margin_percentage'] !== ''
                ? (float) $row['min_margin_percentage']
                : 0.00;

            $nueva = Category::create([
                'name' => $row['nombre'],
                'slug' => $this->generateUniqueSlug($row['nombre']),
                'level' => 1,
                'is_active' => 1,
                'parent_id' => null,
                'normal_margin_percentage' => $normalMargin,
                'min_margin_percentage' => $minMargin,
            ]);

            $this->mapCategorias[$row['idcategoria']] = $nueva->id;
        }
    }

    private function importFamilias()
    {
        $this->command->info('Importando Familias...');
        $data = $this->readCsv('familias.csv');

        foreach ($data as $row) {
            $normalMargin = isset($row['normal_margin_percentage']) && $row['normal_margin_percentage'] !== ''
                ? (float) $row['normal_margin_percentage']
                : 0.00;

            $minMargin = isset($row['min_margin_percentage']) && $row['min_margin_percentage'] !== ''
                ? (float) $row['min_margin_percentage']
                : 0.00;

            $nueva = Category::create([
                'name' => $row['nombre'],
                'slug' => $this->generateUniqueSlug($row['nombre']),
                'level' => 2,
                'is_active' => 1,
                'parent_id' => $this->mapCategorias[$row['categoria_id']] ?? null,
                'normal_margin_percentage' => $normalMargin,
                'min_margin_percentage' => $minMargin,
            ]);

            $this->mapFamilias[$row['idfamilia']] = $nueva->id;
        }
    }

    private function importSubfamilias()
    {
        $this->command->info('Importando Subfamilias...');
        $data = $this->readCsv('subfamilias.csv');

        foreach ($data as $row) {
            $normalMargin = isset($row['normal_margin_percentage']) && $row['normal_margin_percentage'] !== ''
                ? (float) $row['normal_margin_percentage']
                : 0.00;

            $minMargin = isset($row['min_margin_percentage']) && $row['min_margin_percentage'] !== ''
                ? (float) $row['min_margin_percentage']
                : 0.00;

            $nueva = Category::create([
                'name' => $row['nombre'],
                'slug' => $this->generateUniqueSlug($row['nombre']),
                'level' => 3,
                'is_active' => 1,
                'parent_id' => $this->mapFamilias[$row['familia_id']] ?? null,
                'normal_margin_percentage' => $normalMargin,
                'min_margin_percentage' => $minMargin,
            ]);

            $this->mapSubfamilias[$row['idsubfamilia']] = $nueva->id;
        }
    }

    private function importAlmacenes()
    {
        $this->command->info('Importando Almacenes (desde tiendas)...');
        $data = $this->readCsv('tiendas.csv');

        $ubigeoPorDefecto = '150101';

        foreach ($data as $row) {
            $nueva = Warehouse::create([
                'name' => $row['nombre'],
                'address' => $row['direccion'] ?? 'Sin dirección',
                'phone' => $row['telefono'],
                'ubigeo' => $ubigeoPorDefecto,
                'is_main' => ($row['idtienda'] == 1) ? 1 : 0,
                'is_active' => 1,
            ]);

            $this->mapAlmacenes[$row['idtienda']] = $nueva->id;
        }
    }

    private function importProductos(string $filename)
    {
        $this->command->info('Importando Productos...');
        $data = $this->readCsv($filename);

        foreach ($data as $row) {
            $categoryId = $this->mapSubfamilias[$row['subfamilia_id']]
                ?? $this->mapFamilias[$row['familia_id']]
                ?? $this->mapCategorias[$row['categoria_id']]
                ?? null;

            if (is_null($categoryId)) {
                $categoryId = Arr::first($this->mapCategorias);
                if (!$categoryId) continue;
            }

            $precioDistribucion = $row['precio_distribucion'] ?? 0.00;
            if ($precioDistribucion === '' || strcasecmp($precioDistribucion, 'NULL') === 0) {
                $precioDistribucion = 0.00;
            }

            $nueva = Product::create([
                'primary_name' => $row['nombre'],
                'brand' => $row['marca'],
                'category_id' => $categoryId,
                'is_active' => 1,
                'visible_online' => 1,
                'sku' => $row['codigo'] ?? 'MIG-' . $row['idproducto'],
                'unit_measure' => 'NIU',
                'tax_type' => '10',
                'min_stock' => 0,
            ]);

            $this->mapProductos[$row['idproducto']] = $nueva->id;

            // Guardar precio de compra (para lotes), categoría y precio mayorista
            $this->mapProductCosts[$nueva->id] = $row['precio_compra'] ?? 0.00;
            $this->mapProductCategories[$nueva->id] = $categoryId;
            $this->mapProductDistributionPrices[$nueva->id] = (float) $precioDistribucion;
        }

        $this->command->info(count($this->mapProductos) . ' productos creados.');
    }

    private function importInventarioYPrecios()
    {
        $this->command->info('Importando Inventario, Lotes y Precios desde producto_tienda.csv...');

        // Leer datos de producto_tienda.csv (contiene: stock, precio minorista por almacén)
        $data = $this->readCsv('producto_tienda.csv');

        // Estructuras para almacenar datos
        $stockData = [];

        foreach ($data as $row) {
            if (isset($this->mapProductos[$row['producto_id']]) && isset($this->mapAlmacenes[$row['tienda_id']])) {
                $newProductId = $this->mapProductos[$row['producto_id']];
                $newWarehouseId = $this->mapAlmacenes[$row['tienda_id']];

                $stock = (int) ($row['stock'] ?? 0);
                $precioVenta = (float) ($row['precio'] ?? 0.00); // ← Precio MINORISTA del CSV

                // Almacenar stock
                $stockData[$newProductId][$newWarehouseId] = $stock;

                // Almacenar precio minorista (por almacén)
                if ($precioVenta > 0) {
                    $this->mapRetailPrices[$newProductId][$newWarehouseId] = $precioVenta;
                }
            }
        }

        // PASO 1: Crear LOTES (solo con stock > 0)
        $this->command->info('Creando lotes de compra...');
        $lotesParaInsertar = [];
        $now = now();

        foreach ($this->mapProductos as $oldProductId => $newProductId) {
            foreach ($this->mapAlmacenes as $oldWarehouseId => $newWarehouseId) {
                $stock = $stockData[$newProductId][$newWarehouseId] ?? 0;

                if ($stock > 0) {
                    $purchasePrice = (float) ($this->mapProductCosts[$newProductId] ?? 0.00);

                    $lotesParaInsertar[] = [
                        'purchase_id' => $this->dummyPurchaseId,
                        'product_id' => $newProductId,
                        'warehouse_id' => $newWarehouseId,
                        'batch_code' => 'MIG-' . $newProductId . '-' . $newWarehouseId,
                        'quantity_purchased' => $stock,
                        'quantity_available' => $stock,
                        'purchase_price' => $purchasePrice,
                        'purchase_date' => $now,
                        'status' => 'active',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        if (!empty($lotesParaInsertar)) {
            $chunks = array_chunk($lotesParaInsertar, 500);
            foreach ($chunks as $chunk) {
                DB::table('purchase_batches')->insert($chunk);
            }
            $this->command->info(count($lotesParaInsertar) . ' lotes de compra creados.');
        }

        // PASO 2: Calcular COSTO PROMEDIO PONDERADO por producto
        $this->command->info('Calculando costos promedio ponderados...');
        $productAverageCosts = $this->calculateAverageCosts();

        // PASO 3: Obtener IDs de listas de precios
        $retailListId = DB::table('price_lists')->where('code', 'RETAIL')->value('id');
        $wholesaleListId = DB::table('price_lists')->where('code', 'WHOLESALE')->value('id');

        if (!$retailListId || !$wholesaleListId) {
            $this->command->error('No se encontraron listas de precios. Ejecuta PriceListSeeder primero.');
            throw new \Exception('Listas de precios no encontradas.');
        }

        // PASO 4: Crear PRECIOS y REGISTROS DE INVENTARIO
        $this->command->info('Creando precios e inventario...');
        $pricesToInsert = [];
        $inventoryToInsert = [];

        // Contadores para estadísticas
        $generalPriceCount = 0;
        $specificPriceCount = 0;

        foreach ($this->mapProductos as $oldProductId => $newProductId) {
            $averageCost = $productAverageCosts[$newProductId] ?? 0.00;

            // ============================================
            // PRECIOS MINORISTAS (desde producto_tienda.csv)
            // ============================================
            if (isset($this->mapRetailPrices[$newProductId])) {
                $pricesForProduct = $this->mapRetailPrices[$newProductId];

                // Detectar si todos los precios son iguales
                $uniquePrices = array_unique(array_values($pricesForProduct));

                if (count($uniquePrices) === 1) {
                    // TODOS los almacenes tienen el MISMO precio → Crear UN precio general
                    $generalPrice = reset($uniquePrices);

                    $pricesToInsert[] = [
                        'product_id' => $newProductId,
                        'price_list_id' => $retailListId,
                        'warehouse_id' => null, // ← General para todos los almacenes
                        'price' => $generalPrice,
                        'min_price' => null,
                        'currency' => 'PEN',
                        'min_quantity' => 1,
                        'valid_from' => $now,
                        'valid_to' => null,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $generalPriceCount++;
                } else {
                    // Hay PRECIOS DIFERENTES → Crear precio específico por almacén
                    foreach ($pricesForProduct as $warehouseId => $retailPrice) {
                        $pricesToInsert[] = [
                            'product_id' => $newProductId,
                            'price_list_id' => $retailListId,
                            'warehouse_id' => $warehouseId, // ← Específico por almacén
                            'price' => $retailPrice,
                            'min_price' => null,
                            'currency' => 'PEN',
                            'min_quantity' => 1,
                            'valid_from' => $now,
                            'valid_to' => null,
                            'is_active' => true,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];

                        $specificPriceCount++;
                    }
                }
            }

            // ============================================
            // PRECIO MAYORISTA (desde productos.csv)
            // ============================================
            $distributionPrice = $this->mapProductDistributionPrices[$newProductId] ?? null;

            if ($distributionPrice && $distributionPrice > 0) {
                // UN SOLO precio mayorista (sin escalones)
                $pricesToInsert[] = [
                    'product_id' => $newProductId,
                    'price_list_id' => $wholesaleListId,
                    'warehouse_id' => null, // ← Aplica a todos los almacenes
                    'price' => $distributionPrice,
                    'min_price' => null,
                    'currency' => 'PEN',
                    'min_quantity' => 1, // Puedes ajustar esto según tus reglas de negocio
                    'valid_from' => $now,
                    'valid_to' => null,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // ============================================
            // INVENTARIO (stock y costo promedio por almacén)
            // ============================================
            foreach ($this->mapAlmacenes as $oldWarehouseId => $newWarehouseId) {
                $stock = $stockData[$newProductId][$newWarehouseId] ?? 0;

                $inventoryToInsert[] = [
                    'product_id' => $newProductId,
                    'warehouse_id' => $newWarehouseId,
                    'available_stock' => $stock,
                    'reserved_stock' => 0,
                    'average_cost' => $averageCost,
                    'last_movement_at' => $stock > 0 ? $now : null,
                ];
            }
        }

        // Insertar PRECIOS en lotes
        if (!empty($pricesToInsert)) {
            $chunks = array_chunk($pricesToInsert, 500);
            foreach ($chunks as $chunk) {
                DB::table('product_prices')->insert($chunk);
            }
            $this->command->info(count($pricesToInsert) . ' precios creados en total.');
            $this->command->info("  → {$generalPriceCount} productos con precio general (mismo en todos los almacenes)");
            $this->command->info("  → {$specificPriceCount} precios específicos por almacén (precios diferentes)");
        }

        // Insertar INVENTARIOS en lotes
        if (!empty($inventoryToInsert)) {
            $chunks = array_chunk($inventoryToInsert, 500);
            foreach ($chunks as $chunk) {
                DB::table('inventory')->insert($chunk);
            }
            $this->command->info(count($inventoryToInsert) . ' registros de inventario creados.');
        }

        $this->command->info('✅ Importación completada: Lotes, Precios (minorista + mayorista) e Inventario.');
    }

    /**
     * Calcula el costo promedio ponderado por producto
     * basado en todos sus lotes
     */
    private function calculateAverageCosts(): array
    {
        $averageCosts = [];

        $batches = DB::table('purchase_batches')
            ->select('product_id', 'purchase_price', 'quantity_purchased')
            ->get()
            ->groupBy('product_id');

        foreach ($batches as $productId => $productBatches) {
            $totalCost = 0;
            $totalQuantity = 0;

            foreach ($productBatches as $batch) {
                $totalCost += $batch->purchase_price * $batch->quantity_purchased;
                $totalQuantity += $batch->quantity_purchased;
            }

            if ($totalQuantity > 0) {
                $averageCosts[$productId] = round($totalCost / $totalQuantity, 4);
            } else {
                $averageCosts[$productId] = 0.00;
            }
        }

        return $averageCosts;
    }

    private function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (Category::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
