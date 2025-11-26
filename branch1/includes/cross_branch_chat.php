<?php
// Cross-Branch Chat Component - Separated from sidebar to avoid conflicts
// This component can be included in any page that needs cross-branch chat functionality
?>
<!-- Cross-Branch Chat Widget (Separated Component) -->
<div id="cross-branch-chat-container" class="mb-6">
    <div id="main-container" class="main-container flex bg-white overflow-hidden shadow-xl rounded-lg">
        
        <!-- 1. Contacts/Channel Sidebar -->
        <div id="contacts-sidebar" class="w-full bg-gray-50 border-r border-gray-200 flex-shrink-0 flex flex-col h-full">
            
            <!-- Header -->
            <div class="p-5 border-b border-gray-200 bg-white flex-shrink-0">
                <div class="flex justify-between items-center">
                    <h2 class="text-2xl font-bold text-indigo-700">Chats</h2>
                    <div class="flex items-center gap-2">
                        <button onclick="window.openNewChatModal()" class="text-gray-500 hover:text-indigo-600 p-1 rounded-full transition duration-150" title="Start new conversation">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                        </button>
                        <button onclick="toggleZoom()" class="text-gray-500 hover:text-indigo-600 p-1 rounded-full transition duration-150" title="Open in full screen">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4m-4-4l8-8m0 0H8m8 0v8"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Conversations List -->
            <div id="conversations-list" class="flex-1 overflow-y-auto custom-scroll p-2 space-y-1 min-h-0">
                <p class="text-center text-gray-500 text-sm p-6">Loading conversations...</p>
            </div>
        </div>

        <!-- 2. Chat View -->
        <div id="chat-view" class="flex-1 flex flex-col hidden min-h-0">
            
            <!-- Chat Header -->
            <div class="p-4 border-b border-gray-200 flex justify-between items-center bg-white shadow-sm flex-shrink-0">
                <div class="flex items-center space-x-3">
                    <button id="back-button" onclick="switchView('list')" class="hidden text-gray-500 hover:text-indigo-600 p-1 rounded-full">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                    </button>
                    <div class="w-10 h-10 bg-indigo-600 rounded-full flex items-center justify-center text-white font-bold text-base">GC</div>
                    <div class="flex-1 min-w-0">
                        <h3 id="chat-title" class="text-lg font-bold text-gray-900 break-words"></h3>
                        <p id="chat-subtitle" class="text-sm text-gray-500"></p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <button id="clear-messages-btn" onclick="window.clearMessages()" class="text-gray-500 hover:text-red-600 p-1 rounded-full transition duration-150" title="Clear messages">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Messages Area -->
            <div id="message-container-wrapper" class="relative flex-1 p-4 overflow-y-auto message-area custom-scroll min-h-0">
                <div id="initial-prompt" class="absolute inset-0 flex justify-center items-center text-center z-10 bg-white">
                    <div class="p-8 max-w-sm text-gray-500">
                        <svg class="w-12 h-12 mx-auto mb-3 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                        <p class="text-lg font-semibold">Select a Conversation</p>
                        <p class="text-sm">Click on a conversation to start messaging!</p>
                    </div>
                </div>
                <div id="messages-container" class="flex flex-col w-full relative z-0">
                    <!-- Messages loaded here -->
                </div>
            </div>

            <!-- Input Area -->
            <div id="input-area" class="p-2 border-t border-gray-200 bg-gray-50 flex-shrink-0 hidden">
                <div class="flex items-center gap-2">
                    <input type="text" id="message-input" placeholder="Write a message..." 
                           class="flex-1 p-2 border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm text-gray-900" 
                           style="color: #111827 !important;">
                    <button id="send-btn" onclick="window.sendMessage()" >
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M16.6915026,12.4744748 L3.50612381,13.2599618 C3.19218622,13.2599618 3.03521743,13.4170592 3.03521743,13.5741566 L1.15159189,20.0151496 C0.8376543,20.8006365 0.99,21.89 1.77946707,22.52 C2.41,22.99 3.50612381,23.1 4.13399899,22.8429026 L21.714504,14.0454487 C22.6563168,13.5741566 23.1272231,12.6315722 22.9702544,11.6889879 L4.13399899,1.16346272 C3.34915502,0.9 2.40734225,1.00636533 1.77946707,1.4776575 C0.994623095,2.10604706 0.837654326,3.0486314 1.15159189,3.99701575 L3.03521743,10.4380088 C3.03521743,10.5951061 3.19218622,10.7522035 3.50612381,10.7522035 L16.6915026,11.5376905 C16.6915026,11.5376905 17.1624089,11.5376905 17.1624089,12.0089827 C17.1624089,12.4744748 16.6915026,12.4744748 16.6915026,12.4744748 Z"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- New Chat Modal -->
<div id="new-chat-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-2xl w-[90vw] max-w-md flex flex-col overflow-hidden">
        <div class="p-4 border-b border-gray-200 flex justify-between items-center bg-white flex-shrink-0">
            <h2 class="text-xl font-bold text-indigo-700">Start New Conversation</h2>
            <button onclick="window.closeNewChatModal()" class="text-gray-500 hover:text-indigo-600 p-2 rounded-full transition duration-150">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <div id="new-chat-users-list" class="flex-1 overflow-y-auto p-4 min-h-0 max-h-96">
            <p class="text-center text-gray-500 text-sm p-6">Loading users...</p>
        </div>
    </div>
</div>

<!-- Chat Zoom Modal (Full Screen) -->
<div id="chat-zoom-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-2xl w-[90vw] h-[90vh] max-w-6xl flex flex-col overflow-hidden">
        <div id="chat-zoom-content" class="flex-1 overflow-hidden min-h-0">
            <!-- Chat content cloned here -->
        </div>
    </div>
</div>

