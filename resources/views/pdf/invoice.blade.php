<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ optional($order->invoice)->invoice_no }}</title>
    <style>
        @page {
            margin: 0;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 0;
            color: #333;
        }

        .bg-image {
            position: fixed;
            top: 50%;
            left: 50%;
            width: 400px;
            height: 550px;
            transform: translate(-50%, -50%);
            z-index: -1;
            opacity: 0.1;
        }

        .content {
            padding: 20px 40px;
            box-sizing: border-box;
        }

        .header {
            background-color: #231f20;
            color: white;
            padding: 10px 40px;
        }

        .invoice-title {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            padding: 0px 10px;
        }

        .item_table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .item_table th, .item_table td {
            font-size: 12px;
            padding: 6px 5px;
            text-align: center;
        }
        .item_table th{
            border: 2px solid #eee;
        }

        .item_table th {
            background-color: #e2ad07;
            color: white;
        }

        .summary {
            text-align: right;
            margin-top: 20px;
        }

        .summary h4 {
            margin: 5px 0;
            font-size: 12px;
        }

        .grand-total {
            font-size: 14px;
            font-weight: bold;
            background-color: #e2ad07;
            color: white;
            padding: 10px;
        }

        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 10px 60px;
            background-color: #e2ad07;
            color: #fff;
            font-size: 12px;
            text-align: center;
        }

        .folded-corner {
            position: relative;
            height: 40px;
            width: 100%;
            vertical-align: middle !important;
        }

        .folded-corner .top {
            width: 0;
            height: 0;
            border-top: 40px solid #9d9c9c;
            border-right: 40px solid transparent;
            position: absolute;
            top: 0;
            left: 0;
        }

        .folded-corner .label {
            height: 50px;
            line-height: 40px;
            padding-left: 50px;
            color: #3b3b3b;
            font-size: 20px;
            font-weight: bold;
            font-family: Arial, sans-serif;
        }

        .folded-corner .bottom {
            width: 0;
            height: 0;
            border-bottom: 40px solid #3b3b3b;
            border-right: 40px solid transparent;
            position: absolute;
            top: 0;
            left: 0;
        }

        .product-image {
            width: 40px;
            height: auto;
        }

        .product-info {
            vertical-align: top;
            padding-left: 10px;
            line-height: 1.5;
        }

        .text-right {
            text-align: right;
        }

        .logo {
            height: 100px;
        }

        .w-50 {
            width: 50%;
        }
    </style>
</head>
<body>
@php
    $address = optional(optional($order->shipping)->address);
    $shippingCharge = (float) (optional($order->shipping)->shipping_charge ?? 0);
    $taxPrice = (float) ($order->tax_price ?? 0);
    $grandTotal = (float) ($order->grand_total ?? 0);
    $discount = (float) ($order->coupon_discount ?? 0);
    // Tax-exclusive merchandise = grand − tax − shipping (matches checkout GST-inclusive pricing).
    $subtotalExTax = max(0, round($grandTotal - $taxPrice - $shippingCharge, 2));

    $streetParts = array_values(array_filter([
        trim((string) ($address->address_line_1 ?? '')),
        trim((string) ($address->address_line_2 ?? '')),
    ], static fn ($part) => $part !== ''));
    $streetLine = implode(', ', $streetParts);
    $cityStateLine = implode(', ', array_values(array_filter([
        trim((string) ($address->city ?? '')),
        trim((string) ($address->state ?? '')),
    ], static fn ($part) => $part !== '')));
    $pinCountryLine = implode(', ', array_values(array_filter([
        trim((string) ($address->pincode ?? '')),
        trim((string) ($address->country ?? '')),
    ], static fn ($part) => $part !== '')));

    $paymentStatus = (string) (optional($order->payment)->payment_status ?? 'Pending');
    $paymentStatusLabel = $paymentStatus !== ''
        ? strtoupper($paymentStatus[0]) . strtolower(substr($paymentStatus, 1))
        : 'Pending';

    $bgPath = public_path('logos/flower-removebg-preview.png');
    $bgBase64 = null;
    if (is_file($bgPath)) {
        $bgType = pathinfo($bgPath, PATHINFO_EXTENSION);
        $bgBase64 = 'data:image/' . $bgType . ';base64,' . base64_encode((string) file_get_contents($bgPath));
    }

    $logoPath = public_path('logos/liwaas_logo_Black.jpg');
    $logoBase64 = null;
    if (is_file($logoPath)) {
        $logoType = pathinfo($logoPath, PATHINFO_EXTENSION);
        $logoBase64 = 'data:image/' . $logoType . ';base64,' . base64_encode((string) file_get_contents($logoPath));
    }

    $defaultImagePath = public_path('logos/default_liwass.png');
    $defaultImageBase64 = null;
    if (is_file($defaultImagePath)) {
        $defaultType = pathinfo($defaultImagePath, PATHINFO_EXTENSION);
        $defaultImageBase64 = 'data:image/' . $defaultType . ';base64,' . base64_encode((string) file_get_contents($defaultImagePath));
    }
