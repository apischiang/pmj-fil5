@php
    $senderName = $record->creator?->name ?: 'Authorized Signatory';
    $senderEmail = $record->creator?->email ?: '-';
    $signatureTitle = 'Authorized Signatory';

    $items = collect($visibleItems ?? $record->items ?? [])->values();
    $figureItems = collect($imageFigures ?? [])->values();

    $firstPageRowLimit = 8;
    $firstPageItems = $items->take($firstPageRowLimit)->values();
    $blankRowCount = max($firstPageRowLimit - $firstPageItems->count(), 0);

    $totalPages = 1 + $figureItems->count();
    $currentPage = 1;

    $formatMoney = static fn ($amount): string => number_format((float) $amount, 2, '.', ',');
    $formatQuantity = static function ($quantity): string {
        $formatted = number_format((float) $quantity, 2, '.', ',');

        return preg_replace('/\.00$/', '', $formatted) ?: '0';
    };
    $formatDate = static fn ($date): string => $date?->format('d M Y') ?? '-';
    $formatAmountWords = static function ($amount): string {
        $wholeAmount = (int) round((float) $amount);
        $spelled = \Illuminate\Support\Number::spell($wholeAmount, locale: 'en');

        if (blank($spelled)) {
            return 'ZERO RUPIAH ONLY.';
        }

        return \Illuminate\Support\Str::upper($spelled).' RUPIAH ONLY.';
    };

    $customerName = $record->customer?->company_name ?: '-';
    $customerContact = $record->customer?->name ?: '-';
    $customerAddress = $record->customer?->address ?: '-';
    $totalQuantity = $items->sum(fn ($item) => (float) $item->quantity);
    $showTax = (float) ($record->tax_amount ?? 0) > 0;
    $secondaryAmountLabel = $showTax ? 'VAT' : 'Total Discount';
    $secondaryAmountValue = $showTax ? $record->tax_amount : $record->discount_amount;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation {{ $record->quotation_number }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

        @page {
            size: A4;
            margin: 0;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            color: #1f2937;
        }

        .pdf-page {
            width: 210mm;
            height: 297mm;
            margin: 0 auto;
            background: #ffffff;
            page-break-after: always;
            overflow: hidden;
        }

        .pdf-page:last-child {
            page-break-after: auto;
        }

        .pdf-shell {
            position: relative;
            height: 297mm;
            padding: 8mm 10mm 8mm;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
        }

        .page-main {
            position: relative;
            display: flex;
            flex: 1;
            flex-direction: column;
            min-height: 0;
        }

        .watermark {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
            overflow: hidden;
            z-index: 0;
        }

        .watermark img {
            width: 76%;
            opacity: 0.07;
        }

        .table-layer {
            position: relative;
            z-index: 1;
        }

        .items-panel {
            position: relative;
            height: 148mm;
            margin-top: 3mm;
        }

        .item-row td {
            height: 17mm;
        }

        .totals-panel {
            margin-top: 3mm;
        }

        .signature-panel {
            display: flex;
            justify-content: flex-end;
            padding: 2mm 1mm 0;
        }

        .page-footer {
            margin-top: auto;
        }

        .figure-image {
            max-height: 224mm;
            width: 100%;
            object-fit: contain;
        }

        @media print {
            body {
                background: #ffffff;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body class="bg-gray-100 py-8 print:bg-white print:py-0">
    <div class="pdf-page">
        <div class="pdf-shell">
            <div class="flex items-start justify-between pb-4">
                <div class="max-w-[62%]">
                    <!-- <p class="text-[15px] font-bold tracking-wide uppercase text-gray-900">{{ $senderName }}</p>
                    <p class="mt-1 text-[11px] font-semibold text-gray-700">{{ config('app.name') }}</p>
                    <p class="mt-1 text-[10.5px] text-gray-600"><span class="font-semibold">Email:</span> {{ $senderEmail }}</p> -->
                    <p class="text-[15px] font-bold tracking-wide">PT. PUTRAMAS MULIA JAYA</p>
                    <p class="mt-1 text-[12px]"><span class="font-semibold">General Trading, Electrical Support, Stationary, Industrial Equipment</span></p>
                    <p class="text-[10.5px] leading-snug">Jl. Sinar Jaya Gg. Ikrar 3 No.94 Tambun Selatan, Kab. Bekasi, Jawa Barat 17510</p>
                    <p class="text-[10.5px]"><span class="font-semibold">Email:</span> ptputramasmuliajaya@protonmail.com</p>
                </div>
                <div class="text-right">
                    <p class="text-[25px] font-bold tracking-[0.1em] text-blue-500">QUOTATION</p>
                    <!-- <p class="mt-0.5 text-[9px] font-semibold tracking-[0.25em] text-gray-500">ORIGINAL FOR RECIPIENT</p> -->
                </div>
            </div>

            <div class="page-main">
                <table class="w-full table-fixed border border-gray-300 text-[11px] text-gray-800" style="border-collapse: collapse;">
                    <tbody>
                        <tr>
                            <td class="w-[55%] border border-gray-300 p-3 align-top" rowspan="2">
                                <p class="text-[10.5px] font-semibold">Customer Details:</p>
                                <p class="text-[11.5px] font-bold">{{ $customerName }} <span class="text-[10.5px] text-gray-600">({{ $customerContact }})</span></p>
                                <p class="mt-1 text-[10.5px] font-semibold">Billing Address:</p>
                                <p class="text-[10.5px] leading-snug">{!! nl2br(e($customerAddress)) !!}</p>
                                <!-- <p class="mt-1 text-[10.5px] font-semibold">{{ $customerContact }}</p> -->
                            </td>
                            <td class="w-[22.5%] border border-gray-300 p-3 align-top">
                                <p class="text-[10px] text-gray-500">Quotation #:</p>
                                <p class="font-bold">{{ $record->quotation_number ?: '-' }}</p>
                            </td>
                            <td class="w-[22.5%] border border-gray-300 p-3 align-top">
                                <p class="text-[10px] text-gray-500">Date:</p>
                                <p class="font-bold">{{ $formatDate($record->date) }}</p>
                            </td>
                        </tr>
                        <tr>
                            <td class="border border-gray-300 p-3 align-top">
                                <p class="text-[10px] text-gray-500">Validity:</p>
                                <p class="font-bold">{{ $formatDate($record->expiry_date) }}</p>
                            </td>
                            <td class="border border-gray-300 p-3 align-top">
                                <p class="text-[10px] text-gray-500">Salesperson:</p>
                                <p class="font-bold">{{ $senderName }}</p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="items-panel">
                    @if ($watermarkDataUri)
                        <div class="watermark">
                            <img src="{{ $watermarkDataUri }}" alt="Quotation Watermark">
                        </div>
                    @endif

                    <div class="table-layer">
                        <table class="w-full table-fixed border border-gray-300 text-[11px] text-gray-800" style="border-collapse: collapse;">
                            <thead>
                                <tr class="border-b border-gray-300">
                                    <th class="w-8 border border-gray-300 p-2 text-center font-semibold">#</th>
                                    <th class="border border-gray-300 p-2 text-left font-semibold">Item</th>
                                    <th class="w-[110px] border border-gray-300 p-2 text-right font-semibold">Unit Price <br> Discount</th>
                                    <th class="w-14 border border-gray-300 p-2 text-right font-semibold">Qty <br> UoM</th>
                                    <th class="w-[90px] border border-gray-300 p-2 text-right font-semibold">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($firstPageItems as $index => $item)
                                    <tr class="item-row">
                                        <td class="border border-gray-300 p-2 text-center align-top">{{ $index + 1 }}</td>
                                        <td class="border border-gray-300 p-2 align-top">
                                            <p class="font-bold">{{ $item->item_name ?: '-' }}</p>
                                            @if (filled($item->description))
                                                <p class="mt-1 text-[10px] leading-snug text-gray-500">{{ $item->description }}</p>
                                            @endif
                                        </td>
                                        <td class="border border-gray-300 p-2 text-right align-top">
                                            <p>{{ $formatMoney($item->unit_price) }}</p>
                                            @if ((float) $item->discount > 0)
                                                <p class="text-[10px] text-gray-500">(-{{ rtrim(rtrim(number_format((float) $item->discount, 2, '.', ''), '0'), '.') }}%)</p>
                                            @endif
                                        </td>
                                        <td class="border border-gray-300 p-2 text-right align-top">{{ $formatQuantity($item->quantity) }}</td>
                                        <td class="border border-gray-300 p-2 text-right align-top">{{ $formatMoney($item->amount) }}</td>
                                    </tr>
                                @endforeach

                                @for ($row = 0; $row < $blankRowCount; $row++)
                                    <tr class="item-row">
                                        <td class="border border-gray-300">&nbsp;</td>
                                        <td class="border border-gray-300">&nbsp;</td>
                                        <td class="border border-gray-300">&nbsp;</td>
                                        <td class="border border-gray-300">&nbsp;</td>
                                        <td class="border border-gray-300">&nbsp;</td>
                                    </tr>
                                @endfor
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="totals-panel">
                    <table class="w-full table-fixed border border-gray-300 text-[11px] text-gray-800" style="border-collapse: collapse;">
                        <tbody>
                            <tr>
                                <td class="w-[55%] border border-gray-300 p-3 align-top" rowspan="3">
                                    <p class="mb-1 font-semibold">Total Items / Qty : {{ $items->count() }} / {{ $formatQuantity($totalQuantity) }}</p>
                                    <p class="text-[10px] leading-snug text-gray-600">Total amount (in words): {{ $formatAmountWords($record->grand_total) }}</p>
                                </td>
                                <td class="w-[22.5%] border border-gray-300 p-3 text-right align-middle font-bold">Total</td>
                                <td class="w-[22.5%] border border-gray-300 p-3 text-right align-middle font-bold">{{ $formatMoney($record->grand_total) }}</td>
                            </tr>
                            <tr>
                                <td class="border border-gray-300 p-3 text-right align-middle font-medium">{{ $secondaryAmountLabel }}</td>
                                <td class="border border-gray-300 p-3 text-right align-middle">{{ $formatMoney($secondaryAmountValue) }}</td>
                            </tr>
                            <tr>
                                <td class="border-l border-r border-b border-gray-300 p-3" colspan="2" style="height: 72px;"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="signature-panel">
                    <div class="w-48 text-right text-[10.5px] text-gray-700">
                        <p>For {{ $customerName }}</p>
                        <div class="h-10"></div>
                        <p class="border-t border-gray-400 pt-1 font-semibold">{{ $signatureTitle }}</p>
                        <p class="mt-1">{{ $senderName }}</p>
                    </div>
                </div>

                <div class="page-footer flex items-center justify-between border-t border-gray-200 pt-3 text-[9.5px] text-gray-500">
                    <p>Page {{ $currentPage }} / {{ $totalPages }} &bull; This is a computer generated document and requires no signature.</p>
                    <p>ptpmj.com</p>
                </div>
            </div>
        </div>
    </div>

    @foreach ($figureItems as $figure)
        @php
            $currentPage++;
        @endphp
        <div class="pdf-page">
            <div class="pdf-shell">
                <div class="pb-4">
                    <p class="text-[15px] font-bold tracking-wide uppercase text-gray-900">QUOTATION ATTACHMENT</p>
                    <p class="mt-2 text-[12px] font-semibold text-gray-800">{{ $figure['label'] }}</p>
                    <p class="mt-2 text-[10.5px] leading-snug text-gray-700">
                        <span class="font-semibold">Item:</span> {{ $figure['item_name'] ?: '-' }}
                        @if (filled($figure['description']))
                            <br>
                            <span class="font-semibold">Description:</span> {{ $figure['description'] }}
                        @endif
                    </p>
                </div>

                <div class="flex-1 border border-gray-300 p-4 text-center">
                    <img class="figure-image" src="{{ $figure['image_data_uri'] }}" alt="{{ $figure['label'] }}">
                </div>

                <div class="page-footer flex items-center justify-between border-t border-gray-200 pt-3 text-[9.5px] text-gray-500">
                    <p>Page {{ $currentPage }} / {{ $totalPages }} &bull; {{ $figure['label'] }}</p>
                    <p>{{ $record->quotation_number }}</p>
                </div>
            </div>
        </div>
    @endforeach
</body>
</html>
