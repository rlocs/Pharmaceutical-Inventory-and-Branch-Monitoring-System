<?php
// Start the session on every page
session_start();
require_once '../dbconnection.php';

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

// 3. Check Branch (Crucial for Staff access). This file is for Branch 1.
// Admins are not restricted by BranchID, but Staff MUST be from Branch 1.
$required_branch_id = 1;
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
$user_id = $_SESSION['user_id'];

// Mock Branch Name lookup (Replace with actual database query if needed)
$branch_names = [
    1 => 'Lipa, Batangas',
    2 => 'Sto Tomas, Batangas',
    3 => 'Malvar, Batangas'
];
$branch_name = $branch_names[$current_branch_id] ?? "Branch {$current_branch_id}";

// ========== DASHBOARD KPI DATA ==========
$db = new Database();
$conn = $db->getConnection();



?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mercury Dashboard</title>
    
    <!-- Load Tailwind CSS CDN and Configuration -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="js/theme.js"></script>

    <!-- Load Lucide icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/chat_window.css?v=<?php echo time(); ?>">
    <script>
        // Make the current user's ID available to the JavaScript files
        window.currentUserId = <?php echo json_encode($_SESSION['user_id'] ?? 0); ?>;
        
    </script>
    <script src="js/medicine.js" defer></script>
    <script src="js/alerts.js" defer></script>

    
