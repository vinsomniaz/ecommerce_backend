<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // Tipo de notificación
            $table->string('type', 50); // order_confirmed, order_shipped, low_stock, etc.

            // Contenido
            $table->string('title', 200);
            $table->text('message');

            // Datos adicionales (JSON)
            $table->json('data')->nullable(); // { "order_id": 123, "url": "/orders/123" }

            // Estado
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            // Índices
            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
