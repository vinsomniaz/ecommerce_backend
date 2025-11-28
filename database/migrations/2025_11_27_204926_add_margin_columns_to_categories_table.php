<?php
// database/migrations/xxxx_add_margin_columns_to_categories_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // Agregar nuevas columnas de margen
            $table->decimal('normal_margin_percentage', 5, 2)
                ->default(0.00)
                ->after('is_active')
                ->comment('Margen objetivo/normal (0 = heredar del padre)');
                
            $table->decimal('min_margin_percentage', 5, 2)
                ->default(0.00)
                ->after('normal_margin_percentage')
                ->comment('Margen mínimo permitido (0 = heredar del padre)');
        });
        
        // Renombrar columnas antiguas si existen
        if (Schema::hasColumn('categories', 'margin_retail')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->renameColumn('margin_retail', 'margin_retail_old');
                $table->renameColumn('margin_retail_min', 'margin_retail_min_old');
            });
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['normal_margin_percentage', 'min_margin_percentage']);
        });
        
        // Restaurar nombres antiguos si se renombraron
        if (Schema::hasColumn('categories', 'margin_retail_old')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->renameColumn('margin_retail_old', 'margin_retail');
                $table->renameColumn('margin_retail_min_old', 'margin_retail_min');
            });
        }
    }
};