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
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->nullable()->constrained('entities')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('address', 250);
            $table->char('ubigeo', 6)->nullable(); // MODIFICADO: nullable
            $table->char('country_code', 2)->default('PE'); // NUEVO
            $table->string('reference', 200)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('label', 50)->nullable(); // Home, Work, Office
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->foreign('ubigeo')->references('ubigeo')->on('ubigeos')->onDelete('restrict');
            $table->foreign('country_code')->references('code')->on('countries'); // NUEVO
            $table->index('entity_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};