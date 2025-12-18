<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // SKUs de proveedores específicos (mapeo directo para proveedores conocidos)
            $table->string('deltron_sku', 100)->nullable()->after('barcode');
            $table->string('intcomex_sku', 100)->nullable()->after('deltron_sku');
            $table->string('cva_sku', 100)->nullable()->after('intcomex_sku');

            // Índices para búsqueda rápida
            $table->index('deltron_sku');
            $table->index('intcomex_sku');
            $table->index('cva_sku');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['deltron_sku']);
            $table->dropIndex(['intcomex_sku']);
            $table->dropIndex(['cva_sku']);

            $table->dropColumn([
                'deltron_sku',
                'intcomex_sku',
                'cva_sku'
            ]);
        });
    }
};
