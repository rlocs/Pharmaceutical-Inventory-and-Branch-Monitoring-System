document.addEventListener('DOMContentLoaded', () => {
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');

    // Toggle mobile menu
    if (mobileMenuButton) {
        mobileMenuButton.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
        });
    }
});

// --- CROSS-BRANCH CHAT WIDGET ---
document.addEventListener('DOMContentLoaded', () => {
    const chatWidget = document.getElementById('chat-widget');
    if (!chatWidget) return;

    const chatHeader = chatWidget.querySelector('#chat-header');
    const chatBody = chatWidget.querySelector('#chat-body');
    const chatNotificationBubble = chatWidget.querySelector('#chat-notification-bubble');
    const chatToggleButton = chatWidget.querySelector('#chat-toggle-button');
    const zoomHost = document.getElementById('zoomed-chat-host');

    let messageStatusMap = new Map();
    let conversations = [];
    let currentView = 'list';
    let activeConversationId = null;
    let pollingInterval;
    let isZoomed = false;

    const API_URL = 'api/chat_api.php';

    // --- UI Functions ---
    function updateNotificationBubble(count) {
        if (count > 0) {
            chatNotificationBubble.textContent = count > 99 ? '99+' : count;
            chatNotificationBubble.classList.remove('hidden');
        } else {
            chatNotificationBubble.classList.add('hidden');
        }
        document.dispatchEvent(new CustomEvent('chat:new-messages', { 
            detail: { unreadCount: count } 
        }));
    }

    function renderMessageStatus(status) {
        const icons = {
            sending: '<svg class="w-3 h-3 text-gray-400" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/></svg>',
            sent: '<svg class="w-3 h-3 text-gray-400" viewBox="0 0 24 24"><path stroke="currentColor" stroke-width="2" d="M5 13l4 4L19 7" fill="none"/></svg>',
            delivered: '<svg class="w-3 h-3 text-blue-400" viewBox="0 0 24 24"><path stroke="currentColor" stroke-width="2" d="M5 13l4 4L19 7" fill="none"/></svg>',
            failed: '<svg class="w-3 h-3 text-red-500" viewBox="0 0 24 24"><path stroke="currentColor" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" fill="none"/></svg>'
        };
        return icons[status] || icons.sent;
    }

    function renderConversationList() {
        currentView = 'list';
        activeConversationId = null;
        chatBody.innerHTML = '';

        if (!conversations || conversations.length === 0) {
            chatBody.innerHTML = '<p class="text-center text-gray-500 text-sm p-4">No conversations yet.</p>';
            return;
        }

        // Group by unique participants
        const uniqueConvs = new Map();
        conversations.forEach(conv => {
            const key = `${conv.OtherParticipantName}|${conv.OtherParticipantRole}|${conv.OtherParticipantBranch}`;
            if (!uniqueConvs.has(key) || new Date(conv.LastMessageTimestamp) > new Date(uniqueConvs.get(key).LastMessageTimestamp)) {
                uniqueConvs.set(key, conv);
            }
        });

        let totalUnread = 0;
        uniqueConvs.forEach(conv => {
            const unreadCount = parseInt(conv.UnreadCount || 0);
            totalUnread += unreadCount;

            const el = document.createElement('div');
            el.className = 'p-2 border-b border-gray-200 cursor-pointer hover:bg-gray-100';
            el.dataset.conversationId = conv.ConversationID;
            el.innerHTML = `
                <div class="flex justify-between items-center">
                    <div>
                        <p class="font-semibold text-sm">${conv.OtherParticipantName}</p>
                        <p class="text-xs text-gray-500">${conv.OtherParticipantRole} - ${conv.OtherParticipantBranch || 'Branch undefined'}</p>
                    </div>
                    ${unreadCount > 0 ? `<span class="bg-blue-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center">${unreadCount}</span>` : ''}
                </div>
                ${conv.LastMessage ? `<p class="text-xs text-gray-500 truncate mt-1">${conv.LastMessage}</p>` : ''}
            `;
            el.addEventListener('click', () => openConversation(conv.ConversationID));
            chatBody.appendChild(el);
        });

        updateNotificationBubble(totalUnread);
    }

    function renderMessagesView(conversationId, messages, participant) {
        currentView = 'messages';
        activeConversationId = conversationId;

        chatBody.innerHTML = `
            <div class="p-2 border-b bg-white">
                <button id="back-to-list" class="text-sm text-blue-600 hover:underline">&larr; Back</button>
                <div class="mt-1">
                        <p class="font-semibold text-sm">${participant?.OtherParticipantName || 'Chat'}</p>
                        <p class="text-xs text-gray-500">${participant?.OtherParticipantRole} - ${participant?.OtherParticipantBranch || 'Branch undefined'}</p>
                </div>
            </div>
            <div id="messages-container" class="p-2 space-y-2 overflow-y-auto" style="max-height: ${isZoomed ? '400px' : '200px'}">
                ${messages.map(msg => {
                    const isSender = String(msg.SenderUserID) === String(window.currentUserId);
                    const status = messageStatusMap.get(msg.MessageID) || 'delivered';
                    return `
                        <div class="flex flex-col ${isSender ? 'items-end' : 'items-start'}" data-message-id="${msg.MessageID}">
                            <div class="max-w-[75%] px-3 py-2 rounded-lg ${isSender ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-800'}">
                                <p class="text-sm break-words">${msg.MessageContent}</p>
                            </div>
                            <div class="flex items-center gap-1 mt-1">
                                <span class="text-xs text-gray-400">
                                    ${new Date(msg.Timestamp).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
                                </span>
                                ${isSender ? renderMessageStatus(status) : ''}
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
            <div class="p-2 border-t bg-white">
                <form id="message-form" class="flex items-center gap-2">
                    <input type="text" id="message-input" placeholder="Type a message..." 
                           class="flex-1 rounded-lg border border-gray-300 p-2 text-sm" required>
                    <button type="submit" class="p-2 text-blue-600 hover:bg-gray-100 rounded-full">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                    </button>
                </form>
            </div>
        `;

        document.getElementById('back-to-list').addEventListener('click', fetchConversations);
        setupMessageForm();

        const container = document.getElementById('messages-container');
        container.scrollTop = container.scrollHeight;
    }

    function renderUsersView(users) {
        currentView = 'users';
        activeConversationId = null;

        chatBody.innerHTML = `
            <div class="p-2 border-b">
                <button id="back-to-list" class="text-sm text-blue-600 hover:underline">&larr; Back</button>
            </div>
            <div class="p-2">
                ${users.map(user => `
                    <div class="p-2 cursor-pointer hover:bg-gray-100 rounded-lg mb-1" data-user-id="${user.UserID}">
                        <p class="font-semibold text-sm">${user.FirstName} ${user.LastName}</p>
                        <p class="text-xs text-gray-500">${user.Role} - Branch ${user.BranchID}</p>
                    </div>
                `).join('')}
            </div>
        `;

        document.getElementById('back-to-list').addEventListener('click', fetchConversations);
        chatBody.querySelectorAll('[data-user-id]').forEach(el => {
            el.addEventListener('click', () => createConversation(el.dataset.userId));
        });
    }

    function setupMessageForm() {
        const form = document.getElementById('message-form');
        const input = document.getElementById('message-input');
        
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const text = input.value.trim();
            if (!text) return;

            // Create temporary message
            const tempId = 'temp_' + Date.now();
            const tempMsg = {
                MessageID: tempId,
                MessageContent: text,
                SenderUserID: window.currentUserId,
                Timestamp: new Date().toISOString()
            };

            // Show sending status
            messageStatusMap.set(tempId, 'sending');
            appendMessage(tempMsg);
            input.value = '';

            try {
                const result = await sendMessage(activeConversationId, text);
                if (result.success) {
                    messageStatusMap.set(tempId, 'delivered');
                } else {
                    messageStatusMap.set(tempId, 'failed');
                }
            } catch (error) {
                messageStatusMap.set(tempId, 'failed');
            }

            updateMessageStatus(tempId);
        });
    }

    function appendMessage(msg) {
        const container = document.getElementById('messages-container');
        if (!container) return;

        const el = document.createElement('div');
        const isSender = String(msg.SenderUserID) === String(window.currentUserId);
        const status = messageStatusMap.get(msg.MessageID) || 'delivered';

        el.className = `flex flex-col ${isSender ? 'items-end' : 'items-start'}`;
        el.dataset.messageId = msg.MessageID;
        el.innerHTML = `
            <div class="max-w-[75%] px-3 py-2 rounded-lg ${isSender ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-800'}">
                <p class="text-sm break-words">${msg.MessageContent}</p>
            </div>
            <div class="flex items-center gap-1 mt-1">
                <span class="text-xs text-gray-400">
                    ${new Date(msg.Timestamp).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
                </span>
                ${isSender ? renderMessageStatus(status) : ''}
            </div>
        `;

        container.appendChild(el);
        container.scrollTop = container.scrollHeight;
    }

    function updateMessageStatus(messageId) {
        const msgEl = document.querySelector(`[data-message-id="${messageId}"]`);
        if (msgEl) {
            const status = messageStatusMap.get(messageId);
            const statusEl = msgEl.querySelector('.flex.items-center.gap-1');
            if (statusEl) {
                statusEl.innerHTML = `
                    <span class="text-xs text-gray-400">
                        ${new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
                    </span>
                    ${renderMessageStatus(status)}
                `;
            }
        }
    }

    // --- API Functions ---
    async function fetchConversations() {
        chatBody.innerHTML = '<p class="text-center text-gray-500 text-sm p-4">Loading conversations...</p>';
        try {
            const response = await fetch(`${API_URL}?action=get_conversations`, { credentials: 'same-origin' });
            const data = await response.json();
            if (data.success) {
                conversations = data.conversations || [];
                renderConversationList();
            } else {
                throw new Error(data.error || 'Failed to load conversations');
            }
        } catch (error) {
            console.error('Error:', error);
            chatBody.innerHTML = '<p class="p-3 text-center text-sm text-red-600">Failed to load conversations.</p>';
        }
    }

    async function fetchUsers() {
        chatBody.innerHTML = '<p class="text-center text-gray-500 text-sm p-4">Loading users...</p>';
        try {
            const response = await fetch(`${API_URL}?action=get_users`, { credentials: 'same-origin' });
            const data = await response.json();
            if (data.success) {
                renderUsersView(data.users || []);
            } else {
                throw new Error(data.error || 'Failed to load users');
            }
        } catch (error) {
            console.error('Error:', error);
            chatBody.innerHTML = '<p class="p-3 text-center text-sm text-red-600">Failed to load users.</p>';
        }
    }

    async function openConversation(conversationId) {
        try {
            const response = await fetch(`${API_URL}?action=get_messages&conversation_id=${conversationId}`, 
                { credentials: 'same-origin' });
            const data = await response.json();
            if (data.success) {
                const participant = conversations.find(c => String(c.ConversationID) === String(conversationId));
                renderMessagesView(conversationId, data.messages || [], participant);
            } else {
                throw new Error(data.error || 'Failed to load messages');
            }
        } catch (error) {
            console.error('Error:', error);
            chatBody.innerHTML = '<p class="p-3 text-center text-sm text-red-600">Failed to load messages.</p>';
        }
    }

    async function createConversation(userId) {
        try {
            const formData = new FormData();
            formData.append('action', 'create_conversation');
            formData.append('recipient_id', userId);

            const response = await fetch(API_URL, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });
            const data = await response.json();
            
            if (data.success && data.conversation_id) {
                await openConversation(data.conversation_id);
            } else {
                throw new Error(data.error || 'Failed to create conversation');
            }
        } catch (error) {
            console.error('Error:', error);
            chatBody.innerHTML = '<p class="p-3 text-center text-sm text-red-600">Failed to create conversation.</p>';
        }
    }

    async function sendMessage(conversationId, message) {
        const formData = new FormData();
        formData.append('action', 'send_message');
        formData.append('conversation_id', conversationId);
        formData.append('message', message);

        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });
            const data = await response.json();
            return { success: data.success, message: data.message };
        } catch (error) {
            console.error('Error:', error);
            return { success: false, error: 'Failed to send message' };
        }
    }

    // --- Event Listeners ---
    chatHeader.addEventListener('click', (e) => {
        const newButton = e.target.closest('button[title="New Message"]');
        if (newButton) {
            fetchUsers();
            return;
        }

        const toggleButton = e.target.closest('#chat-toggle-button');
        if (toggleButton) {
            chatBody.classList.toggle('hidden');
            const icon = toggleButton.querySelector('svg path');
            icon.setAttribute('d', chatBody.classList.contains('hidden') 
                ? 'M19 9l-7 7-7-7' // down arrow
                : 'M5 15l7-7 7 7'   // up arrow
            );
        }
    });

    // Zoom functionality
    const zoomButton = chatWidget.querySelector('#chat-zoom-button');
    if (zoomButton && zoomHost) {
        zoomButton.addEventListener('click', () => {
            isZoomed = !isZoomed;
            if (isZoomed) {
                zoomHost.classList.remove('hidden');
                zoomHost.innerHTML = `
                    <div class="bg-white rounded-xl shadow-lg max-w-2xl w-full mx-4 overflow-hidden">
                        ${chatWidget.innerHTML}
                    </div>
                `;
                
                // Re-attach event listeners to zoomed chat
                const zoomedChat = zoomHost.querySelector('#chat-widget');
                setupZoomedChat(zoomedChat);
            } else {
                zoomHost.classList.add('hidden');
                zoomHost.innerHTML = '';
            }
        });

        // Close zoomed chat when clicking outside
        zoomHost.addEventListener('click', (e) => {
            if (e.target === zoomHost) {
                zoomButton.click();
            }
        });
    }

    function setupZoomedChat(zoomedChat) {
        const header = zoomedChat.querySelector('#chat-header');
        const body = zoomedChat.querySelector('#chat-body');

        if (header) {
            header.addEventListener('click', (e) => {
                const newButton = e.target.closest('button[title="New Message"]');
                if (newButton) {
                    fetchUsers();
                    return;
                }

                const toggleButton = e.target.closest('#chat-toggle-button');
                if (toggleButton) {
                    body.classList.toggle('hidden');
                    const icon = toggleButton.querySelector('svg path');
                    icon.setAttribute('d', body.classList.contains('hidden')
                        ? 'M19 9l-7 7-7-7'
                        : 'M5 15l7-7 7 7'
                    );
                }
            });
        }

        // Re-render current view
        if (currentView === 'messages' && activeConversationId) {
            openConversation(activeConversationId);
        } else if (currentView === 'users') {
            fetchUsers();
        } else {
            renderConversationList();
        }
    }

    // Start polling for updates
    function startPolling() {
        pollingInterval = setInterval(async () => {
            if (currentView === 'list') {
                await fetchConversations();
            } else if (currentView === 'messages' && activeConversationId) {
                await openConversation(activeConversationId);
            }
        }, 5000);
    }

    // Initialize
    fetchConversations();
    startPolling();
});

