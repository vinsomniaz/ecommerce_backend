<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispatch_guides', function (Blueprint $table) {
            $table->id();

            // Referencias opcionales
            $table->foreignId('sale_id')->nullable()->constrained('sales')->onDelete('set null');
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null');

            // Datos de la guía
            $table->string('series', 10); // Ejemplo: T001
            $table->string('number', 20);
            $table->date('issue_date');
            $table->dateTime('transfer_start_date');

            // Motivo de traslado (Catálogo 20 SUNAT)
            $table->enum('transfer_reason', [
                '01', // Venta
                '02', // Compra
                '03', // Venta con entrega a terceros
                '04', // Traslado entre establecimientos de la misma empresa
                '13', // Otros
                '14'  // Venta sujeta a confirmación del comprador
            ])->comment('Catálogo 20 SUNAT');

            // Entidades
            $table->foreignId('shipper_id')->constrained('entities')->onDelete('restrict')->comment('Remitente');
            $table->foreignId('recipient_id')->constrained('entities')->onDelete('restrict')->comment('Destinatario');

            // Origen y destino
            $table->foreignId('origin_warehouse_id')->constrained('warehouses')->onDelete('restrict');
            $table->string('destination_address', 250);
            $table->char('destination_ubigeo', 6);
            $table->foreign('destination_ubigeo')->references('ubigeo')->on('ubigeos')->onDelete('restrict');

            // Transporte
            $table->enum('transport_mode', ['01', '02'])->comment('01=Público, 02=Privado');
            $table->string('vehicle_plate', 10)->nullable();
            $table->string('driver_doc_type', 2)->nullable(); // 1=DNI
            $table->string('driver_doc_number', 20)->nullable();
            $table->string('driver_name', 200)->nullable();

            // Peso total
            $table->decimal('total_weight', 10, 2);

            // Estado SUNAT
            $table->enum('sunat_status', ['pending', 'sent', 'accepted', 'rejected'])->default('pending');
            $table->text('sunat_response_message')->nullable();

            // Archivos
            $table->string('xml_path', 250)->nullable();
            $table->string('pdf_path', 250)->nullable();
            $table->string('cdr_path', 250)->nullable();

            $table->timestamps();

            // Índices
            $table->unique(['series', 'number']);
            $table->index(['origin_warehouse_id', 'transfer_start_date']);
            $table->index('sunat_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatch_guides');
    }
};
