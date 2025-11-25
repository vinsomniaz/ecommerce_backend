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
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();

            // Relación con usuario vendedor
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->onUpdate('restrict')
                ->onDelete('set null')
                ->comment('Vendedor que genera la cotización');

            // Relación con cliente
            $table->foreignId('customer_id')
                ->constrained('entities')
                ->onUpdate('restrict')
                ->onDelete('restrict')
                ->comment('Cliente asociado a la cotización');

            // Relación con almacén
            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->onUpdate('restrict')
                ->onDelete('restrict')
                ->comment('Almacén de origen');

            // Relación con cupón de descuento
            $table->foreignId('coupon_id')
                ->nullable()
                ->constrained('coupons')
                ->onUpdate('restrict')
                ->onDelete('set null');

            // Código único de cotización
            $table->string('quotation_code', 50)->unique();

            // Información básica
            $table->date('quotation_date');
            $table->date('valid_until')->comment('Fecha de vencimiento de la cotización');

            // Estado de la cotización
            $table->enum('status', [
                'draft',        // Borrador
                'sent',         // Enviada
                'accepted',     // Aceptada
                'rejected',     // Rechazada
                'expired',      // Expirada
                'converted'     // Convertida a venta
            ])->default('draft');

            // Moneda y tipo de cambio
            $table->string('currency', 3)->default('PEN');
            $table->decimal('exchange_rate', 10, 4)->default(1.0000);

            // Cálculos monetarios
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount', 10, 2)->default(0.00);
            $table->decimal('coupon_discount', 10, 2)->nullable();
            $table->decimal('tax', 12, 2)->comment('IGV');
            $table->decimal('shipping_cost', 10, 2)->default(0.00)->comment('Costo de envío');
            $table->decimal('packaging_cost', 10, 2)->default(0.00)->comment('Costo de embalaje');
            $table->decimal('assembly_cost', 10, 2)->default(0.00)->comment('Servicio de ensamble profesional');
            $table->decimal('total', 12, 2);

            // Márgenes de ganancia calculados
            $table->decimal('total_margin', 12, 2)->nullable()->comment('Margen total de ganancia');
            $table->decimal('margin_percentage', 5, 2)->nullable()->comment('Porcentaje de margen');

            // Comisión del vendedor
            $table->decimal('commission_amount', 10, 2)->default(0.00);
            $table->decimal('commission_percentage', 5, 2)->default(0.00);
            $table->boolean('commission_paid')->default(false);

            // Información del cliente en la cotización
            $table->string('customer_name', 200);
            $table->string('customer_document', 20);
            $table->string('customer_email', 100)->nullable();
            $table->string('customer_phone', 20)->nullable();

            // Dirección de envío
            $table->text('shipping_address')->nullable();
            $table->char('shipping_ubigeo', 6)->nullable();
            $table->string('shipping_reference', 200)->nullable();

            // Observaciones y notas
            $table->text('observations')->nullable();
            $table->text('internal_notes')->nullable()->comment('Notas internas no visibles para el cliente');
            $table->text('terms_conditions')->nullable()->comment('Términos y condiciones específicos');

            // 🎯 CONVERSIÓN A VENTA (sin foreign key por ahora)
            $table->unsignedBigInteger('converted_sale_id')
                ->nullable()
                ->comment('Venta generada desde esta cotización');

            $table->timestamp('converted_at')->nullable();

            // Archivos generados
            $table->string('pdf_path', 250)->nullable();

            // Control de envío
            $table->timestamp('sent_at')->nullable();
            $table->string('sent_to_email', 100)->nullable();

            // Auditoría
            $table->timestamp('registered_at')->useCurrent();
            $table->timestamps();
            $table->softDeletes();

            // Índices para optimización
            $table->index('quotation_code');
            $table->index('customer_id');
            $table->index('user_id');
            $table->index('status');
            $table->index('quotation_date');
            $table->index('valid_until');
            $table->index('converted_sale_id'); // Índice manual
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
