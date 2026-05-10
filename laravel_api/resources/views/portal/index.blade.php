<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nearest Technician Portal</title>
    <style>
        :root { color-scheme: light; --bg: #f5f7f8; --panel: #fff; --line: #dce4e8; --ink: #17202a; --muted: #63727d; --brand: #0f766e; --blue: #2563eb; }
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
        .metrics { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; margin-bottom: 18px; }
        .metric, .panel { background: var(--panel); border: 1px solid var(--line); border-radius: 8px; }
        .metric { padding: 18px; }
        .metric span { display: block; color: var(--muted); font-size: 13px; }
        .metric strong { display: block; margin-top: 8px; font-size: 30px; }
        .grid { display: grid; grid-template-columns: 2fr 1fr; gap: 14px; }
        .panel { padding: 18px; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { padding: 12px 10px; border-bottom: 1px solid var(--line); text-align: left; font-size: 14px; }
        th { color: var(--muted); font-size: 12px; text-transform: uppercase; }
        .badge { display: inline-flex; padding: 5px 9px; border-radius: 999px; background: #eef5f3; color: var(--brand); font-size: 12px; font-weight: 800; }
        .badge.pending { background: #fff5df; color: #9a6700; }
        .badge.rejected, .badge.cancelled { background: #fdecec; color: #a62626; }
        .list { display: grid; gap: 10px; margin-top: 12px; }
        .item { border: 1px solid var(--line); border-radius: 8px; padding: 12px; }
        .item strong { display: block; }
        .item span { display: block; color: var(--muted); margin-top: 4px; font-size: 13px; }
        @media (max-width: 850px) { header { align-items: flex-start; flex-direction: column; } .metrics, .grid { grid-template-columns: 1fr; } }
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
            <a href="{{ route('users.index') }}">Users</a>
            <form method="post" action="{{ route('logout') }}">
                @csrf
                <button type="submit">Logout</button>
            </form>
            <span class="badge">Updated {{ now()->format('M j, Y H:i') }}</span>
        </nav>
    </header>

    <section class="metrics">
        <article class="metric"><span>Clients</span><strong>{{ $stats['clients'] }}</strong></article>
        <article class="metric"><span>Technicians</span><strong>{{ $stats['technicians'] }}</strong></article>
        <article class="metric"><span>Available</span><strong>{{ $stats['available'] }}</strong></article>
        <article class="metric"><span>Pending</span><strong>{{ $stats['pending'] }}</strong></article>
        <article class="metric"><span>Completed</span><strong>{{ $stats['completed'] }}</strong></article>
    </section>

    <section class="grid">
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

        <aside>
            <article class="panel">
                <h2>Technicians</h2>
                <div class="list">
                    @forelse ($technicians as $technician)
                        <div class="item">
                            <strong>{{ $technician->name }}</strong>
                            <span>{{ implode(', ', $technician->skills ?? []) ?: 'No skills listed' }}</span>
                            <span>{{ $technician->available ? 'Available' : 'Unavailable' }}</span>
                        </div>
                    @empty
                        <p>No technicians registered.</p>
                    @endforelse
                </div>
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
        </aside>
    </section>
</main>
</body>
</html>
