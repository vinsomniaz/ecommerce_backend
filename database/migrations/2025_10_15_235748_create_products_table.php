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
            $table->string('sku', 50)->nullable()->unique();
            $table->string('primary_name', 200);
            $table->string('secondary_name', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('short_description', 500)->nullable();
            $table->string('brand', 100)->nullable();
            $table->json('ficha_tecnica')->nullable();
            $table->foreignId('category_id')->constrained('categories')->onDelete('restrict');

            // SUNAT fields (Peru-specific)
            $table->string('codigo_sunat', 20)->nullable();
            $table->string('unidad_medida_sunat', 10)->default('NIU'); // NIU=Unidad
            $table->string('tipo_afectacion_igv', 2)->default('10'); // 10=Gravado, 20=Exonerado

            // Pricing
            $table->decimal('cost_price', 10, 2)->default(0);
            $table->decimal('unit_price', 8, 2)->default(0);
            $table->decimal('distribution_price',8,2)->default(0);


            // Stock and physical
            $table->integer('min_stock')->default(5);
            // $table->decimal('weight_kg', 8, 3)->nullable();
            $table->string('barcode', 50)->nullable();

            // Ecommerce
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);

            // Tech specs (for compatibility checker)
            // $table->string('socket', 20)->nullable(); // AM5, LGA1700, etc.
            // $table->string('ram_type', 10)->nullable(); // DDR4, DDR5
            // $table->integer('tdp')->nullable(); // Watts
            // $table->integer('cores')->nullable();
            // $table->integer('threads')->nullable();

            $table->timestamps();

            $table->index('brand');
            $table->index('is_featured');
            $table->index('is_active');
            $table->index('category_id');
            // $table->index('socket');
            // $table->index('ram_type');
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
