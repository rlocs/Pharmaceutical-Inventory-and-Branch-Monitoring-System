// Notification Bell Client Logic (enhanced with search and load more)
(function(){
  const bellButton = document.getElementById('notification-bell-btn');
  const dropdown = document.getElementById('notification-dropdown');
  const badge = document.getElementById('notification-badge');
  const countEl = document.getElementById('notification-count');
  const unreadTotalEl = document.getElementById('notification-unread-total');
  const searchInput = document.getElementById('notification-search');
  const updatedAt = document.getElementById('notification-updated-at');
  const btnLoadMore = document.getElementById('notification-load-more');

  const listAll = document.getElementById('list-all');
  const listAlerts = document.getElementById('list-alerts');
  const listChat = document.getElementById('list-chat');

  const emptyAll = document.getElementById('empty-all');
  const emptyAlerts = document.getElementById('empty-alerts');
  const emptyChat = document.getElementById('empty-chat');

  const markAllBtn = document.getElementById('mark-all-read-btn');
  const closeBtn = document.getElementById('close-notification-dropdown');

  if (!bellButton || !dropdown) return;

  let pollingInterval = null;
  let limit = 50; // can increase with load more
  let filterText = '';
  let previousChatCount = 0;
  let previousAlertsCount = 0;

  function timeAgo(ts){
    const dt = new Date(ts);
    const s = Math.floor((Date.now() - dt.getTime())/1000);
    if (s < 60) return `${s}s ago`;
    const m = Math.floor(s/60); if (m < 60) return `${m}m ago`;
    const h = Math.floor(m/60); if (h < 24) return `${h}h ago`;
    const d = Math.floor(h/24); return `${d}d ago`;
  }

  function escapeHtml(str=''){
    return String(str).replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[s]));
  }

  async function fetchSummary(){
    try {
      const res = await fetch('api/notifications.php?action=summary', {credentials:'same-origin'});
      const ct = res.headers.get('content-type') || '';
      if (ct.indexOf('application/json') === -1) {
        console.error('Summary: Non-JSON response', res.status, await res.text());
        return;
      }
      const data = await res.json();
      if (!data.success) { console.warn('Summary: Not successful', data); return; }
      const total = data.summary.total || 0;
      unreadTotalEl && (unreadTotalEl.textContent = total);
      if (total > 0) {
        badge.classList.remove('hidden');
        countEl.classList.remove('hidden');
        countEl.textContent = total > 99 ? '99+' : String(total);
      } else {
        badge.classList.add('hidden');
        countEl.classList.add('hidden');
        countEl.textContent = '';
      }
    } catch (e) {
      console.error('Summary fetch error:', e);
    }
  }

  function badgeClass(type){
    switch ((type||'').toLowerCase()){
      case 'inventory': return 'badge-inventory';
      case 'med': return 'badge-med';
      case 'chat': return 'badge-chat';
      case 'pos': return 'badge-pos';
      case 'reports': return 'badge-reports';
      case 'account': return 'badge-account';
      default: return 'badge-med';
    }
  }

  function renderList(targetEl, items){
    const filtered = filterText
      ? items.filter(n => (n.Title||'').toLowerCase().includes(filterText) || (n.Message||'').toLowerCase().includes(filterText))
      : items;

    if (filtered.length === 0){
      targetEl.innerHTML = '';
      return;
    }

    targetEl.innerHTML = filtered.map(n => {
      const type = n.Type || 'med';
      const cat = n.Category || '';
      const title = n.Title || 'Notification';
      const msg = n.Message || '';
      const link = n.Link || '#';
      const id = n.NotificationID;
      const isRead = Number(n.IsRead) === 1;
      const created = n.CreatedAt || new Date().toISOString();
      return `
        <div class="notification-item ${isRead ? 'read' : 'unread'}" data-id="${id}" data-link="${escapeHtml(link)}">
          <div>
            <div class="flex items-center gap-2 mb-1">
              <span class="notification-type-badge ${badgeClass(type)}">${escapeHtml(cat||type)}</span>
              <span class="notification-title">${escapeHtml(title)}</span>
            </div>
            <div class="notification-message">${escapeHtml(msg)}</div>
          </div>
          <div class="notification-time">${timeAgo(created)}</div>
        </div>`;
    }).join('');

    targetEl.querySelectorAll('.notification-item').forEach(el => {
      el.addEventListener('click', async () => {
        const id = el.getAttribute('data-id');
        const link = el.getAttribute('data-link');
        // mark as read
        if (id) {
          try {
            await fetch('api/notifications.php?action=mark_read', {
              method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'},
              body: JSON.stringify({notification_id: Number(id)})
            });
          } catch(e){}
        }
        // navigate
        if (link && link !== '#') window.location.href = link;
        refresh();
      });
    });
  }

  async function fetchList(which){
    const url = which === 'alerts' ? `api/notifications.php?action=list&type=alerts&limit=${limit}`
              : which === 'chat' ? `api/notifications.php?action=list&type=chat&limit=${limit}`
              : `api/notifications.php?action=list&type=all&limit=${limit}`;
    try {
      const res = await fetch(url, {credentials:'same-origin'});
      const ct = res.headers.get('content-type') || '';
      if (ct.indexOf('application/json') === -1) {
        const text = await res.text();
        console.error(`List (${which}): Non-JSON response`, res.status, text);
        return [];
      }
      const data = await res.json();
      if (!data.success) { console.warn(`List (${which}): Not successful`, data); return []; }
      return data.notifications || [];
    } catch (e) {
      console.error(`List (${which}) fetch error:`, e);
      return [];
    }
  }

  async function refresh(){
    await fetchSummary();
    const [all, alerts, chat] = await Promise.all([
      fetchList('all'), fetchList('alerts'), fetchList('chat')
    ]);

    renderList(listAll, all);
    renderList(listAlerts, alerts);
    renderList(listChat, chat);

    emptyAll.classList.toggle('hidden', (all||[]).length > 0);
    emptyAlerts.classList.toggle('hidden', (alerts||[]).length > 0);
    emptyChat.classList.toggle('hidden', (chat||[]).length > 0);

    if (updatedAt) updatedAt.textContent = 'just now';
  }

  function startPolling(){
    if (pollingInterval) clearInterval(pollingInterval);
    pollingInterval = setInterval(refresh, 15000);
  }

  // Toggle dropdown
  bellButton.addEventListener('click', () => {
    dropdown.classList.toggle('hidden');
    if (!dropdown.classList.contains('hidden')) refresh();
  });
  closeBtn?.addEventListener('click', () => dropdown.classList.add('hidden'));

  // Tab switching
  document.querySelectorAll('.notification-tab').forEach(tab => {
    tab.addEventListener('click', async () => {
      document.querySelectorAll('.notification-tab').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      const tabName = tab.getAttribute('data-tab');
      listAll.classList.add('hidden');
      listAlerts.classList.add('hidden');
      listChat.classList.add('hidden');
      if (tabName === 'alerts') {
        listAlerts.classList.remove('hidden');
        const items = await fetchList('alerts');
        renderList(listAlerts, items);
        emptyAlerts.classList.toggle('hidden', (items||[]).length > 0);
      } else if (tabName === 'chat') {
        listChat.classList.remove('hidden');
        const items = await fetchList('chat');
        renderList(listChat, items);
        emptyChat.classList.toggle('hidden', (items||[]).length > 0);
      } else {
        listAll.classList.remove('hidden');
        const items = await fetchList('all');
        renderList(listAll, items);
        emptyAll.classList.toggle('hidden', (items||[]).length > 0);
      }
    });
  });

  // Search filter
  searchInput?.addEventListener('input', (e) => {
    filterText = (e.target.value || '').trim().toLowerCase();
    const active = document.querySelector('.notification-tab.active')?.getAttribute('data-tab') || 'all';
    if (active === 'alerts') fetchList('alerts').then(items => renderList(listAlerts, items));
    else if (active === 'chat') fetchList('chat').then(items => renderList(listChat, items));
    else fetchList('all').then(items => renderList(listAll, items));
  });

  // Load more
  btnLoadMore?.addEventListener('click', () => {
    limit = Math.min(200, limit + 50);
    refresh();
  });

  // Mark all read
  markAllBtn?.addEventListener('click', async () => {
    try {
      await fetch('api/notifications.php?action=mark_all_read', {method:'POST', credentials:'same-origin'});
      refresh();
    } catch(e){}
  });

  function showPushNotification(title, body, type) {
    if (!('Notification' in window)) {
      console.warn('This browser does not support desktop notifications');
      return;
    }

    if (Notification.permission === 'granted') {
      const notification = new Notification(title, {
        body: body,
        icon: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjQiIGhlaWdodD0iMjQiIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTYuOCA4YTYuNiA2LjYgMCAxIDEgMTIgMGMwIDcuIDMgOSA2IDloLTZzMy0yIDMtOVoiIGZpbGw9IiM0ZjQ2ZTUiLz4KPHBhdGggZD0iTTEwLjM3NSAyMmEyIDIgMCAwIDAgMy4yNSAwIiBmaWxsPSIjNGY0NmU1Ii8+Cjwvc3ZnPg==',
        tag: type,
        requireInteraction: false
      });

      notification.onclick = function() {
        window.focus();
        dropdown.classList.toggle('hidden');
        if (dropdown.classList.contains('hidden')) {
          // Switch to the relevant tab
          if (type === 'chat') {
            document.querySelectorAll('.notification-tab').forEach(t => t.classList.remove('active'));
            document.querySelector('.notification-tab[data-tab="chat"]').classList.add('active');
            listAll.classList.add('hidden');
            listAlerts.classList.add('hidden');
            listChat.classList.remove('hidden');
            // Mark all chat notifications as read when opening chat tab
            fetch('./api/notifications.php?action=mark_all_read&type=chat', { method: 'POST', credentials: 'same-origin' })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        // Update badge count after marking as read
                        fetchSummary();
                    }
                })
                .catch(err => console.error('Error marking chat notifications as read:', err));
            fetchList('chat').then(items => renderList(listChat, items));
          } else if (type === 'alerts') {
            document.querySelectorAll('.notification-tab').forEach(t => t.classList.remove('active'));
            document.querySelector('.notification-tab[data-tab="alerts"]').classList.add('active');
            listAll.classList.add('hidden');
            listChat.classList.add('hidden');
            listAlerts.classList.remove('hidden');
            // Mark all alerts notifications as read when opening alerts tab
            fetch('./api/notifications.php?action=mark_all_read&type=alerts', { method: 'POST', credentials: 'same-origin' })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        // Update badge count after marking as read
                        fetchSummary();
                    }
                })
                .catch(err => console.error('Error marking alerts notifications as read:', err));
            fetchList('alerts').then(items => renderList(listAlerts, items));
          }
        }
        notification.close();
      };

      setTimeout(() => {
        notification.close();
      }, 5000);
    } else if (Notification.permission !== 'denied') {
      Notification.requestPermission().then(function(permission) {
        if (permission === 'granted') {
          showPushNotification(title, body, type);
        }
      });
    }
  }

  // Initialize
  refresh();
  startPolling();
})();
