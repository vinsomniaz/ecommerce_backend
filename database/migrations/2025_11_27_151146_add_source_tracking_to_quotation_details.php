<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_details', function (Blueprint $table) {
            // Origen del producto (warehouse o supplier)
            $table->enum('source_type', ['warehouse', 'supplier'])
                  ->default('warehouse')
                  ->after('product_brand');
            
            // Si viene de warehouse
            $table->foreignId('warehouse_id')
                  ->nullable()
                  ->after('source_type')
                  ->constrained()
                  ->nullOnDelete();
            
            // Si viene de supplier
            $table->foreignId('supplier_id')
                  ->nullable()
                  ->after('warehouse_id')
                  ->constrained('entities')
                  ->nullOnDelete();
            
            $table->foreignId('supplier_product_id')
                  ->nullable()
                  ->after('supplier_id')
                  ->constrained('supplier_products')
                  ->nullOnDelete();
            
            // Control de solicitud a proveedor
            $table->boolean('is_requested_from_supplier')
                  ->default(false)
                  ->after('supplier_product_id');
            
            // Costos para cálculo de margen
            $table->decimal('unit_cost', 10, 2)
                  ->default(0.00)
                  ->after('purchase_price')
                  ->comment('Costo unitario real (purchase_price o supplier price)');
            
            $table->decimal('total_cost', 12, 2)
                  ->default(0.00)
                  ->after('unit_cost')
                  ->comment('Costo total = unit_cost * quantity');
            
            // Índices para rendimiento
            $table->index(['source_type', 'warehouse_id']);
            $table->index(['source_type', 'supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::table('quotation_details', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropForeign(['supplier_id']);
            $table->dropForeign(['supplier_product_id']);
            
            $table->dropColumn([
                'source_type',
                'warehouse_id',
                'supplier_id',
                'supplier_product_id',
                'is_requested_from_supplier',
                'unit_cost',
                'total_cost'
            ]);
        });
    }
};