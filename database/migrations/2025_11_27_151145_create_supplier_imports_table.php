<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('entities')->cascadeOnDelete();

            // Payload completo del scraper
            $table->json('raw_data')->comment('Payload completo del scraper');

            // Metadata del scraping (viene en tu JSON)
            $table->timestamp('fetched_at')->nullable();
            $table->decimal('margin_percent', 6, 2)->nullable();
            $table->json('source_totals')->nullable();
            $table->integer('items_count')->default(0);

            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');

            // Métricas internas del procesamiento
            $table->integer('total_products')->default(0);
            $table->integer('processed_products')->default(0);
            $table->integer('updated_products')->default(0);
            $table->integer('new_products')->default(0);

            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->index(['supplier_id', 'status']);
            $table->index(['supplier_id', 'fetched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_imports');
    }
};