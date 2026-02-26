<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <title>Order Confirmation - {{ $order->order_code }}</title>
  </head>
  <body style="margin: 0; padding: 0; background-color: #f9f9f9; font-family: Arial, sans-serif;">
    <table width="100%" bgcolor="#f9f9f9" cellpadding="0" cellspacing="0">
      <tr>
        <td align="center">
          <table width="100%" cellpadding="0" cellspacing="0" style="background: #ffffff; padding: 30px; border-radius: 6px; margin: 30px auto;">
            
            <!-- Top Logo & Welcome Message -->
            <tr>
              <td align="left">
                  <img src="{{ asset('storage/liwaas_logo.jpeg') }}" alt="Liwaas Logo" height="50" style="margin-bottom: 10px;">
                <h2 style="margin: 0; color: #444;">Thank you for your order!</h2>
                <p style="margin-top: 5px; color: #777;">We’re processing it and will notify you once it ships.</p>
              </td>
            </tr>

            <!-- Billing + Order Info -->
            <tr>
              <td style="padding-top: 20px;">
                <table width="100%" cellpadding="5" cellspacing="0">
                  <tr>
                    <td valign="top">
                      <h3 style="margin-bottom: 5px;">Billed To:</h3>
                      <strong>{{ $order->user->name ?? 'Customer' }}</strong><br>
                      {{ $order->user->email }}<br>
                      Tel: {{ $order->user->phone ?? 'N/A' }}
                    </td>
                    <td valign="top" align="right">
                      <strong>ORDER:</strong> #{{ $order->order_code }}<br>
                      <strong>ORDER DATE:</strong> {{ \Carbon\Carbon::parse($order->created_at)->format('M d, Y, h:i A') }}<br>
                      <strong>Shipping:</strong> {{ ucfirst($order->shipping->shipping_type ?? '') }}<br>
                      <strong>Payment:</strong> {{ ucfirst($order->payment->payment_status ?? '') }}
                    </td>
                  </tr>
                </table>
              </td>
            </tr>

            <!-- Shipping Address -->
            <tr>
              <td style="padding-top: 20px;">
                <h3 style="margin-bottom: 5px;">Ship To:</h3>
                {{ $order->shipping->address->address_line_1 ?? '' }}, <br>
                {{ $order->shipping->address->city ?? '' }}, {{ $order->shipping->address->state ?? '' }}, <br>
                {{ $order->shipping->address->pincode ?? '' }}, {{ $order->shipping->address->country ?? '' }}
              </td>
            </tr>

            <!-- Order Table -->
            <tr>
              <td style="padding-top: 30px;">
                <table width="100%" border="1" cellpadding="8" cellspacing="0" style="border-collapse: collapse; border-color: #d4a017;">
                  <thead style="background: #d4a017; color: white;">
                    <tr>
                      <th>#</th>
                      <th>Item</th>
                      <th>Variation</th>
                      <th>Qty</th>
                      <th>Discount</th>
                      <th style="text-align: right;">Subtotal (₹)</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach ($order->items as $index => $item)
                      <tr>
                        <td align="center">{{ $index + 1 }}</td>
                        <td>
                          <strong>{{ $item->product->name ?? 'Product Name' }}</strong><br>
                          <strong>AID:</strong> {{ $item->aid }}<br>
                          <strong>UID:</strong> {{ $item->uid }}
                        </td>
                        <td>
                          Size: {{ $item->variation->size ?? '-' }}<br>
                          Color: {{ $item->variation->color ?? '-' }}
                      </td>
                        <td align="center">{{ $item->quantity }}</td>
                        <td align="center">₹ 0.00</td>
                        <td align="right">₹{{ number_format($item->total, 2) }}</td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </td>
            </tr>

            <!-- Totals -->
            <tr>
              <td align="right" style="padding-top: 20px;">
                <table cellpadding="5" cellspacing="0" style="width: 300px; float: right;">
                  <tr>
                    <td style="border-bottom: 1px solid #eee;">Subtotal:</td>
                    <td style="border-bottom: 1px solid #eee;" align="right">₹{{ number_format($order->grand_total - ($order->shipping->shipping_charge ?? 0), 2) }}</td>
                  </tr>
                  <tr>
                    <td style="border-bottom: 1px solid #eee;">Tax (5%):</td>
                    <td style="border-bottom: 1px solid #eee;" align="right">₹{{ number_format($order->tax_price, 2) }}</td>
                  </tr>
                  <tr>
                    <td style="border-bottom: 1px solid #eee;">Shipping:</td>
                    <td style="border-bottom: 1px solid #eee;" align="right">₹{{ number_format($order->shipping->shipping_charge ?? 0, 2) }}</td>
                  </tr>
                  <tr>
                    <td style="border-bottom: 1px solid #eee;">Discount:</td>
                    <td style="border-bottom: 1px solid #eee;" align="right">₹{{ number_format($order->coupon_discount ?? 0, 2) }}</td>
                  </tr>
                  <tr>
                    <td style="font-weight: bold; font-size: 16px;">GRAND TOTAL:</td>
                    <td style="font-weight: bold; font-size: 16px;" align="right">₹{{ number_format($order->grand_total, 2) }}</td>
                  </tr>
                </table>
              </td>
            </tr>

            <!-- Footer -->
            <tr>
              <td align="left" style="padding-top: 50px;">
                  <img src="{{ asset('storage/liwaas_logo.jpeg') }}" alt="Liwaas Logo" height="50" style="margin-bottom: 10px;">
                <p style="font-size: 13px; color: #888;">
                  Thank you for shopping with <strong>Liwaas</strong>. We hope to see you again soon!
                </p>
                <p align="center" style="font-size: 12px; color: #aaa;">&copy; {{ date('Y') }} Liwaas. All rights reserved.</p>
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
</html>
