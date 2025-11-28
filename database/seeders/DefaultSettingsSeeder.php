<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DefaultSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // Cotizaciones
            ['group' => 'quotations', 'key' => 'default_validity_days', 'value' => '15', 'type' => 'integer', 'description' => 'Días de validez por defecto para cotizaciones'],
            ['group' => 'quotations', 'key' => 'min_validity_days', 'value' => '7', 'type' => 'integer', 'description' => 'Mínimo de días de validez permitido'],
            ['group' => 'quotations', 'key' => 'max_validity_days', 'value' => '90', 'type' => 'integer', 'description' => 'Máximo de días de validez permitido'],
            ['group' => 'quotations', 'key' => 'auto_send_pdf', 'value' => 'false', 'type' => 'boolean', 'description' => 'Enviar PDF automáticamente al crear cotización'],
            
            // Márgenes
            ['group' => 'margins', 'key' => 'default_margin_percentage', 'value' => '25.00', 'type' => 'decimal', 'description' => 'Margen por defecto (%)'],
            ['group' => 'margins', 'key' => 'min_margin_percentage', 'value' => '10.00', 'type' => 'decimal', 'description' => 'Margen mínimo permitido (%)'],
            ['group' => 'margins', 'key' => 'alert_low_margin', 'value' => 'true', 'type' => 'boolean', 'description' => 'Alertar cuando el margen es bajo'],
            
            // Comisiones
            ['group' => 'commissions', 'key' => 'calculate_on', 'value' => 'margin', 'type' => 'string', 'description' => 'Base de cálculo: margin, subtotal, total'],
            ['group' => 'commissions', 'key' => 'default_percentage', 'value' => '3.00', 'type' => 'decimal', 'description' => 'Comisión por defecto para nuevos vendedores (%)'],
            
            // Moneda
            ['group' => 'currency', 'key' => 'default_currency', 'value' => 'PEN', 'type' => 'string', 'description' => 'Moneda por defecto'],
            ['group' => 'currency', 'key' => 'default_exchange_rate', 'value' => '3.75', 'type' => 'decimal', 'description' => 'Tipo de cambio USD->PEN por defecto'],
            
            // WhatsApp
            ['group' => 'whatsapp', 'key' => 'method', 'value' => 'wa_me', 'type' => 'string', 'description' => 'Método de envío: wa_me o cloud_api'],
            ['group' => 'whatsapp', 'key' => 'cloud_api_token', 'value' => '', 'type' => 'string', 'description' => 'Token de WhatsApp Cloud API'],
            ['group' => 'whatsapp', 'key' => 'phone_number_id', 'value' => '', 'type' => 'string', 'description' => 'Phone Number ID de WhatsApp Business'],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->insertOrIgnore([
                'group' => $setting['group'],
                'key' => $setting['key'],
                'value' => $setting['value'],
                'type' => $setting['type'],
                'description' => $setting['description'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}