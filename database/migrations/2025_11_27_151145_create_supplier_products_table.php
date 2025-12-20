<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tabla para visualizar opciones rápidas de compra por distribuidor
     */
    public function up(): void
    {
        Schema::create('supplier_products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('supplier_id')
                ->constrained('entities')
                ->onUpdate('restrict')
                ->onDelete('cascade');

            // OJO: ahora puede ser null si aún no se vinculó al catálogo interno
            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products')
                ->onUpdate('restrict')
                ->onDelete('cascade');

            // Identificador estable del item del proveedor (viene en tu payload)
            $table->string('supplier_sku', 160);

            // Snapshot del item (viene del scraper)
            $table->string('supplier_name')->nullable();
            $table->string('brand')->nullable();
            $table->string('location')->nullable();
            $table->text('source_url')->nullable();
            $table->text('image_url')->nullable();

            // Categorías desde el scraper
            $table->string('supplier_category')->nullable();
            $table->string('category_suggested')->nullable();

            // Override manual de categoría (cuando el scraper sugiere mal)
            $table->foreignId('category_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete()
                ->comment('Override manual cuando category_suggested es incorrecto');

            // Precios (pueden venir null)
            $table->decimal('purchase_price', 10, 2)->nullable()->comment('Precio de compra al proveedor (supplier_price)');
            $table->decimal('sale_price', 10, 2)->nullable()->comment('Precio sugerido de venta (price_suggested)');

            $table->string('currency', 3)->default('PEN');

            // Stock (tu payload trae stock_qty + stock_text)
            $table->integer('available_stock')->default(0);
            $table->string('stock_text')->nullable();
            $table->boolean('is_available')->default(false);

            // Sync tracking
            $table->timestamp('last_seen_at')->nullable();
            $table->foreignId('last_import_id')
                ->nullable()
                ->constrained('supplier_imports')
                ->nullOnDelete();

            // Tiempos de entrega y compras
            $table->integer('delivery_days')->nullable();
            $table->integer('min_order_quantity')->default(1);

            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamp('price_updated_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            // Índices
            $table->unique(['supplier_id', 'supplier_sku']);     // clave real del scraper
            $table->index(['supplier_id', 'is_active']);
            $table->index(['product_id', 'is_active', 'priority']);
            $table->index(['supplier_id', 'last_seen_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_products');
    }
};
