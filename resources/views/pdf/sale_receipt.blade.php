<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Recibo de Venta - {{ $sale->sale_number }}</title>
    <style>
        body {
            font-family: 'Helvetica', sans-serif;
            font-size: 12px;
            color: #333;
            margin: 0;
            padding: 0;
        }

        .container {
            width: 100%;
            margin: 0 auto;
            padding: 20px;
        }

        .header,
        .footer {
            text-align: center;
        }

        .header img {
            max-width: 150px;
            max-height: 80px;
            margin-bottom: 10px;
        }

        .header h1 {
            margin: 0;
            font-size: 22px;
        }

        .header p {
            margin: 2px 0;
            font-size: 12px;
        }

        .details {
            margin: 20px 0;
            padding: 10px;
            border-top: 1px solid #eee;
            border-bottom: 1px solid #eee;
        }

        .details .left {
            float: left;
            width: 60%;
        }

        .details .right {
            float: right;
            width: 40%;
            text-align: right;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .items-table th,
        .items-table td {
            border-bottom: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }

        .items-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }

        .items-table .text-right {
            text-align: right;
        }

        .totals {
            float: right;
            width: 40%;
            margin-top: 20px;
        }

        .totals table {
            width: 100%;
        }

        .totals td {
            padding: 5px 0;
        }

        .totals .label {
            font-weight: bold;
        }

        .totals .amount {
            text-align: right;
        }

        .footer {
            margin-top: 40px;
            font-size: 10px;
            color: #777;
        }

        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            @if ($business->logo_path)
                <img src="{{ storage_path('app/public/' . $business->logo_path) }}" alt="Logo">
            @endif
            <h1>{{ $business->name }}</h1>
            <p>{{ $business->address }}</p>
            <p>Tel: {{ $business->phone }} | Email: {{ $business->email }}</p>
        </div>

        <div class="details clearfix">
            <div class="left">
                <strong>Cliente:</strong> {{ $sale->customer_name }}<br>
                <strong>Atendido por:</strong> {{ $sale->creator->full_name }}
            </div>
            <div class="right">
                <strong>Recibo No:</strong> {{ $sale->sale_number }}<br>
                <strong>Fecha:</strong> {{ \Carbon\Carbon::parse($sale->created_at)->format('d/m/Y h:i A') }}
            </div>
        </div>

        <h3>Detalle de la Venta</h3>
        <table class="items-table">
            <thead>
                <tr>
                    <th>Producto / Servicio</th>
                    <th class="text-right">Cantidad</th>
                    <th class="text-right">Precio Unit.</th>
                    <th class="text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sale->items as $item)
                    <tr>
                        <td>{{ $item->item_name }}</td>
                        <td class="text-right">{{ $item->quantity }}</td>
                        <td class="text-right">{{ number_format($item->unit_price, 2) }}</td>
                        <td class="text-right">{{ number_format($item->total_price, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals clearfix">
            <table>
                <tr>
                    <td class="label">Subtotal:</td>
                    <td class="amount">{{ number_format($sale->total_amount, 2) }} {{ $business->currency }}</td>
                </tr>
                <tr>
                    <td class="label">IGV (18%):</td>
                    <td class="amount">{{ number_format($sale->total_amount * 0.18, 2) }} {{ $business->currency }}
                    </td>
                </tr>
                <tr>
                    <td class="label"><strong>Total:</strong></td>
                    <td class="amount"><strong>{{ number_format($sale->total_amount, 2) }}
                            {{ $business->currency }}</strong></td>
                </tr>
            </table>
        </div>

        <div class="clearfix"></div>

        <div class="footer">
            <p>Método de Pago: {{ ucfirst($sale->payment_method) }}</p>
            <p><strong>¡Gracias por su compra!</strong></p>
        </div>
    </div>
</body>

</html>
