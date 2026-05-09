<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password - Nearest Technician</title>
    <style>
        body { margin: 0; font-family: Inter, Arial, sans-serif; background: #f5f7f8; color: #17202a; }
        main { min-height: 100vh; display: grid; place-items: center; padding: 24px; }
        form { width: min(100%, 420px); background: #fff; border: 1px solid #dce4e8; border-radius: 8px; padding: 24px; }
        h1 { margin: 0 0 8px; font-size: 24px; }
        p { color: #63727d; }
        label { display: block; margin-top: 14px; font-weight: 700; font-size: 13px; }
        input { width: 100%; box-sizing: border-box; margin-top: 6px; padding: 12px; border: 1px solid #cfd9de; border-radius: 8px; font: inherit; }
        button { width: 100%; margin-top: 18px; padding: 12px 16px; border: 0; border-radius: 8px; background: #0f766e; color: #fff; font-weight: 800; cursor: pointer; }
        .message { padding: 12px; border-radius: 8px; margin: 14px 0; }
        .ok { background: #e7f7ef; color: #11633f; }
        .error { background: #fdecec; color: #9f1f1f; }
    </style>
</head>
<body>
<main>
    <form method="post" action="{{ route('password.update') }}">
        @csrf
        <h1>Reset password</h1>
        <p>Enter a new password for your Nearest Technician account.</p>

        @if (session('status'))
            <div class="message ok">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="message error">{{ $errors->first() }}</div>
        @endif

        <input type="hidden" name="token" value="{{ old('token', $token) }}">

        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="email">

        <label for="password">New password</label>
        <input id="password" name="password" type="password" required minlength="6" autocomplete="new-password">

        <label for="password_confirmation">Confirm new password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required minlength="6" autocomplete="new-password">

        <button type="submit">Reset password</button>
    </form>
</main>
</body>
</html>
