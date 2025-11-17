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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();  // Clave primaria
            $table->foreignId('customer_id')->constrained('users')->onDelete('cascade');  // Relación con la tabla users (cliente)
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('cascade');  // Relación con la tabla warehouses (almacén)
            $table->foreignId('shipping_address_id')->constrained('addresses')->onDelete('cascade');  // Relación con la tabla addresses (dirección de envío)
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->onDelete('set null');  // Relación con la tabla coupons (opcional)
            $table->enum('status', ['pendiente','confirmado','preparando','enviado','entregado','cancelado']);  // Estado del pedido
            $table->string('currency', 3);  // Moneda del pedido
            $table->decimal('subtotal', 12, 2);  // Subtotal
            $table->decimal('discount', 10, 2);  // Descuento
            $table->decimal('coupon_discount', 10, 2)->nullable();  // Descuento por cupón (nullable)
            $table->decimal('tax', 12, 2);  // Impuestos (IGV)
            $table->decimal('shipping_cost', 10, 2);  // Costo de envío
            $table->decimal('total', 12, 2);  // Total del pedido
            $table->dateTime('order_date');  // Fecha y hora del pedido
            $table->text('observations')->nullable();  // Observaciones adicionales
            $table->timestamps();  // Tiempos de creación y actualización
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
