<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Welcome to Liwaas</title>
</head>
<body style="margin:0; padding:0; font-family: Arial, sans-serif; background-color:#f9f9f9;">
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center" style="padding:30px 0;">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.1); overflow:hidden;">
                    
                    <!-- Header -->
                    <tr>
                        <td align="center" style="background:linear-gradient(90deg,#1a1a1a, #deb64c ,#1a1a1a); padding:20px;">
                            <h1 style="color:#fff; margin:0;">Welcome to Liwaas 🎉</h1>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr style="background:#000; color: #fff;">
                        <td style="padding:30px;">
                            <p style="font-size:16px;">Hello <strong>{{ $user->name }}</strong>,</p>

                            <p style="font-size:15px; line-height:1.6;">
                                Your account has been created successfully. You can now log in and start shopping with us.
                            </p>

                            <h3 style="margin-top:25px;">Your Account Details:</h3>
                            <ul style="list-style:none; padding:0; font-size:14px; line-height:1.8;">
                                <li><strong>Email:</strong> {{ $user->email }}</li>
                                <li><strong>Mobile:</strong> {{ $user->mobile }}</li>
                                <li><strong>Password:</strong> {{ $password }}</li>
                            </ul>

                            <!-- Login Button -->
                            <p style="margin:30px 0;">
                                <a href="{{ config('app.frontend_url') }}/sign-in" 
                                   style="background:linear-gradient(135deg, #deb64c, #1a1a1a); color:#fff; padding:12px 25px; text-decoration:none; border-radius:6px; font-size:15px;">
                                   Login to Your Account
                                </a>
                            </p>

                            <p style="font-size:13px;">
                                If you did not request this account, please contact our support team immediately.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center" style="background:linear-gradient(90deg,#1a1a1a, #deb64c ,#1a1a1a); padding:15px; font-size:13px;">
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
