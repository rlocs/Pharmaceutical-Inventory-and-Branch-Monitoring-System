<?php
// Start the session on every page
session_start();

// ------------------------------------------------------------------
// ACCESS CONTROL CHECK
// ------------------------------------------------------------------

// 1. Check if the user is not logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: ../login.php");
    exit;
}

// 2. Check Role: Only Staff or Admin can view this page.
if ($_SESSION["user_role"] !== 'Staff' && $_SESSION["user_role"] !== 'Admin') {
    die("ERROR: You do not have permission to view this page.");
}

// 3. Check Branch (Crucial for Staff access). This file is for Branch 3.
// Admins are not restricted by BranchID, but Staff MUST be from Branch 3.
$required_branch_id = 3;
if ($required_branch_id > 0 && $_SESSION["user_role"] === 'Staff' && $_SESSION["branch_id"] != $required_branch_id) {
    // Redirect staff who ended up on the wrong branch page
    // Optional: Log this security violation attempt
    header("Location: ../login.php?error=branch_mismatch"); 
    exit;
}

// ------------------------------------------------------------------
// DYNAMIC DATA PREPARATION
// ------------------------------------------------------------------

// Set dynamic variables from session data
$user_full_name = htmlspecialchars($_SESSION['first_name'] ?? 'Staff') . ' ' . htmlspecialchars($_SESSION['last_name'] ?? 'User');
$user_role = htmlspecialchars($_SESSION['user_role'] ?? 'Staff');
$current_branch_id = $_SESSION['branch_id'];

// Mock Branch Name lookup (Replace with actual database query if needed)
$branch_names = [
    1 => 'Lipa, Batangas',
    2 => 'Sto Tomas, Batangas',
    3 => 'Malvar, Batangas'
];
$branch_name = $branch_names[$current_branch_id] ?? "Branch {$current_branch_id}";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Branch <?php echo $current_branch_id; ?> | MERCURY System</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css"> 
    <script>
        // Make the current user's ID available to the JavaScript files
        window.currentUserId = <?php echo json_encode($_SESSION['user_id'] ?? 0); ?>;
    </script>
</head>
<body class="font-sans">

    <div class="main-container">
        <header class="sticky top-0 z-40 bg-white flex justify-between items-center px-4 md:px-10 h-20 shadow-lg">
            <div class="flex items-center space-x-3">
                <img src="https://placehold.co/40x40/fff/1E3A8A?text=B<?php echo $current_branch_id; ?>" alt="Mercury Logo" class="rounded-full border-2 border-primary-accent">
                <span class="text-2xl font-bold text-gray-800 tracking-wider">
                    <span class="text-primary-accent">MERCURY</span>
                </span>
            </div>

            <nav id="navbar" class="hidden md:flex items-center space-x-8">
                <a href="staff1b<?php echo $current_branch_id; ?>.php" class="nav-link active" data-module="dashboard">Dashboard</a>
                <a href="med_inventory.php" class="nav-link" data-module="inventory">Med Inventory</a>
                <a href="pos.php" class="nav-link" data-module="pos">POS</a>
                <a href="reports.php" class="nav-link" data-module="reports">Reports</a>
                <a href="account.php" class="nav-link" data-module="account">Account</a>
            </nav>

            <button id="mobile-menu-button" class="md:hidden p-2 text-gray-700 rounded-lg hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-accent" aria-label="Toggle menu">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"/>
                </svg>
            </button>
        </header>

        <div id="mobile-menu" class="hidden md:hidden bg-white shadow-md">
            <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
                <a href="staff1b<?php echo $current_branch_id; ?>.php" class="mobile-link active">Dashboard</a>
                <a href="med_inventory.php" class="mobile-link">Med Inventory</a>
                <a href="pos.php" class="mobile-link">POS</a>
                <a href="reports.php" class="mobile-link">Reports</a>
                <a href="account.php" class="mobile-link">Account</a>
            </div>
        </div>

        <div class="content lg:flex">
            <div class="main-content flex-1 p-6 lg:p-10 overflow-y-auto bg-main-bg-color">
                <h2 id="page-title" class="text-4xl font-extrabold text-gray-900 mb-4">
                    Branch <?php echo $current_branch_id; ?> Dashboard 
                </h2>
                <p class="text-gray-600 text-lg mb-8">
                    Welcome back, **<?php echo $user_full_name; ?>**. Managing operations for **<?php echo $branch_name; ?>**.
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="bg-white p-6 rounded-3xl shadow-lg border border-gray-100">
                        <p class="text-sm font-semibold text-gray-500">Sales Value (Branch <?php echo $current_branch_id; ?>)</p>
                        <p class="text-3xl font-bold text-gray-800 mt-1">$12,450.00</p>
                        <span class="text-sm text-green-500 flex items-center mt-2">+5.2% Daily</span>
                    </div>

                    <div class="bg-white p-6 rounded-3xl shadow-lg border border-gray-100">
                        <p class="text-sm font-semibold text-gray-500">Inventory Items</p>
                        <p class="text-3xl font-bold text-gray-800 mt-1">452 SKUs</p>
                        <span class="text-sm text-primary-accent flex items-center mt-2">Check for restock needs</span>
                    </div>

                    <div class="bg-white p-6 rounded-3xl shadow-lg border border-gray-100">
                        <p class="text-3xl font-bold text-red-500 mt-1">7</p>
                        <span class="text-sm text-red-500 flex items-center mt-2">Urgent attention required</span>
                    </div>
                </div>
            </div>

            <aside class="sidebar lg:w-96 bg-secondary-dark text-white p-6 lg:p-8 flex flex-col shadow-2xl">
                <div class="w-full text-center mb-8 pt-4">
                    <div class="w-24 h-24 mx-auto rounded-full overflow-hidden border-4 border-primary-accent shadow-xl">
                        <img src="https://placehold.co/96x96/60A5FA/ffffff?text=<?php echo strtoupper(substr($user_full_name, 0, 1) . substr($user_full_name, strpos($user_full_name, ' ') + 1, 1)); ?>" alt="Profile" class="w-full h-full object-cover">
                    </div>
                    <h3 class="text-lg font-semibold mt-3 text-white"><?php echo $user_full_name; ?></h3>
                    <p class="text-sm text-white"><?php echo $user_role; ?></p>
                    <p class="text-xs text-gray-300 mt-1">Branch <?php echo $current_branch_id; ?> - <?php echo $branch_name; ?></p>
                </div>

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

                <div class="mt-auto">
                    <!-- CHAT WIDGET CONTAINER -->
                    <div id="chat-widget" class="bg-white rounded-lg shadow-lg text-gray-900">
                        <!-- Chat Header -->
                        <div id="chat-header" class="flex justify-between items-center p-3 cursor-pointer border-b border-gray-200">
                            <div class="flex items-center">
                                <h4 class="text-sm font-semibold text-gray-800">Cross-Branch Chat</h4>
                                <div id="chat-notification-bubble" class="hidden ml-2 w-5 h-5 bg-blue-500 text-white text-xs font-bold rounded-full flex items-center justify-center"></div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button title="New Message" class="text-gray-500 hover:text-blue-600"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd"></path></svg></button>
                                <button id="chat-toggle-button" class="text-gray-500 hover:text-gray-800">
                                    <!-- Chevron Up -->
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path></svg>
                                </button>
                            </div>
                        </div>
                        <!-- Chat Body (Conversation List) -->
                        <div id="chat-body" class="p-2 max-h-60 overflow-y-auto">
                            <p class="text-center text-gray-500 text-sm p-4">Loading chats...</p>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <script src="js/script.js"></script>
</body>
</html>