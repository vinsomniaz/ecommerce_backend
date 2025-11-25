<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispatch_guide_details', function (Blueprint $table) {
            $table->id();

            $table->foreignId('guide_id')->constrained('dispatch_guides')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');

            $table->integer('quantity');
            $table->string('unit_measure', 10)->default('NIU'); // Catálogo 03 SUNAT
            $table->string('description', 250)->nullable();

            $table->timestamps();

            // Índices
            $table->index('guide_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatch_guide_details');
    }
};
