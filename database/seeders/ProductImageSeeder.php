<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use Illuminate\Support\Facades\File;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ProductImageSeeder extends Seeder
{
    /**
     * Helper para leer CSV
     */
    private function readCsv(string $filename): array
    {
        $path = database_path('data/' . $filename);
        if (!file_exists($path) || !is_readable($path)) {
            $this->command->error("Archivo no encontrado: $filename");
            return [];
        }

        $header = null;
        $data = [];

        if (($handle = fopen($path, 'r')) !== false) {
            while (($row = fgetcsv($handle, 2000, ',')) !== false) {
                if (!$header) {
                    $header = array_map(fn($h) => trim($h, "\"\r\n"), $row);
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
        $this->command->info('🖼️  Iniciando Seeder de Imágenes de Productos...');

        // 1. Verificar directorio fuente
        $sourceImagePath = database_path('data/images');

        if (!File::exists($sourceImagePath)) {
            $this->command->error("❌ Directorio fuente no encontrado: $sourceImagePath");
            $this->command->error("   Cree la carpeta y coloque las imágenes allí.");
            return;
        }

        // 2. Limpiar media anteriores de forma masiva
        $this->command->info('🧹 Limpiando registros de media anteriores...');

        $oldMediaCount = Media::where('model_type', Product::class)
            ->where('collection_name', 'images')
            ->count();

        // Eliminar en bloques para evitar problemas de memoria
        Media::where('model_type', Product::class)
            ->where('collection_name', 'images')
            ->chunkById(100, function ($medias) {
                foreach ($medias as $media) {
                    try {
                        $media->delete();
                    } catch (\Exception $e) {
                        // Silenciar errores para no detener el proceso
                    }
                }
            });

        $this->command->info("   ✓ Eliminados $oldMediaCount registros de media antiguos");

        // 3. Verificar enlace simbólico
        if (!File::exists(public_path('storage'))) {
            $this->command->error('❌ El enlace simbólico storage no existe.');
            $this->command->error('   Ejecute: php artisan storage:link');
            return;
        }

        // 4. Leer CSV
        $productosCsv = $this->readCsv('productos.csv');

        if (empty($productosCsv)) {
            $this->command->error('❌ No se pudieron leer productos del CSV');
            return;
        }

        // 5. ⚡ OPTIMIZACIÓN: Cargar todos los productos en memoria una sola vez
        $this->command->info("📦 Cargando productos en memoria...");
        $products = Product::select('id', 'sku', 'primary_name')
            ->get()
            ->keyBy('sku');

        // 6. ⚡ OPTIMIZACIÓN: Pre-validar archivos existentes
        $this->command->info("🔍 Validando archivos de imágenes...");
        $validRows = [];
        foreach ($productosCsv as $row) {
            if (empty($row['idproducto']) || empty($row['image_url']) || $row['image_url'] === 'NULL') {
                continue;
            }

            $sku = $row['codigo'] ?? 'MIG-' . $row['idproducto'];
            $imageName = basename($row['image_url']);
            $sourceFile = $sourceImagePath . '/' . $imageName;

            // Solo procesar si el producto existe Y el archivo existe
            if ($products->has($sku) && File::exists($sourceFile)) {
                $validRows[] = [
                    'product' => $products[$sku],
                    'sourceFile' => $sourceFile,
                    'imageName' => $imageName
                ];
            }
        }

        $totalImages = count($validRows);
        $this->command->info("   ✓ $totalImages imágenes válidas para procesar");
        
        // Estimación de tiempo
        $estimatedSeconds = ceil($totalImages * 0.5); // ~0.5 segundos por imagen
        $estimatedMinutes = floor($estimatedSeconds / 60);
        $remainingSeconds = $estimatedSeconds % 60;
        
        if ($estimatedMinutes > 0) {
            $this->command->info("   ⏱️  Tiempo estimado: ~{$estimatedMinutes}m {$remainingSeconds}s");
        } else {
            $this->command->info("   ⏱️  Tiempo estimado: ~{$estimatedSeconds}s");
        }

        // 7. ⚡ OPTIMIZACIÓN: Deshabilitar eventos temporalmente
        Product::flushEventListeners();

        // 8. ⚡ OPTIMIZACIÓN: Procesar en transacción
        $count = 0;
        $errors = 0;

        $this->command->newLine();
        $startTime = microtime(true);
        $this->command->info("🚀 Procesando y vinculando imágenes...");
        $progressBar = $this->command->getOutput()->createProgressBar(count($validRows));
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% - %elapsed:6s% / ~%estimated:-6s%');
        $progressBar->start();

        DB::transaction(function () use ($validRows, &$count, &$errors, $progressBar) {
            foreach ($validRows as $item) {
                $progressBar->advance();

                try {
                    $product = $item['product'];
                    $sourceFile = $item['sourceFile'];
                    $imageName = $item['imageName'];

                    // Verificar MIME type rápidamente
                    $mimeType = mime_content_type($sourceFile);
                    if (!str_starts_with($mimeType, 'image/')) {
                        $errors++;
                        continue;
                    }

                    // Agregar media SIN generar conversiones aún (más rápido)
                    $media = $product->addMedia($sourceFile)
                        ->preservingOriginal()
                        ->usingName($product->primary_name)
                        ->usingFileName($imageName)
                        ->withCustomProperties([
                            'is_primary' => true,
                            'order' => 1
                        ])
                        ->toMediaCollection('images', 'public');

                    $count++;

                } catch (\Exception $e) {
                    $errors++;
                }
            }
        });

        $progressBar->finish();
        $this->command->newLine(2);
        
        $endTime = microtime(true);
        $totalTime = round($endTime - $startTime, 2);
        $avgTimePerImage = $count > 0 ? round($totalTime / $count, 3) : 0;

        // 9. Resumen
        $notFound = count($productosCsv) - count($validRows);

        $this->command->info("✅ Proceso completado:");
        $this->command->info("   • Imágenes vinculadas: $count");
        $this->command->info("   • Imágenes no encontradas: $notFound");
        $this->command->info("   • Errores: $errors");
        $this->command->info("   • Tiempo total: {$totalTime}s");
        $this->command->info("   • Promedio por imagen: {$avgTimePerImage}s");

        // 10. Opción: Generar conversiones después
        if ($count > 0) {
            $this->command->newLine();
            $this->command->info("💡 Tip: Las conversiones se generarán automáticamente al acceder a las imágenes.");
            $this->command->info("   O ejecute: php artisan media-library:regenerate");
        }

        // 11. Verificación rápida
        $this->verifyImages();
    }

    /**
     * Verificar que las imágenes son accesibles
     */
    private function verifyImages(): void
    {
        $this->command->info("\n🔍 Verificación rápida...");

        $firstProduct = Product::has('media')->first();

        if (!$firstProduct) {
            $this->command->warn("No hay productos con imágenes para verificar");
            return;
        }

        $media = $firstProduct->getFirstMedia('images');

        if (!$media) {
            $this->command->warn("No se encontró media para verificar");
            return;
        }

        $this->command->info("   Producto: {$firstProduct->primary_name}");
        $this->command->info("   URL original: {$media->getUrl()}");

        // Verificar archivo físico
        $fullPath = $media->getPath();
        if (File::exists($fullPath)) {
            $this->command->info("   ✓ Archivo físico verificado");
        } else {
            $this->command->error("   ❌ Archivo físico NO existe");
        }

        // Intentar generar una conversión como prueba
        try {
            $thumbUrl = $media->getUrl('thumb');
            $this->command->info("   ✓ URL thumb: $thumbUrl");
        } catch (\Exception $e) {
            $this->command->warn("   ⚠️  Error generando thumb: " . $e->getMessage());
        }
    }
}
