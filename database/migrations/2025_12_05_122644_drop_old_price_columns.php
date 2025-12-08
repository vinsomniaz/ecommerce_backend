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
        // database/migrations/xxxx_xx_xx_drop_old_price_columns.php
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('distribution_price');
            //Agregar costo inicial a producto
            $table->decimal('initial_cost', 12, 4)
                ->nullable()
                ->after('barcode');
        });

        Schema::table('inventory', function (Blueprint $table) {
            $table->dropColumn(['sale_price', 'min_sale_price', 'profit_margin']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
