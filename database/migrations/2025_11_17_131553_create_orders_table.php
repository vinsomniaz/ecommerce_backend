<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {

            $table->id();

            // Usuario que registra el pedido (opcional)
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            // Cliente (entity)
            $table->foreignId('customer_id')
                ->constrained('entities')
                ->onDelete('restrict');

            // Almacén
            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->onDelete('cascade');

            // Dirección de envío
            $table->foreignId('shipping_address_id')
                ->constrained('addresses')
                ->onDelete('cascade');

            // Cupón (opcional)
            $table->foreignId('coupon_id')
                ->nullable()
                ->constrained('coupons')
                ->onDelete('set null');

            // Tipo de venta
            $table->enum('sale_type', ['store', 'online'])
                ->default('store')
                ->comment('store = venta tienda física, online = ecommerce');

            // Estado del pedido
            $table->enum('status', [
                'pendiente', 'confirmado', 'preparando',
                'enviado', 'entregado', 'cancelado'
            ]);

            $table->string('currency', 3);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount', 10, 2);
            $table->decimal('coupon_discount', 10, 2)->nullable();
            $table->decimal('tax', 12, 2);
            $table->decimal('shipping_cost', 10, 2);
            $table->decimal('total', 12, 2);
            $table->dateTime('order_date');
            $table->text('observations')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
