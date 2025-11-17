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
        Schema::create('cart_details', function (Blueprint $table) {
            $table->id();  // Clave primaria
            $table->foreignId('cart_id')->constrained('carts')->onDelete('cascade');  // Relación con carritos
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');  // Relación con productos
            $table->integer('quantity');  // Cantidad de producto
            $table->timestamps();  // Tiempos de creación y actualización
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_details');
    }
};
