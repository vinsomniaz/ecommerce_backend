<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('company', function (Blueprint $table) {
            $table->id();
            $table->string('RUC', 11)->unique();
            $table->string('razon_social', 200);
            $table->string('direccion_fiscal', 250);

            // Asegúrate de que 'ubigeo' sea del mismo tipo y longitud que en la tabla 'ubigeos'
            $table->char('ubigeo', 6);  // Debe ser char(6), igual que en 'ubigeos'

            $table->foreign('ubigeo')->references('ubigeo')->on('ubigeos')->onDelete('restrict');  // Relación de la clave foránea

            $table->string('certificado_digital_url', 250);
            $table->string('usuario_sol', 50);
            $table->string('clave_sol_hash', 255);
            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company');
    }
};
