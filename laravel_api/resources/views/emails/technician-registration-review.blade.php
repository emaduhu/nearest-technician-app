<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Technician registration {{ $decision }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #17202a; line-height: 1.5;">
    <p>Hello {{ $name }},</p>

    @if ($decision === 'approved')
        <p>Your Nearest Technician registration has been approved.</p>
        <p>Open the app to continue with the next registration steps.</p>
    @else
        <p>Your Nearest Technician registration has been rejected.</p>
        <p><strong>Reason:</strong> {{ $note }}</p>
        <p>Please correct the issue and contact support or register again with accurate details.</p>
    @endif

    <p>Nearest Technician Support</p>
</body>
</html>
