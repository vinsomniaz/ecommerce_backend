<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ProductImageSeeder extends Seeder
{
    /**
     * Helper para leer CSV (adaptado de CsvMigrationSeeder)
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
            // Usamos coma como delimitador para productos.csv
            while (($row = fgetcsv($handle, 2000, ',')) !== false) {
                if (!$header) {
                    $header = array_map(fn($h) => trim($h, "\"\r\n"), $row); // Limpia cabeceras
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
     *
     */
    public function run(): void
    {
        $this->command->info('Iniciando Seeder de Imágenes de Productos...');

        // 1. Definir la ruta fuente donde el usuario debe colocar las imágenes
        $sourceImagePath = database_path('data/images');

        if (!File::exists($sourceImagePath)) {
            $this->command->error("Directorio fuente de imágenes no encontrado.");
            $this->command->error("Por favor, cree la carpeta: $sourceImagePath");
            $this->command->error("Luego, coloque todas las imágenes de productos.csv allí y re-ejecute el seeder.");
            return; // Detener el seeder
        }

        // 2. Limpiar la colección de medios existente
        $this->command->info('Limpiando base de datos de medios "images" existente...');
        DB::table('media')->where('model_type', 'App\Models\Product')->where('collection_name', 'images')->delete();

        // Limpiamos el directorio de destino (el *root* del disco 'public')
        $destinationPath = storage_path('app/public');

        if (File::exists($destinationPath)) {
            // Obtenemos todos los directorios dentro de storage/app/public
            $directories = File::directories($destinationPath);

            foreach ($directories as $directory) {
                // Borramos solo los directorios que son numéricos (los IDs de media)
                // y el directorio 'images' que mi seeder anterior pudo haber creado
                $dirName = basename($directory);
                if (is_numeric($dirName) || $dirName === 'images') {
                    File::deleteDirectory($directory);
                }
            }
            $this->command->info('Directorio de medios de destino limpiado (carpetas de IDs antiguos).');
        } else {
             File::makeDirectory($destinationPath, 0755, true, true);
             $this->command->info('Directorio de destino storage/app/public creado.');
        }


        // 3. Leer el CSV de productos
        $productosCsv = $this->readCsv('productos.csv');

        $count = 0;
        $notFound = 0;

        foreach ($productosCsv as $row) {
            // Validar fila
            if (empty($row['idproducto']) || empty($row['image_url']) || $row['image_url'] === 'NULL') {
                continue;
            }

            // 4. Encontrar el producto por el SKU generado en CsvMigrationSeeder
            $sku = $row['codigo'] ?? 'MIG-' . $row['idproducto'];

            $product = Product::where('sku', $sku)->first();

            if (!$product) {
                $this->command->warn("Producto no encontrado con SKU: $sku (ID Antiguo: {$row['idproducto']})");
                continue;
            }

            // 5. Preparar la ruta de la imagen
            // La CSV tiene "images/IMAGEN.jpg". Extraemos solo "IMAGEN.jpg".
            $imageName = basename($row['image_url']);
            $sourceFile = $sourceImagePath . '/' . $imageName;

            // 6. Verificar si la imagen existe en la carpeta fuente y asociarla
            if (File::exists($sourceFile)) {
                try {
                    // Añadir el archivo a la colección de medios
                    $product->addMedia($sourceFile)
                        ->preservingOriginal()
                        ->usingName($product->primary_name)
                        ->usingFileName($imageName)

                        // --- ¡AQUÍ ESTÁ LA MODIFICACIÓN! ---
                        ->withCustomProperties([
                            'is_primary' => true,
                            'order' => 1
                        ])
                        // -------------------------------------

                        ->toMediaCollection('images', 'public');

                    $count++;
                } catch (\Exception $e) {
                    $this->command->error("Error añadiendo media al producto $sku: " . $e->getMessage());
                }
            } else {
                $notFound++;
            }
        }

        $this->command->info("Seeder de Imágenes completado.");
        $this->command->info("Imágenes añadidas exitosamente: $count");
        $this->command->info("Imágenes no encontradas (omitidas): $notFound");
        $this->command->info("No olvide ejecutar: php artisan storage:link");
    }
}
