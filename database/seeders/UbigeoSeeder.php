<?php

namespace Database\Seeders;

use App\Models\Ubigeo;
use Illuminate\Database\Seeder;
use League\Csv\Reader;
use League\Csv\Statement;
use Exception;

class UbigeoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            // Ruta al CSV dentro de database/data
            $csvFile = database_path('data/Lista_Ubigeos_INEI.csv');

            // Leer CSV con la versión moderna de League CSV
            $csv = Reader::createFromPath($csvFile, 'r');
            $csv->setDelimiter(';'); // Usa ";" si es tu separador real
            $csv->setHeaderOffset(0); // Primera fila como cabecera

            // Utiliza Statement para filtrar y procesar las filas
            $stmt = (new Statement())->offset(0); // Puedes usar offset si es necesario

            foreach ($stmt->process($csv) as $row) {
                Ubigeo::create([
                    'ubigeo'   => $row['UBIGEO_INEI'] ?? null,
                    'departamento' => $row['DEPARTAMENTO'] ?? null,
                    'provincia' => $row['PROVINCIA'] ?? null,
                    'distrito'=> $row['DISTRITO'] ?? null,
                ]);
            }

            $this->command->info('✅ Seeding de departamentos completado con éxito. 🎉');
        } catch (Exception $e) {
            $this->command->error('❌ Error durante el seeding: ' . $e->getMessage());
        }
    }
}
