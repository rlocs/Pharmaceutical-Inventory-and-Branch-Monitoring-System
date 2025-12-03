<?php
// Notification Bell UI Include (recreated)
?>
<!-- Bell Icon (Notification) -->
<button id="notification-bell-btn" aria-label="Notifications" class="p-2 hover:bg-slate-700 rounded-full transition duration-150 relative">
  <svg class="lucide" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.375 22a2 2 0 0 0 3.25 0"/></svg>
  <span id="notification-badge" class="hidden absolute top-0 right-0 w-3 h-3 bg-red-500 rounded-full border-2 border-white"></span>
  <span id="notification-count" class="hidden absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full min-w-5 h-5 px-1 flex items-center justify-center border-2 border-white"></span>
</button>

<!-- Notification Dropdown -->
<div id="notification-dropdown" class="hidden fixed top-16 right-4 w-[26rem] bg-white rounded-xl shadow-2xl z-50 border border-gray-200 max-h-[70vh] flex flex-col">
  <!-- Header -->
  <div class="flex items-center justify-between p-3 border-b border-gray-200">
    <div>
      <h3 class="text-base font-semibold text-gray-800">Notifications</h3>
      <p class="text-xs text-gray-500">Unread: <span id="notification-unread-total">0</span></p>
    </div>
    <div class="flex items-center gap-2">
      <button id="mark-all-read-btn" class="text-xs text-indigo-600 hover:text-indigo-800">Mark all read</button>
      <button id="close-notification-dropdown" class="text-gray-500 hover:text-gray-700 p-1 rounded" aria-label="Close">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
  </div>

  <!-- Tabs -->
  <div class="flex border-b border-gray-200 bg-gray-50 flex-shrink-0">
    <button class="notification-tab active px-4 py-2 text-sm font-medium text-indigo-600 border-b-2 border-indigo-600" data-tab="all">All</button>
    <button class="notification-tab px-4 py-2 text-sm font-medium text-gray-600 hover:text-indigo-600" data-tab="alerts">Alerts</button>
    <button class="notification-tab px-4 py-2 text-sm font-medium text-gray-600 hover:text-indigo-600" data-tab="chat">Chat</button>
  </div>

  <!-- Search -->
  <div class="p-2 border-b border-gray-100">
    <div class="relative">
      <input id="notification-search" type="text" placeholder="Search notifications..." class="w-full border rounded-lg pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
      <span class="absolute left-3 top-2.5 text-gray-400">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.9 14.32a8 8 0 111.414-1.414l4.387 4.387a1 1 0 01-1.414 1.414l-4.387-4.387zM14 8a6 6 0 11-12 0 6 6 0 0112 0z" clip-rule="evenodd"/></svg>
      </span>
    </div>
  </div>

  <!-- Lists -->
  <div id="notifications-container" class="overflow-y-auto p-1 flex-1">
    <div id="list-all" data-tab="all"></div>
    <div id="list-alerts" class="hidden" data-tab="alerts"></div>
    <div id="list-chat" class="hidden" data-tab="chat"></div>

    <!-- Empty states -->
    <div id="empty-all" class="hidden p-6 text-center text-sm text-gray-500">No notifications yet.</div>
    <div id="empty-alerts" class="hidden p-6 text-center text-sm text-gray-500">No alerts.</div>
    <div id="empty-chat" class="hidden p-6 text-center text-sm text-gray-500">No messages yet.</div>
  </div>

  <!-- Footer -->
  <div class="flex items-center justify-between p-2 border-t border-gray-100 text-xs text-gray-500">
    <button id="notification-load-more" class="px-3 py-1 rounded border text-gray-600 hover:text-gray-800 hover:bg-gray-50">Load more</button>
    <div>Updated <span id="notification-updated-at">just now</span></div>
  </div>
</div>

<style>
  #notification-dropdown { animation: slideDown 0.18s ease-out; font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; color: #0f172a; }
  @keyframes slideDown { from { opacity: 0; transform: translateY(-10px);} to { opacity:1; transform: translateY(0);} }

  /* Notification item layout and improved readability */
  .notification-item { padding: 12px 14px; border-bottom: 1px solid #eef2f7; cursor: pointer; display: block; }
  .notification-item:hover { background-color: #f8fafc; }
  .notification-item.unread { background-color: #eef2ff; border-left: 3px solid #4338ca; }
  .notification-item.read { background-color: #ffffff; }

  .notification-title { font-weight: 700; font-size: 15px; color: #0b1220; margin-bottom: 6px; line-height: 1.2; }
  .notification-message { font-size: 13px; color: #374151; line-height: 1.45; margin: 0; }
  .notification-time { font-size: 12px; color: #6b7280; margin-top: 6px; display: block; }

  .notification-type-badge { display: inline-block; padding: 4px 8px; font-size: 11px; border-radius: 9999px; margin-right: 8px; text-transform: uppercase; font-weight:700; }
  .badge-inventory { background-color: #eef2ff; color: #3730a3; }
  .badge-med { background-color: #dbeafe; color: #1d4ed8; }
  .badge-chat { background-color: #c7d2fe; color: #4338ca; }
  .badge-pos { background-color: #dbf4ff; color: #0369a1; }
  .badge-reports { background-color: #fef3c7; color: #92400e; }
  .badge-account { background-color: #ecfccb; color: #4d7c0f; }

  /* Active tab indicator */
  .notification-tab.active {
    border-bottom: 2px solid #4f46e5; /* indigo-600 */
    color: #4f46e5;
  }
</style>
