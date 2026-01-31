<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Liwaas Password Reset</title>
</head>
<body style="margin:0; padding:0; font-family: Arial, sans-serif; background-color:#f9f9f9;">
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center" style="padding:30px 0;">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.1); overflow:hidden;">
                    
                    <!-- Header -->
                    <tr>
                        <td align="center" style="background:linear-gradient(90deg,#1a1a1a,#deb64c,#1a1a1a); padding:20px;">
                            <h1 style="color:#fff; margin:0;">Liwaas Password Reset 🔐</h1>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr style="background:#000; color:#fff;">
                        <td style="padding:35px; text-align:center;">

                            <p style="font-size:16px;">
                                Hello <strong>{{ $user->name ?? 'Customer' }}</strong>,
                            </p>

                            <p style="font-size:15px; line-height:1.6;">
                                We received a request to reset your password.
                            </p>

                            <p style="font-size:15px; margin-top:20px;">
                                Your One-Time Password (OTP) is:
                            </p>

                            <!-- OTP BOX -->
                            <div style="margin:30px 0;">
                                <span style="
                                    display:inline-block;
                                    background:linear-gradient(135deg,#deb64c,#1a1a1a);
                                    padding:15px 35px;
                                    font-size:32px;
                                    letter-spacing:8px;
                                    font-weight:bold;
                                    border-radius:8px;
                                    color:#fff;
                                ">
                                    {{ $otp }}
                                </span>
                            </div>

                            <p style="font-size:14px;">
                                This OTP will expire in <strong>10 minutes</strong>.
                            </p>

                            <p style="font-size:13px; margin-top:25px;">
                                If you did not request this password reset, please ignore this email.
                            </p>

                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center" style="background:linear-gradient(90deg,#1a1a1a,#deb64c,#1a1a1a); padding:15px; font-size:13px; color:#fff;">
                            Thanks,<br>
                            {{ config('app.name') }}
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
