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
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->char('ubigeo', 6);
            $table->string('address', 200);
            $table->string('phone', 20)->nullable();
            $table->boolean('is_main')->default(false);
            $table->boolean('visible_online')->default(true);
            $table->integer('picking_priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('ubigeo')->references('ubigeo')->on('ubigeos')->onDelete('restrict');
            $table->index('is_active');
            $table->index('visible_online');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
