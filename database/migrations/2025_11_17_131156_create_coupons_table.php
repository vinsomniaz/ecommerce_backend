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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();  // Clave primaria
            $table->string('code', 50)->unique();  // Código del cupón
            $table->enum('type', ['percentage', 'amount']);  // Tipo de cupón
            $table->decimal('value', 10, 2);  // Valor del cupón
            $table->decimal('min_amount', 10, 2);  // Monto mínimo
            $table->date('start_date');  // Fecha de inicio
            $table->date('end_date');  // Fecha de fin
            $table->boolean('active');  // Estado del cupón
            $table->timestamps();  // Tiempos de creación y actualización
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
