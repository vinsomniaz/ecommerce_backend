<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_category_maps', function (Blueprint $table) {
            $table->id();

            $table->foreignId('supplier_id')
                ->constrained('entities')
                ->onUpdate('restrict')
                ->onDelete('cascade');

            // categoría tal cual llega del proveedor (ej: "almacenamiento")
            $table->string('supplier_category', 160);

            // categoría del ERP a la que se mapea (nullable hasta que lo configures)
            $table->foreignId('category_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();

            $table->boolean('is_active')->default(true);
            $table->decimal('confidence', 5, 2)->nullable();

            $table->timestamps();

            $table->unique(['supplier_id', 'supplier_category']);
            $table->index(['supplier_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_category_maps');
    }
};
