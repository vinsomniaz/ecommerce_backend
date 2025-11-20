<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // Referencias (puede ser orden web o venta física)
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null');
            $table->foreignId('sale_id')->nullable()->constrained('sales')->onDelete('set null');

            // Método de pago
            $table->enum('payment_method', [
                'cash',      // Efectivo
                'card',      // Tarjeta (POS físico)
                'transfer',  // Transferencia bancaria
                'culqi',     // Pasarela Culqi
                'izipay',    // Pasarela Izipay
                'yape',      // Yape
                'plin'       // Plin
            ]);

            // Montos
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('PEN');

            // Estado
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');

            // Datos de la pasarela (si aplica)
            $table->string('transaction_id', 100)->nullable()->unique();
            $table->json('gateway_response')->nullable(); // Respuesta completa de la pasarela
            $table->string('authorization_code', 50)->nullable();

            // Fechas
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            // Índices
            $table->index(['order_id', 'status']);
            $table->index(['sale_id', 'status']);
            $table->index('payment_method');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
