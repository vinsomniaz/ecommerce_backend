<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tabla para visualizar opciones rápidas de compra por distribuidor
     */
    public function up(): void
    {
        Schema::create('supplier_products', function (Blueprint $table) {
            $table->id();

            // Relación con proveedor
            $table->foreignId('supplier_id')
                ->constrained('entities')
                ->onUpdate('restrict')
                ->onDelete('cascade');

            // Relación con producto
            $table->foreignId('product_id')
                ->constrained('products')
                ->onUpdate('restrict')
                ->onDelete('cascade');

            // Código del proveedor para este producto
            $table->string('supplier_sku', 100)->nullable();

            // Precios del proveedor
            $table->decimal('purchase_price', 10, 2)->comment('Precio de compra al proveedor');
            $table->decimal('distribution_price', 10, 2)->nullable()->comment('Precio de distribución sugerido');

            // Moneda
            $table->string('currency', 3)->default('PEN');

            // Disponibilidad
            $table->integer('available_stock')->default(0);
            $table->boolean('is_available')->default(true);

            // Tiempos de entrega
            $table->integer('delivery_days')->nullable()->comment('Días de entrega del proveedor');
            $table->integer('min_order_quantity')->default(1)->comment('Cantidad mínima de pedido');

            // Prioridad (para ordenar opciones)
            $table->integer('priority')->default(0)->comment('Mayor número = mayor prioridad');

            // Estado
            $table->boolean('is_active')->default(true);

            // Última actualización de precio
            $table->timestamp('price_updated_at')->nullable();

            // Notas
            $table->text('notes')->nullable();

            $table->timestamps();

            // Índices
            $table->index('supplier_id');
            $table->index('product_id');
            $table->index(['product_id', 'is_active', 'priority']);
            $table->unique(['supplier_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_products');
    }
};
