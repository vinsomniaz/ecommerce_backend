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
        Schema::create('ecommerce_configs', function (Blueprint $table) {
            $table->id();
            $table->string('key')->index()->comment('Identificador de la sección (ej: main_banner, footer_logo)');
            $table->string('title')->nullable()->comment('Título interno o alt text');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ecommerce_configs');
    }
};
