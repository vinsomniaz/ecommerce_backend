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
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->string('currency', 3)->primary()->comment('Código de la moneda (USD, EUR)');
            $table->decimal('exchange_rate', 10, 4)->comment('Tasa: 1 unidad de esta moneda = X PEN');
            $table->timestamps(); // Para guardar cuándo fue la última actualización
        });

        // Insertar valores iniciales
        DB::table('exchange_rates')->insert([
            ['currency' => 'USD', 'exchange_rate' => 3.75, 'created_at' => now(), 'updated_at' => now()],
            ['currency' => 'EUR', 'exchange_rate' => 4.00, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};