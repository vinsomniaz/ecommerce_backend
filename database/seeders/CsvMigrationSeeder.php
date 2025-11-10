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
            // Asumimos punto y coma (;) como delimitador común de exportación, 
            // si usas comas (,) cambia el primer parámetro de fgetcsv a ','
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                if (!$header) {
                    $header = $row;
                } else {
                    // Evita errores si la fila tiene un número diferente de columnas
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
        // Desactivamos llaves foráneas para la BD principal
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Truncamos las tablas para empezar de cero
        Category::truncate();
        Product::truncate();
        Warehouse::truncate();
        Purchase::truncate(); // <-- Limpiar compras
        DB::table('purchase_details')->truncate(); // <-- Limpiar detalles
        DB::table('purchase_batches')->truncate(); // <-- LIMPIAR NUEVA TABLA
        DB::table('inventory')->truncate();

        // Ejecutamos la importación en orden de dependencia
        $this->importCategorias();
        $this->importFamilias();
        $this->importSubfamilias();
        $this->importAlmacenes();

        // --- LÓGICA DE PRECIOS Y STOCK ACTUALIZADA ---

        // 1. Creamos la compra ficticia y guardamos su ID en la clase
        $this->createDummyPurchase();

        // 2. Importamos productos y guardamos sus costos en $this->mapProductCosts
        //    Ya no inserta en purchase_details.
        $this->importProductos('productos.csv');

        // 3. Importamos el stock (inventory) Y TAMBIÉN los lotes (purchase_batches)
        //    usando los costos del mapa y el ID de la compra ficticia.
        $this->importInventario();

        // Reactivamos las llaves
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }


    private function createDummyPurchase(): void
    {
        $this->command->info('Creando Compra Ficticia...');

        // Asumimos que DatabaseSeeder -> UserSeeder ya corrió
        $firstUser = User::first();
        if (!$firstUser) {
            $this->command->error('No se encontró un usuario. Ejecuta UserSeeder primero.');
            throw new \Exception('No se encontró usuario para la compra ficticia.');
        }

        // Asumimos que importAlmacenes ya corrió
        $firstWarehouseId = Arr::first($this->mapAlmacenes);
        if (!$firstWarehouseId) {
            $this->command->error('No se encontró un almacén. El seeder de almacenes debe correr primero.');
            throw new \Exception('No se encontró almacén para la compra ficticia.');
        }

        // Asumimos que existe un Supplier con ID 1 (o créalo si es necesario)
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
            'supplier_id' => $supplier->id, // Usar un supplier válido
            'series' => 'MIG',
            'number' => '0001',
            'date' => now(),
            'tax' => 0,
            'subtotal' => 0.00,
            'total' => 0.00,
            'payment_status' => 'paid',
        ]);

        // Guardamos el ID para usarlo en importInventario
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
                'slug' => Str::slug($row['nombre']),
                'level' => 1,
                'is_active' => 1,
                'parent_id' => null
            ]);

            // Guardamos el mapeo de IDs
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
                'slug' => Str::slug($row['nombre']),
                'level' => 2,
                'is_active' => 1,
                // Usamos el mapeo para asignar el 'parent_id' correcto
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
                'slug' => Str::slug($row['nombre']),
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

        // Tu tabla 'warehouses' requiere 'ubigeo'.
        // Usaré '150101' (Lima) como default, ya que lo vi en tu UbigeoSeeder.
        // ¡Asegúrate de que este ubigeo exista en tu tabla `ubigeos`!
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
        $this->command->info('Importando Productos y mapeando costos...');
        $data = $this->readCsv($filename);

        // Ya no preparamos purchaseDetails
        // $purchaseDetails = [];

        foreach ($data as $row) {
            $categoryId = $this->mapSubfamilias[$row['subfamilia_id']]
                ?? $this->mapFamilias[$row['familia_id']]
                ?? $this->mapCategorias[$row['categoria_id']]
                ?? null;

            if (is_null($categoryId)) {
                $categoryId = Arr::first($this->mapCategorias);
                if (!$categoryId) continue;
            }

            // 1. Crear el Producto 
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

            // Guardamos el mapeo del producto
            $this->mapProductos[$row['idproducto']] = $nueva->id;

            // 2. Guardar los costos en el mapa temporal
            $this->mapProductCosts[$nueva->id] = [
                'purchase' => $row['precio_compra'] ?? 0.00,
                'distribution' => $row['precio_distribucion'] ?? 0.00,
            ];
        }

        // 3. Ya no insertamos en purchase_details
        $this->command->info(count($this->mapProductos) . ' productos creados y ' . count($this->mapProductCosts) . ' costos mapeados.');
    }

    private function importInventario()
    {
        $this->command->info('Importando Inventario (stock) y Lotes de Compra (batches)...');
        $data = $this->readCsv('producto_tienda.csv');

        $inventarioParaInsertar = [];
        $lotesParaInsertar = []; // <-- NUEVO: Array para lotes
        $now = now();

        foreach ($data as $row) {
            // Verificamos que el producto y el almacén existan en nuestros mapas
            if (isset($this->mapProductos[$row['producto_id']]) && isset($this->mapAlmacenes[$row['tienda_id']])) {

                // --- Datos base ---
                $newProductId = $this->mapProductos[$row['producto_id']];
                $newWarehouseId = $this->mapAlmacenes[$row['tienda_id']];
                $stock = (int) $row['stock'];

                // --- Lógica de Precio de Venta (para inventory) ---
                $precioCsv = $row['precio'] ?? null;
                $salePrice = null;
                if ($precioCsv !== null && $precioCsv !== '' && strcasecmp($precioCsv, 'NULL') !== 0) {
                    $salePrice = (float) $precioCsv;
                }

                // 1. Preparar INVENTORY
                $inventarioParaInsertar[] = [
                    'product_id' => $newProductId,
                    'warehouse_id' => $newWarehouseId,
                    'available_stock' => $stock,
                    'sale_price' => $salePrice, // Precio de VENTA
                    'reserved_stock' => 0,
                    'last_movement_at' => $now,
                    'price_updated_at' => $salePrice ? $now : null,
                ];

                // 2. Preparar PURCHASE_BATCHES (NUEVO)

                // Buscamos los costos que guardamos en el mapa
                $costs = $this->mapProductCosts[$newProductId]
                    ?? ['purchase' => 0.00, 'distribution' => 0.00];

                $lotesParaInsertar[] = [
                    'purchase_id' => $this->dummyPurchaseId, // ID de la compra ficticia
                    'product_id' => $newProductId,
                    'warehouse_id' => $newWarehouseId,
                    'batch_code' => 'MIG-' . $newProductId . '-' . $newWarehouseId, // Generamos un código único
                    'quantity_purchased' => $stock,
                    'quantity_available' => $stock,
                    'purchase_price' => $costs['purchase'],     // <-- Precio de Compra
                    'distribution_price' => $costs['distribution'], // <-- Precio de Distribución
                    'purchase_date' => $now,
                    'expiry_date' => null,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // Insertar en INVENTORY
        if (!empty($inventarioParaInsertar)) {
            DB::table('inventory')->insert($inventarioParaInsertar);
            $this->command->info(count($inventarioParaInsertar) . ' registros de inventario creados.');
        }

        // Insertar en PURCHASE_BATCHES (NUEVO)
        if (!empty($lotesParaInsertar)) {
            // Usamos el nombre de la tabla 'purchase_batches' de tu migración
            DB::table('purchase_batches')->insert($lotesParaInsertar);
            $this->command->info(count($lotesParaInsertar) . ' lotes de compra (batches) creados.');
        }
    }
}
