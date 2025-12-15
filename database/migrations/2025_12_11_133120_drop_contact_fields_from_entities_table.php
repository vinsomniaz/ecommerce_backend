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
        Schema::table('entities', function (Blueprint $table) {
            // NOTA: Las direcciones y teléfonos NO viven en entities.
            // La fuente de verdad es addresses (direcciones) y contacts (personas).
            
            // Drop indexes first (if they exist)
            if (Schema::hasColumn('entities', 'email')) {
                try {
                    $table->dropIndex(['email']); // Drop email index
                } catch (\Exception $e) {
                    // Index might not exist, continue
                }
            }
            
            // Drop foreign keys
            if (Schema::hasColumn('entities', 'ubigeo')) {
                try {
                    $table->dropForeign(['ubigeo']);
                } catch (\Exception $e) {
                    // Foreign key might not exist
                }
            }
            
            if (Schema::hasColumn('entities', 'country_code')) {
                try {
                    $table->dropForeign(['country_code']);
                } catch (\Exception $e) {
                    // Foreign key might not exist
                }
            }
            
            // Drop columns if they exist
            $columnsToDrop = [];
            foreach (['address', 'ubigeo', 'country_code', 'phone', 'email'] as $column) {
                if (Schema::hasColumn('entities', $column)) {
                    $columnsToDrop[] = $column;
                }
            }
            
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            // Restore columns
            $table->string('address', 250)->nullable();
            $table->char('ubigeo', 6)->nullable();
            $table->char('country_code', 2)->default('PE');
            $table->string('phone', 20)->nullable();
            $table->string('email', 100)->nullable();
            
            // Restore foreign keys
            $table->foreign('ubigeo')->references('ubigeo')->on('ubigeos')->onDelete('set null');
            $table->foreign('country_code')->references('code')->on('countries');
            
            // Restore index
            $table->index('email');
        });
    }
};
