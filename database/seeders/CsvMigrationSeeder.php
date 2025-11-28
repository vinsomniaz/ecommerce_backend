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
     * Almacena los precios de costo/distribución por ID de producto nuevo
     * Formato: [nuevo_product_id => ['purchase' => X, 'distribution' => Y]]
     */
    private $mapProductCosts = [];

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
            $nueva = Category::create([
                'name' => $row['nombre'],
                'slug' => $this->generateUniqueSlug($row['nombre']),
                'level' => 1,
                'is_active' => 1,
                'parent_id' => null
            ]);

            $this->mapCategorias[$row['idcategoria']] = $nueva->id;
        }
    }

    private function importFamilias()
    {
        $this->command->info('Importando Familias...');
        $data = $this->readCsv('familias.csv');

        foreach ($data as $row) {
            $nueva = Category::create([
                'name' => $row['nombre'],
                'slug' => $this->generateUniqueSlug($row['nombre']),
                'level' => 2,
                'is_active' => 1,
                'parent_id' => $this->mapCategorias[$row['categoria_id']] ?? null
            ]);

            $this->mapFamilias[$row['idfamilia']] = $nueva->id;
        }
    }

    private function importSubfamilias()
    {
        $this->command->info('Importando Subfamilias...');
        $data = $this->readCsv('subfamilias.csv');

        foreach ($data as $row) {
            $nueva = Category::create([
                'name' => $row['nombre'],
                'slug' => $this->generateUniqueSlug($row['nombre']),
                'level' => 3,
                'is_active' => 1,
                'parent_id' => $this->mapFamilias[$row['familia_id']] ?? null
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

            // ⭐ CAMBIO PRINCIPAL: Agregamos distribution_price al crear el producto
            $precioDistribucion = $row['precio_distribucion'] ?? 0.00;
            
            // Validar que no sea NULL o string vacío
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
                'distribution_price' => (float) $precioDistribucion, // ⭐ AQUÍ SE SETEA
            ]);

            $this->mapProductos[$row['idproducto']] = $nueva->id;

            // Guardamos solo el precio de COMPRA para los lotes
            $this->mapProductCosts[$nueva->id] = [
                'purchase' => $row['precio_compra'] ?? 0.00,
            ];
        }

        $this->command->info(count($this->mapProductos) . ' productos creados con precio de distribución.');
    }

    private function importInventario()
    {
        $this->command->info('Importando Inventario (stock) y Lotes de Compra (batches)...');
        $data = $this->readCsv('producto_tienda.csv');

        $inventarioParaInsertar = [];
        $lotesParaInsertar = [];
        $now = now();

        foreach ($data as $row) {
            if (isset($this->mapProductos[$row['producto_id']]) && isset($this->mapAlmacenes[$row['tienda_id']])) {

                $newProductId = $this->mapProductos[$row['producto_id']];
                $newWarehouseId = $this->mapAlmacenes[$row['tienda_id']];
                $stock = (int) $row['stock'];

                // Precio de VENTA desde producto_tienda.csv
                $precioCsv = $row['precio'] ?? null;
                $salePrice = null;
                if ($precioCsv !== null && $precioCsv !== '' && strcasecmp($precioCsv, 'NULL') !== 0) {
                    $salePrice = (float) $precioCsv;
                }

                // 1. INVENTORY (con precio de venta)
                $inventarioParaInsertar[] = [
                    'product_id' => $newProductId,
                    'warehouse_id' => $newWarehouseId,
                    'available_stock' => $stock,
                    'sale_price' => $salePrice,
                    'reserved_stock' => 0,
                    'last_movement_at' => $now,
                    'price_updated_at' => $salePrice ? $now : null,
                ];

                // 2. PURCHASE_BATCHES (con precio de compra)
                $costs = $this->mapProductCosts[$newProductId] ?? ['purchase' => 0.00];

                $lotesParaInsertar[] = [
                    'purchase_id' => $this->dummyPurchaseId,
                    'product_id' => $newProductId,
                    'warehouse_id' => $newWarehouseId,
                    'batch_code' => 'MIG-' . $newProductId . '-' . $newWarehouseId,
                    'quantity_purchased' => $stock,
                    'quantity_available' => $stock,
                    'purchase_price' => (float) $costs['purchase'], // Precio de COMPRA
                    'purchase_date' => $now,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (!empty($inventarioParaInsertar)) {
            DB::table('inventory')->insert($inventarioParaInsertar);
            $this->command->info(count($inventarioParaInsertar) . ' registros de inventario creados.');
        }

        if (!empty($lotesParaInsertar)) {
            DB::table('purchase_batches')->insert($lotesParaInsertar);
            $this->command->info(count($lotesParaInsertar) . ' lotes de compra (batches) creados.');
        }
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