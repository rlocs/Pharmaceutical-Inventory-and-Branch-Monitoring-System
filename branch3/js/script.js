         // State management (initialized globally)
        let isChatMaximized = false;
        let isChatCollapsed = false;

        // Element declarations
        let sidebar, iconsSection, backdrop, chatWidget, chatTitle, chatBody, activeChatContent, chatBackButton, chatNotifBubble, zoomIcon, collapseIcon, chatCollapseButton, chatNewButton, chatZoomButton, nonChatContent, zoomedChatHost, sidebarChatParent;

        /**
         * Toggles the visibility of the sidebar and the icons section.
         */
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const backdrop = document.getElementById('backdrop');
            const iconsSection = document.getElementById('icons-section');
            
            if (!sidebar || !backdrop || !iconsSection) {
                 console.error("Sidebar elements not found during toggle attempt.");
                 return;
            }

            const isSidebarOpen = sidebar.classList.toggle('open');
            backdrop.classList.toggle('visible');

            if (isSidebarOpen) {
                iconsSection.classList.add('hidden');
            } else {
                iconsSection.classList.remove('hidden');
                // Attempt to call the reset function defined below
                if (typeof setChatView !== 'undefined') {
                    setChatView('list');
                    // Reset global chat states
                    if (typeof isChatMaximized !== 'undefined') isChatMaximized = false;
                    if (typeof isChatCollapsed !== 'undefined') isChatCollapsed = false;
                }
            }
        }
 /**
         * Switches the chat widget view between conversation list and active chat.
         * @param {string} viewType - 'list' or 'chat'
         * @param {string} [chatPartner] - The name of the person being chatted with.
         */
        function setChatView(viewType, chatPartner = 'Cross-Branch Chat') {
            if (!chatBody || !activeChatContent || !chatTitle || !chatBackButton || !chatNotifBubble) {
                console.error("Chat view elements not fully initialized, cannot set chat view.");
                return;
            }
            
            if (isChatCollapsed && !isChatMaximized) return; // Guard: Don't show content if collapsed

            if (viewType === 'chat') {
                // Switch to Active Chat view
                chatTitle.textContent = chatPartner;
                chatBody.classList.add('hidden');
                activeChatContent.classList.remove('hidden');
                chatBackButton.classList.remove('hidden');
                chatNotifBubble.classList.add('hidden');
                
            } else {
                // Switch back to Conversation List view
                chatTitle.textContent = 'Cross-Branch Chat';
                chatBody.classList.remove('hidden');
                activeChatContent.classList.add('hidden');
                chatBackButton.classList.add('hidden');
                chatNotifBubble.classList.remove('hidden');
            }
        }

        /**
         * Handler for clicking a chat item.
         * @param {string} partnerName 
         */
        function openChat(partnerName) {
            setChatView('chat', partnerName);
        }

        /**
         * Toggles the chat widget content between visible (expanded) and hidden (collapsed) states in the sidebar.
         */
        function toggleChatCollapse() {
            if (isChatMaximized) return; // Cannot collapse/expand the modal
            
            if (!chatBody || !activeChatContent || !collapseIcon || !chatCollapseButton) {
                console.error("Chat collapse elements not fully initialized, cannot collapse/expand chat.");
                return;
            }

            isChatCollapsed = !isChatCollapsed;

            // Toggle the content visibility and icon
            if (isChatCollapsed) {
                // Collapse: hide all content below the header
                chatBody.classList.add('hidden');
                activeChatContent.classList.add('hidden');
                
                // Change icon to chevron down (to indicate it can be expanded)
                collapseIcon.innerHTML = `<path d="m6 9 6 6 6-6"/>`;
                chatCollapseButton.title = 'Expand Chat';

            } else {
                // Expand: show the appropriate content (list or active chat)
                setChatView(chatBackButton.classList.contains('hidden') ? 'list' : 'chat');
                
                // Change icon to chevron up (to indicate it can be collapsed)
                collapseIcon.innerHTML = `<path d="m18 15-6-6-6 6"/>`;
                chatCollapseButton.title = 'Collapse Chat';
            }

            // Re-render icons if Lucide is available
            if (typeof lucide !== 'undefined' && lucide.createIcons) {
                lucide.createIcons();
            }
        }


        /**
         * Toggles the chat widget between its default size and a maximized size (center modal).
         */
        function toggleChatMaximized() {
            if (!chatWidget || !zoomedChatHost || !sidebarChatParent || !zoomIcon || !chatZoomButton || !chatCollapseButton || !chatNewButton || !chatBody || !activeChatContent) {
                console.error("Chat elements not initialized for maximize function.");
                return;
            }
            
            isChatMaximized = !isChatMaximized;
            
            if (isChatMaximized) {
                // MAXIMIZED (ZOOMED)
                
                // 1. Move chat widget to the center host (Modal view)
                zoomedChatHost.classList.remove('hidden');
                zoomedChatHost.insertBefore(chatWidget, zoomedChatHost.firstChild);

                // 2. Apply centered modal styling to chat widget
                chatWidget.classList.remove('h-fit', 'w-full', 'border'); 
                chatBody.classList.remove('hidden', 'max-h-60');
                activeChatContent.classList.remove('hidden');
                
                // Set the modal size
                chatWidget.classList.add('w-full', 'max-w-lg', 'h-[70vh]', 'rounded-xl', 'border-4', 'overflow-hidden'); 
                
                // 3. Update icon to minimize (Shrink)
                zoomIcon.innerHTML = `<path d="M15 3h3a2 2 0 0 1 2 2v3m-5 13h-3a2 2 0 0 1-2-2v-3m13-3v-3a2 2 0 0 1-2-2h-3M3 15v3a2 2 0 0 0 2 2h3"/>`;
                chatZoomButton.title = 'Minimize Chat';
                
                // 4. Ensure body/content grows inside the modal
                chatBody.classList.add('flex-grow', 'overflow-y-auto');
                activeChatContent.style.flexGrow = 1;
                activeChatContent.style.height = '100%';
                
                // 5. Hide collapse button and show the new chat button when maximized
                chatCollapseButton.classList.add('hidden');
                chatNewButton.classList.remove('hidden');

            } else {
                // MINIMIZED (Returning to sidebar)

                // 1. Move chat widget back to the sidebar
                sidebarChatParent.insertBefore(chatWidget, sidebarChatParent.firstChild);
                zoomedChatHost.classList.add('hidden');

                // 2. Remove centered modal styling
                chatWidget.classList.add('h-fit', 'w-full', 'border');
                chatWidget.classList.remove('w-full', 'max-w-lg', 'h-[70vh]', 'rounded-xl', 'border-4', 'overflow-hidden', 'flex-grow');
                
                // 3. Update icon back to maximize (Expand)
                zoomIcon.innerHTML = `<path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/>`;
                chatZoomButton.title = 'Maximize Chat';
                
                // 4. Restore sidebar sizing and collapse state
                chatBody.classList.remove('flex-grow', 'overflow-y-auto');
                activeChatContent.style.flexGrow = 0;
                
                // Force chat to expanded view when returning from modal
                isChatCollapsed = false; 
                
                // 5. Show collapse button again and hide new chat button
                chatCollapseButton.classList.remove('hidden');
                chatNewButton.classList.add('hidden');
                
                // Restore expanded view (which is the default list view)
                setChatView(chatBackButton.classList.contains('hidden') ? 'list' : 'chat');
                chatBody.classList.add('max-h-60');
                
                // Update collapse icon to 'up' to show it's currently expanded
                collapseIcon.innerHTML = `<path d="m18 15-6-6-6 6"/>`;
                chatCollapseButton.title = 'Collapse Chat';
            }

            // Re-render icons to ensure they look correct
            if (typeof lucide !== 'undefined' && lucide.createIcons) {
                lucide.createIcons();
            }
        }

        /**
         * Placeholder function for the new chat/select branch action
         */
        function handleNewChat() {
            console.log("New Chat / Select Branch feature triggered!");
        }


        /**
         * Updates the date and time display in the sidebar.
         */
        function updateDateTime() {
            const now = new Date();
            
            // Shortened, more compact date format (e.g., Mon, Nov 3)
            const dateOptions = { weekday: 'short', month: 'short', day: 'numeric' };
            const dateString = now.toLocaleDateString('en-US', dateOptions);

            // Time format (e.g., 11:50 AM) - removing seconds for less clutter
            const timeOptions = { hour: 'numeric', minute: '2-digit', hour12: true };
            const timeString = now.toLocaleTimeString('en-US', timeOptions);

            const displayElement = document.getElementById('datetime-display');
            if (displayElement) {
                // Combined into one line with a separator - INCREASED text size to text-lg
                displayElement.innerHTML = `
                    <p class="text-lg font-medium">${dateString} <span class="mx-1 opacity-50">|</span> ${timeString}</p>
                `;
            }
        }


        // Initialization function to run when the DOM is fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            // 1. Assign Element Variables inside the DOM ready handler
            sidebar = document.getElementById('sidebar');
            iconsSection = document.getElementById('icons-section');
            backdrop = document.getElementById('backdrop');
            chatWidget = document.getElementById('chat-widget');
            chatTitle = document.getElementById('chat-title');
            chatBody = document.getElementById('chat-body');
            activeChatContent = document.getElementById('active-chat-content');
            chatBackButton = document.getElementById('chat-back-button');
            chatNotifBubble = document.getElementById('chat-notification-bubble');
            zoomIcon = document.getElementById('zoom-icon');
            collapseIcon = document.getElementById('collapse-icon');
            chatCollapseButton = document.getElementById('chat-collapse-button');
            chatNewButton = document.getElementById('chat-new-button');
            chatZoomButton = document.getElementById('chat-zoom-button'); 
            nonChatContent = document.getElementById('non-chat-content'); 
            zoomedChatHost = document.getElementById('zoomed-chat-host');
            sidebarChatParent = document.getElementById('sidebar-chat-parent'); 

            // 2. ATTACH EVENT LISTENERS
            
            if (chatZoomButton) {
                chatZoomButton.addEventListener('click', toggleChatMaximized);
            } else {
                console.error("Element chat-zoom-button not found for event listener.");
            }
            
            if (chatCollapseButton) {
                chatCollapseButton.addEventListener('click', toggleChatCollapse);
            } else {
                console.error("Element chat-collapse-button not found for event listener.");
            }


            // 3. Initialize Lucide icons (check if available)
            if (typeof lucide !== 'undefined' && lucide.createIcons) {
                lucide.createIcons();
            }
            
            // 4. Set initial date/time and start the clock interval
            updateDateTime();
            setInterval(updateDateTime, 1000);
        });