</head>
<body class="overflow-x-hidden">

    <!-- Backdrop for click outside close -->
    <div id="backdrop" class="backdrop" onclick="toggleSidebar()"></div>

    <!-- Outer container takes full screen space -->
    <div class="app-container bg-white">
        
       <!-- 1. TOP HEADER BAR (Dark Slate) -->
       <header id="main-header" class="bg-slate-800 text-white p-4 flex justify-between items-center sticky top-0 z-30 shadow-lg">
 
            <!-- Logo Section -->
            <div class="flex items-center space-x-3">
                <img src="https://placehold.co/40x40/fff/1E3A8A?text=B<?php echo $current_branch_id; ?>" alt="Mercury Logo" class="rounded-full border-2 border-white">
                <span class="text-2xl font-bold text-gray-800 tracking-wider">
                    <span class="text-white">MERCURY</span>
                </span>
            </div>

            <!-- Icons Section -->
            <div id="icons-section" class="flex items-center space-x-4">
                <?php include __DIR__ . '/includes/notification_bell.php'; ?>
                                                                <!-- Hamburger Menu Icon (OPEN BUTTON) -->
                <!-- Calls toggleSidebar() -->
                <button id="open-sidebar-btn" aria-label="Menu" onclick="toggleSidebar()" class="p-2 bg-slate-700 hover:bg-slate-600 rounded-lg transition duration-150">
                    <svg class="lucide" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/></svg>
                </button>
            </div>
        </header>

        <!-- 2. SECONDARY NAVIGATION BAR (Light Cream/White) -->
        <nav class="bg-[#F4F6FA] border-b border-gray-200 flex justify-between items-center px-6 py-3 shadow-sm sticky top-16 z-20">

            <!-- Navigation Links - INCREASED TEXT SIZE to text-base -->
            <div class="flex space-x-8 text-base font-medium">

                <a href="staff1b<?php echo $current_branch_id; ?>.php" class="py-2 px-3 rounded-md bg-blue-100 text-blue-800 font-medium transition-all duration-300">Dashboard</a>
                <a href="reports.php" class="py-2 px-3 rounded-md text-black hover:bg-gray-100 hover:text-black font-medium transition-all duration-300">Reports</a>
                <a href="account.php" class="py-2 px-3 rounded-md text-black hover:bg-gray-100 hover:text-black font-medium transition-all duration-300">Account</a>
            </div>

            <!-- Logout Button -->
            <a href="b-crud/logout.php" class="bg-red-500 hover:bg-red-600 text-white font-medium py-2 px-4 rounded-lg transition duration-150 shadow-md text-sm inline-block">
                Logout
            </a>
        </nav>

        <!-- 3. MAIN CONTENT AREA (Cream Background) -->
        <main class="bg-custom-bg-[#FFFFFF] p-6 flex-grow h-full relative z-10">
        <div class="max-w-8xl mx-auto p-4 md:p-8">
        
        <!-- Header Section -->
        <header class="flex flex-col md:flex-row justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-extrabold text-pharmacy-primary">Centralized Admin Dashboard</h1>
                <p class="text-sm text-text-light">System-wide Performance Overview</p>
            </div>
            
            <div class="flex items-center space-x-4 mt-4 md:mt-0">
                <!-- Export Button -->
                <button onclick="exportDashboardData()" class="flex items-center space-x-2 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    <span>Export to CSV</span>
                </button>
            </div>
        </header>

        <!-- Unified Control Bar -->
        <div class="bg-card-bg p-4 rounded-xl shadow-lg mb-8 border border-gray-100">
            <div class="flex flex-col xl:flex-row justify-between items-start xl:items-center space-y-4 xl:space-y-0">
                
                <!-- View Branch Group -->
                <div class="flex flex-col sm:flex-row items-start sm:items-center space-y-2 sm:space-y-0 sm:space-x-4">
                    <div class="flex items-center space-x-2 min-w-max">
                        <svg class="w-5 h-5 text-text-medium" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        <span class="font-semibold text-text-medium text-sm">View Branch:</span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button onclick="switchBranch('all')" data-target="all" class="nav-btn branch-btn px-4 py-2 rounded-lg border text-sm font-medium hover:bg-gray-50 focus:outline-none">All Branches</button>
                        <button onclick="switchBranch('branch1')" data-target="branch1" class="nav-btn branch-btn px-4 py-2 rounded-lg border text-sm font-medium hover:bg-gray-50 focus:outline-none">Branch 1</button>
                        <button onclick="switchBranch('branch2')" data-target="branch2" class="nav-btn branch-btn px-4 py-2 rounded-lg border text-sm font-medium hover:bg-gray-50 focus:outline-none">Branch 2</button>
                        <button onclick="switchBranch('branch3')" data-target="branch3" class="nav-btn branch-btn px-4 py-2 rounded-lg border text-sm font-medium hover:bg-gray-50 focus:outline-none">Branch 3</button>
                    </div>
                </div>

                <!-- Divider -->
                <div class="hidden xl:block w-px h-8 bg-gray-200 mx-6"></div>

                <!-- Compare Group -->
                <div class="flex flex-col sm:flex-row items-start sm:items-center space-y-2 sm:space-y-0 sm:space-x-4">
                    <div class="flex items-center space-x-2 min-w-max">
                        <svg class="w-5 h-5 text-pharmacy-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                        <span class="font-semibold text-text-medium text-sm">Compare:</span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button onclick="compareBranches('sales')" data-target="sales" class="nav-btn comp-btn px-3 py-2 rounded-lg border text-sm font-medium hover:bg-gray-50">Sales</button>
                        <button onclick="compareBranches('stock')" data-target="stock" class="nav-btn comp-btn px-3 py-2 rounded-lg border text-sm font-medium hover:bg-gray-50">Med Stocks</button>
                        <button onclick="compareBranches('alerts')" data-target="alerts" class="nav-btn comp-btn px-3 py-2 rounded-lg border text-sm font-medium hover:bg-gray-50">Alerts</button>
                    </div>
                </div>

            </div>
            
            <div class="mt-4 pt-4 border-t border-gray-100">
                <p id="view-title" class="text-sm text-pharmacy-primary font-bold">Viewing: All Branches (Consolidated)</p>
            </div>
        </div>

        <!-- === CONTAINER 1: SINGLE BRANCH VIEW (Default) === -->
        <div id="single-branch-view">
            <!-- Top KPI Row -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- System Status -->
                <div class="bg-card-bg p-6 rounded-xl shadow-lg flex flex-col justify-between border-b-4 border-gray-400">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-semibold text-text-dark">System Time</h2>
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-success-green text-white">System Online</span>
                    </div>
                    <div class="flex flex-col items-start">
                        <p id="current-time" class="text-5xl font-extrabold text-pharmacy-primary">--:--</p>
                        <p id="current-date" class="text-sm text-text-light mt-1">--</p>
                        <div class="mt-4 pt-4 border-t border-gray-100 w-full">
                            <p class="text-xs text-text-medium font-medium">Central Database Connected</p>
                        </div>
                    </div>
                </div>

                <!-- Sales Today -->
                <div class="bg-pharmacy-primary text-white p-6 rounded-xl shadow-lg flex flex-col justify-between">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-2 bg-white bg-opacity-20 rounded-lg">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                    </div>
                    <div class="flex flex-col">
                        <p class="text-sm mb-1">Sales Today</p>
                        <p id="val-sales" class="text-3xl font-extrabold transition-opacity duration-200">--</p>
                        <p id="sub-sales" class="text-xs text-white text-opacity-80">--</p>
                    </div>
                </div>

                <!-- Monthly Revenue -->
                <div class="bg-card-bg p-6 rounded-xl shadow-lg flex flex-col justify-between border-l-4 border-success-green">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-2 icon-success rounded-lg">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                        </div>
                        <span class="text-text-medium text-sm font-semibold">This Month</span>
                    </div>
                    <div class="flex flex-col">
                        <p class="text-sm mb-1 text-text-light">Revenue (Selected View)</p>
                        <p id="val-revenue" class="text-3xl font-bold text-text-dark transition-opacity duration-200">--</p>
                        <p class="text-xs text-success-green font-medium">Growth trend 📈</p>
                    </div>
                </div>

                <!-- Alerts -->
                <div class="bg-card-bg p-6 rounded-xl shadow-lg flex flex-col justify-between border-r-4 border-danger-red">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-2 icon-danger rounded-lg">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                        </div>
                    </div>
                    <div class="flex flex-col">
                        <p class="text-sm mb-1 text-text-light">Active Alerts</p>
                        <p id="val-alerts" class="text-3xl font-bold text-danger-red transition-opacity duration-200">--</p>
                        <p class="text-xs text-danger-red font-medium">Across selected branches</p>
                    </div>
                </div>
            </div>

            <!-- Operational Metrics Row -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <!-- This Week's Sales -->
                <div class="bg-card-bg p-6 rounded-xl shadow-lg flex flex-col justify-between border-t-4 border-pharmacy-secondary">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-2 bg-blue-100 text-blue-600 rounded-lg">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                        </div>
                    </div>
                    <div class="flex flex-col">
                        <p class="text-sm mb-1 text-text-light">This Week's Sales</p>
                        <p id="val-weekly-sales" class="text-3xl font-bold text-pharmacy-primary">--</p>
                        <p class="text-xs text-text-medium font-medium">N/A compared to last week</p>
                    </div>
                </div>

                <!-- Payment Methods -->
                <div class="bg-card-bg p-6 rounded-xl shadow-lg flex flex-col justify-between border-t-4 border-warning-yellow">
                    <div class="mb-2">
                        <p class="text-sm text-text-light font-semibold mb-3">Payment Methods (30 days)</p>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center text-sm">
                                <div class="flex items-center text-text-dark"><span class="w-2 h-2 bg-green-500 rounded-full mr-2"></span>Cash:</div>
                                <div class="text-right">
                                    <span id="pay-cash-amt" class="font-bold block">--</span>
                                    <span id="pay-cash-count" class="text-xs text-text-light">--</span>
                                </div>
                            </div>
                            <div class="flex justify-between items-center text-sm">
                                <div class="flex items-center text-text-dark"><span class="w-2 h-2 bg-blue-500 rounded-full mr-2"></span>Card:</div>
                                <div class="text-right">
                                    <span id="pay-card-amt" class="font-bold block">--</span>
                                    <span id="pay-card-count" class="text-xs text-text-light">--</span>
                                </div>
                            </div>
                            <div class="flex justify-between items-center text-sm">
                                <div class="flex items-center text-text-dark"><span class="w-2 h-2 bg-purple-500 rounded-full mr-2"></span>Credit:</div>
                                <div class="text-right">
                                    <span id="pay-credit-amt" class="font-bold block">--</span>
                                    <span id="pay-credit-count" class="text-xs text-text-light">--</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Inventory Value -->
                <div class="bg-card-bg p-6 rounded-xl shadow-lg flex flex-col justify-between border-t-4 border-pharmacy-primary">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-2 icon-info rounded-lg">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                        </div>
                    </div>
                    <div class="flex flex-col">
                        <p class="text-sm mb-1 text-text-light">Total Inventory Value</p>
                        <p id="val-inv-value" class="text-3xl font-bold text-pharmacy-primary">--</p>
                        <p class="text-xs text-text-medium font-medium">Estimated asset value</p>
                    </div>
                </div>
            </div>

            <!-- Inventory, Top Sellers, and Weekly Chart Section -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">

                <!-- Column 1: Inventory & Top Sellers (Takes 1/3) -->
                <div class="lg:col-span-1 space-y-6">
                    
                    <!-- Inventory Stock Status -->
                    <div class="bg-card-bg p-6 rounded-xl shadow-lg flex flex-col justify-between">
                        <h2 class="text-xl font-semibold text-text-dark mb-6">Inventory Stock Status</h2>
                        <div class="space-y-4">
                            <div class="flex justify-between items-center border-b border-gray-100 pb-2">
                                <div class="flex items-center"><div class="inventory-icon icon-success"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.047m17.236 0c.264.495.385 1.05.385 1.638v10.518c0 1.24-.766 2.302-1.92 2.721L12 21.052l-9.357-3.23c-1.154-.419-1.92-1.481-1.92-2.721V7.629c0-.588.121-1.143.385-1.638"></path></svg></div><span class="text-base font-medium text-text-medium">Active Medicines</span></div>
                                <span id="inv-active" class="text-2xl font-bold text-success-green">--</span>
                            </div>
                            <div class="flex justify-between items-center border-b border-gray-100 pb-2">
                                <div class="flex items-center"><div class="inventory-icon icon-warning"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg></div><span class="text-base font-medium text-text-medium">Low Stock</span></div>
                                <span id="inv-low" class="text-2xl font-bold text-warning-yellow">--</span>
                            </div>
                            <div class="flex justify-between items-center border-b border-gray-100 pb-2">
                                <div class="flex items-center"><div class="inventory-icon icon-danger"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg></div><span class="text-base font-medium text-text-medium">Out of Stock</span></div>
                                <span id="inv-out" class="text-2xl font-bold text-danger-red">--</span>
                            </div>
                            <div class="flex justify-between items-center border-b border-gray-100 pb-2">
                                <div class="flex items-center"><div class="inventory-icon icon-warning"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h.01M3 21h18a2 2 0 002-2V7a2 2 0 00-2-2H3a2 2 0 00-2 2v12a2 2 0 002 2zm7-9L7 16h6l-3-5z"></path></svg></div><span class="text-base font-medium text-text-medium">Expiring Soon</span></div>
                                <span id="inv-exp" class="text-2xl font-bold text-text-alert">--</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Top 10 Bestselling Medicines -->
                    <div class="bg-card-bg p-6 rounded-xl shadow-lg flex flex-col">
                        <h2 class="text-xl font-semibold text-text-dark mb-6">Top 10 Bestselling Medicines</h2>
                        <div class="relative h-96 w-full flex-grow overflow-y-auto pr-2" id="top-medicines-chart">
                            <!-- Dynamic Rows populated by JS -->
                        </div>
                    </div>

                </div>

                <!-- Column 2: Sales Charts (Takes 2/3) -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Weekly Sales Trend (Daily Breakdown) - FIXED HEIGHT AND ALIGNMENT -->
                    <div class="bg-card-bg p-6 rounded-xl shadow-lg"> <!-- Removed fixed height on card wrapper -->
                        <h2 class="text-xl font-semibold text-text-dark mb-6">Weekly Sales Trend (Daily)</h2>
                        <!-- Set chart container to fixed height (h-72) for consistent sizing -->
                        <div class="relative h-72 w-full">
                            
                            <!-- Y-Axis Labels - Aligned with grid lines using flex-col justify-between.
                                 Bottom margin matches grid bottom offset. -->
                            <div class="absolute left-0 top-0 bottom-6 w-14 flex flex-col justify-between text-xs text-text-light text-right pr-2">
                                <span>₱200k</span><span>₱150k</span><span>₱100k</span><span>₱50k</span><span>0</span>
                            </div>

                            <!-- Chart Area - bottom-6 leaves room for X-axis labels. border-l and border-b create the axis lines. -->
                            <div class="absolute left-14 right-0 top-0 bottom-6 border-l border-b border-gray-200">
                                <!-- Grid Lines -->
                                <div class="absolute w-full border-b border-gray-200" style="top: 0%"></div>
                                <div class="absolute w-full border-b border-gray-200" style="top: 25%"></div>
                                <div class="absolute w-full border-b border-gray-200" style="top: 50%"></div>
                                <div class="absolute w-full border-b border-gray-200" style="top: 75%"></div>
                                <!-- 100% line is covered by the main container border-b -->
                                
                                <!-- Bars Container - aligned to bottom of grid area -->
                                <div id="weekly-sales-chart" class="absolute inset-0 flex justify-around items-end w-full px-1"></div>
                            </div>
                        </div>
                    </div>

                    <!-- NEW: Sales by Product Category Chart (FIXED LAYOUT) -->
                    <div class="bg-card-bg p-6 rounded-xl shadow-lg"> <!-- Removed h-full -->
                        <h2 class="text-xl font-semibold text-text-dark mb-6">Sales by Product Category</h2>
                        <div id="category-sales-chart" class="space-y-4">
                            <!-- Populated by JS -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- === CONTAINER 2: COMPARISON VIEW (Hidden by default) === -->
        <div id="comparison-view" class="hidden">
            <div id="comparison-results" class="space-y-4">
                <!-- Cards injected by JS -->
            </div>
        </div>

    </div>
    </main>
    <!-- Tailwind Configuration -->
    <script>
        function configureTailwind() {
            if (typeof tailwind !== 'undefined') {
                tailwind.config = {
                    theme: {
                        extend: {
                            colors: {
                                'pharmacy-primary': '#1E293B', 
                                'pharmacy-secondary': '#334155', 
                                'pharmacy-bg': '#F3F4F6',
                                'card-bg': '#FFFFFF',
                                'success-green': '#10B981',
                                'danger-red': '#EF4444',
                                'warning-yellow': '#FBBF24',
                                'text-dark': '#1F2937',
                                'text-medium': '#4B5563',
                                'text-light': '#6B7280',
                                'text-alert': '#F59E0B',
                                'text-danger': '#EF4444',
                            }
                        }
                    }
                };
            } else {
                setTimeout(configureTailwind, 50);
            }
        }
        configureTailwind();
    </script>

    <!-- Main Logic Script -->
    <script src="js/dashboard.js"></script>


    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <script src="js/report_table.js"></script>
    <!-- Combined JavaScript Logic (from script.js and inline functions) -->

    <script src="js/chat.js?v=<?php echo time(); ?>"></script>
        <script src="js/notifications_bell.js" defer></script>
        <script src="js/script.js"></script>
</body>
</html>
