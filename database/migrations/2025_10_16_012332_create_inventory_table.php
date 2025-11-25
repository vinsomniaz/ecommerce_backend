<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory', function (Blueprint $table) {
            // ✅ PK surrogate
            $table->bigIncrements('id');

            // FKs
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();

            // Stock
            $table->integer('available_stock')->default(0);
            $table->integer('reserved_stock')->default(0);

            // Precios
            $table->decimal('average_cost', 10, 4)->default(0);
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->decimal('profit_margin', 5, 2)->nullable();
            $table->decimal('min_sale_price', 10, 2)->nullable();

            // Fechas
            $table->timestamp('price_updated_at')->nullable();
            $table->timestamp('last_movement_at')->nullable();

            // ✅ Regla de negocio: no duplicar (product, warehouse)
            $table->unique(['product_id', 'warehouse_id'], 'inventory_product_warehouse_unique');

            // Índices útiles
            $table->index('available_stock');
            $table->index('warehouse_id');
            $table->index('last_movement_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory');
    }
};
