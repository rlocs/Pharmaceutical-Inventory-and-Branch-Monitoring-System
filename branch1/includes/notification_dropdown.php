<?php
/**
 * Notification Dropdown Component
 * Displays notifications for alerts and chat messages
 */
?>
<!-- Notification Dropdown -->
<div id="notification-dropdown" class="hidden fixed top-16 right-4 w-96 bg-white rounded-lg shadow-2xl z-50 border border-gray-200 max-h-[600px] flex flex-col">
    <!-- Header -->
    <div class="p-4 border-b border-gray-200 bg-indigo-50 flex justify-between items-center flex-shrink-0">
        <h3 class="text-lg font-bold text-indigo-700">Notifications</h3>
        <div class="flex items-center gap-2">
            <button id="mark-all-read-btn" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium" title="Mark all as read">
                Mark all read
            </button>
            <button id="close-notification-dropdown" class="text-gray-500 hover:text-gray-700 p-1 rounded">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
    </div>
    
    <!-- Tabs -->
    <div class="flex border-b border-gray-200 bg-gray-50 flex-shrink-0">
        <button class="notification-tab active px-4 py-2 text-sm font-medium text-indigo-600 border-b-2 border-indigo-600" data-tab="all">
            All
        </button>
        <button class="notification-tab px-4 py-2 text-sm font-medium text-gray-600 hover:text-indigo-600" data-tab="alerts">
            Alerts
        </button>
        <button class="notification-tab px-4 py-2 text-sm font-medium text-gray-600 hover:text-indigo-600" data-tab="chat">
            Messages
        </button>
    </div>
    
    <!-- Notifications List -->
    <div id="notifications-list" class="flex-1 overflow-y-auto custom-scroll">
        <div class="p-4 text-center text-gray-500">
            <div class="animate-spin inline-block w-6 h-6 border-2 border-indigo-600 border-t-transparent rounded-full"></div>
            <p class="mt-2 text-sm">Loading notifications...</p>
        </div>
    </div>
    
    <!-- Footer -->
    <div class="p-3 border-t border-gray-200 bg-gray-50 flex-shrink-0 text-center">
        <a href="#" id="view-all-notifications" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
            View all notifications
        </a>
    </div>
</div>

<!-- Notification Badge (Red dot on bell icon) -->
<span id="notification-badge" class="hidden absolute top-0 right-0 w-3 h-3 bg-red-500 rounded-full border-2 border-white"></span>
<span id="notification-count" class="hidden absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center border-2 border-white"></span>

<style>
#notification-dropdown {
    animation: slideDown 0.2s ease-out;
    font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    color: #0f172a;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Notifications readability improvements */
.notification-item {
    padding: 12px 14px;
    border-bottom: 1px solid #eef2f7;
    cursor: pointer;
    transition: background-color 0.12s ease;
}

.notification-item:hover {
    background-color: #f8fafc;
}

.notification-item.unread {
    background-color: #eef2ff;
    border-left: 3px solid #4338ca;
}

.notification-item.read {
    background-color: #ffffff;
}

.notification-item .notification-title {
    font-weight: 700;
    font-size: 15px;
    color: #0b1220;
    margin-bottom: 6px;
    line-height: 1.2;
}

.notification-item .notification-message {
    font-size: 13px;
    color: #374151;
    line-height: 1.45;
    margin: 0;
}

.notification-item .notification-time {
    font-size: 12px;
    color: #6b7280;
    margin-top: 6px;
    display: block;
}

.notification-item .notification-type-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 9999px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    margin-right: 8px;
}

.notification-type-badge.alert { background-color: #fef3c7; color: #92400e; }
.notification-type-badge.chat  { background-color: #dbeafe; color: #1e40af; }
.notification-type-badge.warning { background-color: #fed7aa; color: #9a3412; }
.notification-type-badge.error { background-color: #fee2e2; color: #991b1b; }
.notification-type-badge.info { background-color: #e0e7ff; color: #3730a3; }

.notification-tab.active { color: #4338ca; border-bottom-color: #4338ca; }
.notification-tab { transition: all 0.15s ease; }

#notification-badge, #notification-count { pointer-events: none; }
</style>

