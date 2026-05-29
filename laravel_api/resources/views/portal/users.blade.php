<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>User Management - Nearest Technician Portal</title>
    <style>
        :root { color-scheme: light; --bg: #f5f7f8; --panel: #fff; --line: #dce4e8; --ink: #17202a; --muted: #63727d; --brand: #0f766e; --danger: #a62626; --soft: #eef5f3; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Inter, Arial, sans-serif; background: var(--bg); color: var(--ink); }
        .shell { max-width: 1180px; margin: 0 auto; padding: 28px 20px 44px; }
        header { display: flex; justify-content: space-between; gap: 20px; align-items: center; margin-bottom: 22px; }
        h1 { margin: 0; font-size: clamp(26px, 4vw, 38px); }
        h2 { margin: 0; font-size: 18px; }
        nav { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        nav a, nav button, .button { border: 1px solid var(--line); background: var(--panel); color: var(--ink); border-radius: 8px; padding: 9px 12px; font: inherit; font-size: 14px; text-decoration: none; cursor: pointer; }
        nav a.active, .button.primary { background: var(--brand); color: white; border-color: var(--brand); }
        nav form, .inline { margin: 0; }
        .eyebrow { margin: 0 0 4px; color: var(--brand); font-weight: 800; text-transform: uppercase; font-size: 12px; letter-spacing: .08em; }
        .panel { background: var(--panel); border: 1px solid var(--line); border-radius: 8px; padding: 18px; margin-bottom: 14px; }
        .grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 10px; align-items: end; }
        label { display: block; margin: 0 0 6px; color: var(--muted); font-size: 12px; font-weight: 800; text-transform: uppercase; }
        input, select, textarea { width: 100%; border: 1px solid var(--line); border-radius: 8px; padding: 10px; font: inherit; background: white; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { padding: 12px 10px; border-bottom: 1px solid var(--line); text-align: left; font-size: 14px; vertical-align: top; }
        th { color: var(--muted); font-size: 12px; text-transform: uppercase; }
        .badge { display: inline-flex; padding: 5px 9px; border-radius: 999px; background: var(--soft); color: var(--brand); font-size: 12px; font-weight: 800; }
        .status { margin-bottom: 12px; padding: 10px 12px; border-radius: 8px; background: var(--soft); color: var(--brand); }
        .error { margin-bottom: 12px; padding: 10px 12px; border-radius: 8px; background: #fdecec; color: var(--danger); }
        .actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .danger { color: var(--danger); border-color: #f1c7c7; }
        .blocked { background: #fdecec; color: var(--danger); }
        .muted { color: var(--muted); font-size: 13px; }
        @media (max-width: 950px) { header { align-items: flex-start; flex-direction: column; } .grid { grid-template-columns: 1fr 1fr; } table, thead, tbody, th, td, tr { display: block; } thead { display: none; } tr { border-bottom: 1px solid var(--line); padding: 10px 0; } td { border: 0; padding: 7px 0; } }
        @media (max-width: 560px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<main class="shell">
    <header>
        <div>
            <p class="eyebrow">Administration</p>
            <h1>User Management</h1>
        </div>
        <nav aria-label="Portal navigation">
            <a href="{{ route('dispatch') }}">Dispatch</a>
            <a class="active" href="{{ route('users.index') }}">Users</a>
            <form method="post" action="{{ route('logout') }}">
                @csrf
                <button type="submit">Logout</button>
            </form>
        </nav>
    </header>

    @if (session('status'))
        <div class="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <section class="panel">
        <h2>Add user</h2>
        <form method="post" action="{{ route('users.store') }}" class="grid">
            @csrf
            <div>
                <label for="name">Name</label>
                <input id="name" name="name" value="{{ old('name') }}" required>
            </div>
            <div>
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required>
            </div>
            <div>
                <label for="phone">Phone</label>
                <input id="phone" name="phone" value="{{ old('phone') }}">
            </div>
            <div>
                <label for="role">Role</label>
                <select id="role" name="role" required>
                    @foreach ($roles as $role)
                        <option value="{{ $role }}" @selected(old('role', 'client') === $role)>{{ ucfirst($role) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="password">Password</label>
                <input id="password" name="password" type="password" required>
            </div>
            <div>
                <button class="button primary" type="submit">Add user</button>
            </div>
        </form>
    </section>

    <section class="panel">
        <h2>Users</h2>
        <table>
            <thead>
            <tr><th>Name</th><th>Contact</th><th>Role</th><th>Password</th><th>Actions</th></tr>
            </thead>
            <tbody>
            @forelse ($users as $user)
                <tr>
                    <td>
                        <input form="update-user-{{ $user->id }}" name="name" value="{{ $user->name }}" required>
                        <div class="muted">#{{ $user->id }} joined {{ $user->created_at?->format('M j, Y') }}</div>
                    </td>
                    <td>
                        <input form="update-user-{{ $user->id }}" name="email" type="email" value="{{ $user->email }}" required>
                        <input form="update-user-{{ $user->id }}" name="phone" value="{{ $user->phone }}" placeholder="Phone" style="margin-top: 8px;">
                    </td>
                    <td>
                        <select form="update-user-{{ $user->id }}" name="role" required>
                            @foreach ($roles as $role)
                                <option value="{{ $role }}" @selected($user->role === $role)>{{ ucfirst($role) }}</option>
                            @endforeach
                        </select>
                        @if ($user->technician)
                            <div class="badge" style="margin-top: 8px;">Technician profile</div>
                        @endif
                        @if ($user->blocked)
                            <div class="badge blocked" style="margin-top: 8px;">Blocked</div>
                            <div class="muted">{{ $user->blocked_reason }}</div>
                        @endif
                    </td>
                    <td>
                        <input form="update-user-{{ $user->id }}" name="password" type="password" placeholder="Leave unchanged">
                    </td>
                    <td>
                        <div class="actions">
                            <form id="update-user-{{ $user->id }}" class="inline" method="post" action="{{ route('users.update', $user) }}">
                                @csrf
                                @method('put')
                                <button class="button primary" type="submit">Save</button>
                            </form>
                            <form class="inline" method="post" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('Delete this user?');">
                                @csrf
                                @method('delete')
                                <button class="button danger" type="submit">Delete</button>
                            </form>
                            @if (filled($user->device_token))
                                <form class="inline" method="post" action="{{ route('notifications.test') }}">
                                    @csrf
                                    <input type="hidden" name="user_id" value="{{ $user->id }}">
                                    <button class="button" type="submit">Test FCM</button>
                                </form>
                                <form class="inline" method="post" action="{{ route('notifications.warning') }}">
                                    @csrf
                                    <input type="hidden" name="user_id" value="{{ $user->id }}">
                                    <input type="hidden" name="message" value="Please review your recent activity. Repeated misuse may lead to account suspension.">
                                    <button class="button" type="submit">Warn</button>
                                </form>
                            @endif
                            <form class="inline" method="post" action="{{ route('users.block', $user) }}" onsubmit="return confirm('{{ $user->blocked ? 'Unblock this user?' : 'Block this user?' }}');">
                                @csrf
                                @method('patch')
                                <input type="hidden" name="blocked" value="{{ $user->blocked ? '0' : '1' }}">
                                <input type="hidden" name="blocked_reason" value="Blocked by portal administrator.">
                                <button class="button {{ $user->blocked ? '' : 'danger' }}" type="submit">{{ $user->blocked ? 'Unblock' : 'Block' }}</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">No users found.</td></tr>
            @endforelse
            </tbody>
        </table>

        <div style="margin-top: 14px;">
            {{ $users->links() }}
        </div>
    </section>
</main>
</body>
</html>
