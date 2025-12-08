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
        Schema::table('users', function (Blueprint $table) {
            // Esto crea la columna 'remember_token' (VARCHAR 100, nullable)
            // 'after' es opcional, pero ayuda a mantener la tabla ordenada visualmente
            $table->rememberToken()->after('password'); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Si hacemos rollback, eliminamos la columna
            $table->dropRememberToken();
        });
    }
};