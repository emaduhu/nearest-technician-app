<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Technician Dashboard - Nearest Technician Portal</title>
    <style>
        :root { color-scheme: light; --bg: #f5f7f8; --panel: #fff; --line: #dce4e8; --ink: #17202a; --muted: #63727d; --brand: #0f766e; --danger: #a62626; --warn: #9a6700; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Inter, Arial, sans-serif; background: var(--bg); color: var(--ink); }
        .shell { max-width: 1120px; margin: 0 auto; padding: 28px 20px 44px; }
        header { display: flex; justify-content: space-between; gap: 20px; align-items: center; margin-bottom: 22px; }
        h1 { margin: 0; font-size: clamp(26px, 4vw, 38px); }
        h2 { margin: 0; font-size: 18px; }
        nav { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        nav a, nav button, .button { border: 1px solid var(--line); background: var(--panel); color: var(--ink); border-radius: 8px; padding: 9px 12px; font: inherit; font-size: 14px; text-decoration: none; cursor: pointer; }
        nav a.active, .button.primary { background: var(--brand); color: white; border-color: var(--brand); }
        nav form, .inline { margin: 0; }
        .eyebrow { margin: 0 0 4px; color: var(--brand); font-weight: 800; text-transform: uppercase; font-size: 12px; letter-spacing: .08em; }
        .status { margin-bottom: 12px; padding: 10px 12px; border-radius: 8px; background: #eef5f3; color: var(--brand); }
        .metrics { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 18px; }
        .metric, .panel { background: var(--panel); border: 1px solid var(--line); border-radius: 8px; }
        .metric { padding: 18px; }
        .metric span { display: block; color: var(--muted); font-size: 13px; }
        .metric strong { display: block; margin-top: 8px; font-size: 30px; }
        .grid { display: grid; grid-template-columns: 1fr 2fr; gap: 14px; }
        .panel { padding: 18px; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { padding: 12px 10px; border-bottom: 1px solid var(--line); text-align: left; font-size: 14px; vertical-align: middle; }
        th { color: var(--muted); font-size: 12px; text-transform: uppercase; }
        .badge { display: inline-flex; padding: 5px 9px; border-radius: 999px; background: #eef5f3; color: var(--brand); font-size: 12px; font-weight: 800; }
        .badge.pending { background: #fff5df; color: var(--warn); }
        .badge.rejected, .badge.cancelled { background: #fdecec; color: var(--danger); }
        .badge.unavailable { background: #f2f4f5; color: var(--muted); }
        .profile { display: grid; gap: 10px; margin-top: 12px; }
        .profile div { border: 1px solid var(--line); border-radius: 8px; padding: 12px; }
        .profile span { display: block; color: var(--muted); font-size: 13px; }
        .profile strong { display: block; margin-top: 4px; }
        .actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-top: 14px; }
        @media (max-width: 850px) { header { align-items: flex-start; flex-direction: column; } .metrics, .grid { grid-template-columns: 1fr; } }
        @media (max-width: 620px) { table, thead, tbody, th, td, tr { display: block; } thead { display: none; } tr { border-bottom: 1px solid var(--line); padding: 10px 0; } td { border: 0; padding: 7px 0; } }
    </style>
</head>
<body>
<main class="shell">
    <header>
        <div>
            <p class="eyebrow">Technician portal</p>
            <h1>{{ $technician->name }}</h1>
        </div>
        <nav aria-label="Technician navigation">
            <a class="active" href="{{ route('technician.dashboard') }}">My work</a>
            <form method="post" action="{{ route('logout') }}">
                @csrf
                <button type="submit">Logout</button>
            </form>
        </nav>
    </header>

    @if (session('status'))
        <div class="status">{{ session('status') }}</div>
    @endif

    <section class="metrics">
        <article class="metric"><span>Status</span><strong>{{ $technician->available ? 'On' : 'Off' }}</strong></article>
        <article class="metric"><span>Pending</span><strong>{{ $stats['pending'] }}</strong></article>
        <article class="metric"><span>Accepted</span><strong>{{ $stats['accepted'] }}</strong></article>
        <article class="metric"><span>Completed</span><strong>{{ $stats['completed'] }}</strong></article>
    </section>

    <section class="grid">
        <aside>
            <article class="panel">
                <h2>Availability</h2>
                <div class="profile">
                    <div>
                        <span>Current status</span>
                        <strong>{{ $technician->available ? 'Available for dispatch' : 'Unavailable' }}</strong>
                    </div>
                    <div>
                        <span>Skills</span>
                        <strong>{{ implode(', ', $technician->skills ?? []) ?: 'No skills listed' }}</strong>
                    </div>
                    <div>
                        <span>Last seen</span>
                        <strong>{{ $technician->last_seen_at?->diffForHumans() ?? 'Not seen yet' }}</strong>
                    </div>
                </div>
                <form class="actions" method="post" action="{{ route('technician.availability') }}">
                    @csrf
                    @method('patch')
                    <input type="hidden" name="available" value="{{ $technician->available ? '0' : '1' }}">
                    <button class="button {{ $technician->available ? '' : 'primary' }}" type="submit">
                        {{ $technician->available ? 'Set unavailable' : 'Set available' }}
                    </button>
                </form>
            </article>
        </aside>

        <article class="panel">
            <h2>Assigned requests</h2>
            <table>
                <thead><tr><th>Client</th><th>Skill</th><th>Status</th><th>Distance</th><th>Requested</th></tr></thead>
                <tbody>
                @forelse ($requests as $request)
                    <tr>
                        <td>{{ $request->client?->name ?? 'Client' }}</td>
                        <td>{{ $request->skill ?: 'General service' }}</td>
                        <td><span class="badge {{ $request->status }}">{{ $request->status }}</span></td>
                        <td>{{ $request->distance_km ?? '-' }} km</td>
                        <td>{{ $request->created_at?->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">No assigned requests yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </article>
    </section>
</main>
</body>
</html>
