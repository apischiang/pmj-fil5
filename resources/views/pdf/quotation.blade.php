<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Quotation {{ $record->quotation_number }}</title>
    <style>
        body { font-family: sans-serif; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .header { margin-bottom: 30px; }
        .totals { margin-top: 20px; text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Quotation</h1>
        <p><strong>Number:</strong> {{ $record->quotation_number }}</p>
        <p><strong>Date:</strong> {{ $record->date->format('d/m/Y') }}</p>
        <p><strong>To:</strong> {{ $record->customer->company_name }}<br>
           {{ $record->customer->name }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>Description</th>
                <th>Qty</th>
                <th>Price</th>
                <th>Disc.</th>
                <th>VAT %</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($record->items as $item)
            <tr>
                <td>{{ $item->item_name }}</td>
                <td>{{ $item->description }}</td>
                <td>{{ $item->quantity }}</td>
                <td>{{ number_format($item->unit_price, 2) }}</td>
                <td>{{ number_format($item->discount, 2) }}</td>
                <td>{{ $item->vat_rate }}%</td>
                <td>{{ number_format($item->amount, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <p>Subtotal: {{ number_format($record->subtotal, 2) }}</p>
        <p>Discount: {{ number_format($record->discount_amount, 2) }}</p>
        <p>Tax: {{ number_format($record->tax_amount, 2) }}</p>
        <h3>Grand Total: {{ number_format($record->grand_total, 2) }}</h3>
    </div>
</body>
</html>
