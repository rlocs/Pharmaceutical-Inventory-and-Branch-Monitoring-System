// Notification Bell Client Logic - UPDATED for NotificationReadState
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
  let chatPollingInterval = null;
  let limit = 50;
  let filterText = '';
  let previousUnreadCount = 0;

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

  // Check for new chat messages
  async function checkChatMessages() {
    try {
      const res = await fetch('api/chat_api.php?action=get_conversations', {
        credentials: 'same-origin'
      });
      const data = await res.json();
      
      if (data.success && data.conversations) {
        let newMessages = [];
        
        data.conversations.forEach(conv => {
          if (conv.UnreadCount > 0) {
            // Check if this is a new message that needs notification
            const messageKey = `chat_msg_${conv.ConversationID}_${conv.LastMessageTimestamp}`;
            const alreadyNotified = localStorage.getItem(messageKey);
            
            if (!alreadyNotified) {
              newMessages.push({
                conversationId: conv.ConversationID,
                from: `${conv.FirstName} ${conv.LastName}`,
                message: conv.LastMessage || 'New message',
                timestamp: conv.LastMessageTimestamp
              });
              
              // Mark as notified in localStorage
              localStorage.setItem(messageKey, 'true');
              
              console.log('💬 NEW CHAT MESSAGE:', `${conv.FirstName}: ${conv.LastMessage}`);
            }
          }
        });
        
        // Show notification for new messages
        if (newMessages.length > 0) {
          showNewMessageNotification(newMessages);
        }
      }
    } catch (error) {
      console.error('Error checking chat messages:', error);
    }
  }

  // Show desktop notifications for new messages
  function showNewMessageNotification(messages) {
    if (!('Notification' in window)) {
      return;
    }

    if (Notification.permission === 'granted') {
      if (messages.length === 1) {
        const message = messages[0];
        const notification = new Notification(`💬 New message from ${message.from}`, {
          body: message.message,
          icon: '/favicon.ico',
          tag: 'chat-message'
        });

        notification.onclick = function() {
          window.focus();
          // Open notification dropdown on chat tab
          if (dropdown.classList.contains('hidden')) {
            dropdown.classList.remove('hidden');
          }
          switchToTab('chat');
          notification.close();
        };

        setTimeout(() => {
          notification.close();
        }, 7000);
        
      } else if (messages.length > 1) {
        const notification = new Notification(`💬 ${messages.length} new messages`, {
          body: `From ${messages.map(m => m.from).join(', ')}`,
          icon: '/favicon.ico',
          tag: 'chat-messages'
        });

        notification.onclick = function() {
          window.focus();
          if (dropdown.classList.contains('hidden')) {
            dropdown.classList.remove('hidden');
          }
          switchToTab('chat');
          notification.close();
        };

        setTimeout(() => {
          notification.close();
        }, 7000);
      }
    } else if (Notification.permission === 'default') {
      Notification.requestPermission().then(function(permission) {
        if (permission === 'granted') {
          showNewMessageNotification(messages);
        }
      });
    }
  }

  async function fetchSummary(){
    try {
      const res = await fetch('api/notifications.php?action=summary', {credentials:'same-origin'});
      const data = await res.json();
      
      if (!data.success) { 
        return { total: 0, chat: 0, alerts: 0 };
      }
      
      const total = data.summary.total || 0;
      
      // Check if total unread count increased
      if (total > previousUnreadCount && previousUnreadCount > 0) {
        console.log(`🔔 New notifications detected: ${previousUnreadCount} → ${total}`);
        // Check for new chat messages specifically
        await checkChatMessages();
      }
      
      previousUnreadCount = total;
      
      // Update display
      if (unreadTotalEl) unreadTotalEl.textContent = total;
      
      if (total > 0) {
        badge.classList.remove('hidden');
        countEl.classList.remove('hidden');
        countEl.textContent = total > 99 ? '99+' : String(total);
      } else {
        badge.classList.add('hidden');
        countEl.classList.add('hidden');
        countEl.textContent = '';
      }
      
      return data.summary;
    } catch (e) {
      console.error('Summary fetch error:', e);
      return { total: 0, chat: 0, alerts: 0 };
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
        
        // Mark as read using NotificationReadState
        if (id) {
          try {
            await fetch('api/notifications.php?action=mark_read', {
              method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'},
              body: JSON.stringify({notification_id: Number(id)})
            });
          } catch(e){}
        }
        
        // Navigate
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
      const data = await res.json();
      if (!data.success) { return []; }
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
    
    // Check for new chat messages more frequently
    if (chatPollingInterval) clearInterval(chatPollingInterval);
    chatPollingInterval = setInterval(checkChatMessages, 10000);
  }

  function switchToTab(tabName) {
    document.querySelectorAll('.notification-tab').forEach(t => t.classList.remove('active'));
    document.querySelector(`.notification-tab[data-tab="${tabName}"]`).classList.add('active');
    
    listAll.classList.add('hidden');
    listAlerts.classList.add('hidden');
    listChat.classList.add('hidden');
    
    if (tabName === 'alerts') {
      listAlerts.classList.remove('hidden');
    } else if (tabName === 'chat') {
      listChat.classList.remove('hidden');
    } else {
      listAll.classList.remove('hidden');
    }
  }

  // Toggle dropdown
  bellButton.addEventListener('click', () => {
    dropdown.classList.toggle('hidden');
    if (!dropdown.classList.contains('hidden')) {
      refresh();
      if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
      }
    }
  });
  
  closeBtn?.addEventListener('click', () => dropdown.classList.add('hidden'));

  // Tab switching
  document.querySelectorAll('.notification-tab').forEach(tab => {
    tab.addEventListener('click', async () => {
      const tabName = tab.getAttribute('data-tab');
      switchToTab(tabName);
      
      if (tabName === 'alerts') {
        const items = await fetchList('alerts');
        renderList(listAlerts, items);
        emptyAlerts.classList.toggle('hidden', (items||[]).length > 0);
      } else if (tabName === 'chat') {
        const items = await fetchList('chat');
        renderList(listChat, items);
        emptyChat.classList.toggle('hidden', (items||[]).length > 0);
      } else {
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

  // Initialize
  function initialize() {
    // Request notification permission
    if ('Notification' in window && Notification.permission === 'default') {
      Notification.requestPermission();
    }
    
    refresh();
    startPolling();
    
    console.log('🔔 Notification system started with NotificationReadState support');
  }

  initialize();
})();