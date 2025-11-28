<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Solo para staff/vendedores (roles via Spatie)
            $table->decimal('commission_percentage', 5, 2)->nullable()->after('warehouse_id')
                  ->comment('Porcentaje de comisión (solo para vendedores)');
            
            $table->date('hired_at')->nullable()->after('commission_percentage')
                  ->comment('Fecha de contratación (solo para staff)');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['commission_percentage', 'hired_at']);
        });
    }
};