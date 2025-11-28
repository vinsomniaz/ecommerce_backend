<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_supplier_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('entities')->cascadeOnDelete();
            $table->string('supplier_sku', 100);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            
            $table->unique(['supplier_id', 'supplier_sku'], 'unique_supplier_sku');
            $table->index(['product_id', 'supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_supplier_codes');
    }
};