@endphp

    @if ($bgBase64)
        <img src="{{ $bgBase64 }}" class="bg-image" />
    @endif

    <div class="header">
        <table width="100%">
            <tr>
                <td class="w-50">
                    @if ($logoBase64)
                        <img src="{{ $logoBase64 }}" alt="Logo" class="logo">
                    @else
                        <strong>LIWAAS</strong>
                    @endif
                </td>
                <td class="w-50 text-right">
                    <strong>INVOICE NUMBER:</strong> #{{ optional($order->invoice)->invoice_no }}<br>
                    <strong>INVOICE DATE:</strong> {{ \Carbon\Carbon::now()->setTimezone('Asia/Kolkata')->format('M d, Y, h:i A') }}
                </td>
            </tr>
        </table>
    </div>

    <div class="content">
        <table class="invoice-title">
            <tr>
                <td>
                    <div class="folded-corner">
                        <div class="top"></div>
                        <div class="label">INVOICE</div>
                        <div class="bottom"></div>
                    </div>
                </td>
            </tr>
        </table>

        <table width="100%" style="table-layout: fixed; margin-top: 5px; font-family: 'DejaVu Sans', sans-serif; font-size: 12px;">
            <tr>
                <td style="width: 50%; vertical-align: top; padding-right: 10px;">
                    <div style="padding: 10px; line-height: 1.45;">
                        <strong>Billed To:</strong><br>
                        <strong>{{ $address->name ?? 'Customer' }}</strong>,
                        <strong>Tel: {{ $address->mobile ?? '+91 xxxxx-xxxxx' }}</strong><br>
                        {{ $address->email ?? '' }}<br><br>

                        <strong>Ship To:</strong><br>
                        @if ($streetLine !== '')
                            {{ $streetLine }}<br>
                        @endif
                        @if ($cityStateLine !== '')
                            {{ $cityStateLine }}<br>
                        @endif
                        @if ($pinCountryLine !== '')
                            {{ $pinCountryLine }}
                        @endif
                    </div>
                </td>

                <td style="width: 50%; vertical-align: top; padding-left: 10px;">
                    <div style="padding: 10px; line-height: 1.45; float: right; min-width: 220px;">
                        <strong>ORDER:</strong> #{{ $order->order_code }}<br>
                        <strong>ORDER DATE:</strong> {{ \Carbon\Carbon::parse($order->created_at)->setTimezone('Asia/Kolkata')->format('M d, Y, h:i A') }}<br>
                        <strong>Shipping:</strong> {{ optional($order->shipping)->shipping_type ?? 'Home' }}<br>
                        <strong>Payment:</strong> {{ $order->payment_type ?? 'N/A' }}<br>
                        <strong>Payment Status:</strong> {{ $paymentStatusLabel }}
                    </div>
                </td>
            </tr>
        </table>

        <table class="item_table">
            <thead>
                <tr>
                    <th>##</th>
                    <th>ITEM</th>
                    <th>VARIATION</th>
                    <th>QTY</th>
                    <th>DISCOUNT</th>
                    <th class="text-right">SUBTOTAL (₹)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                @php
                    $variation = $item->variation;
                    $colorInfo = is_array($item->color ?? null)
                        ? $item->color
                        : \App\Helpers\ColorHelper::get($variation->color ?? ($item->color_name ?? null));
                    $colorName = $item->color_name
                        ?? ($colorInfo['name'] ?? null)
                        ?? 'N/A';
                    $colorCode = $item->color_code
                        ?? ($colorInfo['code'] ?? null)
                        ?? '#cccccc';
                    $sizeLabel = $item->size
                        ?? optional($variation)->size
                        ?? 'N/A';
                    $rowImage = $defaultImageBase64;
                @endphp
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>
                        <table width="100%">
                            <tr>
                                <td style="width: 40px;">
                                    @if ($rowImage)
                                        <img src="{{ $rowImage }}" class="product-image">
                                    @endif
                                </td>
                                <td class="product-info" style="text-align: left;">
                                    <strong>{{ optional($item->product)->name ?? 'N/A' }}</strong><br>
                                    <span>
                                        <strong>AID:</strong> {{ $item->aid ?? 'N/A' }} &nbsp;&nbsp;
                                        <strong>UID:</strong> {{ $item->uid ?? 'N/A' }}
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td style="text-align:center;">
                        Color:
                        <span style="display:inline-block;border-radius:50%;width:12px;height:12px;background-color:{{ $colorCode }};border:1px solid #ddd;"></span>
                        {{ $colorName }}
                        <br>
                        Size: {{ $sizeLabel }}
                    </td>
                    <td>{{ $item->quantity }}</td>
                    <td>₹ 0.00</td>
                    <td class="text-right">₹{{ number_format((float) $item->total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="summary">
            <h4>SUBTOTAL: ₹{{ number_format($subtotalExTax, 2) }}</h4>
            <h4>TAX (5%): ₹{{ number_format($taxPrice, 2) }}</h4>
            <h4>SHIPPING &amp; HANDLING: ₹{{ number_format($shippingCharge, 2) }}</h4>
            <h4>DISCOUNT: ₹{{ number_format($discount, 2) }}</h4>
            <h4 class="grand-total">GRAND TOTAL: ₹{{ number_format($grandTotal, 2) }}</h4>
        </div>
    </div>

    @php
        $signLogoPath = public_path('logos/liwaas_logo.png');
        $signLogoBase64 = null;
        if (is_file($signLogoPath)) {
            $signLogoType = pathinfo($signLogoPath, PATHINFO_EXTENSION);
            $signLogoBase64 = 'data:image/' . $signLogoType . ';base64,' . base64_encode((string) file_get_contents($signLogoPath));
        }

        $qrBase64 = null;
        if (!empty(optional($order->invoice)->invoice_qr)) {
            $qrPath = storage_path('app/public/qr/' . $order->invoice->invoice_qr);
            if (is_file($qrPath)) {
                $qrBase64 = 'data:image/png;base64,' . base64_encode((string) file_get_contents($qrPath));
            }
        }
    @endphp
    <table width="100%" style="margin-top: 40px; padding: 20px 40px;">
        <tr>
            <td style="width: 50%; text-align: left;">
                @if ($qrBase64)
                    <img src="{{ $qrBase64 }}" alt="QR Code" style="width: 120px; height: 120px;">
                    <div style="font-size: 11px; margin-bottom: 5px;"><strong>Scan to View Invoice</strong></div>
                @endif
            </td>

            <td style="width: 50%; text-align: right; vertical-align: bottom;">
                <div style="border-top: 1px solid #333; width: 200px; margin-left: auto; margin-bottom: 5px;"></div>
                <div style="margin-bottom: 10px; font-size: 12px;">Authorized Signatory</div>
                @if ($signLogoBase64)
                    <img src="{{ $signLogoBase64 }}" alt="Signature Logo" style="height: 40px;"><br>
                @endif
                <div style="font-size: 11px; margin-top: 2px;"><strong>LIWAAS Store</strong></div>
            </td>
        </tr>
    </table>

    <div class="footer" style="font-size: 11px; line-height: 1.5;">
        <strong>LIWAAS STORE</strong> &nbsp; | &nbsp;
        Azad nagar, Memari, Burdwan, WB - 713146<br>
        Email: <a href="mailto:business.liwaas@gmail.com" style="color: #fff; text-decoration: underline;">business.liwaas@gmail.com</a> &nbsp; | &nbsp;
        Phone: +91 8348381252
    </div>

</body>
</html>
