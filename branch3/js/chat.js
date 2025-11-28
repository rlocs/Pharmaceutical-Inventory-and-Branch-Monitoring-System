/* Sidebar-Optimized Messenger Chat UI
   - Responsive design that fits perfectly in sidebar
   - Single-pane flow with zoom capability
   - Integrates with api/chat_api.php backend
*/

(function () {
    const apiBase = './api/chat_api.php';
    const currentUserId = Number(window.currentUserId || 0);

    // DOM Elements
    const mainContainer = document.getElementById('main-container');
    const contactsSidebar = document.getElementById('contacts-sidebar');
    const chatView = document.getElementById('chat-view');
    const messagesContainer = document.getElementById('messages-container');
    const messageInput = document.getElementById('message-input');
    const backButton = document.getElementById('back-button');
    const initialPrompt = document.getElementById('initial-prompt');
    const inputArea = document.getElementById('input-area');

    // State
    let state = {
        conversations: [],
        currentChatId: null,
        currentPartnerLabel: '',
        messages: [],
        isZoomed: false,
        isCleared: false // Track if current conversation is cleared
    };

    // Make functions globally accessible
    window.openNewChatModal = function() {
        const modal = document.getElementById('new-chat-modal');
        if (modal) {
            modal.classList.remove('hidden');
            fetchUsersForNewChat();
        }
    };

    window.closeNewChatModal = function() {
        const modal = document.getElementById('new-chat-modal');
        if (modal) {
            modal.classList.add('hidden');
        }
    };

    // Function to fetch users for new chat modal
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

    // FIXED: Improved API request with better error handling
    function apiRequest(action, params = {}, method = 'GET') {
        let url = apiBase;
        const opts = { method, credentials: 'same-origin' };
        if (method === 'GET') {
            const qs = new URLSearchParams({ action, ...params }).toString();
            url = apiBase + '?' + qs;
        } else {
            // POST request
            const formData = new FormData();
            formData.append('action', action);
            Object.keys(params).forEach(key => {
                formData.append(key, params[key]);
            });
            opts.body = formData;
        }
        
        return fetch(url, opts)
            .then(response => {
                // First, check if response is JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('Server returned non-JSON response. Please check the API endpoint.');
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

    let zoomWindow = null;

    window.toggleZoom = function() {
        // Close existing zoom window if open
        if (zoomWindow && !zoomWindow.closed) {
            zoomWindow.close();
            zoomWindow = null;
            state.isZoomed = false;
            return;
        }
        
        state.isZoomed = true;
        
        // Open new window for zoom (like before)
        zoomWindow = window.open('includes/chat_zoom_modal.php', 'ChatZoom', 'width=1400,height=800,left=100,top=100');
        
        if (!zoomWindow) {
            alert('Please allow popups for this site to use the zoom feature');
            state.isZoomed = false;
            return;
        }
        
        // Wait for window to load, then send content
        const checkWindow = setInterval(() => {
            if (zoomWindow.closed) {
                clearInterval(checkWindow);
                state.isZoomed = false;
                zoomWindow = null;
                return;
            }
            
            try {
                if (zoomWindow.document.readyState === 'complete') {
                    clearInterval(checkWindow);
                    
                    // Clone the main container content AND the new chat modal
                    if (mainContainer) {
                        const clone = mainContainer.cloneNode(true);
                        
                        // Also clone the new chat modal
                        const newChatModal = document.getElementById('new-chat-modal');
                        let modalHtml = '';
                        if (newChatModal) {
                            const modalClone = newChatModal.cloneNode(true);
                            modalHtml = modalClone.outerHTML;
                        }
                        
                        // Combine both in the HTML
                        const html = clone.outerHTML + modalHtml;
                        
                        // Send content to the new window
// In chat.js - update the postMessage call
zoomWindow.postMessage({
    type: 'LOAD_CHAT',
    html: html,
    state: state,
    currentUserId: currentUserId // Add this line to pass the current user ID
}, '*');
                        
                        // Re-attach listeners in the new window
                        setTimeout(() => {
                            attachZoomedEventListenersToWindow(zoomWindow);
                        }, 200);
                    }
                }
            } catch (e) {
                // Window might not be ready yet, continue checking
            }
        }, 100);
        
        // Timeout after 5 seconds
        setTimeout(() => {
            clearInterval(checkWindow);
        }, 5000);
    };

    // FIXED: Improved fetchConversations with better error handling
    function fetchConversations() {
        const listContainer = document.getElementById('conversations-list');
        if (!listContainer) return;

        console.log('Fetching conversations...');
        
        apiRequest('get_conversations', {}, 'GET').then(res => {
            console.log('Conversations API response:', res);
            
            if (!res.success) {
                console.log('No conversations found, fetching users instead');
                // If no conversations or error, fetch users instead
                fetchUsers();
                return;
            }
            
            const conversations = res.conversations || [];
            console.log('Raw conversations:', conversations);
            
            // Remove duplicates based on ConversationID
            const uniqueConversations = [];
            const seenIds = new Set();
            
            conversations.forEach(conv => {
                if (conv.ConversationID && !seenIds.has(conv.ConversationID)) {
                    seenIds.add(conv.ConversationID);
                    uniqueConversations.push(conv);
                }
            });
            
            state.conversations = uniqueConversations;
            console.log('Unique conversations:', uniqueConversations);
            
            if (uniqueConversations.length === 0) {
                // No conversations, show users instead
                console.log('No conversations found, showing users list');
                fetchUsers();
            } else {
                renderConversationList(uniqueConversations);
            }
        }).catch(err => {
            console.error('Error fetching conversations:', err);
            // On error, try to fetch users as fallback
            fetchUsers();
        });
    }

    // FIXED: Improved renderConversationList with better fallback
    function renderConversationList(convs) {
        const listContainer = document.getElementById('conversations-list');
        if (!listContainer) return;

        console.log('Rendering conversation list:', convs);

        // Remove duplicates based on ConversationID - more robust deduplication
        const uniqueConvs = [];
        const seenIds = new Set();
        const seenKeys = new Set(); // Track by ConversationID + Name + Branch for extra safety
        
        convs.forEach(c => {
            if (!c.ConversationID) {
                console.log('Skipping invalid conversation:', c);
                return; // Skip invalid entries
            }
            
            const convId = parseInt(c.ConversationID);
            if (seenIds.has(convId)) {
                // Already seen this conversation ID, skip
                console.log('Duplicate conversation ID skipped:', convId);
                return;
            }
            
            // Create a unique key for additional deduplication
            const name = (c.FirstName || 'Unknown') + (c.LastName ? ' ' + c.LastName : '');
            const branch = c.BranchName || '';
            const uniqueKey = convId + '|' + name + '|' + branch;
            
            if (seenKeys.has(uniqueKey)) {
                // Same conversation with same details, skip
                console.log('Duplicate conversation key skipped:', uniqueKey);
                return;
            }
            
            seenIds.add(convId);
            seenKeys.add(uniqueKey);
            uniqueConvs.push(c);
        });

        console.log('Final unique conversations to render:', uniqueConvs);

        if (uniqueConvs.length === 0) {
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
        ` + uniqueConvs.map(c => {
            const name = escapeHtml((c.FirstName || 'Unknown') + (c.LastName ? ' ' + c.LastName : ''));
            const branch = escapeHtml(c.BranchName || '');
            const lastMessage = escapeHtml(c.LastMessage || '');
            const isActive = state.currentChatId === parseInt(c.ConversationID);
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
    }

    // FIXED: Improved fetchUsers with better error handling
    function fetchUsers() {
        console.log('Fetching users as fallback...');
        apiRequest('get_users', {}, 'GET').then(res => {
            console.log('Get users response:', res);
            if (!res.success) {
                console.error('Failed to fetch users:', res.error);
                const listContainer = document.getElementById('conversations-list');
                if (listContainer) {
                    listContainer.innerHTML = `
                        <div class="p-2 text-xs text-center text-gray-500 border-b border-gray-200 mb-2">
                            Start a conversation
                        </div>
                        <p class="text-center text-gray-500 text-sm p-6">No users available. Try refreshing the page.</p>
                    `;
                }
                return;
            }
            const users = res.users || [];
            console.log('Users fetched:', users.length);
            
            // Filter out current user and get unique users
            const currentUserId = Number(window.currentUserId || 0);
            const uniqueUsers = [];
            const seenUserIds = new Set();
            
            users.forEach(user => {
                if (user.UserID && user.UserID != currentUserId && !seenUserIds.has(user.UserID)) {
                    seenUserIds.add(user.UserID);
                    uniqueUsers.push(user);
                }
            });
            
            renderUserList(uniqueUsers);
        }).catch(err => {
            console.error('Error fetching users:', err);
            const listContainer = document.getElementById('conversations-list');
            if (listContainer) {
                listContainer.innerHTML = `
                    <div class="p-2 text-xs text-center text-gray-500 border-b border-gray-200 mb-2">
                        Connection Error
                    </div>
                    <p class="text-center text-red-500 text-sm p-6">Error loading conversations. Please check your connection.</p>
                `;
            }
        });
    }
    
    // FIXED: Improved renderUserList
    function renderUserList(users) {
        const listContainer = document.getElementById('conversations-list');
        if (!listContainer) return;
        
        console.log('Rendering user list:', users);
        
        if (users.length === 0) {
            listContainer.innerHTML = `
                <div class="p-2 text-xs text-center text-gray-500 border-b border-gray-200 mb-2">
                    Start a conversation
                </div>
                <p class="text-center text-gray-500 text-sm p-6">No other users available</p>
            `;
            return;
        }
        
        listContainer.innerHTML = `
            <div class="p-2 text-xs text-center text-gray-500 border-b border-gray-200 mb-2">
                Start a conversation
            </div>
        ` + users.map(user => {
            const name = escapeHtml((user.FirstName || 'Unknown') + (user.LastName ? ' ' + user.LastName : ''));
            const role = escapeHtml(user.Role || '');
            const branchName = escapeHtml(user.BranchName || '');
            
            return `
            <div class="conversation-item p-3 flex items-center space-x-3 rounded-xl cursor-pointer shadow-sm border transition hover:bg-gray-100" 
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
    }

    // UPDATED: Clear messages function - now deletes from database
    window.clearMessages = async function() {
        if (!state.currentChatId) {
            alert('No conversation selected');
            return;
        }

        if (confirm('⚠️ WARNING:\nAre you sure you want to delete all messages?')) {
            try {
                // Show loading state
                if (messagesContainer) {
                    messagesContainer.innerHTML = '<p class="text-center text-sm italic text-gray-500 p-8">Deleting messages...</p>';
                }

                // Call API to delete messages from database
                const res = await apiRequest('delete_messages', { 
                    conversation_id: state.currentChatId 
                }, 'POST');
                
                if (res.success) {
                    // Update state
                    state.isCleared = true;
                    state.messages = [];

                    // Show success message
                    if (messagesContainer) {
                        messagesContainer.innerHTML = '<p class="text-center text-sm text-green-600 p-8">All messages have been permanently deleted from the database.</p>';
                    }

                    // Refresh conversations list to update last message
                    fetchConversations();

                    // If zoomed, update zoom window as well
                    if (zoomWindow && !zoomWindow.closed) {
                        try {
                            const zoomDoc = zoomWindow.document;
                            const zoomMessagesContainer = zoomDoc.getElementById('messages-container');
                            if (zoomMessagesContainer) {
                                zoomMessagesContainer.innerHTML = '<p class="text-center text-sm text-green-600 p-8">All messages have been permanently deleted from the database.</p>';
                            }
                        } catch (e) {
                            console.log('Could not update zoom window:', e);
                        }
                    }
                } else {
                    alert('Failed to delete messages: ' + (res.error || 'Unknown error'));
                    // Reload messages if deletion failed
                    loadMessages(state.currentChatId);
                }
            } catch (err) {
                console.error('Error deleting messages:', err);
                alert('Error deleting messages: ' + err.message);
                // Reload messages if deletion failed
                loadMessages(state.currentChatId);
            }
        }
    };

    // REMOVED: restoreMessages function since we're now deleting from database

    // FIXED: Completely rewritten message sending with better error handling
    window.sendMessage = async function() {
        const content = messageInput.value.trim();
        
        // Check if we have a conversation and message content
        if (!state.currentChatId) {
            alert('Please select a conversation first');
            return;
        }
        
        if (!content) {
            alert('Please enter a message');
            return;
        }

        console.log('Sending message:', { conversation_id: state.currentChatId, message: content });

        // Clear input and focus
        messageInput.value = '';
        messageInput.focus();

        try {
            const res = await apiRequest('send_message', { 
                conversation_id: state.currentChatId, 
                message: content 
            }, 'POST');
            
            console.log('Send message response:', res);
            
            if (res.success && res.message) {
                // Add new message to state and render
                state.messages.push(res.message);
                renderMessages();
                
                // Refresh conversations list to update last message and timestamp
                fetchConversations();
            } else {
                // FIXED: Proper error message handling without duplication
                const errorMsg = res.error || 'Unknown error occurred';
                console.error('Failed to send message:', errorMsg);
                alert('Failed to send message: ' + errorMsg);
            }
        } catch (err) {
            console.error('Error sending message:', err);
            alert('Network error: Please check your connection and try again.');
        }
    };

    // UPDATED: Improved open conversation (removed clearance check since we're deleting from DB)
    window.openConversation = function(conversationId, label) {
        state.currentChatId = conversationId;
        state.currentPartnerLabel = label;

        // Reset cleared state since we're now deleting from database
        state.isCleared = false;

        // Update header
        const chatTitleEl = document.getElementById('chat-title');
        const chatSubtitleEl = document.getElementById('chat-subtitle');
        if (chatTitleEl) chatTitleEl.textContent = label;
        if (chatSubtitleEl) chatSubtitleEl.textContent = '';

        // Hide initial prompt and show input
        if (initialPrompt) initialPrompt.classList.add('hidden');
        if (chatView) chatView.classList.remove('justify-center', 'items-center');
        if (inputArea) inputArea.classList.remove('hidden');

        // Load messages
        loadMessages(conversationId);

        window.switchView('chat');
    };

    // NEW: Centralized message loading function
    function loadMessages(conversationId) {
        if (!messagesContainer) return;

        messagesContainer.innerHTML = '<p class="text-center text-sm italic text-gray-500 p-8">Loading messages...</p>';
        
        apiRequest('get_messages', { conversation_id: conversationId }, 'GET').then(res => {
            if (!res.success) {
                if (messagesContainer) messagesContainer.innerHTML = `<p class="text-center text-red-500 text-sm p-8">${escapeHtml(res.error || 'Failed to load messages')}</p>`;
                return;
            }
            state.messages = res.messages || [];
            renderMessages();
        });
    }

    // UPDATED: Render messages function
    function renderMessages() {
        if (!messagesContainer) return;
        
        // Hide initial prompt when messages are loaded
        if (initialPrompt) initialPrompt.classList.add('hidden');
        
        if (state.messages.length === 0) {
            messagesContainer.innerHTML = '<p class="text-center text-sm italic text-gray-500 p-8">Send the first message to start the conversation!</p>';
            return;
        }

        messagesContainer.innerHTML = state.messages.map(m => {
            const isUserMessage = Number(m.SenderUserID) === currentUserId;
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

        // Scroll to bottom - ensure we scroll the container
        setTimeout(() => {
            const messageArea = document.getElementById('message-container-wrapper');
            if (messageArea) {
                messageArea.scrollTop = messageArea.scrollHeight;
            }
        }, 100);
    }

    window.switchView = function(view) {
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
            state.currentChatId = null;
            state.isCleared = false;
        }
    };

    // NEW: Function to start conversation with user
    window.startConversationWithUser = async function(userId, label) {
        try {
            const res = await apiRequest('create_conversation', { recipient_id: userId }, 'POST');
            
            if (res.success && res.conversation_id) {
                // Close the modal
                window.closeNewChatModal();
                
                // Open the new conversation
                window.openConversation(res.conversation_id, label);
                
                // Refresh conversations list
                fetchConversations();
            } else {
                alert('Failed to create conversation: ' + (res.error || 'Unknown error'));
            }
        } catch (err) {
            console.error('Error starting conversation:', err);
            alert('Error starting conversation: ' + err.message);
        }
    };

    // Initialize
    document.addEventListener('DOMContentLoaded', () => {
        console.log('Chat system initialized');
        console.log('Current User ID:', window.currentUserId);
        
        // Load conversations immediately
        fetchConversations();
        
        // Start polling for new messages
        startMessagePolling();
        
        // Set up periodic refresh for conversations list (when not in active chat)
        setInterval(() => {
            if (!state.currentChatId) {
                fetchConversations();
            }
        }, 10000); // Refresh every 10 seconds

        // Message input Enter key
        if (messageInput) {
            messageInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    window.sendMessage();
                }
            });
        }

        // Back button
        if (backButton) {
            backButton.addEventListener('click', () => window.switchView('list'));
        }
    });
    // Add this function to chat.js
function startMessagePolling() {
    // Check for new messages every 3 seconds when in a conversation
    setInterval(() => {
        if (state.currentChatId && !state.isCleared) {
            checkForNewMessages();
        }
    }, 3000);
}

function checkForNewMessages() {
    if (!state.currentChatId) return;
    
    apiRequest('get_messages', { conversation_id: state.currentChatId }, 'GET').then(res => {
        if (res.success) {
            const newMessages = res.messages || [];
            const currentMessageCount = state.messages.length;
            
            // Only update if new messages arrived
            if (newMessages.length > currentMessageCount) {
                state.messages = newMessages;
                renderMessages();
                
                // Also refresh conversations list to update last message and unread counts
                fetchConversations();
            }
        }
    }).catch(err => {
        console.error('Error checking for new messages:', err);
    });
}
})();