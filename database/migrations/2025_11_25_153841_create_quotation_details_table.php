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
        Schema::create('quotation_details', function (Blueprint $table) {
            $table->id();

            // Relación con cotización
            $table->foreignId('quotation_id')
                ->constrained('quotations')
                ->onUpdate('restrict')
                ->onDelete('cascade');

            // Relación con producto
            $table->foreignId('product_id')
                ->constrained('products')
                ->onUpdate('restrict')
                ->onDelete('restrict');

            // Información del producto en el momento de la cotización
            $table->string('product_name', 200)->comment('Nombre del producto al momento de cotizar');
            $table->string('product_sku', 50)->nullable();
            $table->string('product_brand', 100)->nullable();

            // Cantidades
            $table->integer('quantity');

            // Precios y costos
            $table->decimal('purchase_price', 10, 2)->comment('Precio de compra/costo');
            $table->decimal('distribution_price', 10, 2)->nullable()->comment('Precio de distribuidor');
            $table->decimal('unit_price', 12, 2)->comment('Precio unitario de venta');
            $table->decimal('discount', 10, 2)->default(0.00);
            $table->decimal('discount_percentage', 5, 2)->default(0.00);

            // Cálculos
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax_amount', 12, 2)->comment('Monto de IGV');
            $table->decimal('total', 12, 2);

            // Margen de ganancia por producto
            $table->decimal('unit_margin', 10, 2)->comment('Margen unitario');
            $table->decimal('total_margin', 12, 2)->comment('Margen total del item');
            $table->decimal('margin_percentage', 5, 2)->comment('Porcentaje de margen');

            // Información del proveedor sugerido
            $table->foreignId('suggested_supplier_id')
                ->nullable()
                ->constrained('entities')
                ->onUpdate('restrict')
                ->onDelete('set null')
                ->comment('Proveedor sugerido para compra');

            $table->decimal('supplier_price', 10, 2)->nullable()->comment('Precio del proveedor sugerido');

            // Disponibilidad
            $table->integer('available_stock')->default(0)->comment('Stock disponible al momento de cotizar');
            $table->boolean('in_stock')->default(true);

            // Notas específicas del item
            $table->text('notes')->nullable();

            $table->timestamps();

            // Índices
            $table->index('quotation_id');
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotation_details');
    }
};
