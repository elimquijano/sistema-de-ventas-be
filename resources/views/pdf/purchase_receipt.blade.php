<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Recibo de Compra</title>
    <style>
        body {
            font-family: 'Helvetica', sans-serif;
            font-size: 12px;
            color: #333;
        }

        .container {
            width: 100%;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
        }

        .header h2 {
            margin: 5px 0;
            font-size: 18px;
        }

        .details {
            margin-bottom: 20px;
            width: 100%;
        }

        .details,
        .details th,
        .details td {
            border: none;
        }

        .details .left {
            float: left;
            width: 50%;
        }

        .details .right {
            float: right;
            width: 50%;
            text-align: right;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .items-table th,
        .items-table td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }

        .items-table th {
            background-color: #f2f2f2;
        }

        .total {
            text-align: right;
            margin-top: 20px;
        }

        .total h3 {
            margin: 0;
            font-size: 16px;
        }

        .footer {
            text-align: center;
            margin-top: 30px;
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
            <h1>{{ $business->name }}</h1>
            <h2>Recibo de Compra</h2>
        </div>

        <div class="details clearfix">
            <div class="left">
                <strong>Proveedor:</strong> {{ $purchase->supplier_name ?? 'Varios' }}<br>
                <strong>Fecha de Compra:</strong> {{ \Carbon\Carbon::parse($purchase->purchase_date)->format('d/m/Y') }}
            </div>
            <div class="right">
                <strong>No. de Compra:</strong> {{ $purchase->purchase_number }}<br>
                <strong>Fecha de Emisión:</strong> {{ now()->format('d/m/Y') }}
            </div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Cantidad</th>
                    <th>Costo Unitario</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($purchase->items as $item)
                    <tr>
                        <td>{{ $item->product_name }}</td>
                        <td>{{ $item->quantity }}</td>
                        <td>{{ number_format($item->cost, 2) }} {{ $business->currency }}</td>
                        <td>{{ number_format($item->subtotal, 2) }} {{ $business->currency }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="total">
            <h3>Total: {{ number_format($purchase->total_amount, 2) }} {{ $business->currency }}</h3>
        </div>

        <div class="footer">
            <p>Este es un recibo generado automáticamente por el sistema.</p>
        </div>
    </div>
</body>

</html>
