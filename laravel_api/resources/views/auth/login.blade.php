<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in - Nearest Technician Portal</title>
    <style>
        :root { color-scheme: light; --bg: #f5f7f8; --panel: #fff; --line: #dce4e8; --ink: #17202a; --muted: #63727d; --brand: #0f766e; --danger: #a62626; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; font-family: Inter, Arial, sans-serif; background: var(--bg); color: var(--ink); padding: 20px; }
        .panel { width: min(420px, 100%); background: var(--panel); border: 1px solid var(--line); border-radius: 8px; padding: 26px; }
        h1 { margin: 0 0 6px; font-size: 28px; }
        p { margin: 0 0 22px; color: var(--muted); }
        label { display: block; margin: 14px 0 6px; color: var(--muted); font-size: 13px; font-weight: 800; text-transform: uppercase; }
        input { width: 100%; border: 1px solid var(--line); border-radius: 8px; padding: 12px; font: inherit; }
        .row { display: flex; align-items: center; gap: 8px; margin: 14px 0 18px; color: var(--muted); font-size: 14px; }
        .row input { width: auto; }
        button { width: 100%; border: 0; border-radius: 8px; padding: 12px 16px; background: var(--brand); color: white; font: inherit; font-weight: 800; cursor: pointer; }
        .error { margin-top: 12px; color: var(--danger); font-size: 14px; }
    </style>
</head>
<body>
<main class="panel">
    <h1>Nearest Technician</h1>
    <p>Sign in to open dispatch and user management.</p>

    <form method="post" action="{{ route('login.store') }}">
        @csrf
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>

        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>

        <label class="row">
            <input name="remember" type="checkbox" value="1">
            Keep me signed in
        </label>

        <button type="submit">Sign in</button>

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif
    </form>
</main>
</body>
</html>
