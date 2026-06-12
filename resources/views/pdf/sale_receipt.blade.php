<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Boleta de Venta - {{ $sale->sale_number }}</title>
    <style>
        @page {
            margin: 8px;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 9px;
            color: #1a202c;
            margin: 0;
            padding: 0;
            line-height: 1.2;
        }

        .receipt-container {
            width: 100%;
        }

        /* Header Styles */
        .business-header {
            text-align: center;
            margin-bottom: 12px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 8px;
        }

        .logo-container {
            margin-bottom: 6px;
        }

        .logo-container img {
            max-width: 100px;
            max-height: 50px;
            object-fit: contain;
        }

        .business-name {
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0 0 3px 0;
            color: #2d3748;
        }

        .business-info {
            font-size: 8.5px;
            color: #4a5568;
            margin: 0;
        }

        /* Document Type Box */
        .document-box {
            border: 1.5px solid #2d3748;
            text-align: center;
            padding: 8px;
            margin: 10px 0;
            border-radius: 4px;
            background-color: #f7fafc;
        }

        .document-box .ruc {
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 3px;
        }

        .document-box .type {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            display: block;
            margin: 4px 0;
        }

        .document-box .number {
            font-size: 11px;
            font-weight: bold;
        }

        /* Section details */
        .section {
            margin-bottom: 10px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table td {
            padding: 2px 0;
            vertical-align: top;
        }

        .info-table .label {
            font-weight: bold;
            width: 55px;
            color: #4a5568;
        }

        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }

        .items-table thead th {
            font-weight: bold;
            text-align: left;
            padding: 6px 0;
            border-top: 1px solid #2d3748;
            border-bottom: 1px solid #2d3748;
            font-size: 8.5px;
            text-transform: uppercase;
        }

        .items-table tbody td {
            padding: 6px 0;
            border-bottom: 1px solid #edf2f7;
            vertical-align: top;
        }

        .items-table .qty {
            width: 25px;
            text-align: center;
        }

        .items-table .description {
            padding-left: 5px;
        }

        .items-table .price {
            text-align: right;
            width: 50px;
        }

        .items-table .item-name {
            font-weight: bold;
            display: block;
            margin-bottom: 1px;
        }

        .items-table .item-meta {
            font-size: 8px;
            color: #718096;
        }

        /* Totals Section */
        .totals-container {
            margin-top: 8px;
            border-top: 1px solid #2d3748;
            padding-top: 6px;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }

        .totals-table td {
            padding: 2px 0;
        }

        .totals-table .label {
            text-align: right;
            padding-right: 10px;
            color: #4a5568;
        }

        .totals-table .amount {
            text-align: right;
            font-weight: bold;
            width: 70px;
        }

        .totals-table .grand-total td {
            padding-top: 5px;
            font-size: 11px;
        }

        /* Footer */
        .footer {
            text-align: center;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px dashed #cbd5e0;
        }

        .payment-method {
            margin-bottom: 8px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 8.5px;
        }

        .qr-section {
            margin-top: 10px;
        }

        .qr-section img {
            width: 80px;
            height: 80px;
        }

        .qr-text {
            font-size: 7.5px;
            color: #718096;
            margin-top: 4px;
        }

        .thanks {
            font-weight: bold;
            margin-top: 10px;
            font-size: 9px;
        }
    </style>
</head>

<body>
    <div class="receipt-container">
        <!-- Header -->
        <div class="business-header">
            @if ($business->logo_path)
                <div class="logo-container">
                    <img src="{{ storage_path('app/public/' . $business->logo_path) }}" alt="Logo">
                </div>
            @endif
            <h1 class="business-name">{{ $business->name }}</h1>
            <p class="business-info">
                {{ $business->address }}<br>
                {{ $business->phone }} | {{ $business->email }}
            </p>
        </div>

        <!-- Document Type Box -->
        <div class="document-box">
            <div class="ruc">R.U.C. {{ $business->tax_id ?? '----------' }}</div>
            <div class="type">Boleta de Venta Electrónica</div>
            <div class="number">{{ $sale->sale_number }}</div>
        </div>

        <!-- Info Section -->
        <div class="section">
            <table class="info-table">
                <tr>
                    <td class="label">FECHA:</td>
                    <td>{{ \Carbon\Carbon::parse($sale->created_at)->format('d/m/Y h:i A') }}</td>
                </tr>
                <tr>
                    <td class="label">CLIENTE:</td>
                    <td>{{ $sale->customer_name }}</td>
                </tr>
                <tr>
                    <td class="label">VENDEDOR:</td>
                    <td>{{ $sale->creator->full_name }}</td>
                </tr>
            </table>
        </div>

        <!-- Items -->
        <table class="items-table">
            <thead>
                <tr>
                    <th class="qty">Cant</th>
                    <th class="description">Descripción</th>
                    <th class="price">Importe</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sale->items as $item)
                    <tr>
                        <td class="qty">{{ number_format($item->quantity, 0) }}</td>
                        <td class="description">
                            <span class="item-name">{{ $item->item_name }}</span>
                            <span class="item-meta">P. Unit: {{ number_format($item->unit_price, 2) }}</span>
                        </td>
                        <td class="price">{{ number_format($item->total_price, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Totals -->
        <div class="totals-container">
            <table class="totals-table">
                <tr>
                    <td class="label">OP. GRAVADA:</td>
                    <td class="amount">{{ $business->currency }} {{ number_format($sale->total_amount / 1.18, 2) }}</td>
                </tr>
                <tr>
                    <td class="label">I.G.V. (18%):</td>
                    <td class="amount">{{ $business->currency }} {{ number_format($sale->total_amount - ($sale->total_amount / 1.18), 2) }}</td>
                </tr>
                <tr class="grand-total">
                    <td class="label">TOTAL:</td>
                    <td class="amount">{{ $business->currency }} {{ number_format($sale->total_amount, 2) }}</td>
                </tr>
            </table>
        </div>

        <!-- Footer -->
        <div class="footer">
            @php
                $isCredit = $sale->payments->contains('payment_method', 'credit');
                $paymentType = $isCredit ? 'Venta al Crédito' : 'Venta al Contado';
            @endphp
            <div class="payment-method">Condición: {{ $paymentType }}</div>
            
            <div class="qr-section">
                @if ($sale->uuid)
                    @php
                        $publicReceiptUrl = route('receipt.public', ['uuid' => $sale->uuid]);
                    @endphp
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data={{ urlencode($publicReceiptUrl) }}" alt="QR Code">
                    <div class="qr-text">Representación impresa de la Boleta de Venta Electrónica.<br>Puede consultar este documento en nuestro portal.</div>
                @endif
            </div>

            <div class="thanks">¡GRACIAS POR SU PREFERENCIA!</div>
        </div>
    </div>
</body>

</html>
