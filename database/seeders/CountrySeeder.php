<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;
use League\Csv\Reader;
use League\Csv\Statement;
use Exception;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            // Ruta al CSV dentro de database/data
            $csvFile = database_path('data/countries.csv');

            if (!file_exists($csvFile)) {
                $this->command->error('❌ Archivo CSV no encontrado en: ' . $csvFile);
                $this->command->info('💡 Por favor, coloca tu archivo countries.csv en database/data/');
                return;
            }

            // Leer CSV con League CSV (API nueva)
            $csv = Reader::from($csvFile, 'r');

            // Tu CSV usa PUNTO Y COMA como delimitador
            $csv->setDelimiter(';'); // Punto y coma
            $csv->setHeaderOffset(0); // Primera fila como cabecera

            // Obtener los headers para debugging
            $headers = $csv->getHeader();
            $this->command->info('📋 Columnas detectadas en el CSV: ' . implode(', ', $headers));

            // Utiliza Statement para procesar las filas
            $stmt = (new Statement())->offset(0)->limit(1); // Solo primera fila para debug

            // Mostrar primera fila para verificar
            foreach ($stmt->process($csv) as $row) {
                $this->command->info('🔍 Primera fila de datos:');
                $this->command->info('  - ISO2: ' . ($row['ISO2'] ?? 'NO ENCONTRADO'));
                $this->command->info('  - name: ' . ($row['name'] ?? 'NO ENCONTRADO'));
                $this->command->info('  - phone_code: ' . ($row['phone_code'] ?? 'NO ENCONTRADO'));
                break;
            }

            // Procesar todas las filas
            $stmt = (new Statement())->offset(0);
            $count = 0;
            $errors = 0;

            foreach ($stmt->process($csv) as $row) {
                // Verificar que tengamos el código ISO2
                $code = $row['ISO2'] ?? $row['iso2'] ?? $row[' ISO2'] ?? null;

                if (!$code || strlen(trim($code)) !== 2) {
                    $this->command->warn("⚠️ Fila ignorada - código inválido: " . json_encode($row));
                    $errors++;
                    continue;
                }

                Country::updateOrCreate(
                    ['code' => trim($code)],
                    [
                        'name' => trim($row['name'] ?? $row['Name'] ?? ''),
                        'phone_code' => trim($row['phone_code'] ?? $row['Phone_code'] ?? ''),
                    ]
                );
                $count++;
            }

            $this->command->info("✅ Seeding de países completado: {$count} países importados. 🎉");
            if ($errors > 0) {
                $this->command->warn("⚠️ {$errors} filas ignoradas por datos inválidos.");
            }
        } catch (Exception $e) {
            $this->command->error('❌ Error durante el seeding: ' . $e->getMessage());
            $this->command->error('Stack trace: ' . $e->getTraceAsString());
        }
    }
}
