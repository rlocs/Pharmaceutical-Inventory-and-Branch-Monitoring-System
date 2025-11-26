<?php
// Canonical sidebar for branch1 pages
// This file expects the including page to define these variables:
// $user_full_name, $user_role, $current_branch_id, $branch_name
?>

<!-- 4. SIDEBAR (Off-screen by default) -->
<aside id="sidebar" class="sidebar bg-secondary-dark text-white p-6 lg:p-8  flex flex-col shadow-2xl overflow-y-auto">

    <!-- Sidebar Header / Close Button (Static) -->
    <div class="flex justify-end mb-6 pt-1">
        <!-- Calls toggleSidebar() -->
        <button aria-label="Close Menu" onclick="toggleSidebar()" class="p-2 bg-slate-700 hover:bg-slate-600 rounded-lg transition duration-150">
            <svg class="lucide" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/></svg>
        </button>
    </div>

    <!-- Content Area: Profile and Alerts (Uses ASIDE's scrollbar) -->
    <div id="non-chat-content" class="transition-opacity duration-200">

        <!-- User Profile Section - INCREASED TEXT SIZES -->
        <div id="user-profile-section" class="w-full text-center mb-6 py-4 border-b border-slate-700">
            <!-- Profile Image -->
            <div class="w-24 h-24 mx-auto rounded-full overflow-hidden border-4 border-primary-accent shadow-xl">
                <img src="https://placehold.co/96x96/60A5FA/ffffff?text=<?php echo strtoupper(substr($user_full_name ?? '', 0, 1) . (strpos($user_full_name ?? '', ' ') ? substr($user_full_name, strpos($user_full_name, ' ') + 1, 1) : '')); ?>" alt="Profile" class="w-full h-full object-cover">
            </div>
            <h3 class="text-lg font-semibold mt-3 text-white"><?php echo $user_full_name ?? 'User'; ?></h3>
            <p class="text-sm text-white"><?php echo $user_role ?? ''; ?></p>
            <p class="text-xs text-gray-300 mt-1">Branch <?php echo $current_branch_id ?? ''; ?> - <?php echo $branch_name ?? ''; ?></p>

            <!-- Condensed Date and Time Display -->
            <div id="datetime-display" class="mt-4 font-medium text-gray-200">
                <!-- Date and Time will be inserted here by JavaScript -->
            </div>
        </div>

        <!-- Cross-Branch Chat Widget (Separated Component) -->
        <?php include __DIR__ . '/cross_branch_chat.php'; ?>

        <!-- Alerts & Notifications Section -->
        <div class="space-y-6 mb-8 pt-2" id="alerts-section">
            <h4 class="text-lg font-semibold text-white mb-4">Alerts & Notifications</h4>
            <div class="bg-gradient-to-r from-red-50 to-red-100 p-4 rounded-xl shadow-lg text-gray-900 overflow-x-hidden hover:shadow-xl hover:scale-105 transition-all duration-200">
                <h5 class="text-base font-semibold text-red-700 mb-2 flex items-center">
                    <svg class="lucide w-5 h-5 mr-2" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                    Low Stock / Out of Stock
                </h5>
                <ul id="low-stock-list" class="text-sm text-gray-800 space-y-2">
                    <li>Loading alerts...</li>
                </ul>
            </div>
            <div class="bg-gradient-to-r from-yellow-50 to-yellow-100 p-4 rounded-xl shadow-lg text-gray-900 overflow-x-hidden hover:shadow-xl hover:scale-105 transition-all duration-200">
                <h5 class="text-base font-semibold text-yellow-700 mb-2 flex items-center">
                    <svg class="lucide w-5 h-5 mr-2" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>
                    Expiring Soon
                </h5>
                <ul id="expiring-soon-list" class="text-sm text-gray-800 space-y-2">
                    <li>Loading alerts...</li>
                </ul>
            </div>
            <div class="bg-gradient-to-r from-gray-50 to-gray-100 p-4 rounded-xl shadow-lg text-gray-900 overflow-x-hidden hover:shadow-xl hover:scale-105 transition-all duration-200">
                <h5 class="text-base font-semibold text-gray-800 mb-2 flex items-center">
                    <svg class="lucide w-5 h-5 mr-2" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>
                    Expired
                </h5>
                <ul id="expired-list" class="text-sm text-gray-800 space-y-2">
                    <li>Loading alerts...</li>
                </ul>
            </div>
        </div>

    </div> <!-- End Content Area -->

</aside>
