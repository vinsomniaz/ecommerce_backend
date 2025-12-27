<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $rolesTable = $tableNames['roles'] ?? 'roles';

        Schema::table($rolesTable, function (Blueprint $table) {
            if (!Schema::hasColumn($table->getTable(), 'color_hex')) {
                $table->string('color_hex', 7)->nullable()->after('guard_name');
            }
            if (!Schema::hasColumn($table->getTable(), 'description')) {
                $table->string('description', 255)->nullable()->after('color_hex');
            }
        });

        // Set default colors for system roles
        DB::table($rolesTable)->where('name', 'super-admin')->update([
            'color_hex' => '#7C3AED',
            'description' => 'Acceso completo al sistema'
        ]);
        DB::table($rolesTable)->where('name', 'admin')->update([
            'color_hex' => '#2563EB',
            'description' => 'Gestión de ventas y cotizaciones, aprobación de operaciones'
        ]);
        DB::table($rolesTable)->where('name', 'vendor')->update([
            'color_hex' => '#059669',
            'description' => 'Creación de cotizaciones y ventas'
        ]);
        DB::table($rolesTable)->where('name', 'customer')->update([
            'color_hex' => '#F59E0B',
            'description' => 'Acceso a compras y perfil personal'
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');
        $rolesTable = $tableNames['roles'] ?? 'roles';

        Schema::table($rolesTable, function (Blueprint $table) {
            $table->dropColumn(['color_hex', 'description']);
        });
    }
};
