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
        // Receive data from parent window
        window.addEventListener('message', (event) => {
            if (event.data.type === 'LOAD_CHAT') {
                const zoomContent = document.getElementById('zoom-content');
                zoomContent.innerHTML = event.data.html;
                
                // Update send button styling in zoom modal and set up modal handlers
                setTimeout(() => {
                    const sendBtn = zoomContent.querySelector('#send-btn');
                    if (sendBtn) {
                        sendBtn.style.background = 'transparent';
                        sendBtn.style.color = '#4f46e5';
                        sendBtn.style.border = 'none';
                        sendBtn.style.borderRadius = '0';
                        sendBtn.style.padding = '8px';
                        const svg = sendBtn.querySelector('svg');
                        if (svg) {
                            svg.style.width = '24px';
                            svg.style.height = '24px';
                        }
                    }
                    
                    // Set up close button for new chat modal in zoom window
                    const closeModalBtn = document.querySelector('#new-chat-modal button[onclick="window.closeNewChatModal()"]');
                    if (closeModalBtn) {
                        closeModalBtn.onclick = function(e) {
                            e.preventDefault();
                            window.closeNewChatModal();
                        };
                    }
                    
                    // Set up click outside to close modal in zoom window
                    const clickHandler = function(e) {
                        const newChatModal = document.getElementById('new-chat-modal');
                        if (newChatModal && !newChatModal.classList.contains('hidden')) {
                            const modalContent = newChatModal.querySelector('div > div');
                            if (modalContent && !modalContent.contains(e.target) && e.target === newChatModal) {
                                window.closeNewChatModal();
                            }
                        }
                    };
                    document.addEventListener('click', clickHandler);
                }, 100);
            }
        });

        // Make functions available globally for zoom window
        window.openNewChatModal = function() {
            const modal = document.getElementById('new-chat-modal');
            if (modal) {
                modal.classList.remove('hidden');
                // Fetch users for the modal
                fetchUsersForNewChatInZoomWindowSelf();
            }
        };
        
        function fetchUsersForNewChatInZoomWindowSelf() {
            const usersList = document.getElementById('new-chat-users-list');
            if (!usersList) return;
            
            usersList.innerHTML = '<p class="text-center text-gray-500 text-sm p-6">Loading users...</p>';
            
            const apiBase = 'api/chat_api.php';
            const currentUserId = Number(window.opener ? window.opener.currentUserId : (window.currentUserId || 0));
            
            function apiRequest(action, params = {}, method = 'GET') {
                let url = apiBase;
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
                }
                return fetch(url, opts).then(r => r.json());
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
                    if (user.UserID && user.UserID != currentUserId && !seenUserIds.has(user.UserID)) {
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
                         onclick="window.startConversationWithUserInZoom(${user.UserID}, '${escapeHtml(name + (branchName ? ' (' + branchName + ')' : ''))}')">
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
        
        window.startConversationWithUserInZoom = async function(userId, label) {
            // Create conversation directly in zoom window
            try {
                const formData = new FormData();
                formData.append('action', 'create_conversation');
                formData.append('recipient_id', userId);
                
                const response = await fetch('api/chat_api.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });
                
                const res = await response.json();
                
                if (res.success && res.conversation_id) {
                    // Close the modal
                    window.closeNewChatModal();
                    
                    // Reload the zoom window to show the new conversation
                    window.location.reload();
                } else {
                    alert('Failed to create conversation: ' + (res.error || 'Unknown error'));
                }
            } catch (err) {
                console.error('Error starting conversation:', err);
                alert('Error starting conversation: ' + err.message);
            }
        };
        
        window.toggleZoom = function() {
            // Close this zoom window
            window.close();
        };
        
        // Helper functions for zoom window
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

        // Close window when clicking outside
        document.addEventListener('click', (e) => {
            if (e.target === document.body) {
                window.close();
            }
        });
    </script>
</body>
</html>
