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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('restrict');
            $table->enum('type', ['in', 'out', 'adjustment', 'transfer']);
            $table->integer('quantity');
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->string('reference_type', 50)->nullable(); // purchase, sale, adjustment
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->text('notes')->nullable();
            $table->timestamp('moved_at')->useCurrent();

            $table->index(['product_id', 'warehouse_id', 'moved_at']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
