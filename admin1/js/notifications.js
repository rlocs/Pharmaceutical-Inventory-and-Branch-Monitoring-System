/**
 * Notification System - Handles bell icon notifications
 * Integrates with notification_api.php and displays alerts/chat notifications
 */

(function() {
    let notificationState = {
        notifications: [],
        unreadCount: 0,
        alertsCount: 0,
        chatCount: 0,
        currentTab: 'all',
        pollInterval: null
    };

    // DOM Elements
    const bellButton = document.getElementById('notification-bell-btn') || document.querySelector('button[aria-label="Notifications"]');
    const notificationDropdown = document.getElementById('notification-dropdown');
    const notificationsList = document.getElementById('notifications-list');
    const notificationBadge = document.getElementById('notification-badge');
    const notificationCount = document.getElementById('notification-count');
    const markAllReadBtn = document.getElementById('mark-all-read-btn');
    const closeDropdownBtn = document.getElementById('close-notification-dropdown');
    const viewAllLink = document.getElementById('view-all-notifications');

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        if (!bellButton) {
            console.warn('Bell notification button not found');
            return;
        }

        // Add relative positioning to bell button for badge
        if (bellButton && notificationBadge && notificationCount) {
            bellButton.style.position = 'relative';
            if (!bellButton.contains(notificationBadge)) {
                bellButton.appendChild(notificationBadge);
            }
            if (!bellButton.contains(notificationCount)) {
                bellButton.appendChild(notificationCount);
            }
        }

        // Event listeners
        bellButton.addEventListener('click', toggleNotificationDropdown);
        if (closeDropdownBtn) {
            closeDropdownBtn.addEventListener('click', closeNotificationDropdown);
        }
        if (markAllReadBtn) {
            markAllReadBtn.addEventListener('click', markAllAsRead);
        }

        // Tab switching
        document.querySelectorAll('.notification-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                switchTab(this.dataset.tab);
            });
        });

        // Close on outside click
        document.addEventListener('DOMContentLoaded', function() {
            if (notificationDropdown && 
                !notificationDropdown.contains(e.target) && 
                !bellButton.contains(e.target)) {
                closeNotificationDropdown();
            }
        });

        // Load notifications
        loadNotifications();
        
    // Poll for new notifications every 30 seconds
    notificationState.pollInterval = setInterval(loadNotifications, 30000);
    
    // Poll specifically for new chat messages every 15 seconds
    notificationState.chatPollInterval = setInterval(checkForNewChatMessages, 15000);
    
    // Request notification permission on page load
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }
});

    function toggleNotificationDropdown() {
        if (!notificationDropdown) return;
        
        if (notificationDropdown.classList.contains('hidden')) {
            openNotificationDropdown();
        } else {
            closeNotificationDropdown();
        }
    }

    function openNotificationDropdown() {
        if (!notificationDropdown) return;
        notificationDropdown.classList.remove('hidden');
        loadNotifications();
    }

    function closeNotificationDropdown() {
        if (!notificationDropdown) return;
        notificationDropdown.classList.add('hidden');
    }

    function switchTab(tab) {
        notificationState.currentTab = tab;
        
        // Update tab UI
        document.querySelectorAll('.notification-tab').forEach(t => {
            t.classList.remove('active');
            if (t.dataset.tab === tab) {
                t.classList.add('active');
            }
        });
        
        renderNotifications();
    }

    function loadNotifications() {
        fetch('api/notification_api.php?action=get_notifications', {
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                notificationState.notifications = data.notifications || [];
                notificationState.unreadCount = data.unread_count || 0;
                notificationState.alertsCount = data.alerts_count || 0;
                notificationState.chatCount = data.chat_count || 0;
                
                updateBadge();
                renderNotifications();
                
                // Also load alerts and chat notifications
                loadAlertsNotifications();
                loadChatNotifications();
            }
        })
        .catch(error => {
            console.error('Error loading notifications:', error);
            if (notificationsList) {
                notificationsList.innerHTML = '<div class="p-4 text-center text-red-500 text-sm">Error loading notifications</div>';
            }
        });
    }

    function loadAlertsNotifications() {
        fetch('api/medicine_api.php?action=get_alerts', {
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.alerts) {
                // Add alert notifications to the list
                const alerts = data.alerts;
                const alertNotifications = [];
                
                // Low Stock
                if (alerts.lowStock && alerts.lowStock.length > 0) {
                    alertNotifications.push({
                        type: 'alert',
                        title: 'Low Stock Alert',
                        message: `${alerts.lowStock.length} medicine(s) are running low on stock`,
                        link: 'med_inventory.php',
                        created_at: new Date().toISOString(),
                        is_read: 0
                    });
                }
                
                // Out of Stock
                if (alerts.outOfStock && alerts.outOfStock.length > 0) {
                    alertNotifications.push({
                        type: 'warning',
                        title: 'Out of Stock',
                        message: `${alerts.outOfStock.length} medicine(s) are out of stock`,
                        link: 'med_inventory.php',
                        created_at: new Date().toISOString(),
                        is_read: 0
                    });
                }
                
                // Expiring Soon
                if (alerts.expiringSoon && alerts.expiringSoon.length > 0) {
                    alertNotifications.push({
                        type: 'warning',
                        title: 'Expiring Soon',
                        message: `${alerts.expiringSoon.length} medicine(s) will expire within 30 days`,
                        link: 'med_inventory.php',
                        created_at: new Date().toISOString(),
                        is_read: 0
                    });
                }
                
                // Expired
                if (alerts.expired && alerts.expired.length > 0) {
                    alertNotifications.push({
                        type: 'error',
                        title: 'Expired Items',
                        message: `${alerts.expired.length} medicine(s) have expired`,
                        link: 'med_inventory.php',
                        created_at: new Date().toISOString(),
                        is_read: 0
                    });
                }
                
                // Merge with existing notifications
                notificationState.notifications = [
                    ...alertNotifications,
                    ...notificationState.notifications.filter(n => n.type !== 'alert' && n.type !== 'warning' && n.type !== 'error')
                ];
            }
        })
        .catch(error => {
            console.error('Error loading alerts:', error);
        });
    }

    function loadChatNotifications() {
        fetch('api/chat_api.php?action=get_conversations', {
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.conversations) {
                const chatNotifications = [];
                
                data.conversations.forEach(conv => {
                    if (conv.UnreadCount > 0) {
                        chatNotifications.push({
                            type: 'chat',
                            title: `New message from ${conv.FirstName} ${conv.LastName}`,
                            message: conv.LastMessage || 'You have unread messages',
                            link: '#chat',
                            created_at: conv.LastMessageTimestamp || new Date().toISOString(),
                            is_read: 0,
                            unread_count: conv.UnreadCount
                        });
                    }
                });
                
                // Merge with existing notifications
                notificationState.notifications = [
                    ...chatNotifications,
                    ...notificationState.notifications.filter(n => n.type !== 'chat')
                ];
            }
        })
        .catch(error => {
            console.error('Error loading chat notifications:', error);
        });
    }

    function renderNotifications() {
        if (!notificationsList) return;
        
        let filtered = [...notificationState.notifications];
        
        // Filter by tab
        if (notificationState.currentTab === 'alerts') {
            filtered = filtered.filter(n => ['alert', 'warning', 'error'].includes(n.type));
        } else if (notificationState.currentTab === 'chat') {
            filtered = filtered.filter(n => n.type === 'chat');
        }
        
        // Sort by date (newest first)
        filtered.sort((a, b) => {
            const dateA = new Date(a.created_at || a.CreatedAt || 0);
            const dateB = new Date(b.created_at || b.CreatedAt || 0);
            return dateB - dateA;
        });
        
        if (filtered.length === 0) {
            notificationsList.innerHTML = `
                <div class="p-8 text-center text-gray-500">
                    <svg class="w-12 h-12 mx-auto mb-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                    </svg>
                    <p class="text-sm">No notifications</p>
                </div>
            `;
            return;
        }
        
        notificationsList.innerHTML = filtered.map(notif => {
            const isRead = notif.is_read === 1 || notif.IsRead === 1;
            const title = notif.title || notif.Title || 'Notification';
            const message = notif.message || notif.Message || '';
            const type = notif.type || notif.Type || 'info';
            const createdAt = notif.created_at || notif.CreatedAt || new Date().toISOString();
            const timeAgo = formatTimeAgo(new Date(createdAt));
            const link = notif.link || notif.Link || '#';
            const notificationId = notif.notification_id || notif.NotificationID;
            
            return `
                <div class="notification-item ${isRead ? 'read' : 'unread'}" 
                     data-notification-id="${notificationId || ''}"
                     onclick="handleNotificationClick(${notificationId || 'null'}, '${escapeHtml(link)}')">
                    <div class="flex items-start gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="notification-type-badge ${type}">${type}</span>
                                <span class="notification-title">${escapeHtml(title)}</span>
                            </div>
                            <div class="notification-message">${escapeHtml(message)}</div>
                            <div class="notification-time">${timeAgo}</div>
                        </div>
                        ${!isRead ? '<div class="w-2 h-2 bg-indigo-600 rounded-full flex-shrink-0 mt-2"></div>' : ''}
                    </div>
                </div>
            `;
        }).join('');
    }

    function updateBadge() {
        const totalUnread = notificationState.unreadCount + notificationState.alertsCount + notificationState.chatCount;
        
        if (totalUnread > 0) {
            if (totalUnread > 99) {
                notificationCount.textContent = '99+';
                notificationCount.classList.remove('hidden');
                notificationBadge.classList.add('hidden');
            } else {
                notificationCount.textContent = totalUnread;
                notificationCount.classList.remove('hidden');
                notificationBadge.classList.add('hidden');
            }
        } else {
            notificationCount.classList.add('hidden');
            notificationBadge.classList.add('hidden');
        }
    }

    function markAllAsRead() {
        fetch('api/notification_api.php?action=mark_all_read', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                notificationState.notifications.forEach(n => {
                    n.is_read = 1;
                    n.IsRead = 1;
                });
                notificationState.unreadCount = 0;
                updateBadge();
                renderNotifications();
            }
        })
        .catch(error => {
            console.error('Error marking all as read:', error);
        });
    }

    function formatTimeAgo(date) {
        const now = new Date();
        const diff = now - date;
        const seconds = Math.floor(diff / 1000);
        const minutes = Math.floor(seconds / 60);
        const hours = Math.floor(minutes / 60);
        const days = Math.floor(hours / 24);
        
        if (days > 0) return `${days} day${days > 1 ? 's' : ''} ago`;
        if (hours > 0) return `${hours} hour${hours > 1 ? 's' : ''} ago`;
        if (minutes > 0) return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
        return 'Just now';
    }

    function escapeHtml(text) {
        if (text === undefined || text === null) return '';
        const div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

    // Global function to handle notification click
    window.handleNotificationClick = function(notificationId, link) {
        if (notificationId) {
            // Mark as read
            fetch('api/notification_api.php?action=mark_read', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ notification_id: notificationId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update local state
                    const notif = notificationState.notifications.find(n => 
                        (n.notification_id || n.NotificationID) == notificationId
                    );
                    if (notif) {
                        notif.is_read = 1;
                        notif.IsRead = 1;
                    }
                    notificationState.unreadCount = Math.max(0, notificationState.unreadCount - 1);
                    updateBadge();
                    renderNotifications();
                }
            })
            .catch(error => {
                console.error('Error marking notification as read:', error);
            });
        }
        
        // Navigate to link
        if (link && link !== '#') {
            if (link.startsWith('#')) {
                // Handle hash links (like #chat)
                if (link === '#chat') {
                    // Open sidebar chat
                    if (typeof toggleSidebar === 'function') {
                        toggleSidebar();
                    }
                }
            } else {
                window.location.href = link;
            }
        }
        
        closeNotificationDropdown();
    };

    // Expose functions globally
    window.loadNotifications = loadNotifications;

    // Add this function to notifications.js to check for new chat messages specifically
