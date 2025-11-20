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
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // Identificación
            $table->string('sku', 50)->nullable()->unique();
            $table->string('primary_name', 200);
            $table->string('secondary_name', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('brand', 100)->nullable();

            // Categoría (OBLIGATORIO)
            $table->foreignId('category_id')->constrained('categories')->onDelete('restrict');

            // Campos SUNAT (Perú)
            $table->string('unit_measure', 10)->default('NIU'); // ← CAMBIO: renombrado
            $table->string('tax_type', 2)->default('10'); // ← CAMBIO: renombrado

            // Stock y físicos
            $table->integer('min_stock')->default(5);
            $table->decimal('weight', 10, 2)->nullable(); // ← CAMBIO: nombre simplificado
            $table->string('barcode', 50)->nullable();

            // Estados booleanos
            $table->boolean('is_new')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('visible_online')->default(true); // ← AGREGADO: nuevo campo

            $table->timestamps();
            $table->softDeletes(); // ← AGREGADO: para soft delete

            // Índices para optimización
            $table->index('sku');
            $table->index('brand');
            $table->index('is_active');
            $table->index('is_featured');
            $table->index('visible_online');
            $table->index('category_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
