<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MERCURY System</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="staff/css/style.css">
</head>
<body class="font-sans">

    <div class="main-container">
        <!-- HEADER -->
        <header class="sticky top-0 z-40 bg-white flex justify-between items-center px-4 md:px-10 h-20 shadow-lg">
            <div class="flex items-center space-x-3">
                <img src="https://placehold.co/40x40/fff/1E3A8A?text=LOGO" alt="Mercury Logo" class="rounded-full border-2 border-primary-accent">
                <span class="text-2xl font-bold text-gray-800 tracking-wider">
                    <span class="text-primary-accent">MERCURY</span>
                </span>
            </div>

            <!-- NAV LINKS -->
            <nav id="navbar" class="hidden md:flex items-center space-x-8">
                <a href="staff/dashboard.php" class="nav-link active" data-module="dashboard">Dashboard</a>
                <a href="staff/med_inventory.php" class="nav-link" data-module="inventory">Med Inventory</a>
                <a href="staff/pos/pos.php" class="nav-link" data-module="pos">POS</a>
                <a href="staff/reports.php" class="nav-link" data-module="reports">Reports</a>
                <a href="staff/account.php" class="nav-link" data-module="account">Account</a>
            </nav>

            

            <!-- MOBILE MENU BUTTON -->
            <button id="mobile-menu-button" class="md:hidden p-2 text-gray-700 rounded-lg hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-accent" aria-label="Toggle menu">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"/>
                </svg>
            </button>
        </header>

        <!-- MOBILE MENU -->
        <div id="mobile-menu" class="hidden md:hidden bg-white shadow-md">
            <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
                <a href="staff/dashboard.php" class="mobile-link active">Dashboard</a>
                <a href="staff/med_inventory.php" class="mobile-link">Med Inventory</a>
                <a href="staff/pos/pos.php" class="mobile-link">POS</a>
                <a href="staff/reports.php" class="mobile-link">Reports</a>
                <a href="staff/account.php" class="mobile-link">Account</a>
            </div>
        </div>

        <!-- CONTENT AREA -->
        <div class="content">
            <div class="main-content flex-1 p-6 lg:p-10 overflow-y-auto bg-main-bg-color">
                <h2 id="page-title" class="text-4xl font-extrabold text-gray-900 mb-4">Dashboard Module</h2>
                <p class="text-gray-600 text-lg mb-8">Select a module to manage your operations efficiently.</p>

                <!-- Dashboard Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="bg-white p-6 rounded-3xl shadow-lg border border-gray-100">
                        <p class="text-sm font-semibold text-gray-500">Sales Value</p>
                        <p class="text-3xl font-bold text-gray-800 mt-1">$12,450.00</p>
                        <span class="text-sm text-green-500 flex items-center mt-2">+5.2% Daily</span>
                    </div>

                    <div class="bg-white p-6 rounded-3xl shadow-lg border border-gray-100">
                        <p class="text-sm font-semibold text-gray-500">Inventory Items</p>
                        <p class="text-3xl font-bold text-gray-800 mt-1">452 SKUs</p>
                        <span class="text-sm text-primary-accent flex items-center mt-2">Check for restock needs</span>
                    </div>

                    <div class="bg-white p-6 rounded-3xl shadow-lg border border-gray-100">
                        <p class="text-sm font-semibold text-gray-500">Pending Orders</p>
                        <p class="text-3xl font-bold text-red-500 mt-1">7</p>
                        <span class="text-sm text-red-500 flex items-center mt-2">Urgent attention required</span>
                    </div>
                </div>
            </div>

            <!-- SIDEBAR -->
            <aside class="sidebar lg:w-96 bg-secondary-dark text-white p-6 lg:p-8 flex flex-col shadow-2xl">
                <!-- USER INFO -->
                <div class="w-full text-center mb-8 pt-4">
                    <div class="w-24 h-24 mx-auto rounded-full overflow-hidden border-4 border-primary-accent shadow-xl">
                        <img src="https://placehold.co/96x96/60A5FA/ffffff?text=AD" alt="Profile" class="w-full h-full object-cover">
                    </div>
                    <h3 class="text-lg font-semibold mt-3 text-white">John Bautista</h3>
                    <p class="text-sm text-white">Administrator</p>
                    <p class="text-xs text-gray-300 mt-1">Branch 1 - Lipa, Batangas</p>
                </div>

                <!-- ALERT SECTION -->
                <div class="space-y-4 mb-6">
                    <h4 class="text-md font-semibold text-white mb-2">Alerts & Notifications</h4>

                    <div class="bg-white p-4 rounded-lg shadow-lg text-gray-900 mb-6">
                        <h5 class="text-sm font-semibold text-red-600 mb-1">Low Stock / Out of Stock</h5>
                        <ul id="low-stock-list" class="text-xs text-gray-700 space-y-1">
                            <li class="font-medium text-gray-500">Loading low stock alerts...</li>
                        </ul>
                    </div>

                    <div class="bg-white p-4 rounded-lg shadow-lg text-gray-900">
                        <h5 class="text-sm font-semibold text-yellow-600 mb-1">Expiring Soon</h5>
                        <ul id="expiring-soon-list" class="text-xs text-gray-700 space-y-1">
                            <li class="font-medium text-gray-500">Loading expiration alerts...</li>
                        </ul>
                    </div>

                    <div class="bg-white p-4 rounded-lg shadow-lg text-gray-900">
                        <h5 class="text-sm font-semibold text-gray-800 mb-1">Expired</h5>
                        <ul id="expired-list" class="text-xs text-gray-700 space-y-1">
                            <li class="font-medium text-gray-500">Loading expired alerts...</li>
                        </ul>
                    </div>
                </div>

                <!-- CROSS BRANCH CHAT -->
                <div class="mt-auto">
                    <div class="bg-white p-4 rounded-lg shadow-lg text-gray-900">
                        <h4 class="text-sm font-semibold mb-2 text-gray-800">Cross Branch Chatting</h4>
                        <button class="w-full bg-blue-500 hover:bg-blue-600 text-white font-medium py-2 rounded-lg transition duration-150 ease-in-out">
                            Open Chat
                        </button>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <script src="staff/js/script.js"></script>
</body>
</html>