function checkForNewChatMessages() {
    fetch('api/chat_api.php?action=get_conversations', {
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.conversations) {
            let totalUnreadChats = 0;
            let newMessages = [];
            
            data.conversations.forEach(conv => {
                if (conv.UnreadCount > 0) {
                    totalUnreadChats += conv.UnreadCount;
                    
                    // Check if this is a new message that we haven't notified about yet
                    const messageKey = `chat_${conv.ConversationID}_${conv.LastMessageTimestamp}`;
                    const alreadyNotified = localStorage.getItem(messageKey);
                    
                    if (!alreadyNotified && conv.UnreadCount > 0) {
                        newMessages.push({
                            conversationId: conv.ConversationID,
                            from: `${conv.FirstName} ${conv.LastName}`,
                            message: conv.LastMessage,
                            timestamp: conv.LastMessageTimestamp
                        });
                        
                        // Mark as notified
                        localStorage.setItem(messageKey, 'true');
                    }
                }
            });
            
            // Show push notification for new messages
            if (newMessages.length > 0) {
                showChatPushNotification(newMessages);
            }
            
            // Update chat count
            notificationState.chatCount = totalUnreadChats;
            updateBadge();
        }
    })
    .catch(error => {
        console.error('Error checking for new chat messages:', error);
    });
}

