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
        Schema::create('carts', function (Blueprint $table) {
            $table->id();  // Clave primaria
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');  // Clave foránea hacia la tabla users
            $table->string('session_id', 100);  // ID de la sesión
            $table->timestamps();  // Tiempos de creación y actualización
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
