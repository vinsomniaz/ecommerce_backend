<?php
// database/migrations/xxxx_create_purchase_batches_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_batches', function (Blueprint $table) {
            $table->id();

            // ✅ purchase_id NULLABLE (puede ser NULL si es lote manual)
            $table->foreignId('purchase_id')->nullable()->constrained('purchases')->onDelete('cascade');

            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('warehouse_id')->constrained()->onDelete('cascade');

            $table->string('batch_code', 50)->unique();
            $table->integer('quantity_purchased');
            $table->integer('quantity_available');
            $table->decimal('purchase_price', 10, 2);
            $table->decimal('distribution_price', 10, 2);
            $table->date('purchase_date');
            $table->date('expiry_date')->nullable();
            $table->enum('status', ['active', 'inactive', 'depleted'])->default('active');

            $table->timestamps();

            $table->index(['product_id', 'warehouse_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_batches');
    }
};
