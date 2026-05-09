const state = {
  overview: null,
};

const els = {
  apiBase: document.querySelector('#apiBase'),
  refreshBtn: document.querySelector('#refreshBtn'),
  pushStatus: document.querySelector('#pushStatus'),
  lastUpdated: document.querySelector('#lastUpdated'),
  totalClients: document.querySelector('#totalClients'),
  totalTechnicians: document.querySelector('#totalTechnicians'),
  availableTechnicians: document.querySelector('#availableTechnicians'),
  pendingRequests: document.querySelector('#pendingRequests'),
  requestsBody: document.querySelector('#requestsBody'),
  techniciansList: document.querySelector('#techniciansList'),
  skillsList: document.querySelector('#skillsList'),
};

function apiUrl() {
  return `${els.apiBase.value.replace(/\/$/, '')}/api/portal/overview`;
}

async function loadOverview() {
  els.refreshBtn.disabled = true;
  els.refreshBtn.textContent = 'Refreshing';
  try {
    const res = await fetch(apiUrl(), {
      headers: { Accept: 'application/json' },
    });
    state.overview = await decodeResponse(res);
    render();
  } catch (err) {
    els.requestsBody.innerHTML = `<tr><td colspan="5">Unable to load portal data: ${err.message}</td></tr>`;
    els.pushStatus.textContent = 'Offline';
  } finally {
    els.refreshBtn.disabled = false;
    els.refreshBtn.textContent = 'Refresh';
  }
}

async function decodeResponse(res) {
  const text = await res.text();
  let body = null;
  if (text) {
    try {
      body = JSON.parse(text);
    } catch (_) {
      body = null;
    }
  }

  if (!res.ok) {
    const message = body?.error || body?.message || `HTTP ${res.status}`;
    throw new Error(message);
  }

  if (!body) {
    throw new Error('Server returned an invalid response');
  }

  return body;
}

function render() {
  const data = state.overview;
  const stats = data.stats;
  els.totalClients.textContent = stats.totalClients;
  els.totalTechnicians.textContent = stats.totalTechnicians;
  els.availableTechnicians.textContent = stats.availableTechnicians;
  els.pendingRequests.textContent = stats.pendingRequests;
  els.pushStatus.textContent = data.firebasePush ? 'Configured' : 'Not configured';
  els.lastUpdated.textContent = new Date(data.generatedAt).toLocaleString();
  renderRequests(data.recentRequests);
  renderTechnicians(data.technicians);
  renderSkills(data.topSkills);
}

function renderRequests(requests) {
  if (!requests.length) {
    els.requestsBody.innerHTML = '<tr><td colspan="5">No requests yet</td></tr>';
    return;
  }
  els.requestsBody.innerHTML = requests.map((request) => {
    const client = request.client?.name || 'Client';
    const technician = request.technician?.name || 'Technician';
    const skill = request.skill || 'General service';
    return `
      <tr>
        <td>${escapeHtml(client)}</td>
        <td>${escapeHtml(technician)}</td>
        <td>${escapeHtml(skill)}</td>
        <td><span class="badge ${escapeHtml(request.status)}">${escapeHtml(request.status)}</span></td>
        <td>${request.distanceKm ?? '-'} km</td>
      </tr>
    `;
  }).join('');
}

function renderTechnicians(technicians) {
  if (!technicians.length) {
    els.techniciansList.innerHTML = '<p class="subtle">No technicians registered</p>';
    return;
  }
  els.techniciansList.innerHTML = technicians.map((tech) => `
    <article class="tech-card">
      <strong>${escapeHtml(tech.name)}</strong>
      <span>${escapeHtml((tech.skills || []).join(', ') || 'No skills listed')}</span>
      <span><span class="badge ${tech.available ? '' : 'cancelled'}">${tech.available ? 'Available' : 'Unavailable'}</span></span>
    </article>
  `).join('');
}

function renderSkills(skills) {
  if (!skills.length) {
    els.skillsList.innerHTML = '<p class="subtle">No skill demand yet</p>';
    return;
  }
  const max = Math.max(...skills.map((item) => item.count), 1);
  els.skillsList.innerHTML = skills.map((item) => `
    <div>
      <div class="bar-label"><span>${escapeHtml(item.skill)}</span><strong>${item.count}</strong></div>
      <div class="bar-track"><div class="bar-fill" style="width:${Math.max((item.count / max) * 100, 6)}%"></div></div>
    </div>
  `).join('');
}

function escapeHtml(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

els.refreshBtn.addEventListener('click', loadOverview);
loadOverview();
