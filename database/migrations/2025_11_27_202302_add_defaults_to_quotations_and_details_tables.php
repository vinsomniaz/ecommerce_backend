<?php
// database/migrations/xxxx_add_defaults_to_quotations_and_details_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Primero actualizar registros existentes NULL a 0
        DB::table('quotations')
            ->whereNull('subtotal')
            ->orWhereNull('tax')
            ->orWhereNull('total')
            ->orWhereNull('total_margin')
            ->orWhereNull('margin_percentage')
            ->orWhereNull('commission_amount')
            ->update([
                'subtotal' => 0,
                'tax' => 0,
                'total' => 0,
                'total_margin' => 0,
                'margin_percentage' => 0,
                'commission_amount' => 0,
            ]);
        
        // Modificar columnas de quotations
        Schema::table('quotations', function (Blueprint $table) {
            $table->decimal('subtotal', 12, 2)->default(0)->change();
            $table->decimal('tax', 12, 2)->default(0)->change();
            $table->decimal('total', 12, 2)->default(0)->change();
            $table->decimal('total_margin', 12, 2)->default(0)->change();
            $table->decimal('margin_percentage', 5, 2)->default(0)->change();
            $table->decimal('commission_amount', 10, 2)->default(0.00)->change();
        });
        
        // Actualizar registros existentes NULL a 0 en quotation_details
        DB::table('quotation_details')
            ->whereNull('subtotal')
            ->orWhereNull('tax_amount')
            ->orWhereNull('total')
            ->orWhereNull('unit_margin')
            ->orWhereNull('total_margin')
            ->orWhereNull('margin_percentage')
            ->update([
                'subtotal' => 0,
                'tax_amount' => 0,
                'total' => 0,
                'unit_margin' => 0,
                'total_margin' => 0,
                'margin_percentage' => 0,
                'purchase_price' => 0,
            ]);
        
        // Modificar columnas de quotation_details
        Schema::table('quotation_details', function (Blueprint $table) {
            $table->decimal('purchase_price', 10, 2)->default(0)->change();
            $table->decimal('subtotal', 12, 2)->default(0)->change();
            $table->decimal('tax_amount', 12, 2)->default(0)->change();
            $table->decimal('total', 12, 2)->default(0)->change();
            $table->decimal('unit_margin', 10, 2)->default(0)->change();
            $table->decimal('total_margin', 12, 2)->default(0)->change();
            $table->decimal('margin_percentage', 5, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        // No es necesario revertir, pero si quieres:
        Schema::table('quotations', function (Blueprint $table) {
            $table->decimal('subtotal', 12, 2)->change();
            $table->decimal('tax', 12, 2)->change();
            $table->decimal('total', 12, 2)->change();
            $table->decimal('total_margin', 12, 2)->nullable()->change();
            $table->decimal('margin_percentage', 5, 2)->nullable()->change();
            $table->decimal('commission_amount', 10, 2)->default(0.00)->change();
        });
        
        Schema::table('quotation_details', function (Blueprint $table) {
            $table->decimal('purchase_price', 10, 2)->change();
            $table->decimal('subtotal', 12, 2)->change();
            $table->decimal('tax_amount', 12, 2)->change();
            $table->decimal('total', 12, 2)->change();
            $table->decimal('unit_margin', 10, 2)->change();
            $table->decimal('total_margin', 12, 2)->change();
            $table->decimal('margin_percentage', 5, 2)->change();
        });
    }
};