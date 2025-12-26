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
        Schema::table('coupons', function (Blueprint $table) {
            // Campos descriptivos
            $table->string('name', 100)->after('code');
            $table->text('description')->nullable()->after('name');

            // Control de uso
            $table->unsignedInteger('usage_limit')->nullable()->after('min_amount')
                ->comment('Límite total de usos, null = ilimitado');
            $table->unsignedInteger('usage_count')->default(0)->after('usage_limit')
                ->comment('Contador de usos actuales');
            $table->unsignedSmallInteger('usage_per_user')->nullable()->after('usage_count')
                ->comment('Límite de usos por usuario, null = ilimitado');

            // Descuento máximo para cupones porcentuales
            $table->decimal('max_discount', 10, 2)->nullable()->after('value')
                ->comment('Descuento máximo para cupones porcentuales');

            // Aplicación flexible
            $table->enum('applies_to', ['all', 'categories', 'products'])->default('all')->after('end_date')
                ->comment('A qué aplica el cupón: todo, categorías específicas o productos específicos');

            // Índices para búsquedas comunes
            $table->index(['active', 'start_date', 'end_date'], 'coupons_validity_index');
            $table->index('applies_to');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropIndex('coupons_validity_index');
            $table->dropIndex(['applies_to']);

            $table->dropColumn([
                'name',
                'description',
                'usage_limit',
                'usage_count',
                'usage_per_user',
                'max_discount',
                'applies_to',
            ]);
        });
    }
};
