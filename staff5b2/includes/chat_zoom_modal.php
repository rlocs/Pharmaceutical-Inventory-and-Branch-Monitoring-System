<?php
// Chat Zoom Modal - Standalone PHP file for zoomed chat view
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Zoom Modal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
        
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        body {
            margin: 0;
            padding: 0;
            background: rgba(0, 0, 0, 0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .zoom-container {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            width: 90vw;
            max-width: 1400px;
            height: 85vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Removed zoom-header and close-btn styles - X button removed */

        .zoom-content {
            flex: 1;
            overflow: hidden;
            display: flex;
        }

        /* Custom scrollbar */
        .custom-scroll::-webkit-scrollbar {
            width: 8px;
        }

        .custom-scroll::-webkit-scrollbar-thumb {
            background-color: #93c5fd;
            border-radius: 10px;
        }

        .custom-scroll::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        /* Message area */
        .message-area {
            height: 100%;
            overflow-y: auto;
            display: flex;
            flex-direction: column-reverse;
        }

        /* Main container */
        .main-container {
            height: 100%;
            width: 100%;
            border-radius: 0;
            box-shadow: none;
            background: #ffffff;
            overflow: hidden;
            display: flex;
        }

        /* Sidebar */
        #contacts-sidebar {
            width: 100%;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
        }

        #chat-view {
            display: none;
        }

        /* Header */
        #contacts-sidebar .p-5 {
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
            background: #ffffff;
            flex-shrink: 0;
        }

        #contacts-sidebar h2 {
            font-size: 24px;
            font-weight: 700;
            color: #4f46e5;
            margin: 0;
        }

        #contacts-sidebar button {
            background: transparent;
            border: none;
            color: #6b7280;
            cursor: pointer;
            padding: 4px;
            border-radius: 50%;
            transition: all 0.2s ease;
        }

        #contacts-sidebar button:hover {
            color: #4f46e5;
        }

        /* Conversations List */
        #conversations-list {
            flex: 1;
            overflow-y: auto;
            padding: 8px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            background: #f9fafb;
        }

        /* Conversation Item */
        .conversation-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.15s ease;
            background: #ffffff;
            border: 1px solid transparent;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .conversation-item:hover {
            background: #f3f4f6;
        }

        .conversation-item.bg-indigo-50 {
            background: #eef2ff;
            border-color: #c7d2fe;
        }

        .conversation-item .w-10 {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            color: #ffffff;
            flex-shrink: 0;
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
        }

        .conversation-item .flex-1 {
            flex: 1;
            min-width: 0;
        }

        .conversation-item p {
            margin: 0;
        }

        .conversation-item .font-semibold {
            font-size: 14px;
            font-weight: 600;
            color: #1f2937;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .conversation-item .text-xs {
            font-size: 12px;
            color: #6b7280;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Chat View */
        #chat-view {
            display: flex;
            flex-direction: column;
            background: #ffffff;
            flex: 1;
            min-height: 0;
        }

        /* Chat Header */
        #chat-view .p-4.border-b {
            padding: 8px 12px;
            border-bottom: 1px solid #e5e7eb;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            min-height: auto;
        }

        #back-button {
            background: transparent;
            border: none;
            color: #6b7280;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        #back-button:hover {
            color: #4f46e5;
            background: #f3f4f6;
        }

        #back-button.hidden {
            display: none;
        }

        #chat-view .w-10 {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 12px;
            color: #ffffff;
            flex-shrink: 0;
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
        }

        #chat-view .flex.items-center.space-x-3 {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            min-width: 0;
        }

        #chat-title {
            font-size: 14px;
            font-weight: 700;
            color: #111827;
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.2;
        }

        #chat-subtitle {
            font-size: 11px;
            color: #6b7280;
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.2;
        }

        /* Messages Container */
        #message-container-wrapper {
            flex: 1;
            padding: 8px;
            overflow-y: auto;
            background: #ffffff;
            display: flex;
            flex-direction: column-reverse;
            min-height: 0;
        }

        #initial-prompt {
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
        }

        #initial-prompt.hidden {
            display: none;
        }

        #initial-prompt svg {
            width: 48px;
            height: 48px;
            margin: 0 auto 12px;
            color: #818cf8;
        }

        #initial-prompt p {
            margin: 0;
        }

        #initial-prompt .text-lg {
            font-size: 18px;
            font-weight: 600;
            color: #6b7280;
        }

        #initial-prompt .text-sm {
            font-size: 14px;
            color: #9ca3af;
            margin-top: 8px;
        }

        #messages-container {
            display: flex;
            flex-direction: column;
            gap: 6px;
            width: 100%;
        }

        /* Message Bubbles */
        #messages-container .flex {
            display: flex;
            margin-bottom: 6px;
            width: 100%;
        }

        #messages-container .flex.justify-end {
            justify-content: flex-end;
        }

        #messages-container .flex.justify-start {
            justify-content: flex-start;
        }

        #messages-container .max-w-\[85\%\] {
            max-width: 85%;
            padding: 8px 10px;
            border-radius: 10px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        #messages-container .bg-indigo-600 {
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            color: #ffffff;
            border-bottom-right-radius: 4px;
        }

        #messages-container .bg-gray-200 {
            background: #e5e7eb;
            color: #1f2937;
            border-bottom-left-radius: 4px;
        }

        #messages-container .text-xs {
            font-size: 11px;
            margin-bottom: 2px;
        }

        #messages-container .text-indigo-200 {
            color: #c7d2fe;
            font-weight: 600;
        }

        #messages-container .text-indigo-600 {
            color: #4f46e5;
            font-weight: 600;
        }

        #messages-container .text-base {
            font-size: 13px;
            line-height: 1.3;
            word-wrap: break-word;
        }

        #messages-container .text-indigo-300 {
            color: #a5b4fc;
        }

        #messages-container .text-gray-500 {
            color: #6b7280;
        }

        /* Input Area */
        #input-area {
            padding: 8px 10px;
            border-top: 1px solid #e5e7eb;
            background: #f9fafb;
            flex-shrink: 0;
            min-height: auto;
        }

        #input-area.hidden {
            display: none;
        }

        #input-area .flex {
            display: flex;
            gap: 6px;
            align-items: center;
            width: 100%;
        }

        #message-input {
            color: #111827 !important; /* Explicit black text color */
            flex: 1;
            padding: 6px 10px;
            border: 1px solid #d1d5db;
            border-radius: 999px;
            font-size: 13px;
            font-family: 'Inter', sans-serif;
            outline: none;
            transition: all 0.2s ease;
            background: #ffffff;
        }

        #message-input:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
            color: #111827 !important;
        }

        #input-area button[onclick="window.sendMessage()"] {
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            color: #ffffff;
            padding: 6px 10px;
            border: none;
            border-radius: 100px;
            font-weight: 50;
            box-shadow: 0 1px 3px rgba(79, 70, 229, 0.2);
            transition: all 0.2s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        #input-area button[onclick="window.sendMessage()"]:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }

        #input-area button[onclick="window.sendMessage()"]:active {
            transform: scale(0.95);
        }
    </style>
