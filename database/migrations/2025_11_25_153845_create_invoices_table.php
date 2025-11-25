<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            // Referencias
            $table->foreignId('sale_id')->nullable()->constrained('sales')->onDelete('set null');
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null');
            $table->foreignId('customer_id')->constrained('entities')->onDelete('restrict');

            // Datos del comprobante
            $table->enum('invoice_type', ['01', '03', '07', '08'])->comment('01=Factura, 03=Boleta, 07=NC, 08=ND');
            $table->string('series', 10); // Ejemplo: B001, F001
            $table->string('number', 20); // Ejemplo: 00000123
            $table->date('issue_date');

            // Montos
            $table->string('currency', 3)->default('PEN');
            $table->decimal('exchange_rate', 10, 4)->default(1.0000);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('tax', 12, 2); // IGV
            $table->decimal('total', 12, 2);

            // Estado SUNAT
            $table->enum('sunat_status', ['pending', 'sent', 'accepted', 'rejected', 'voided'])->default('pending');
            $table->string('sunat_response_code', 10)->nullable();
            $table->text('sunat_response_message')->nullable();

            // Archivos generados
            $table->string('xml_path', 250)->nullable();
            $table->string('pdf_path', 250)->nullable();
            $table->string('cdr_path', 250)->nullable(); // Constancia de Recepción

            // Seguridad
            $table->string('hash', 100)->nullable(); // Hash del XML
            $table->text('qr_code')->nullable(); // Código QR

            // Fechas
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            // Índices
            $table->unique(['series', 'number']);
            $table->index(['customer_id', 'issue_date']);
            $table->index('sunat_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
