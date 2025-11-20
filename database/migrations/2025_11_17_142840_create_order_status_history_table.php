<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');

            // Estado (debe coincidir con los estados de la tabla orders)
            $table->enum('status', [
                'pendiente',
                'confirmado',
                'preparando',
                'enviado',
                'entregado',
                'cancelado'
            ]);

            // Detalles adicionales
            $table->text('notes')->nullable();
            $table->string('tracking_code', 100)->nullable(); // Código de courier

            // Usuario responsable del cambio
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');

            // Fecha del cambio
            $table->timestamp('created_at')->useCurrent();

            // Índices
            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_history');
    }
};
