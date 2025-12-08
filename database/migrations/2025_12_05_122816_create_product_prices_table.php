<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products')
                ->onUpdate('restrict')
                ->onDelete('cascade');

            $table->foreignId('price_list_id')
                ->constrained('price_lists')
                ->onUpdate('restrict')
                ->onDelete('restrict');

            $table->foreignId('warehouse_id')
                ->nullable()
                ->constrained('warehouses')
                ->onUpdate('restrict')
                ->onDelete('set null');

            // PRECIOS
            $table->decimal('price', 12, 2);               // precio de venta
            $table->decimal('min_price', 12, 2)->nullable(); // límite mínimo (si quieres proteger descuento)
            $table->string('currency', 3)->default('PEN');

            // ESCALONADO / MAYORISTA
            $table->integer('min_quantity')->default(1);

            // VIGENCIA
            $table->dateTime('valid_from')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->dateTime('valid_to')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(
                ['product_id', 'price_list_id', 'warehouse_id', 'is_active'],
                'pp_prod_list_wh_active_idx'
            );

            $table->index(
                ['valid_from', 'valid_to'],
                'pp_valid_range_idx'
            );

            // 🚀 UNICOS
            $table->unique(
                ['product_id', 'price_list_id', 'warehouse_id', 'min_quantity'],
                'unique_product_price'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_prices');
    }
};
