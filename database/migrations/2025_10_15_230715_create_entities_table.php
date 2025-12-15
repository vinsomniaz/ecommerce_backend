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
        Schema::create('entities', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['customer', 'supplier', 'both'])->default('customer');

            // Peru-specific fields (SUNAT)
            $table->string('tipo_documento', 2); // 01=DNI, 06=RUC
            $table->string('numero_documento', 20);
            $table->enum('tipo_persona', ['natural', 'juridica'])->default('natural');

            // Generic business fields
            $table->string('business_name', 200)->nullable(); // Razón Social
            $table->string('trade_name', 100)->nullable(); // Nombre Comercial
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();

            // Address fields
            $table->string('address', 250)->nullable(); // Dirección Fiscal
            $table->char('ubigeo', 6)->nullable(); // MODIFICADO: nullable
            $table->char('country_code', 2)->default('PE'); // NUEVO
            $table->string('phone', 20)->nullable();
            $table->string('email', 100)->nullable();

            // SUNAT status
            $table->enum('estado_sunat', ['ACTIVO', 'BAJA', 'SUSPENDIDO'])->nullable();
            $table->enum('condicion_sunat', ['HABIDO', 'NO HABIDO'])->nullable();

            // Relations and metadata
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->boolean('is_active')->default(true);
            $table->datetime('registered_at')->useCurrent();
            $table->timestamps();

            // Indexes
            $table->unique(['tipo_documento', 'numero_documento']);
            $table->index('email');
            $table->index('type');

            // LLAVES FORÁNEAS
            $table->foreign('ubigeo')->references('ubigeo')->on('ubigeos')->onDelete('set null');
            $table->foreign('country_code')->references('code')->on('countries'); // NUEVO
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entities');
    }
};
