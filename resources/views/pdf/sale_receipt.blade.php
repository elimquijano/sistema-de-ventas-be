<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Boleta de Venta - {{ $sale->sale_number }}</title>
    <style>
            @page {
                margin: 10px;
            }
    
            body {
                font-family: 'Inter', 'Helvetica', sans-serif;
                font-size: 10px;
                color: #333;
                margin: 0;
                padding: 0;
            }
    
            .receipt-container {
                width: 100%;
            }
    
            .header {
                text-align: center;
                margin-bottom: 20px;
            }
    
            .header img {
                max-width: 120px;
                max-height: 60px;
                margin-bottom: 10px;
            }
    
            .header h1 {
                margin: 0;
                font-size: 16px;
                font-weight: 700;
                color: #2c5282; /* Un azul corporativo */
            }
    
            .header p {
                margin: 2px 0;
                font-size: 9px;
                line-height: 1.4;
            }
    
            .receipt-title {
                text-align: center;
                font-size: 14px;
                font-weight: 700;
                margin-bottom: 15px;
                padding-bottom: 5px;
                border-bottom: 1px dashed #888;
            }
            
            .details, .customer {
                margin-bottom: 15px;
                font-size: 10px;
            }
    
            .details table, .customer table {
                width: 100%;
                border-collapse: collapse;
            }
            
            .details td, .customer td {
                padding: 1px 0;
            }
            
            .details .label, .customer .label {
                font-weight: 600;
                color: #555;
                width: 90px;
            }
    
            .items-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 15px;
                font-family: 'Menlo', 'Courier New', monospace;
            }
    
            .items-table thead th {
                font-weight: 700;
                text-align: left;
                padding-bottom: 8px;
                border-bottom: 1px solid #000;
            }
    
            .items-table tbody td {
                padding: 8px 0;
                border-bottom: 1px dashed #ccc;
            }
            
            .items-table .item-name {
                font-weight: 600;
            }
            
            .items-table .item-details {
                font-size: 9px;
                color: #444;
            }
    
            .items-table .text-right {
                text-align: right;
            }
    
            .totals {
                margin-top: 15px;
                font-size: 11px;
            }
            
            .totals table {
                width: 100%;
            }
    
            .totals td {
                padding: 3px 0;
            }
            
            .totals .label {
                font-weight: 600;
            }
            
            .totals .amount {
                text-align: right;
            }
            
            .totals .grand-total .label,
            .totals .grand-total .amount {
                font-weight: 700;
                font-size: 13px;
            }
    
            .footer {
                text-align: center;
                margin-top: 20px;
                font-size: 9px;
                color: #777;
            }
    
            .footer .qr-code {
                margin-top: 15px;
            }
    
            .footer .qr-code img {
                width: 100px;
                height: 100px;
            }
        </style>
    </head>
    
    <body>
        <div class="receipt-container">
            <div class="header">
                @if ($business->logo_path)
                    <img src="{{ storage_path('app/public/' . $business->logo_path) }}" alt="Logo">
                @endif
                <h1>{{ $business->name }}</h1>
                <p>{{ $business->address }}</p>
                <p>Tel: {{ $business->phone }} | Email: {{ $business->email }}</p>
            </div>
    
            <div class="receipt-title">
                BOLETA DE VENTA ELECTRÓNICA
            </div>
    
            <div class="details">
                <table>
                    <tr>
                        <td class="label">Recibo No:</td>
                        <td>{{ $sale->sale_number }}</td>
                    </tr>
                    <tr>
                        <td class="label">Fecha:</td>
                        <td>{{ \Carbon\Carbon::parse($sale->created_at)->format('d/m/Y h:i A') }}</td>
                    </tr>
                    <tr>
                        <td class="label">Atendido por:</td>
                        <td>{{ $sale->creator->full_name }}</td>
                    </tr>
                </table>
            </div>
    
            <div class="customer">
                <table>
                    <tr>
                        <td class="label">Cliente:</td>
                        <td>{{ $sale->customer_name }}</td>
                    </tr>
                </table>
            </div>
    
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Descripción</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sale->items as $item)
                        <tr>
                            <td colspan="2">
                                <div class="item-name">{{ $item->item_name }}</div>
                                <div class="item-details">
                                    {{ $item->quantity }} x {{ number_format($item->unit_price, 2) }}
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" class="text-right">{{ number_format($item->total_price, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
    
            <div class="totals">
                <table>
                    <tr>
                        <td class="label">Subtotal:</td>
                        <td class="amount">{{ number_format($sale->total_amount / 1.18, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="label">IGV (18%):</td>
                        <td class="amount">{{ number_format($sale->total_amount - ($sale->total_amount / 1.18), 2) }}</td>
                    </tr>
                    <tr class="grand-total">
                        <td class="label">TOTAL:</td>
                        <td class="amount">{{ number_format($sale->total_amount, 2) }} {{ $business->currency }}</td>
                    </tr>
                </table>
            </div>
    
            <div class="footer">
                @php
                    $isCredit = $sale->payments->contains('payment_method', 'credit');
                    $paymentType = $isCredit ? 'Crédito' : 'Al contado';
                @endphp
                <p>Método de Pago: {{ $paymentType }}</p>
                <p><strong>¡Gracias por su compra!</strong></p>
                
                <div class="qr-code">
                    <p>Representación Impresa de la Boleta de Venta Electrónica</p>
                    @if ($sale->uuid)
                        @php
                            $publicReceiptUrl = route('receipt.public', ['uuid' => $sale->uuid]);
                        @endphp
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data={{ urlencode($publicReceiptUrl) }}" alt="QR Code">
                        <p>Puede escanear este código para ver o descargar su boleta.</p>
                    @endif
                </div>
            </div>
        </div>
    </body>
    </html>
