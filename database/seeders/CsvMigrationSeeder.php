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

        $this->importCategorias();
        $this->importFamilias();
        $this->importSubfamilias();
        $this->importAlmacenes();

        $this->createDummyPurchase();
        $this->importProductos('productos.csv');
        $this->importInventario();

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
                'address' => 'S/N',
                'user_id' => $firstUser->id,
            ]
        );

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
            // Leer márgenes del CSV si existen
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
        $this->command->info('Importando Productos con precio de distribución...');
        $data = $this->readCsv($filename);

        foreach ($data as $row) {
            $categoryId = $this->mapSubfamilias[$row['subfamilia_id']]
                ?? $this->mapFamilias[$row['familia_id']]
                ?? $this->mapCategorias[$row['categoria_id']]
                ?? null;

            if (is_null($categoryId)) {
                $categoryId = Arr::first($this->mapCategorias);
                if (!$categoryId)
                    continue;
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
                'distribution_price' => (float) $precioDistribucion,
            ]);

            $this->mapProductos[$row['idproducto']] = $nueva->id;

            // Guardamos el precio de compra y la categoría
            $this->mapProductCosts[$nueva->id] = $row['precio_compra'] ?? 0.00;
            $this->mapProductCategories[$nueva->id] = $categoryId;
        }

        $this->command->info(count($this->mapProductos) . ' productos creados con precio de distribución.');
    }

    private function importInventario()
    {
        $this->command->info('Importando Inventario (stock) y Lotes de Compra (batches)...');

        // Leer datos existentes del CSV
        $data = $this->readCsv('producto_tienda.csv');

        // Crear estructura para almacenar datos de stock por producto-almacén
        $stockData = [];

        foreach ($data as $row) {
            if (isset($this->mapProductos[$row['producto_id']]) && isset($this->mapAlmacenes[$row['tienda_id']])) {
                $newProductId = $this->mapProductos[$row['producto_id']];
                $newWarehouseId = $this->mapAlmacenes[$row['tienda_id']];

                $stock = (int) $row['stock'];

                // Almacenar en matriz
                $stockData[$newProductId][$newWarehouseId] = $stock;
            }
        }

        // 🔥 PASO 1: Crear LOTES (solo con stock > 0)
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

        // 🔥 PASO 2: Calcular COSTO PROMEDIO PONDERADO por producto
        $this->command->info('Calculando costos promedio ponderados...');
        $productAverageCosts = $this->calculateAverageCosts();

        // 🔥 PASO 3: Obtener MÁRGENES de categorías
        $categoryMargins = $this->getCategoryMargins();

        // 🔥 PASO 4: Crear INVENTARIOS con precios calculados
        $this->command->info('Creando inventarios con precios calculados...');
        $inventarioParaInsertar = [];

        foreach ($this->mapProductos as $oldProductId => $newProductId) {
            $averageCost = $productAverageCosts[$newProductId] ?? 0.00;
            $categoryId = $this->mapProductCategories[$newProductId] ?? null;

            // Obtener márgenes de la categoría
            $marginRetail = $categoryMargins[$categoryId]['normal_margin_percentage'] ?? 0.00;
            $marginRetailMin = $categoryMargins[$categoryId]['min_margin_percentage'] ?? 0.00;

            // Calcular precios de venta
            $salePrice = null;
            $minSalePrice = null;
            $profitMargin = null;

            if ($averageCost > 0) {
                // sale_price = average_cost * (1 + margin_retail / 100) - redondeo comercial
                $salePrice = round($averageCost * (1 + $marginRetail / 100));

                // min_sale_price = average_cost * (1 + margin_retail_min / 100) - redondeo comercial
                $minSalePrice = round($averageCost * (1 + $marginRetailMin / 100));

                // profit_margin = margin_retail (para registro)
                $profitMargin = $marginRetail;
            }

            foreach ($this->mapAlmacenes as $oldWarehouseId => $newWarehouseId) {
                $stock = $stockData[$newProductId][$newWarehouseId] ?? 0;

                $inventarioParaInsertar[] = [
                    'product_id' => $newProductId,
                    'warehouse_id' => $newWarehouseId,
                    'available_stock' => $stock,
                    'reserved_stock' => 0,
                    'average_cost' => $averageCost,
                    'sale_price' => $salePrice,
                    'profit_margin' => $profitMargin,
                    'min_sale_price' => $minSalePrice,
                    'last_movement_at' => $now,
                    'price_updated_at' => $salePrice ? $now : null,
                ];
            }
        }

        if (!empty($inventarioParaInsertar)) {
            $chunks = array_chunk($inventarioParaInsertar, 500);
            foreach ($chunks as $chunk) {
                DB::table('inventory')->insert($chunk);
            }
            $this->command->info(count($inventarioParaInsertar) . ' registros de inventario creados con precios calculados.');
        }
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

    /**
     * Obtiene los márgenes de todas las categorías
     */
    private function getCategoryMargins(): array
    {
        $margins = [];

        $categories = DB::table('categories')
            ->select('id', 'normal_margin_percentage', 'min_margin_percentage')
            ->get();

        foreach ($categories as $category) {
            $margins[$category->id] = [
                'normal_margin_percentage' => (float) $category->normal_margin_percentage,
                'min_margin_percentage' => (float) $category->min_margin_percentage,
            ];
        }

        return $margins;
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
