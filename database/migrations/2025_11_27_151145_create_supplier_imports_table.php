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
            $table->longText('raw_data')->comment('JSON con datos del scraper');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->integer('total_products')->default(0);
            $table->integer('processed_products')->default(0);
            $table->integer('updated_products')->default(0);
            $table->integer('new_products')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            
            $table->index(['supplier_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_imports');
    }
};