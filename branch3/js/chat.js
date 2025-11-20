/* Sidebar-Optimized Messenger Chat UI
   - Responsive design that fits perfectly in sidebar
   - Single-pane flow with zoom capability
   - Integrates with api/chat_api.php backend
*/

(function () {
    const apiBase = 'api/chat_api.php';
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
        isZoomed: false
    };

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
                        zoomWindow.postMessage({
                            type: 'LOAD_CHAT',
                            html: html
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
    
    window.closeZoomModal = function() {
        const zoomModal = document.getElementById('chat-zoom-modal');
        if (zoomModal) {
            zoomModal.classList.add('hidden');
            state.isZoomed = false;
        }
    };
    
    // Close modal when clicking outside (on backdrop)
    document.addEventListener('click', function(e) {
        const zoomModal = document.getElementById('chat-zoom-modal');
        if (zoomModal && !zoomModal.classList.contains('hidden')) {
            // Only close if clicking directly on the backdrop (the modal itself)
            if (e.target === zoomModal) {
                closeZoomModal();
            }
        }
    });
    
    function initializeZoomedChat(container) {
        // Re-attach event listeners to cloned elements
        const clonedBackBtn = container.querySelector('#back-button');
        const clonedContactsSidebar = container.querySelector('#contacts-sidebar');
        const clonedChatView = container.querySelector('#chat-view');
        const clonedMsgInput = container.querySelector('#message-input');
        const clonedSendBtn = container.querySelector('#send-btn');
        const clonedConvItems = container.querySelectorAll('.conversation-item');
        
        // Back button
        if (clonedBackBtn) {
            clonedBackBtn.onclick = function(e) {
                e.preventDefault();
                if (clonedContactsSidebar && clonedChatView) {
                    clonedContactsSidebar.style.display = 'flex';
                    clonedChatView.style.display = 'none';
                    clonedBackBtn.classList.add('hidden');
                }
            };
        }
        
        // Conversation items
        clonedConvItems.forEach(item => {
            item.onclick = function(e) {
                e.preventDefault();
                const onclickAttr = this.getAttribute('onclick');
                if (onclickAttr) {
                    const match = onclickAttr.match(/openConversation\((\d+),\s*'([^']+)'\)/);
                    if (match) {
                        const id = parseInt(match[1]);
                        const lbl = match[2];
                        openConversationInZoom(id, lbl, container);
                    }
                }
            };
        });
        
        // Message input
        if (clonedMsgInput) {
            clonedMsgInput.onkeydown = function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    sendMessageInZoom(container);
                }
            };
        }
        
        // Send button
        if (clonedSendBtn) {
            clonedSendBtn.onclick = function(e) {
                e.preventDefault();
                sendMessageInZoom(container);
            };
        }
    }

    function attachZoomedEventListenersToWindow(zoomWin) {
        try {
            const doc = zoomWin.document;
            
            // + Button (New Chat) - open modal in zoom window
            const newChatBtn = doc.querySelector('button[onclick="window.openNewChatModal()"]');
            if (newChatBtn) {
                newChatBtn.onclick = function(e) {
                    e.preventDefault();
                    // Open new chat modal in the zoom window itself
                    const modal = doc.getElementById('new-chat-modal');
                    if (modal) {
                        modal.classList.remove('hidden');
                        // Fetch users for the modal using the zoom window's context
                        fetchUsersForNewChatInZoomWindow(zoomWin);
                    }
                };
            }
            
            // Zoom button (should close the zoom window)
            const zoomBtn = doc.querySelector('button[onclick="toggleZoom()"]');
            if (zoomBtn) {
                zoomBtn.onclick = function(e) {
                    e.preventDefault();
                    // Close the zoom window
                    zoomWin.close();
                    if (window.opener) {
                        window.opener.state.isZoomed = false;
                        window.opener.zoomWindow = null;
                    }
                };
            }
            
            // Back button
            const backBtn = doc.querySelector('#back-button');
            if (backBtn) {
                backBtn.onclick = function(e) {
                    e.preventDefault();
                    const contactsSidebar = doc.querySelector('#contacts-sidebar');
                    const chatView = doc.querySelector('#chat-view');
                    if (contactsSidebar && chatView) {
                        contactsSidebar.style.display = 'flex';
                        chatView.style.display = 'none';
                        backBtn.classList.add('hidden');
                    }
                };
            }
            
            // Conversation items
            const convItems = doc.querySelectorAll('.conversation-item');
            convItems.forEach(item => {
                item.onclick = function(e) {
                    e.preventDefault();
                    const onclickAttr = this.getAttribute('onclick');
                    if (onclickAttr) {
                        const match = onclickAttr.match(/openConversation\((\d+),\s*'([^']+)'\)/);
                        if (match) {
                            const id = parseInt(match[1]);
                            const lbl = match[2];
                            openConversationInZoomWindow(id, lbl, zoomWin);
                        }
                    }
                };
            });
            
            // Message input
            const msgInput = doc.querySelector('#message-input');
            if (msgInput) {
                msgInput.onkeydown = function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        sendMessageInZoomWindow(zoomWin);
                    }
                };
            }
            
            // Send button - update styling and attach handler
            const sendBtn = doc.querySelector('#send-btn');
            if (sendBtn) {
                // Remove circular background styling
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
                
                sendBtn.onclick = function(e) {
                    e.preventDefault();
                    sendMessageInZoomWindow(zoomWin);
                };
            }
        } catch (err) {
            console.error('Error attaching listeners to zoom window:', err);
        }
    }

    function openConversationInZoomWindow(conversationId, label, zoomWin) {
        state.currentChatId = conversationId;
        state.currentPartnerLabel = label;

        const doc = zoomWin.document;
        const clonedTitle = doc.querySelector('#chat-title');
        const clonedSubtitle = doc.querySelector('#chat-subtitle');
        if (clonedTitle) clonedTitle.textContent = label;
        if (clonedSubtitle) clonedSubtitle.textContent = ''; // Removed "Active now"

        const clonedInitialPrompt = doc.querySelector('#initial-prompt');
        const clonedInputArea = doc.querySelector('#input-area');
        const clonedChatView = doc.querySelector('#chat-view');
        const clonedContactsSidebar = doc.querySelector('#contacts-sidebar');
        const clonedBackBtn = doc.querySelector('#back-button');
        const clonedMsgContainer = doc.querySelector('#messages-container');

        if (clonedInitialPrompt) clonedInitialPrompt.classList.add('hidden');
        if (clonedInputArea) clonedInputArea.classList.remove('hidden');
        if (clonedChatView) clonedChatView.classList.remove('justify-center', 'items-center');
        
        if (clonedContactsSidebar) clonedContactsSidebar.style.display = 'none';
        if (clonedChatView) clonedChatView.style.display = 'flex';
        if (clonedBackBtn) clonedBackBtn.classList.remove('hidden');

        if (clonedMsgContainer) clonedMsgContainer.innerHTML = '<p class="text-center text-sm italic text-gray-500 p-8">Loading messages...</p>';
        
        apiRequest('get_messages', { conversation_id: conversationId }, 'GET').then(res => {
            if (!res.success) {
                if (clonedMsgContainer) clonedMsgContainer.innerHTML = `<p class="text-center text-red-500 text-sm p-8">${escapeHtml(res.error || 'Failed to load messages')}</p>`;
                return;
            }
            state.messages = res.messages || [];
            renderMessagesInZoomWindow(state.messages, zoomWin);
        });
    }

    function renderMessagesInZoomWindow(messages, zoomWin) {
        const doc = zoomWin.document;
        const clonedMsgContainer = doc.querySelector('#messages-container');
        if (!clonedMsgContainer) return;

        if (messages.length === 0) {
            clonedMsgContainer.innerHTML = '<p class="text-center text-sm italic text-gray-500 p-8">Send the first message to start the conversation!</p>';
            return;
        }

        clonedMsgContainer.innerHTML = messages.map(m => {
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

        setTimeout(() => {
            const messageArea = doc.querySelector('#message-container-wrapper');
            if (messageArea) messageArea.scrollTop = messageArea.scrollHeight;
        }, 0);
    }

    function sendMessageInZoomWindow(zoomWin) {
        const doc = zoomWin.document;
        const clonedInput = doc.querySelector('#message-input');
        const content = clonedInput ? clonedInput.value.trim() : '';
        
        if (!content || !state.currentChatId) return;

        if (clonedInput) clonedInput.value = '';
        if (clonedInput) clonedInput.focus();

        apiRequest('send_message', { conversation_id: state.currentChatId, message: content }, 'POST').then(res => {
            if (res.success && res.message) {
                state.messages.push(res.message);
                renderMessagesInZoomWindow(state.messages, zoomWin);
            }
        });
    }

    function attachZoomedEventListeners(container) {
        // Get references to cloned elements
        const clonedContactsSidebar = container.querySelector('#contacts-sidebar');
        const clonedChatView = container.querySelector('#chat-view');
        const clonedBackBtn = container.querySelector('#back-button');
        const clonedZoomBtn = container.querySelector('button[onclick="toggleZoom()"]');
        const clonedMsgInput = container.querySelector('#message-input');
        const clonedSendBtn = container.querySelector('button[onclick="window.sendMessage()"]');
        const clonedConvList = container.querySelector('#conversations-list');
        const clonedMsgContainer = container.querySelector('#messages-container');
        const clonedInitialPrompt = container.querySelector('#initial-prompt');
        const clonedInputArea = container.querySelector('#input-area');
        
        // Back button - switch view in zoomed container
        if (clonedBackBtn) {
            clonedBackBtn.onclick = function(e) {
                e.preventDefault();
                if (clonedContactsSidebar && clonedChatView) {
                    clonedContactsSidebar.style.display = 'flex';
                    clonedChatView.style.display = 'none';
                    clonedBackBtn.classList.add('hidden');
                }
            };
        }
        
        // Zoom button - close zoom
        if (clonedZoomBtn) {
            clonedZoomBtn.onclick = function(e) {
                e.preventDefault();
                window.toggleZoom();
            };
        }
        
        // Conversation items - open conversation in zoomed view
        const convItems = container.querySelectorAll('.conversation-item');
        convItems.forEach(item => {
            item.onclick = function(e) {
                e.preventDefault();
                const convId = this.getAttribute('data-conv-id');
                const label = this.getAttribute('data-label');
                
                if (!convId || !label) {
                    // Try to extract from onclick attribute
                    const onclickAttr = this.getAttribute('onclick');
                    if (onclickAttr) {
                        const match = onclickAttr.match(/openConversation\((\d+),\s*'([^']+)'\)/);
                        if (match) {
                            const id = parseInt(match[1]);
                            const lbl = match[2];
                            openConversationInZoom(id, lbl, container);
                        }
                    }
                } else {
                    openConversationInZoom(parseInt(convId), label, container);
                }
            };
        });
        
        // Message input - Enter to send
        if (clonedMsgInput) {
            clonedMsgInput.onkeydown = function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    sendMessageInZoom(container);
                }
            };
        }
        
        // Send button
        if (clonedSendBtn) {
            clonedSendBtn.onclick = function(e) {
                e.preventDefault();
                sendMessageInZoom(container);
            };
        }
    }

    function openConversationInZoom(conversationId, label, container) {
        state.currentChatId = conversationId;
        state.currentPartnerLabel = label;

        // Update header in zoomed view
        const clonedTitle = container.querySelector('#chat-title');
        const clonedSubtitle = container.querySelector('#chat-subtitle');
        if (clonedTitle) clonedTitle.textContent = label;
        if (clonedSubtitle) clonedSubtitle.textContent = ''; // Removed "Active now"

        // Hide initial prompt and show input
        const clonedInitialPrompt = container.querySelector('#initial-prompt');
        const clonedInputArea = container.querySelector('#input-area');
        const clonedChatView = container.querySelector('#chat-view');
        const clonedContactsSidebar = container.querySelector('#contacts-sidebar');
        const clonedBackBtn = container.querySelector('#back-button');
        const clonedMsgContainer = container.querySelector('#messages-container');
        const clonedMsgWrapper = container.querySelector('#message-container-wrapper');

        if (clonedInitialPrompt) clonedInitialPrompt.classList.add('hidden');
        if (clonedInputArea) clonedInputArea.classList.remove('hidden');
        if (clonedChatView) clonedChatView.classList.remove('justify-center', 'items-center');
        
        // Switch view
        if (clonedContactsSidebar) clonedContactsSidebar.style.display = 'none';
        if (clonedChatView) clonedChatView.style.display = 'flex';
        if (clonedBackBtn) clonedBackBtn.classList.remove('hidden');

        // Load messages
        if (clonedMsgContainer) clonedMsgContainer.innerHTML = '<p class="text-center text-sm italic text-gray-500 p-8">Loading messages...</p>';
        
        apiRequest('get_messages', { conversation_id: conversationId }, 'GET').then(res => {
            if (!res.success) {
                if (clonedMsgContainer) clonedMsgContainer.innerHTML = `<p class="text-center text-red-500 text-sm p-8">${escapeHtml(res.error || 'Failed to load messages')}</p>`;
                return;
            }
            state.messages = res.messages || [];
            renderMessagesInZoom(state.messages, container);
            
            // Scroll to bottom after rendering
            setTimeout(() => {
                if (clonedMsgWrapper) {
                    clonedMsgWrapper.scrollTop = clonedMsgWrapper.scrollHeight;
                }
            }, 100);
        });
    }

    function renderMessagesInZoom(messages, container) {
        const clonedMsgContainer = container.querySelector('#messages-container');
        const clonedInitialPrompt = container.querySelector('#initial-prompt');
        if (!clonedMsgContainer) return;

        // Hide initial prompt when messages are loaded
        if (clonedInitialPrompt) clonedInitialPrompt.classList.add('hidden');

        if (messages.length === 0) {
            clonedMsgContainer.innerHTML = '<p class="text-center text-sm italic text-gray-500 p-8">Send the first message to start the conversation!</p>';
            return;
        }

        clonedMsgContainer.innerHTML = messages.map(m => {
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

        // Scroll to bottom
        setTimeout(() => {
            const messageArea = container.querySelector('#message-container-wrapper');
            if (messageArea) {
                messageArea.scrollTop = messageArea.scrollHeight;
            }
        }, 100);
    }

    function sendMessageInZoom(container) {
        const clonedInput = container.querySelector('#message-input');
        const content = clonedInput ? clonedInput.value.trim() : '';
        
        if (!content || !state.currentChatId) return;

        if (clonedInput) clonedInput.value = '';
        if (clonedInput) clonedInput.focus();

        apiRequest('send_message', { conversation_id: state.currentChatId, message: content }, 'POST').then(res => {
            if (res.success && res.message) {
                state.messages.push(res.message);
                renderMessagesInZoom(state.messages, container);
            }
        });
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
        }
    };

    function renderConversationList(convs) {
        const listContainer = document.getElementById('conversations-list');
        if (!listContainer) return;

        // Remove duplicates based on ConversationID - more robust deduplication
        const uniqueConvs = [];
        const seenIds = new Set();
        const seenKeys = new Set(); // Track by ConversationID + Name + Branch for extra safety
        
        convs.forEach(c => {
            if (!c.ConversationID) return; // Skip invalid entries
            
            const convId = parseInt(c.ConversationID);
            if (seenIds.has(convId)) {
                // Already seen this conversation ID, skip
                return;
            }
            
            // Create a unique key for additional deduplication
            const name = (c.FirstName || '') + (c.LastName ? ' ' + c.LastName : '');
            const branch = c.BranchName || '';
            const uniqueKey = convId + '|' + name + '|' + branch;
            
            if (seenKeys.has(uniqueKey)) {
                // Same conversation with same details, skip
                return;
            }
            
            seenIds.add(convId);
            seenKeys.add(uniqueKey);
            uniqueConvs.push(c);
        });

        if (uniqueConvs.length === 0) {
            listContainer.innerHTML = '<p class="text-center text-gray-500 text-sm p-6">No conversations yet. Click + to start a new one!</p>';
            return;
        }

        listContainer.innerHTML = `
            <div class="p-2 text-xs text-center text-gray-500 border-b border-gray-200 mb-2">
                Your Conversations
            </div>
        ` + uniqueConvs.map(c => {
            const name = escapeHtml((c.FirstName || 'Unknown') + (c.LastName ? ' ' + c.LastName : ''));
            const branch = escapeHtml(c.BranchName || '');
            const isActive = state.currentChatId === c.ConversationID;
            const activeClass = isActive ? 'bg-indigo-50 border-indigo-200' : 'hover:bg-gray-100';

            return `
            <div class="conversation-item p-3 flex items-center space-x-3 rounded-xl cursor-pointer shadow-sm border transition ${activeClass}" 
                 onclick="window.openConversation(${c.ConversationID}, '${escapeHtml((c.FirstName || '') + (c.LastName ? ' ' + c.LastName : '') + (c.BranchName ? ' (' + c.BranchName + ')' : ''))}')">
                <div class="w-10 h-10 bg-indigo-600 rounded-full flex items-center justify-center text-white font-semibold text-sm flex-shrink-0">
                    ${escapeHtml(initials(name))}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-gray-800 truncate">${name}</p>
                    <p class="text-xs text-gray-600 truncate">${branch}</p>
                </div>
            </div>`;
        }).join('');
    }

    function getUserDisplayName() {
        return 'Pharma User';
    }

    window.openConversation = function(conversationId, label) {
        state.currentChatId = conversationId;
        state.currentPartnerLabel = label;

        // Update header
        const chatTitleEl = document.getElementById('chat-title');
        const chatSubtitleEl = document.getElementById('chat-subtitle');
        if (chatTitleEl) chatTitleEl.textContent = label;
        if (chatSubtitleEl) chatSubtitleEl.textContent = ''; // Removed "Active now"

        // Hide initial prompt and show input
        if (initialPrompt) initialPrompt.classList.add('hidden');
        if (chatView) chatView.classList.remove('justify-center', 'items-center');
        if (inputArea) inputArea.classList.remove('hidden');

        // Load messages
        if (messagesContainer) messagesContainer.innerHTML = '<p class="text-center text-sm italic text-gray-500 p-8">Loading messages...</p>';
        apiRequest('get_messages', { conversation_id: conversationId }, 'GET').then(res => {
            if (!res.success) {
                if (messagesContainer) messagesContainer.innerHTML = `<p class="text-center text-red-500 text-sm p-8">${escapeHtml(res.error || 'Failed to load messages')}</p>`;
                return;
            }
            state.messages = res.messages || [];
            renderMessages();
        });

        window.switchView('chat');
    };

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

    window.sendMessage = async function() {
        const content = messageInput.value.trim();
        if (!content || !state.currentChatId) return;

        messageInput.value = '';
        messageInput.focus();

        apiRequest('send_message', { conversation_id: state.currentChatId, message: content }, 'POST').then(res => {
            if (res.success && res.message) {
                state.messages.push(res.message);
                renderMessages();
            }
        });
    };

    function fetchConversations() {
        apiRequest('get_conversations', {}, 'GET').then(res => {
            if (!res.success) {
                // If no conversations, fetch users instead
                fetchUsers();
                return;
            }
            const conversations = res.conversations || [];
            
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
            
            if (uniqueConversations.length === 0) {
                // No conversations, show users instead
                fetchUsers();
            } else {
                renderConversationList(uniqueConversations);
            }
        }).catch(err => {
            console.error('Error fetching conversations:', err);
            fetchUsers();
        });
    }

    /* Chat unread badge updater
       Queries notifications summary and updates the sidebar + zoom badges.
    */
    function updateChatBadge() {
        // notifications summary endpoint
        fetch('api/notifications.php?action=summary', { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (!data || !data.success) return;
                const chatCount = Number(data.summary?.chat || 0);
                const el = document.getElementById('chat-badge-sidebar');
                const z = document.getElementById('chat-badge-zoom');
                if (el) {
                    if (chatCount > 0) {
                        el.classList.remove('hidden');
                        el.textContent = chatCount > 99 ? '99+' : String(chatCount);
                    } else {
                        el.classList.add('hidden');
                    }
                }
                if (z) {
                    if (chatCount > 0) {
                        z.classList.remove('hidden');
                        z.textContent = chatCount > 99 ? '99+' : String(chatCount);
                    } else {
                        z.classList.add('hidden');
                    }
                }

                // If chat is zoomed into a separate window, try updating it as well
                try {
                    if (window.zoomWindow && !window.zoomWindow.closed) {
                        const wz = window.zoomWindow.document.getElementById('chat-badge-zoom');
                        if (wz) {
                            if (chatCount > 0) { wz.classList.remove('hidden'); wz.textContent = chatCount > 99 ? '99+' : String(chatCount); }
                            else { wz.classList.add('hidden'); }
                        }
                    }
                } catch (e) {
                    // cross-window access may fail in some browsers; ignore
                }
            }).catch(err => {
                // ignore errors silently
            });
    }

    // Update badge immediately and poll every 15s
    updateChatBadge();
    setInterval(updateChatBadge, 15000);
    
    function fetchUsers() {
        console.log('Fetching users...');
        apiRequest('get_users', {}, 'GET').then(res => {
            console.log('Get users response:', res);
            if (!res.success) {
                console.error('Failed to fetch users:', res.error);
                const listContainer = document.getElementById('conversations-list');
                if (listContainer) {
                    listContainer.innerHTML = '<p class="text-center text-gray-500 text-sm p-6">No users available</p>';
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
                listContainer.innerHTML = '<p class="text-center text-gray-500 text-sm p-6">Error loading users</p>';
            }
        });
    }
    
    function renderUserList(users) {
        const listContainer = document.getElementById('conversations-list');
        if (!listContainer) return;
        
        if (users.length === 0) {
            listContainer.innerHTML = '<p class="text-center text-gray-500 text-sm p-6">No other users available</p>';
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
    
    window.startConversationWithUser = async function(userId, label) {
        console.log('Starting conversation with user:', userId, label);
        try {
            // Create or find conversation (API already handles finding existing ones)
            const res = await apiRequest('create_conversation', { recipient_id: userId }, 'POST');
            console.log('Create conversation response:', res);
            if (!res.success) {
                console.error('Failed to create conversation:', res.error);
                alert('Failed to create conversation: ' + (res.error || 'Unknown error'));
                return;
            }
            
            const conversationId = res.conversation_id;
            if (conversationId) {
                // Close the new chat modal
                closeNewChatModal();
                
                // Open the conversation (will show empty if new, or existing messages if conversation already existed)
                window.openConversation(conversationId, label);
                
                // Refresh conversations list to show the conversation
                fetchConversations();
            }
        } catch (err) {
            console.error('Error starting conversation:', err);
            alert('Error starting conversation: ' + err.message);
        }
    };
    
    window.openNewChatModal = function() {
        const modal = document.getElementById('new-chat-modal');
        if (!modal) {
            console.error('New chat modal not found');
            return;
        }
        
        modal.classList.remove('hidden');
        
        // Fetch and display users
        fetchUsersForNewChat();
    };
    
    window.closeNewChatModal = function() {
        const modal = document.getElementById('new-chat-modal');
        if (modal) {
            modal.classList.add('hidden');
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
            const currentUserId = Number(window.currentUserId || 0);
            
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
    
    function fetchUsersForNewChatInZoomWindow(zoomWin) {
        try {
            const doc = zoomWin.document;
            const usersList = doc.getElementById('new-chat-users-list');
            if (!usersList) return;
            
            usersList.innerHTML = '<p class="text-center text-gray-500 text-sm p-6">Loading users...</p>';
            
            // Use parent window's API request (since zoom window shares the same origin)
            apiRequest('get_users', {}, 'GET').then(res => {
                if (!res.success) {
                    usersList.innerHTML = '<p class="text-center text-red-500 text-sm p-6">Failed to load users</p>';
                    return;
                }
                
                const users = res.users || [];
                const currentUserId = Number(window.currentUserId || 0);
                
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
        } catch (err) {
            console.error('Error in fetchUsersForNewChatInZoomWindow:', err);
        }
    }
    
    // Close modal when clicking outside
    document.addEventListener('click', function(e) {
        const newChatModal = document.getElementById('new-chat-modal');
        if (newChatModal && !newChatModal.classList.contains('hidden')) {
            const modalContent = newChatModal.querySelector('div > div');
            if (modalContent && !modalContent.contains(e.target) && e.target === newChatModal) {
                closeNewChatModal();
            }
        }
    });

    // Initialize
    document.addEventListener('DOMContentLoaded', () => {
        console.log('Chat system initialized');
        console.log('Current User ID:', window.currentUserId);
        fetchConversations();
        setInterval(() => {
            if (!state.currentChatId) fetchConversations();
        }, 5000);

        // Message input Enter key
        messageInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                window.sendMessage();
            }
        });

        // Back button
        backButton.addEventListener('click', () => window.switchView('list'));
    });
})();
