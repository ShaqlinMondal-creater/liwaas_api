<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Password Reset OTP</title>
</head>
<body style="font-family: Arial, sans-serif; background: #f9f9f9; padding: 20px;">
  <div style="max-width: 700px; margin: auto; background: white; padding: 30px; border-radius: 6px;">
    
    <h2 style="text-align: right;">Liwaas Password Reset</h2>
    
    <p style="text-align: right;">
      Hello {{ $user->name ?? 'Customer' }},
    </p>

    <p style="text-align: right;">
      We received a request to reset your password.
    </p>

    <p style="text-align: right;">
      Your One-Time Password (OTP) is:
    </p>

    <h1 style="text-align: center; letter-spacing: 5px; margin: 25px 0;">
      {{ $otp }}
    </h1>

    <p style="text-align: right;">
      This OTP will expire in <strong>10 minutes</strong>.
    </p>

    <p style="text-align: right;">
      If you did not request this password reset, please ignore this email.
    </p>

    <p style="text-align: right; font-size: 13px; color: #888;">
      – Team Liwaas
    </p>

  </div>
</body>
</html>
