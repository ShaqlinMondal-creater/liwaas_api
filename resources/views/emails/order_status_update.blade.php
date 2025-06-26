<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Order Status Updated</title>
</head>
<body style="font-family: Arial, sans-serif; background: #f9f9f9; padding: 20px;">
  <div style="max-width: 700px; margin: auto; background: white; padding: 30px; border-radius: 6px;">
    <h2 style="text-align: right;">Liwaas Order Update</h2>
    <p style="text-align: right;">Hello {{ $order->user->name ?? 'Customer' }},</p>
    <p style="text-align: right;">Your order <strong>#{{ $order->order_code }}</strong> has been updated.</p>

    <p style="text-align: right;">
      <strong>Shipping:</strong> {{ $order->shipping }}<br>
      <strong>Delivery Status:</strong> {{ ucfirst($order->delivery_status) }}<br>
    </p>

    <p style="text-align: right;">
      You can download your invoice here:<br>
      <a href="{{ $order->invoice_link }}" target="_blank">{{ $order->invoice_link }}</a>
    </p>

    <p style="text-align: right; font-size: 13px; color: #888;">Thank you for shopping with Liwaas!</p>
  </div>
</body>
</html>
