<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nearest Technician Portal</title>
    <style>
        :root { color-scheme: light; --bg: #f5f7f8; --panel: #fff; --line: #dce4e8; --ink: #17202a; --muted: #63727d; --brand: #0f766e; --blue: #2563eb; --danger: #a62626; --warn: #9a6700; }
        * { box-sizing: border-box; }
        [hidden] { display: none !important; }
        body { margin: 0; font-family: Inter, Arial, sans-serif; background: var(--bg); color: var(--ink); }
        .shell { max-width: 1360px; margin: 0 auto; padding: 28px 20px 44px; }
        header { display: flex; justify-content: space-between; gap: 20px; align-items: center; margin-bottom: 22px; }
        h1 { margin: 0; font-size: clamp(26px, 4vw, 38px); }
        h2 { margin: 0; font-size: 18px; }
        nav { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        nav a, nav button { border: 1px solid var(--line); background: var(--panel); color: var(--ink); border-radius: 8px; padding: 9px 12px; font: inherit; font-size: 14px; text-decoration: none; cursor: pointer; }
        nav a.active { background: var(--brand); color: white; border-color: var(--brand); }
        nav form { margin: 0; }
        .eyebrow { margin: 0 0 4px; color: var(--brand); font-weight: 800; text-transform: uppercase; font-size: 12px; letter-spacing: .08em; }
        .metrics { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 12px; margin-bottom: 18px; }
        .metric, .panel { background: var(--panel); border: 1px solid var(--line); border-radius: 8px; }
        .metric { padding: 18px; }
        .metric span { display: block; color: var(--muted); font-size: 13px; }
        .metric strong { display: block; margin-top: 8px; font-size: 30px; }
        .grid { display: grid; grid-template-columns: minmax(0, 2fr) minmax(340px, 1fr); gap: 14px; }
        .panel { padding: 18px; margin-bottom: 14px; }
        .status { margin-bottom: 12px; padding: 10px 12px; border-radius: 8px; background: #eef5f3; color: var(--brand); }
        .status.error { background: #fdecec; color: var(--danger); }
        #dispatch-live { transition: opacity .18s ease; }
        #dispatch-live.is-refreshing { opacity: .82; }
        #technician-review-panel { transition: opacity .18s ease; }
        #technician-review-panel.is-refreshing { opacity: .82; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        .table-scroll { overflow: auto; max-height: 560px; margin-top: 12px; border: 1px solid var(--line); border-radius: 8px; }
        .table-scroll table { min-width: 780px; margin-top: 0; }
        .table-scroll th { position: sticky; top: 0; z-index: 1; background: var(--panel); }
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
        .review-scroll { max-height: 380px; overflow-x: auto; overflow-y: hidden; margin-top: 12px; padding: 0 4px 8px 0; }
        .review-grid { display: flex; gap: 12px; min-width: max-content; margin-top: 12px; }
        .review-scroll .review-grid { margin-top: 0; }
        .review-card { flex: 0 0 340px; border: 1px solid var(--line); border-radius: 8px; padding: 12px; background: #fff; }
        .review-card .item { padding: 10px; }
        .review-card .item span { font-size: 12px; line-height: 1.25; }
        .review-images { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin: 8px 0; }
        .review-images label { color: var(--muted); font-size: 11px; font-weight: 800; text-transform: uppercase; }
        .review-image { border: 1px solid var(--line); border-radius: 8px; overflow: hidden; background: #f8fafc; aspect-ratio: 16 / 9; max-height: 104px; display: grid; place-items: center; }
        .review-image img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .review-actions { display: grid; grid-template-columns: minmax(130px, 1fr) auto auto; gap: 6px; align-items: center; }
        .review-actions input { padding: 8px; font-size: 12px; }
        .review-actions .button { padding: 8px 9px; }
        .request-block-form { display: grid; grid-template-columns: minmax(180px, 1fr) auto; gap: 8px; align-items: center; min-width: 260px; }
        .helper { color: var(--muted); font-size: 13px; margin: 6px 0 0; }
        @media (max-width: 950px) { header { align-items: flex-start; flex-direction: column; } .metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); } .grid { grid-template-columns: 1fr; } }
        @media (max-width: 620px) { .metrics, .split, .review-actions, .request-block-form { grid-template-columns: 1fr; } .review-card { flex-basis: 320px; } .table-scroll table { min-width: 0; } table, thead, tbody, th, td, tr { display: block; } thead { display: none; } tr { border-bottom: 1px solid var(--line); padding: 10px 0; } td { border: 0; padding: 7px 0; } }
    </style>
</head>
<body>
<main class="shell">
    @php
        $formatNida = static function ($value): string {
            $digits = preg_replace('/\D+/', '', (string) $value) ?? '';
            if (strlen($digits) !== 20) {
                return $digits !== '' ? $digits : '-';
            }

            return substr($digits, 0, 8).'-'.substr($digits, 8, 5).'-'.substr($digits, 13, 5).'-'.substr($digits, 18, 2);
        };
        $registrationFeeMinimum = \App\Services\AppSettingsService::MIN_TECHNICIAN_REGISTRATION_FEE;
    @endphp
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
            <span id="portal-updated-at" class="badge">Updated {{ now()->format('M j, Y H:i') }}</span>
        </nav>
    </header>

    <div id="portal-status" class="status" @if (! session('status')) hidden @endif>{{ session('status') }}</div>
    <div id="portal-errors" class="status error" @if (! $errors->any()) hidden @endif>{{ $errors->first() }}</div>

    <div id="dispatch-live" data-refresh-url="{{ route('dispatch') }}" data-refresh-ms="5000">
    <section class="metrics">
        <article class="metric"><span>Clients</span><strong>{{ $stats['clients'] }}</strong></article>
        <article class="metric"><span>Technicians</span><strong>{{ $stats['technicians'] }}</strong></article>
        <article class="metric"><span>Available</span><strong>{{ $stats['available'] }}</strong></article>
        <article class="metric"><span>Unavailable</span><strong>{{ $stats['unavailable'] }}</strong></article>
        <article class="metric"><span>Reviews</span><strong>{{ $stats['pendingReviews'] }}</strong></article>
        <article class="metric"><span>Pending</span><strong>{{ $stats['pending'] }}</strong></article>
        <article class="metric"><span>Completed</span><strong>{{ $stats['completed'] }}</strong></article>
    </section>

    @if (auth()->user()?->role === 'admin')
        <section id="technician-review-panel" class="panel">
            <div class="toolbar">
                <h2>Technician registration review</h2>
                <span class="badge pending">{{ $pendingTechnicianReviews->where('registration_review_status', 'pending')->count() }} pending</span>
            </div>
            <div class="review-scroll">
                <div class="review-grid">
                    @forelse ($pendingTechnicianReviews as $technician)
                        <article class="review-card">
                            <strong>{{ $technician->name }}</strong>
                            <span class="badge pending" style="margin-top:8px;">{{ $technician->registration_review_status }}</span>
                            <div class="item" style="margin-top:10px;">
                                <span>Email: {{ $technician->email }}</span>
                                <span>Phone: {{ $technician->phone ?: '-' }}</span>
                                <span>NIDA: {{ $formatNida($technician->nida) }}</span>
                                <span>Skills: {{ implode(', ', $technician->skills ?? []) ?: '-' }}</span>
                                <span>Payment: {{ str_replace('_', ' ', $technician->registration_payment_status ?? 'not_requested') }} · {{ $technician->registration_fee_currency ?? 'TZS' }} {{ number_format($technician->registration_fee_amount ?? $registrationFee) }}</span>
                                @if ($technician->registration_payment_requested_at)
                                    <span>Payment requested: {{ $technician->registration_payment_requested_at->diffForHumans() }}</span>
                                @endif
                            </div>
                            <div class="review-images">
                                <div>
                                    <label>NIDA ID</label>
                                    <div class="review-image">
                                        @if ($technician->nida_id_image)
                                            <img src="{{ $technician->nida_id_image }}" alt="NIDA ID for {{ $technician->name }}">
                                        @else
                                            <span>No NIDA image</span>
                                        @endif
                                    </div>
                                </div>
                                <div>
                                    <label>Face</label>
                                    <div class="review-image">
                                        @if ($technician->face_image)
                                            <img src="{{ $technician->face_image }}" alt="Face photo for {{ $technician->name }}">
                                        @else
                                            <span>No face image</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <form class="review-actions" method="post" action="{{ route('technicians.registration-review', $technician) }}">
                                @csrf
                                @method('patch')
                                <input name="note" placeholder="Review note; required when rejecting">
                                <button class="button primary" name="decision" value="approved" type="submit">Approve</button>
                                <button class="button" name="decision" value="rejected" type="submit">Reject</button>
                            </form>
                        </article>
                    @empty
                        <p>No technician registrations need review.</p>
                    @endforelse
                </div>
            </div>
        </section>
    @endif

    <section class="grid">
        <div>
            <article class="panel">
                <h2>Recent requests</h2>
                <div class="table-scroll">
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
                </div>
            </article>

            <article class="panel">
                <div class="toolbar">
                    <h2>Field team</h2>
                    <span class="badge">{{ $stats['technicians'] }} members</span>
                </div>
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>Technician</th><th>NIDA</th><th>Skills</th><th>Contact</th><th>Payment</th><th>Requests</th><th>Last seen</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse ($technicians as $technician)
                            <tr>
                                <td>{{ $technician->name }}</td>
                                <td>{{ $formatNida($technician->nida) }}</td>
                                <td>{{ implode(', ', $technician->skills ?? []) ?: 'No skills listed' }}</td>
                                <td>{{ $technician->phone ?: $technician->email }}</td>
                                <td>
                                    <span class="badge {{ in_array($technician->registration_payment_status, ['success', 'settled'], true) ? '' : 'pending' }}">
                                        {{ str_replace('_', ' ', $technician->registration_payment_status ?? 'not requested') }}
                                    </span>
                                    <span>{{ $technician->registration_fee_currency ?? 'TZS' }} {{ number_format($technician->registration_fee_amount ?? $registrationFee) }}</span>
                                </td>
                                <td>
                                    @if ($technician->client_requests_blocked)
                                        <span class="badge rejected">Blocked</span>
                                        <span>{{ $technician->client_requests_blocked_reason ?: 'No reason supplied' }}</span>
                                    @else
                                        <span class="badge">Allowed</span>
                                    @endif
                                    @if (auth()->user()?->role === 'admin')
                                        <form class="request-block-form" method="post" action="{{ route('technicians.request-block', $technician) }}" style="margin-top:8px;">
                                            @csrf
                                            @method('patch')
                                            <input type="hidden" name="blocked" value="{{ $technician->client_requests_blocked ? '0' : '1' }}">
                                            @if ($technician->client_requests_blocked)
                                                <span class="helper">Unblock to allow new client requests.</span>
                                            @else
                                                <input name="reason" placeholder="Block reason">
                                            @endif
                                            <button class="button {{ $technician->client_requests_blocked ? 'primary' : '' }}" type="submit">
                                                {{ $technician->client_requests_blocked ? 'Unblock' : 'Block' }}
                                            </button>
                                        </form>
                                    @endif
                                </td>
                                <td>{{ $technician->last_seen_at?->diffForHumans() ?? 'Not seen yet' }}</td>
                                <td>
                                    <span class="badge {{ $technician->available ? '' : 'unavailable' }}">
                                        {{ $technician->available ? 'Available' : 'Unavailable' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8">No technicians registered.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </article>
        </div>

        <aside>
            <article class="panel">
                <h2>Technician availability</h2>
                <div class="split">
                    <div class="availability-card"><span>Available now</span><strong>{{ $stats['available'] }}</strong></div>
                    <div class="availability-card"><span>Unavailable</span><strong>{{ $stats['unavailable'] }}</strong></div>
                </div>
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>Technician</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                        @forelse ($technicians as $technician)
                            <tr>
                                <td>{{ $technician->name }}</td>
                                <td>
                                    <span class="badge {{ $technician->client_requests_blocked ? 'rejected' : ($technician->available ? '' : 'unavailable') }}">
                                        {{ $technician->client_requests_blocked ? 'Requests blocked' : ($technician->available ? 'Available' : 'Unavailable') }}
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        @if ($technician->client_requests_blocked)
                                            <span class="helper">Unblock requests first.</span>
                                        @else
                                            <form method="post" action="{{ route('technicians.availability', $technician) }}">
                                                @csrf
                                                @method('patch')
                                                <input type="hidden" name="available" value="{{ $technician->available ? '0' : '1' }}">
                                                <button class="button {{ $technician->available ? 'muted' : 'primary' }}" type="submit">
                                                    {{ $technician->available ? 'Set unavailable' : 'Set available' }}
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3">No technicians registered.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
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

            @if (auth()->user()?->role === 'admin')
                <article class="panel">
                    <h2>Registration fee</h2>
                    <form method="post" action="{{ route('settings.registration-fee') }}" class="list">
                        @csrf
                        @method('patch')
                        <label for="registration-fee">Amount (TZS)</label>
                        <input id="registration-fee" name="amount" type="number" min="{{ $registrationFeeMinimum }}" step="500" value="{{ $registrationFee }}">
                        <p class="helper">Minimum allowed fee is TZS {{ number_format($registrationFeeMinimum) }}.</p>
                        <button class="button primary" type="submit">Save registration fee</button>
                    </form>
                </article>
            @endif

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
    </div>
</main>
<script>
(() => {
    const live = document.getElementById('dispatch-live');
    if (!live) return;

    const refreshUrl = live.dataset.refreshUrl || window.location.href;
    const refreshMs = Math.max(Number.parseInt(live.dataset.refreshMs || '5000', 10), 3000);
    const updatedAt = document.getElementById('portal-updated-at');
    const statusBox = document.getElementById('portal-status');
    const errorBox = document.getElementById('portal-errors');
    let reviewPanel = document.getElementById('technician-review-panel');
    let inFlight = false;

    const interactiveTags = new Set(['INPUT', 'TEXTAREA', 'SELECT', 'BUTTON']);
    const hasActiveFieldIn = (element) => {
        if (!element) return false;
        const active = document.activeElement;
        return active && element.contains(active) && interactiveTags.has(active.tagName);
    };

    const showMessage = (message, error = false) => {
        const target = error ? errorBox : statusBox;
        const other = error ? statusBox : errorBox;
        if (!target) return;
        target.textContent = message || '';
        target.hidden = !message;
        if (other && message) {
            other.textContent = '';
            other.hidden = true;
        }
    };

    const syncBox = (doc, id) => {
        const source = doc.getElementById(id);
        const target = document.getElementById(id);
        if (!source || !target) return;
        target.textContent = source.textContent.trim();
        target.hidden = source.hidden || target.textContent.length === 0;
    };

    const syncFromDocument = (doc, force = false) => {
        const nextLive = doc.getElementById('dispatch-live');
        const nextReviewPanel = doc.getElementById('technician-review-panel');
        const nextUpdatedAt = doc.getElementById('portal-updated-at');

        if (nextLive && (force || !hasActiveFieldIn(live))) {
            live.innerHTML = nextLive.innerHTML;
            reviewPanel = document.getElementById('technician-review-panel');
        } else if (nextReviewPanel && reviewPanel && !hasActiveFieldIn(reviewPanel)) {
            reviewPanel.classList.add('is-refreshing');
            reviewPanel.replaceWith(nextReviewPanel.cloneNode(true));
            reviewPanel = document.getElementById('technician-review-panel');
        }

        if (nextUpdatedAt && updatedAt) {
            updatedAt.textContent = nextUpdatedAt.textContent;
        }
        syncBox(doc, 'portal-status');
        syncBox(doc, 'portal-errors');
    };

    const fetchPage = async (url, options = {}) => {
        const response = await fetch(url, {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'Accept': 'text/html',
                'X-Requested-With': 'XMLHttpRequest',
                ...(options.headers || {}),
            },
            ...options,
        });
        const html = await response.text();
        if (!response.ok) {
            throw new Error(html || 'The portal could not be updated.');
        }
        return new DOMParser().parseFromString(html, 'text/html');
    };

    const refreshLive = async (force = false) => {
        if (inFlight || (!force && document.hidden)) {
            return;
        }

        inFlight = true;
        live.classList.add('is-refreshing');
        try {
            const doc = await fetchPage(refreshUrl);
            syncFromDocument(doc, force);
        } catch (error) {
            console.warn('Portal live refresh failed', error);
        } finally {
            inFlight = false;
            live.classList.remove('is-refreshing');
        }
    };

    live.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;

        event.preventDefault();
        const submitter = event.submitter;
        const body = new FormData(form);
        if (submitter && submitter.name && !body.has(submitter.name)) {
            body.append(submitter.name, submitter.value);
        }

        inFlight = true;
        live.classList.add('is-refreshing');
        showMessage('Updating...');
        try {
            const doc = await fetchPage(form.action, {
                method: form.method || 'POST',
                body,
            });
            syncFromDocument(doc, true);
        } catch (error) {
            showMessage(error.message || 'The portal could not be updated.', true);
        } finally {
            inFlight = false;
            live.classList.remove('is-refreshing');
        }
    });

    window.setInterval(() => refreshLive(false), refreshMs);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refreshLive(false);
    });
})();
</script>
</body>
</html>
