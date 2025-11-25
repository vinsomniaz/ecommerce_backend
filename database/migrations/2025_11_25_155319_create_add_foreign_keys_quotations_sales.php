<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * EJECUTAR DESPUÉS de crear las tablas quotations y sales
     */
    public function up(): void
    {
        // 1. Agregar foreign key de converted_sale_id en quotations
        Schema::table('quotations', function (Blueprint $table) {
            $table->foreign('converted_sale_id')
                ->references('id')
                ->on('sales')
                ->onUpdate('restrict')
                ->onDelete('set null');
        });

        // 2. Agregar columna y foreign key de quotation_id en sales
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('quotation_id')
                ->nullable()
                ->after('id')
                ->constrained('quotations')
                ->onUpdate('restrict')
                ->onDelete('set null')
                ->comment('Cotización que originó esta venta');

            $table->index('quotation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['quotation_id']);
            $table->dropIndex(['quotation_id']);
            $table->dropColumn('quotation_id');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropForeign(['converted_sale_id']);
        });
    }
};
