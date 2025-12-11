<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Crear tabla document_types
        Schema::create('document_types', function (Blueprint $table) {
            $table->char('code', 2)->primary()->comment('Código SUNAT');
            $table->string('name', 100)->comment('Nombre del tipo de documento');
            $table->tinyInteger('length')->nullable()->comment('Longitud esperada del documento');
            $table->string('validation_pattern', 100)->nullable()->comment('Patrón de validación regex');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Insertar los tipos de documento según catálogo SUNAT
        DB::table('document_types')->insert([
            [
                'code' => '01',
                'name' => 'DNI',
                'length' => 8,
                'validation_pattern' => '^[0-9]{8}$',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => '04',
                'name' => 'Carnet de Extranjería',
                'length' => 12,
                'validation_pattern' => '^[A-Z0-9]{9,12}$',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => '06',
                'name' => 'RUC',
                'length' => 11,
                'validation_pattern' => '^(10|20)[0-9]{9}$',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => '07',
                'name' => 'Pasaporte',
                'length' => 12,
                'validation_pattern' => '^[A-Z0-9]{5,12}$',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Modificar tabla entities para agregar foreign key
        Schema::table('entities', function (Blueprint $table) {
            // Cambiar tipo_documento a char(2) si no lo es
            $table->char('tipo_documento', 2)->change();
            
            // Agregar foreign key constraint
            $table->foreign('tipo_documento')
                ->references('code')
                ->on('document_types')
                ->onUpdate('restrict')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->dropForeign(['tipo_documento']);
        });

        Schema::dropIfExists('document_types');
    }
};