</head>
<body>
    <div class="zoom-container">
        <div class="zoom-content" id="zoom-content">
            <!-- Chat content will be loaded here -->
        </div>
    </div>

    <script>
        // Global state for zoom window
        const zoomState = {
            currentChatId: null,
            currentPartnerLabel: '',
            messages: []
        };

        // Receive data from parent window
        window.addEventListener('message', (event) => {
    if (event.data.type === 'LOAD_CHAT') {
        const zoomContent = document.getElementById('zoom-content');
        zoomContent.innerHTML = event.data.html;
        
        // Store the current user ID from the main window
        if (event.data.currentUserId) {
            window.currentUserId = event.data.currentUserId;
        }
        
        // Initialize the zoom window functionality
        setTimeout(() => {
            initializeZoomWindow();
        }, 100);
    }
});

function initializeZoomWindow() {
    // Set up event listeners for buttons
    setupZoomEventListeners();
    
    // Load conversations
    fetchConversationsForZoom();
    
    // Start polling for new messages in zoom window
    startMessagePollingForZoom();
}

        function setupZoomEventListeners() {
            // Send message button
            const sendBtn = document.querySelector('#send-btn');
            if (sendBtn) {
                sendBtn.onclick = window.sendMessage;
            }

            // Message input enter key
            const messageInput = document.getElementById('message-input');
            if (messageInput) {
                messageInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        window.sendMessage();
                    }
                });
            }

            // Back button
            const backButton = document.getElementById('back-button');
            if (backButton) {
                backButton.onclick = () => window.switchView('list');
            }

            // New chat button
            const newChatBtn = document.querySelector('button[onclick="window.openNewChatModal()"]');
            if (newChatBtn) {
                newChatBtn.onclick = window.openNewChatModal;
            }

            // Zoom button
            const zoomBtn = document.querySelector('button[onclick="toggleZoom()"]');
            if (zoomBtn) {
                zoomBtn.onclick = window.toggleZoom;
            }

            // Clear messages button
            const clearBtn = document.getElementById('clear-messages-btn');
            if (clearBtn) {
                clearBtn.onclick = window.clearMessages;
            }

            // Close modal button
            const closeModalBtn = document.querySelector('#new-chat-modal button[onclick="window.closeNewChatModal()"]');
            if (closeModalBtn) {
                closeModalBtn.onclick = window.closeNewChatModal;
            }

            // Click outside to close modal
            document.addEventListener('click', (e) => {
                const newChatModal = document.getElementById('new-chat-modal');
                if (newChatModal && !newChatModal.classList.contains('hidden')) {
                    const modalContent = newChatModal.querySelector('div > div');
                    if (modalContent && !modalContent.contains(e.target) && e.target === newChatModal) {
                        window.closeNewChatModal();
                    }
                }
            });
        }

        // API request function for zoom window
        function apiRequest(action, params = {}, method = 'GET') {
            // FIXED: Use correct API path - go up one level since zoom modal is in includes folder
            const apiBase = '../api/chat_api.php';
            const opts = { method, credentials: 'same-origin' };
            
            if (method === 'GET') {
                const qs = new URLSearchParams({ action, ...params }).toString();
                url = apiBase + '?' + qs;
            } else {
                const formData = new FormData();
                formData.append('action', action);
                Object.keys(params).forEach(key => {
                    formData.append(key, params[key]);
                });
                opts.body = formData;
                url = apiBase;
            }
            
            return fetch(url, opts)
                .then(response => {
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        throw new Error('Server returned non-JSON response.');
                    }
                    return response.json();
                })
                .catch(error => {
                    console.error('API request failed:', error);
                    throw error;
                });
        }

        function escapeHtml(s) {
            if (s === undefined || s === null) return '';
            return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]);
        }

        function initials(name) {
            const parts = String(name || '').trim().split(/\s+/);
            const first = parts[0] ? parts[0][0] : '';
            const second = parts[1] ? parts[1][0] : '';
            return (first + second).toUpperCase() || '?';
        }

        function fmtTime(ts) {
            if (!ts) return '';
            const d = new Date(ts);
            return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        }

        // Make functions available globally for zoom window
        window.openNewChatModal = function() {
            const modal = document.getElementById('new-chat-modal');
            if (modal) {
                modal.classList.remove('hidden');
                fetchUsersForNewChat();
            }
        };
        
        function fetchUsersForNewChat() {
            const usersList = document.getElementById('new-chat-users-list');
            if (!usersList) return;
            
            usersList.innerHTML = '<p class="text-center text-gray-500 text-sm p-6">Loading users...</p>';
            
            apiRequest('get_users', {}, 'GET').then(res => {
                if (!res.success) {
                    usersList.innerHTML = '<p class="text-center text-red-500 text-sm p-6">Failed to load users</p>';
                    return;
                }
                
                const users = res.users || [];
                
                // Filter out current user and get unique users
                const uniqueUsers = [];
                const seenUserIds = new Set();
                
                users.forEach(user => {
                    if (user.UserID && !seenUserIds.has(user.UserID)) {
                        seenUserIds.add(user.UserID);
                        uniqueUsers.push(user);
                    }
                });
                
                if (uniqueUsers.length === 0) {
                    usersList.innerHTML = '<p class="text-center text-gray-500 text-sm p-6">No other users available</p>';
                    return;
                }
                
                // Render user list in modal
                usersList.innerHTML = uniqueUsers.map(user => {
                    const name = escapeHtml((user.FirstName || 'Unknown') + (user.LastName ? ' ' + user.LastName : ''));
                    const role = escapeHtml(user.Role || '');
                    const branchName = escapeHtml(user.BranchName || '');
                    
                    return `
                    <div class="conversation-item p-3 flex items-center space-x-3 rounded-xl cursor-pointer shadow-sm border transition hover:bg-gray-100 mb-2" 
                         onclick="window.startConversationWithUser(${user.UserID}, '${escapeHtml(name + (branchName ? ' (' + branchName + ')' : ''))}')">
                        <div class="w-10 h-10 bg-indigo-600 rounded-full flex items-center justify-center text-white font-semibold text-sm flex-shrink-0">
                            ${escapeHtml(initials(name))}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-gray-800 truncate">${name}</p>
                            <p class="text-xs text-gray-600 truncate">${role}${branchName ? ' - ' + branchName : ''}</p>
                        </div>
                    </div>`;
                }).join('');
            }).catch(err => {
                console.error('Error fetching users:', err);
                usersList.innerHTML = '<p class="text-center text-red-500 text-sm p-6">Error loading users</p>';
            });
        }
        
        window.closeNewChatModal = function() {
            const modal = document.getElementById('new-chat-modal');
            if (modal) {
                modal.classList.add('hidden');
            }
        };
        
        window.startConversationWithUser = async function(userId, label) {
            try {
                const res = await apiRequest('create_conversation', { recipient_id: userId }, 'POST');
                
                if (res.success && res.conversation_id) {
                    // Close the modal
                    window.closeNewChatModal();
                    
                    // Open the new conversation
                    window.openConversation(res.conversation_id, label);
                    
                    // Refresh conversations list
                    fetchConversationsForZoom();
                } else {
                    alert('Failed to create conversation: ' + (res.error || 'Unknown error'));
                }
            } catch (err) {
                console.error('Error starting conversation:', err);
                alert('Error starting conversation: ' + err.message);
            }
        };

        // Fetch conversations for zoom window
        function fetchConversationsForZoom() {
            const listContainer = document.getElementById('conversations-list');
            if (!listContainer) return;

            listContainer.innerHTML = '<p class="text-center text-gray-500 text-sm p-6">Loading conversations...</p>';
            
            apiRequest('get_conversations', {}, 'GET').then(res => {
                if (!res.success) {
                    listContainer.innerHTML = '<p class="text-center text-red-500 text-sm p-6">Failed to load conversations</p>';
                    return;
                }
                
                const conversations = res.conversations || [];
                
                if (conversations.length === 0) {
                    listContainer.innerHTML = `
                        <div class="p-2 text-xs text-center text-gray-500 border-b border-gray-200 mb-2">
                            Start a conversation
                        </div>
                        <p class="text-center text-gray-500 text-sm p-6">No conversations yet. Click + to start a new one!</p>
                    `;
                    return;
                }

                listContainer.innerHTML = `
                    <div class="p-2 text-xs text-center text-gray-500 border-b border-gray-200 mb-2">
                        Your Conversations
                    </div>
                ` + conversations.map(c => {
                    const name = escapeHtml((c.FirstName || 'Unknown') + (c.LastName ? ' ' + c.LastName : ''));
                    const branch = escapeHtml(c.BranchName || '');
                    const lastMessage = escapeHtml(c.LastMessage || '');
                    const isActive = zoomState.currentChatId === parseInt(c.ConversationID);
                    const activeClass = isActive ? 'bg-indigo-50 border-indigo-200' : 'hover:bg-gray-100';
                    const unreadCount = parseInt(c.UnreadCount || 0);
                    const hasUnread = unreadCount > 0;

                    return `
                    <div class="conversation-item p-3 flex items-center space-x-3 rounded-xl cursor-pointer shadow-sm border transition ${activeClass}" 
                         onclick="window.openConversation(${c.ConversationID}, '${escapeHtml((c.FirstName || '') + (c.LastName ? ' ' + c.LastName : '') + (c.BranchName ? ' (' + c.BranchName + ')' : ''))}')">
                        <div class="w-10 h-10 bg-indigo-600 rounded-full flex items-center justify-center text-white font-semibold text-sm flex-shrink-0">
                            ${escapeHtml(initials(name))}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-start">
                                <p class="font-semibold text-gray-800 truncate">${name}</p>
                                ${hasUnread ? `<span class="bg-red-500 text-white text-xs rounded-full px-2 py-1 min-w-5 h-5 flex items-center justify-center">${unreadCount}</span>` : ''}
                            </div>
                            <p class="text-xs text-gray-600 truncate">${branch}</p>
                            ${lastMessage ? `<p class="text-xs text-gray-500 truncate mt-1">${lastMessage}</p>` : ''}
                        </div>
                    </div>`;
                }).join('');
            }).catch(err => {
                console.error('Error fetching conversations:', err);
                listContainer.innerHTML = '<p class="text-center text-red-500 text-sm p-6">Error loading conversations</p>';
            });
        }

        window.openConversation = function(conversationId, label) {
            zoomState.currentChatId = conversationId;
            zoomState.currentPartnerLabel = label;

            // Update header
            const chatTitleEl = document.getElementById('chat-title');
            const chatSubtitleEl = document.getElementById('chat-subtitle');
            if (chatTitleEl) chatTitleEl.textContent = label;
            if (chatSubtitleEl) chatSubtitleEl.textContent = '';

            // Hide initial prompt and show input
            const initialPrompt = document.getElementById('initial-prompt');
            const chatView = document.getElementById('chat-view');
            const inputArea = document.getElementById('input-area');
            
            if (initialPrompt) initialPrompt.classList.add('hidden');
            if (chatView) chatView.classList.remove('justify-center', 'items-center');
            if (inputArea) inputArea.classList.remove('hidden');

            // Load messages
            loadMessages(conversationId);

            window.switchView('chat');
        };

        function loadMessages(conversationId) {
            const messagesContainer = document.getElementById('messages-container');
            if (!messagesContainer) return;

            messagesContainer.innerHTML = '<p class="text-center text-sm italic text-gray-500 p-8">Loading messages...</p>';
            
            apiRequest('get_messages', { conversation_id: conversationId }, 'GET').then(res => {
                if (!res.success) {
                    if (messagesContainer) messagesContainer.innerHTML = `<p class="text-center text-red-500 text-sm p-8">${escapeHtml(res.error || 'Failed to load messages')}</p>`;
                    return;
                }
                zoomState.messages = res.messages || [];
                renderMessages();
            });
        }

        function renderMessages() {
    const messagesContainer = document.getElementById('messages-container');
    if (!messagesContainer) return;
    
    const initialPrompt = document.getElementById('initial-prompt');
    if (initialPrompt) initialPrompt.classList.add('hidden');
    
    if (zoomState.messages.length === 0) {
        messagesContainer.innerHTML = '<p class="text-center text-sm italic text-gray-500 p-8">Send the first message to start the conversation!</p>';
        return;
    }

    // Get current user ID from the main window or use a default
    const currentUserId = window.opener ? window.opener.currentUserId : (window.currentUserId || 0);

    messagesContainer.innerHTML = zoomState.messages.map(m => {
        // FIXED: Properly check if message was sent by current user
        const isUserMessage = Number(m.SenderUserID) === Number(currentUserId);
        const senderName = escapeHtml((m.FirstName || '') + (m.LastName ? ' ' + m.LastName : ''));
        const timeStr = escapeHtml(fmtTime(m.Timestamp));

        const bubbleClasses = isUserMessage
            ? 'bg-indigo-600 text-white rounded-br-lg rounded-t-xl rounded-l-xl'
            : 'bg-gray-200 text-gray-800 rounded-tl-lg rounded-t-xl rounded-r-xl';

        const nameClasses = isUserMessage ? 'text-indigo-200' : 'text-indigo-600';
        const timeClasses = isUserMessage ? 'text-indigo-300' : 'text-gray-500';

        return `
        <div class="flex mb-3 ${isUserMessage ? 'justify-end' : 'justify-start'}">
            <div class="max-w-[85%] p-3 shadow-md ${bubbleClasses}">
                <p class="text-xs font-semibold ${nameClasses} mb-1">${senderName}</p>
                <p class="text-base break-words">${escapeHtml(m.MessageContent)}</p>
                <p class="text-right text-xs mt-1 ${timeClasses}">${timeStr}</p>
            </div>
        </div>`;
    }).join('');

    // Scroll to bottom
    setTimeout(() => {
        const messageArea = document.getElementById('message-container-wrapper');
        if (messageArea) {
            messageArea.scrollTop = messageArea.scrollHeight;
        }
    }, 100);
}

        window.sendMessage = async function() {
            const messageInput = document.getElementById('message-input');
            const content = messageInput.value.trim();
            
            if (!zoomState.currentChatId) {
                alert('Please select a conversation first');
                return;
            }
            
            if (!content) {
                alert('Please enter a message');
                return;
            }

            // Clear input and focus
            messageInput.value = '';
            messageInput.focus();

            try {
                const res = await apiRequest('send_message', { 
                    conversation_id: zoomState.currentChatId, 
                    message: content 
                }, 'POST');
                
                if (res.success && res.message) {
                    // Add new message to state and render
                    zoomState.messages.push(res.message);
                    renderMessages();
                    
                    // Refresh conversations list
                    fetchConversationsForZoom();
                } else {
                    const errorMsg = res.error || 'Unknown error occurred';
                    alert('Failed to send message: ' + errorMsg);
                }
            } catch (err) {
                console.error('Error sending message:', err);
                alert('Network error: Please check your connection and try again.');
            }
        };

        window.switchView = function(view) {
            const contactsSidebar = document.getElementById('contacts-sidebar');
            const chatView = document.getElementById('chat-view');
            const backButton = document.getElementById('back-button');
            const initialPrompt = document.getElementById('initial-prompt');
            const inputArea = document.getElementById('input-area');
            
            if (!contactsSidebar || !chatView || !backButton) return;
            
            if (view === 'chat') {
                contactsSidebar.style.display = 'none';
                chatView.style.display = 'flex';
                backButton.classList.remove('hidden');
            } else {
                contactsSidebar.style.display = 'flex';
                chatView.style.display = 'none';
                backButton.classList.add('hidden');
                // Reset to initial state
                if (initialPrompt) initialPrompt.classList.remove('hidden');
                if (inputArea) inputArea.classList.add('hidden');
                zoomState.currentChatId = null;
            }
        };

        window.clearMessages = async function() {
            if (!zoomState.currentChatId) {
                alert('No conversation selected');
                return;
            }

            if (confirm('⚠️ WARNING: This will permanently delete ALL messages in this conversation from the database. This action cannot be undone!\n\nAre you sure you want to delete all messages?')) {
                try {
                    const messagesContainer = document.getElementById('messages-container');
                    if (messagesContainer) {
                        messagesContainer.innerHTML = '<p class="text-center text-sm italic text-gray-500 p-8">Deleting messages...</p>';
                    }

                    const res = await apiRequest('delete_messages', { 
                        conversation_id: zoomState.currentChatId 
                    }, 'POST');
                    
                    if (res.success) {
                        zoomState.messages = [];

                        if (messagesContainer) {
                            messagesContainer.innerHTML = '<p class="text-center text-sm text-green-600 p-8">All messages have been permanently deleted from the database.</p>';
                        }

                        // Refresh conversations list
                        fetchConversationsForZoom();
                    } else {
                        alert('Failed to delete messages: ' + (res.error || 'Unknown error'));
                        loadMessages(zoomState.currentChatId);
                    }
                } catch (err) {
                    console.error('Error deleting messages:', err);
                    alert('Error deleting messages: ' + err.message);
                    loadMessages(zoomState.currentChatId);
                }
            }
        };

        window.toggleZoom = function() {
            // Close this zoom window
            window.close();
        };

        // Close window when clicking outside
        document.addEventListener('click', (e) => {
            if (e.target === document.body) {
                window.close();
            }
        });

        // Add this to chat_zoom_modal.php in the script section
function startMessagePollingForZoom() {
    // Check for new messages every 3 seconds when in a conversation
    setInterval(() => {
        if (zoomState.currentChatId) {
            checkForNewMessagesInZoom();
        }
    }, 3000);
}

function checkForNewMessagesInZoom() {
    if (!zoomState.currentChatId) return;
    
    apiRequest('get_messages', { conversation_id: zoomState.currentChatId }, 'GET').then(res => {
        if (res.success) {
            const newMessages = res.messages || [];
            const currentMessageCount = zoomState.messages.length;
            
            // Only update if new messages arrived
            if (newMessages.length > currentMessageCount) {
                zoomState.messages = newMessages;
                renderMessages();
                
                // Also refresh conversations list in zoom window
                fetchConversationsForZoom();
            }
        }
    }).catch(err => {
        console.error('Error checking for new messages in zoom:', err);
    });
}
    </script>
</body>
</html>