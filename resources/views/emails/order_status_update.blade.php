<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Liwaas Order Update</title>
</head>

<body style="margin:0; padding:0; font-family: Arial, sans-serif; background-color:#f9f9f9;">
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center" style="padding:30px 0;">
                <table width="600" cellpadding="0" cellspacing="0"
                    style="background:#ffffff; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.1); overflow:hidden;">

                    <!-- HEADER -->
                    <tr>
                        <td align="center" style="background:linear-gradient(90deg,#1a1a1a,#deb64c,#1a1a1a); padding:20px;">
                            <!-- <img src="{{ asset('storage/liwaas_logo.jpeg') }}" height="50" style="margin-bottom:10px;"> -->

                            <h1 style="color:#fff; margin:0; font-size:22px;">
                                Order Status Updated
                            </h1>
                        </td>
                    </tr>

                    <!-- BODY -->
                    <tr style="background:#000; color:#fff;">
                        <td style="padding:35px;">

                            <p style="font-size:16px;">
                                Hello <strong>{{ $order->user->name ?? 'Customer' }}</strong>,
                            </p>
                            <p style="font-size:15px; line-height:1.6;">
                                Your order <strong>#{{ $order->order_code }}</strong> has been updated.
                            </p>

                            <!-- STATUS BOX -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin:25px 0; background:#111; border-radius:8px;">
                                <tr>
                                    <td style="padding:18px; text-align:center;">
                                        <p style="margin:5px 0; font-size:14px;">
                                            Order Status
                                        </p>
                                        <p style="margin:0; font-size:20px; font-weight:bold; color:#deb64c;">
                                            {{ ucfirst($order->order_status) }}
                                        </p>
                                        <p style="margin:15px 0 5px; font-size:14px;">
                                            Shipping Status
                                        </p>
                                        <p style="margin:0; font-size:18px; font-weight:bold; color:#deb64c;">
                                            {{ $order->shipping->shipping_status ?? 'Pending' }}
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <!-- INVOICE BUTTON -->
                            @if(optional($order->invoice)->invoice_link)
                                <div align="center" style="margin:30px 0;">
                                    <a href="{{ $order->invoice->invoice_link }}"
                                        style=" display:inline-block; background:linear-gradient(135deg,#deb64c,#1a1a1a); color:#fff; padding:14px 35px;
                                        font-size:16px; font-weight:bold; border-radius:8px; text-decoration:none; letter-spacing:1px; ">
                                        DOWNLOAD INVOICE
                                    </a>
                                </div>
                            @endif

                            <p style="font-size:14px; margin-top:25px;">
                                We’ll notify you once your order moves to the next stage.
                            </p>
                            <p style="font-size:14px;">
                                Thank you for shopping with <strong>LIWAAS</strong> ✨
                            </p>
                        </td>
                    </tr>

                    <!-- FOOTER -->
                    <tr>
                        <td align="left" style="background:linear-gradient(90deg,#1a1a1a,#deb64c,#1a1a1a); padding:15px;
                            font-size:13px; color:#fff;">
                            Thanks,<br>
                            {{ config('app.frontend_name') }} Team
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>