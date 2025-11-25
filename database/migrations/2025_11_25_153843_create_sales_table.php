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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->nullable()
                ->constrained('orders')
                ->onUpdate('restrict')
                ->onDelete('set null');

            $table->foreignId('customer_id')
                ->constrained('entities')
                ->onUpdate('restrict')
                ->onDelete('restrict');

            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->onUpdate('restrict')
                ->onDelete('restrict');

            // Document Info
            $table->date('date');

            // Currency
            $table->string('currency', 3)->default('PEN');
            $table->decimal('exchange_rate', 10, 4)->default(1.0000);

            // Amounts
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax', 12, 2);
            $table->decimal('total', 12, 2);

            // Payment Status
            $table->enum('payment_status', ['pending', 'partial', 'paid'])
                ->default('pending');

            // User
            $table->foreignId('user_id')
                ->constrained('users')
                ->onUpdate('restrict')
                ->onDelete('restrict');

            // Timestamps
            $table->timestamp('registered_at')->useCurrent();
            $table->timestamps();

            // Indexes
            $table->index(['customer_id', 'date']);
            $table->index('payment_status');
            $table->index('date');
            // $table->index('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
