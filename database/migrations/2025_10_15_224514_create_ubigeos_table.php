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
        Schema::create('ubigeos', function (Blueprint $table) {
            $table->char('ubigeo', 6)->primary();
            $table->string('departamento', 50);
            $table->string('provincia', 50);
            $table->string('distrito', 50);
            $table->string('codigo_sunat', 6)->nullable();
            $table->index(['departamento', 'provincia', 'distrito']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ubigeos');
    }
};
