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
        Schema::create('quotation_status_history', function (Blueprint $table) {
            $table->id();

            // Relación con cotización
            $table->foreignId('quotation_id')
                ->constrained('quotations')
                ->onUpdate('restrict')
                ->onDelete('cascade');

            // Estado
            $table->enum('status', [
                'draft',
                'sent',
                'accepted',
                'rejected',
                'expired',
                'converted'
            ]);

            // Usuario que realizó el cambio
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->onUpdate('restrict')
                ->onDelete('set null');

            // Notas del cambio
            $table->text('notes')->nullable();

            // Información adicional
            $table->json('metadata')->nullable()->comment('Información adicional del cambio de estado');

            $table->timestamp('created_at')->useCurrent();

            // Índices
            $table->index('quotation_id');
            $table->index(['quotation_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotation_status_history');
    }
};
