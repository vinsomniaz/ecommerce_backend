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
            // Commission percentage for sales staff
            if (!Schema::hasColumn('users', 'commission_percentage')) {
                $table->decimal('commission_percentage', 5, 2)->nullable()->after('is_active')
                    ->comment('Porcentaje de comision para vendedores');
            }

            // Last login tracking
            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('commission_percentage')
                    ->comment('Ultima fecha y hora de acceso');
            }
        });

        // Index for commission queries - add outside the schema callback
        if (Schema::hasColumn('users', 'commission_percentage')) {
            try {
                Schema::table('users', function (Blueprint $table) {
                    $table->index('commission_percentage');
                });
            } catch (\Exception $e) {
                // Index may already exist
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop index if exists
            try {
                $table->dropIndex(['commission_percentage']);
            } catch (\Exception $e) {
                // Index may not exist
            }

            if (Schema::hasColumn('users', 'commission_percentage')) {
                $table->dropColumn('commission_percentage');
            }
            if (Schema::hasColumn('users', 'last_login_at')) {
                $table->dropColumn('last_login_at');
            }
        });
    }
};
