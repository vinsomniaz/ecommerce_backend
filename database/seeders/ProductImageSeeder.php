<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
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

        // 2. Limpiar SOLO los registros de media de productos (no los archivos físicos aún)
        $this->command->info('🧹 Limpiando registros de media anteriores...');

        $oldMediaIds = Media::where('model_type', Product::class)
            ->where('collection_name', 'images')
            ->pluck('id')
            ->toArray();

        // Eliminar los archivos físicos de los medios antiguos
        Media::where('model_type', Product::class)
            ->where('collection_name', 'images')
            ->each(function ($media) {
                try {
                    $media->delete(); // Esto elimina el registro Y los archivos
                } catch (\Exception $e) {
                    $this->command->warn("Error al eliminar media {$media->id}: {$e->getMessage()}");
                }
            });

        $this->command->info("   ✓ Eliminados " . count($oldMediaIds) . " registros de media antiguos");

        // 3. Verificar que el enlace simbólico existe
        if (!File::exists(public_path('storage'))) {
            $this->command->error('❌ El enlace simbólico storage no existe.');
            $this->command->error('   Ejecute: php artisan storage:link');
            return;
        }

        // 4. Leer el CSV de productos
        $productosCsv = $this->readCsv('productos.csv');

        if (empty($productosCsv)) {
            $this->command->error('❌ No se pudieron leer productos del CSV');
            return;
        }

        $count = 0;
        $notFound = 0;
        $errors = 0;

        $this->command->info("📦 Procesando " . count($productosCsv) . " productos...");

        $progressBar = $this->command->getOutput()->createProgressBar(count($productosCsv));
        $progressBar->start();

        foreach ($productosCsv as $row) {
            $progressBar->advance();

            // Validar fila
            if (empty($row['idproducto']) || empty($row['image_url']) || $row['image_url'] === 'NULL') {
                continue;
            }

            // Encontrar el producto
            $sku = $row['codigo'] ?? 'MIG-' . $row['idproducto'];
            $product = Product::where('sku', $sku)->first();

            if (!$product) {
                $notFound++;
                continue;
            }

            // Preparar la ruta de la imagen
            $imageName = basename($row['image_url']);
            $sourceFile = $sourceImagePath . '/' . $imageName;

            // Verificar y agregar la imagen
            if (File::exists($sourceFile)) {
                try {
                    // Verificar que el archivo es una imagen válida
                    $mimeType = mime_content_type($sourceFile);
                    if (!str_starts_with($mimeType, 'image/')) {
                        $this->command->warn("\n⚠️  Archivo no es imagen: $imageName");
                        $errors++;
                        continue;
                    }

                    // Agregar la imagen
                    $media = $product->addMedia($sourceFile)
                        ->preservingOriginal() // Mantiene el original en database/data/images
                        ->usingName($product->primary_name)
                        ->usingFileName($imageName)
                        ->withCustomProperties([
                            'is_primary' => true,
                            'order' => 1
                        ])
                        ->toMediaCollection('images', 'public');

                    // ✅ FORZAR la generación de conversiones inmediatamente
                    try {
                        $media->getUrl('thumb');
                        $media->getUrl('medium');
                        $media->getUrl('large');
                    } catch (\Exception $conversionError) {
                        $this->command->warn("\n⚠️  Error generando conversiones para $imageName");
                    }

                    $count++;

                    // Verificar que se crearon las conversiones
                    if (!$media->hasGeneratedConversion('thumb')) {
                        $this->command->warn("\n⚠️  No se generó conversión thumb para: $imageName");
                    }

                } catch (\Exception $e) {
                    $this->command->warn("\n❌ Error con $imageName: " . $e->getMessage());
                    $errors++;
                }
            } else {
                $notFound++;
            }
        }

        $progressBar->finish();
        $this->command->newLine(2);

        // 5. Resumen
        $this->command->info("✅ Proceso completado:");
        $this->command->info("   • Imágenes agregadas: $count");
        $this->command->info("   • Imágenes no encontradas: $notFound");
        $this->command->info("   • Errores: $errors");

        // 6. Verificar que las imágenes son accesibles
        $this->verifyImages();
    }

    /**
     * Verificar que las imágenes son accesibles
     */
    private function verifyImages(): void
    {
        $this->command->info("\n🔍 Verificando accesibilidad de imágenes...");

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

        $this->command->info("   Producto de prueba: {$firstProduct->primary_name}");
        $this->command->info("   URL original: {$media->getUrl()}");
        $this->command->info("   URL thumb: {$media->getUrl('thumb')}");

        // Verificar que el archivo físico existe
        $fullPath = $media->getPath();
        if (File::exists($fullPath)) {
            $this->command->info("   ✓ Archivo físico existe: $fullPath");
        } else {
            $this->command->error("   ❌ Archivo físico NO existe: $fullPath");
        }

        // Verificar conversiones
        $conversions = ['thumb', 'medium', 'large'];
        foreach ($conversions as $conversion) {
            if ($media->hasGeneratedConversion($conversion)) {
                $this->command->info("   ✓ Conversión '$conversion' generada");
            } else {
                $this->command->warn("   ⚠️  Conversión '$conversion' NO generada");
            }
        }
    }
}
