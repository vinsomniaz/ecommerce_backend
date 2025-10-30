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

        // Truncamos las tablas para empezar de cero (opcional pero recomendado)
        Category::truncate();
        Product::truncate();
        Warehouse::truncate();
        DB::table('inventory')->truncate();

        // Ejecutamos la importación en orden de dependencia
        $this->importCategorias();
        $this->importFamilias();
        $this->importSubfamilias();
        $this->importAlmacenes();
        //$this->importProductos();


        // --- NUEVA LÓGICA ---
        // 1. Creamos la compra ficticia ANTES de importar productos
        $dummyPurchaseId = $this->createDummyPurchase();

        // 2. Importamos los productos y les asignamos los precios en la compra ficticia
        $this->importProductos('productos.csv', $dummyPurchaseId);

        // 3. Importamos el stock
        $this->importInventario();

        // Reactivamos las llaves
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }


    private function createDummyPurchase(): int
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

        $dummyPurchase = Purchase::create([
            'warehouse_id' => $firstWarehouseId,
            'user_id' => $firstUser->id,
            'supplier_id' => 1,
            'series' => 0,
            'number' => 00,
            'date' => now(),
            'tax' => 0,
            'subtotal' => 0.00,
            'total' => 0.00,
            'payment_status' => 'paid', // O el estado que corresponda
        ]);

        $this->command->info('Compra Ficticia creada con ID: ' . $dummyPurchase->id);
        return $dummyPurchase->id;
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

    private function importProductos(string $filename, int $dummyPurchaseId)
    {
        $this->command->info('Importando Productos...');
        $data = $this->readCsv($filename);

        $purchaseDetails = [];
        $now = now();

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

            // 2. Preparar el Detalle de Compra (para inserción masiva)
            $purchaseDetails[] = [
                'purchase_id' => $dummyPurchaseId,
                'product_id' => $nueva->id,
                'quantity' => 0, // 0 porque solo queremos registrar precios
                'purchase_price' => $row['precio_compra'], // Precio de compra
                'distribution_price' => $row['precio_distribucion'], // Asignamos precio distribución
                'subtotal' => 0,
                'tax_amount' => 0,
                'total' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // 3. Insertar todos los detalles de compra en un solo query
        if (!empty($purchaseDetails)) {
            DB::table('purchase_details')->insert($purchaseDetails);
            $this->command->info(count($purchaseDetails) . ' precios de productos registrados en purchase_details.');
        }
    }

    private function importInventario()
    {
        $this->command->info('Importando Inventario (stock)...');
        $data = $this->readCsv('producto_tienda.csv'); // (original: producto_tienda)

        $inventarioParaInsertar = [];
        $now = now();

        foreach ($data as $row) {
            // Verificamos que el producto y el almacén existan en nuestros mapas
            if (isset($this->mapProductos[$row['producto_id']]) && isset($this->mapAlmacenes[$row['tienda_id']])) {

                $precioCsv = $row['precio'] ?? null;
                $salePrice = null; // Por defecto, será NULL si no es un número válido

                // Verificamos que $precioCsv no sea null, ni un string vacío, 
                // ni la palabra "NULL" (insensible a mayúsculas)
                if ($precioCsv !== null && $precioCsv !== '' && strcasecmp($precioCsv, 'NULL') !== 0) {
                    // Si es un valor numérico válido, lo convertimos a float
                    $salePrice = (float) $precioCsv;
                }

                $inventarioParaInsertar[] = [
                    'product_id' => $this->mapProductos[$row['producto_id']],
                    'warehouse_id' => $this->mapAlmacenes[$row['tienda_id']],
                    'available_stock' => $row['stock'],
                    'sale_price' => $salePrice,
                    'reserved_stock' => 0,
                    'last_movement_at' => $now,
                ];
            }
        }

        // Usamos insert() para alta eficiencia en lugar de create() en un bucle
        if (!empty($inventarioParaInsertar)) {
            // Usamos el nombre de la tabla 'inventory' de tu migración
            DB::table('inventory')->insert($inventarioParaInsertar);
        }
    }
}
