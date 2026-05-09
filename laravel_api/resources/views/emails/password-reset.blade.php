<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Reset your password</title>
</head>
<body style="font-family: Arial, sans-serif; color: #17202a; line-height: 1.5;">
    <p>Hello {{ $name }},</p>
    <p>We received a request to reset your Nearest Technician password.</p>
    <p>
        <a href="{{ $resetUrl }}" style="display: inline-block; padding: 12px 18px; background: #0f766e; color: #ffffff; text-decoration: none; border-radius: 6px;">
            Reset password
        </a>
    </p>
    <p>This link expires in 60 minutes. If you did not request a reset, you can ignore this email.</p>
    <p>Nearest Technician Support</p>
</body>
</html>
