<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Cotización {{ $quotation->quotation_code }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #333;
        }

        .container {
            padding: 20px;
        }

        /* Header */
        .header {
            display: table;
            width: 100%;
            margin-bottom: 25px;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 15px;
        }

        .header-left {
            display: table-cell;
            width: 60%;
            vertical-align: top;
        }

        .header-right {
            display: table-cell;
            width: 40%;
            text-align: right;
            vertical-align: top;
        }

        .company-name {
            font-size: 22px;
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 5px;
        }

        .company-info {
            font-size: 10px;
            color: #666;
        }

        .quotation-title {
            font-size: 18px;
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 8px;
        }

        .quotation-code {
            font-size: 14px;
            color: #059669;
            font-weight: bold;
        }

        .quotation-date {
            font-size: 10px;
            color: #666;
            margin-top: 5px;
        }

        /* Info boxes */
        .info-boxes {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }

        .info-box {
            display: table-cell;
            width: 50%;
            padding: 10px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            vertical-align: top;
        }

        .info-box:first-child {
            border-right: none;
        }

        .info-box-title {
            font-size: 10px;
            font-weight: bold;
            color: #1e40af;
            text-transform: uppercase;
            margin-bottom: 8px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 5px;
        }

        .info-row {
            margin-bottom: 3px;
        }

        .info-label {
            font-weight: bold;
            color: #666;
        }

        /* Products table */
        .products-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .products-table th {
            background: #1e40af;
            color: white;
            padding: 8px 5px;
            font-size: 10px;
            text-align: left;
            font-weight: bold;
        }

        .products-table th:last-child,
        .products-table td:last-child {
            text-align: right;
        }

        .products-table td {
            padding: 8px 5px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 10px;
        }

        .products-table tr:nth-child(even) {
            background: #f8fafc;
        }

        .product-name {
            font-weight: bold;
            color: #1e293b;
        }

        .product-sku {
            font-size: 9px;
            color: #666;
        }

        .product-source {
            font-size: 8px;
            padding: 2px 5px;
            border-radius: 3px;
            display: inline-block;
        }

        .source-warehouse {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .source-supplier {
            background: #fef3c7;
            color: #b45309;
        }

        /* Totals */
        .totals-section {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }

        .totals-notes {
            display: table-cell;
            width: 50%;
            padding-right: 20px;
            vertical-align: top;
        }

        .totals-box {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }

        .totals-table td {
            padding: 6px 10px;
            border-bottom: 1px solid #e2e8f0;
        }

        .totals-table td:last-child {
            text-align: right;
            font-weight: bold;
        }

        .total-row {
            background: #1e40af;
            color: white;
        }

        .total-row td {
            font-size: 14px;
            border: none;
            padding: 10px;
        }

        /* Notes */
        .notes-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 10px;
            font-size: 10px;
        }

        .notes-title {
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 5px;
        }

        /* Footer */
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
            font-size: 9px;
            color: #666;
            text-align: center;
        }

        .validity-notice {
            background: #fef3c7;
            border: 1px solid #fcd34d;
            padding: 8px;
            text-align: center;
            font-size: 10px;
            color: #b45309;
            margin-bottom: 15px;
        }

        .currency {
            font-size: 9px;
            color: #666;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <div class="company-name">{{ config('app.name', 'DataStore') }}</div>
                <div class="company-info">
                    {{-- Aquí puedes agregar info de la empresa --}}
                    RUC: 20XXXXXXXXX<br>
                    Dirección: Lima, Perú<br>
                    Teléfono: +51 XXX XXX XXX
                </div>
            </div>
            <div class="header-right">
                <div class="quotation-title">COTIZACIÓN</div>
                <div class="quotation-code">{{ $quotation->quotation_code }}</div>
                <div class="quotation-date">
                    Fecha: {{ $quotation->quotation_date->format('d/m/Y') }}<br>
                    Válida hasta: {{ $quotation->valid_until->format('d/m/Y') }}
                </div>
            </div>
        </div>

        <!-- Validity Notice -->
        <div class="validity-notice">
            ⚠️ Esta cotización es válida hasta el {{ $quotation->valid_until->format('d/m/Y') }}.
            Los precios están sujetos a disponibilidad de stock.
        </div>

        <!-- Client and Seller Info -->
        <div class="info-boxes">
            <div class="info-box">
                <div class="info-box-title">📋 Datos del Cliente</div>
                <div class="info-row">
                    <span class="info-label">Nombre:</span> {{ $quotation->customer_name }}
                </div>
                @if($quotation->customer_document)
                <div class="info-row">
                    <span class="info-label">Documento:</span> {{ $quotation->customer_document }}
                </div>
                @endif
                @if($quotation->customer_email)
                <div class="info-row">
                    <span class="info-label">Email:</span> {{ $quotation->customer_email }}
                </div>
                @endif
                @if($quotation->customer_phone)
                <div class="info-row">
                    <span class="info-label">Teléfono:</span> {{ $quotation->customer_phone }}
                </div>
                @endif
            </div>
            <div class="info-box">
                <div class="info-box-title">👤 Ejecutivo de Ventas</div>
                <div class="info-row">
                    <span class="info-label">Nombre:</span> {{ $quotation->user?->full_name ?? 'N/A' }}
                </div>
                @if($quotation->user?->email)
                <div class="info-row">
                    <span class="info-label">Email:</span> {{ $quotation->user->email }}
                </div>
                @endif
                @if($quotation->warehouse)
                <div class="info-row">
                    <span class="info-label">Almacén:</span> {{ $quotation->warehouse->name }}
                </div>
                @endif
            </div>
        </div>

        <!-- Products Table -->
        <table class="products-table">
            <thead>
                <tr>
                    <th style="width: 5%">#</th>
                    <th style="width: 40%">Producto</th>
                    <th style="width: 12%">Origen</th>
                    <th style="width: 10%">Cant.</th>
                    <th style="width: 15%">P. Unit.</th>
                    <th style="width: 18%">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($quotation->details as $index => $detail)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>
                        <div class="product-name">{{ $detail->product_name }}</div>
                        @if($detail->product_sku)
                        <div class="product-sku">SKU: {{ $detail->product_sku }}</div>
                        @endif
                        @if($detail->product_brand)
                        <div class="product-sku">{{ $detail->product_brand }}</div>
                        @endif
                    </td>
                    <td>
                        @if($detail->source_type === 'warehouse')
                        <span class="product-source source-warehouse">📦 Stock</span>
                        @else
                        <span class="product-source source-supplier">🚚 Proveedor</span>
                        @endif
                    </td>
                    <td>{{ $detail->quantity }}</td>
                    <td>{{ $quotation->currency }} {{ number_format($detail->unit_price, 2) }}</td>
                    <td>{{ $quotation->currency }} {{ number_format($detail->subtotal, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Totals and Notes -->
        <div class="totals-section">
            <div class="totals-notes">
                @if($quotation->observations)
                <div class="notes-box">
                    <div class="notes-title">📝 Observaciones</div>
                    {{ $quotation->observations }}
                </div>
                @endif

                @if($quotation->terms_conditions)
                <div class="notes-box" style="margin-top: 10px;">
                    <div class="notes-title">📜 Términos y Condiciones</div>
                    {{ $quotation->terms_conditions }}
                </div>
                @endif
            </div>

            <div class="totals-box">
                <table class="totals-table">
                    <tr>
                        <td>Subtotal</td>
                        <td>{{ $quotation->currency }} {{ number_format($quotation->subtotal, 2) }}</td>
                    </tr>
                    @if($quotation->discount > 0)
                    <tr>
                        <td>Descuento</td>
                        <td style="color: #dc2626;">- {{ $quotation->currency }} {{ number_format($quotation->discount, 2) }}</td>
                    </tr>
                    @endif
                    @if($quotation->shipping_cost > 0)
                    <tr>
                        <td>Envío</td>
                        <td>{{ $quotation->currency }} {{ number_format($quotation->shipping_cost, 2) }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td>IGV (18%)</td>
                        <td>{{ $quotation->currency }} {{ number_format($quotation->tax, 2) }}</td>
                    </tr>
                    <tr class="total-row">
                        <td>TOTAL</td>
                        <td>{{ $quotation->currency }} {{ number_format($quotation->total, 2) }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            Cotización generada el {{ now()->format('d/m/Y H:i') }} |
            {{ config('app.name') }} - Sistema de Gestión
            <br>
            <small>Este documento no es un comprobante de pago.</small>
        </div>
    </div>
</body>

</html>