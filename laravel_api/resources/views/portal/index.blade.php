<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nearest Technician Portal</title>
    <style>
        :root { color-scheme: light; --bg: #f5f7f8; --panel: #fff; --line: #dce4e8; --ink: #17202a; --muted: #63727d; --brand: #0f766e; --blue: #2563eb; --danger: #a62626; --warn: #9a6700; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Inter, Arial, sans-serif; background: var(--bg); color: var(--ink); }
        .shell { max-width: 1180px; margin: 0 auto; padding: 28px 20px 44px; }
        header { display: flex; justify-content: space-between; gap: 20px; align-items: center; margin-bottom: 22px; }
        h1 { margin: 0; font-size: clamp(26px, 4vw, 38px); }
        h2 { margin: 0; font-size: 18px; }
        nav { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        nav a, nav button { border: 1px solid var(--line); background: var(--panel); color: var(--ink); border-radius: 8px; padding: 9px 12px; font: inherit; font-size: 14px; text-decoration: none; cursor: pointer; }
        nav a.active { background: var(--brand); color: white; border-color: var(--brand); }
        nav form { margin: 0; }
        .eyebrow { margin: 0 0 4px; color: var(--brand); font-weight: 800; text-transform: uppercase; font-size: 12px; letter-spacing: .08em; }
        .metrics { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 12px; margin-bottom: 18px; }
        .metric, .panel { background: var(--panel); border: 1px solid var(--line); border-radius: 8px; }
        .metric { padding: 18px; }
        .metric span { display: block; color: var(--muted); font-size: 13px; }
        .metric strong { display: block; margin-top: 8px; font-size: 30px; }
        .grid { display: grid; grid-template-columns: 2fr 1fr; gap: 14px; }
        .panel { padding: 18px; margin-bottom: 14px; }
        .status { margin-bottom: 12px; padding: 10px 12px; border-radius: 8px; background: #eef5f3; color: var(--brand); }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { padding: 12px 10px; border-bottom: 1px solid var(--line); text-align: left; font-size: 14px; vertical-align: middle; }
        th { color: var(--muted); font-size: 12px; text-transform: uppercase; }
        .badge { display: inline-flex; padding: 5px 9px; border-radius: 999px; background: #eef5f3; color: var(--brand); font-size: 12px; font-weight: 800; }
        .badge.pending { background: #fff5df; color: #9a6700; }
        .badge.unavailable { background: #f2f4f5; color: var(--muted); }
        .badge.rejected, .badge.cancelled { background: #fdecec; color: #a62626; }
        .toolbar { display: flex; justify-content: space-between; gap: 10px; align-items: center; }
        .split { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 12px; }
        .availability-card { border: 1px solid var(--line); border-radius: 8px; padding: 12px; }
        .availability-card span { display: block; color: var(--muted); font-size: 13px; }
        .availability-card strong { display: block; margin-top: 6px; font-size: 24px; }
        .actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .actions form { margin: 0; }
        .button { border: 1px solid var(--line); background: white; color: var(--ink); border-radius: 8px; padding: 8px 10px; font: inherit; font-size: 13px; cursor: pointer; }
        .button.primary { background: var(--brand); color: white; border-color: var(--brand); }
        .button.muted { color: var(--muted); }
        select, input, textarea { width: 100%; border: 1px solid var(--line); border-radius: 8px; padding: 10px; font: inherit; background: white; }
        .list { display: grid; gap: 10px; margin-top: 12px; }
        .item { border: 1px solid var(--line); border-radius: 8px; padding: 12px; }
        .item strong { display: block; }
        .item span { display: block; color: var(--muted); margin-top: 4px; font-size: 13px; }
        @media (max-width: 950px) { header { align-items: flex-start; flex-direction: column; } .metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); } .grid { grid-template-columns: 1fr; } }
        @media (max-width: 620px) { .metrics, .split { grid-template-columns: 1fr; } table, thead, tbody, th, td, tr { display: block; } thead { display: none; } tr { border-bottom: 1px solid var(--line); padding: 10px 0; } td { border: 0; padding: 7px 0; } }
    </style>
</head>
<body>
<main class="shell">
    <header>
        <div>
            <p class="eyebrow">Live dispatch</p>
            <h1>Nearest Technician Portal</h1>
        </div>
        <nav aria-label="Portal navigation">
            <a class="active" href="{{ route('dispatch') }}">Dispatch</a>
            @if (auth()->user()?->role === 'admin')
                <a href="{{ route('users.index') }}">Users</a>
            @endif
            <form method="post" action="{{ route('logout') }}">
                @csrf
                <button type="submit">Logout</button>
            </form>
            <span class="badge">Updated {{ now()->format('M j, Y H:i') }}</span>
        </nav>
    </header>

    @if (session('status'))
        <div class="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="status" style="background:#fdecec;color:var(--danger);">{{ $errors->first() }}</div>
    @endif

    <section class="metrics">
        <article class="metric"><span>Clients</span><strong>{{ $stats['clients'] }}</strong></article>
        <article class="metric"><span>Technicians</span><strong>{{ $stats['technicians'] }}</strong></article>
        <article class="metric"><span>Available</span><strong>{{ $stats['available'] }}</strong></article>
        <article class="metric"><span>Unavailable</span><strong>{{ $stats['unavailable'] }}</strong></article>
        <article class="metric"><span>Pending</span><strong>{{ $stats['pending'] }}</strong></article>
        <article class="metric"><span>Completed</span><strong>{{ $stats['completed'] }}</strong></article>
    </section>

    <section class="grid">
        <div>
            <article class="panel">
                <h2>Recent requests</h2>
                <table>
                    <thead><tr><th>Client</th><th>Technician</th><th>Skill</th><th>Status</th><th>Distance</th></tr></thead>
                    <tbody>
                    @forelse ($requests as $request)
                        <tr>
                            <td>{{ $request->client?->name ?? 'Client' }}</td>
                            <td>{{ $request->technician?->name ?? 'Technician' }}</td>
                            <td>{{ $request->skill ?: 'General service' }}</td>
                            <td><span class="badge {{ $request->status }}">{{ $request->status }}</span></td>
                            <td>{{ $request->distance_km ?? '-' }} km</td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No requests yet</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </article>

            <article class="panel">
                <div class="toolbar">
                    <h2>Field team</h2>
                    <span class="badge">{{ $stats['technicians'] }} members</span>
                </div>
                <table>
                    <thead><tr><th>Technician</th><th>Skills</th><th>Contact</th><th>Last seen</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse ($technicians as $technician)
                        <tr>
                            <td>{{ $technician->name }}</td>
                            <td>{{ implode(', ', $technician->skills ?? []) ?: 'No skills listed' }}</td>
                            <td>{{ $technician->phone ?: $technician->email }}</td>
                            <td>{{ $technician->last_seen_at?->diffForHumans() ?? 'Not seen yet' }}</td>
                            <td>
                                <span class="badge {{ $technician->available ? '' : 'unavailable' }}">
                                    {{ $technician->available ? 'Available' : 'Unavailable' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No technicians registered.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </article>
        </div>

        <aside>
            <article class="panel">
                <h2>Technician availability</h2>
                <div class="split">
                    <div class="availability-card"><span>Available now</span><strong>{{ $stats['available'] }}</strong></div>
                    <div class="availability-card"><span>Unavailable</span><strong>{{ $stats['unavailable'] }}</strong></div>
                </div>
                <table>
                    <thead><tr><th>Technician</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                    @forelse ($technicians as $technician)
                        <tr>
                            <td>{{ $technician->name }}</td>
                            <td>
                                <span class="badge {{ $technician->available ? '' : 'unavailable' }}">
                                    {{ $technician->available ? 'Available' : 'Unavailable' }}
                                </span>
                            </td>
                            <td>
                                <div class="actions">
                                    <form method="post" action="{{ route('technicians.availability', $technician) }}">
                                        @csrf
                                        @method('patch')
                                        <input type="hidden" name="available" value="{{ $technician->available ? '0' : '1' }}">
                                        <button class="button {{ $technician->available ? 'muted' : 'primary' }}" type="submit">
                                            {{ $technician->available ? 'Set unavailable' : 'Set available' }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3">No technicians registered.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </article>

            <article class="panel">
                <h2>Top skills</h2>
                <div class="list">
                    @forelse ($topSkills as $skill)
                        <div class="item"><strong>{{ $skill->skill }}</strong><span>{{ $skill->count }} requests</span></div>
                    @empty
                        <p>No skill demand yet.</p>
                    @endforelse
                </div>
            </article>

            <article class="panel">
                <h2>Notifications</h2>
                <form method="post" action="{{ route('notifications.test') }}" class="list">
                    @csrf
                    <select name="user_id" aria-label="User with device token">
                        @forelse ($notificationUsers as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} · {{ $user->role }} · {{ $user->email }}</option>
                        @empty
                            <option value="">No registered devices</option>
                        @endforelse
                    </select>
                    <button class="button primary" type="submit" @disabled($notificationUsers->isEmpty())>Send test notification</button>
                </form>
                <form method="post" action="{{ route('notifications.warning') }}" class="list">
                    @csrf
                    <select name="user_id" aria-label="Warning recipient">
                        @forelse ($notificationUsers as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} · {{ $user->role }}</option>
                        @empty
                            <option value="">No registered devices</option>
                        @endforelse
                    </select>
                    <input name="title" value="Account warning" aria-label="Warning title">
                    <textarea name="message" rows="3" required aria-label="Warning message" placeholder="Warning message"></textarea>
                    <button class="button" type="submit" @disabled($notificationUsers->isEmpty())>Send warning</button>
                </form>
                <form method="post" action="{{ route('notifications.news') }}" class="list">
                    @csrf
                    <select name="audience" aria-label="News audience">
                        <option value="all">All users</option>
                        <option value="clients">Clients only</option>
                        <option value="technicians">Technicians only</option>
                    </select>
                    <input name="title" required aria-label="News title" placeholder="News title">
                    <textarea name="message" rows="3" required aria-label="News message" placeholder="News message"></textarea>
                    <button class="button primary" type="submit">Send news</button>
                </form>
            </article>

            <article class="panel">
                <h2>Abuse & misconduct reports</h2>
                <div class="list">
                    @forelse ($abuseReports as $report)
                        <div class="item">
                            <strong>{{ $report->reporter_name ?? ucfirst($report->reporter_role) }} reported {{ $report->reported_name ?? ucfirst($report->reported_role) }}</strong>
                            <span>{{ $report->skill ?: 'General service' }} · {{ $report->reason }} · {{ \Illuminate\Support\Carbon::parse($report->created_at)->diffForHumans() }}</span>
                            <span>{{ $report->details }}</span>
                        </div>
                    @empty
                        <p>No abuse or misconduct reports.</p>
                    @endforelse
                </div>
            </article>
        </aside>
    </section>
</main>
</body>
</html>