// Add this function to show push notifications for new chat messages
function showChatPushNotification(messages) {
    if (!('Notification' in window)) {
        console.warn('This browser does not support desktop notifications');
        return;
    }

    if (Notification.permission === 'granted') {
        if (messages.length === 1) {
            const message = messages[0];
            const notification = new Notification(`New message from ${message.from}`, {
                body: message.message || 'You have a new message',
                icon: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjQiIGhlaWdodD0iMjQiIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTIxIDE1YTIgMiAwIDAgMS0yIDJIN2wtNCA0VjVhMiAyIDAgMCAxIDItMmgxNGEyIDIgMCAwIDEgMiAydjEwWiIgZmlsbD0iIzRmNDZlNSIvPgo8L3N2Zz4K',
                tag: 'chat-message',
                requireInteraction: true
            });

            notification.onclick = function() {
                window.focus();
                // Open chat interface
                if (typeof toggleSidebar === 'function') {
                    toggleSidebar();
                }
                notification.close();
            };
        } else {
            const notification = new Notification(`You have ${messages.length} new messages`, {
                body: `From ${messages.map(m => m.from).join(', ')}`,
                icon: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjQiIGhlaWdodD0iMjQiIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTIxIDE1YTIgMiAwIDAgMS0yIDJIN3wtNCA0VjVhMiAyIDAgMCAxIDItMmgxNGEyIDIgMCAwIDEgMiAydjEwWiIgZmlsbD0iIzRmNDZlNSIvPgo8L3N2Zz4K',
                tag: 'chat-messages',
                requireInteraction: true
            });

            notification.onclick = function() {
                window.focus();
                // Open notification dropdown on chat tab
                openNotificationDropdown();
                switchTab('chat');
                notification.close();
            };
        }

        // Auto-close after 7 seconds
        setTimeout(() => {
            notification.close();
        }, 7000);
        
    } else if (Notification.permission !== 'denied') {
        Notification.requestPermission().then(function(permission) {
            if (permission === 'granted') {
                showChatPushNotification(messages);
            }
        });
    }
}
})();

