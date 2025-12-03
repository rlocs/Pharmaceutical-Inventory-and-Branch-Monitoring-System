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

// 2. Check Role: Only Admin can view this page.
if ($_SESSION["user_role"] !== 'Admin') {
    die("ERROR: You do not have permission to view this page.");
}

// ------------------------------------------------------------------
// DYNAMIC DATA PREPARATION
// ------------------------------------------------------------------

// Set dynamic variables from session data
$user_full_name = htmlspecialchars($_SESSION['first_name'] ?? 'Admin') . ' ' . htmlspecialchars($_SESSION['last_name'] ?? 'User');
$user_role = htmlspecialchars($_SESSION['user_role'] ?? 'Admin');
$current_branch_id = $_SESSION['branch_id'];
$user_id = $_SESSION['user_id'];

// Mock Branch Name lookup
$branch_names = [
    1 => 'Lipa, Batangas',
    2 => 'Sto Tomas, Batangas',
    3 => 'Malvar, Batangas'
];
$branch_name = $branch_names[$current_branch_id] ?? "Branch {$current_branch_id}";
// ========== DASHBOARD KPI DATA FOR ALL BRANCHES ==========
$db = new Database();
$conn = $db->getConnection();


// Function to execute stored procedure and handle multiple result sets
function executeStoredProcedure($conn, $procedureName, $params = []) {
    $placeholders = str_repeat('?,', count($params) - 1) . '?';
    $stmt = $conn->prepare("CALL $procedureName($placeholders)");
    $stmt->execute($params);
    return $stmt;
}

// Function to get single result from stored procedure
function getSingleResult($conn, $procedureName, $params = []) {
    $stmt = executeStoredProcedure($conn, $procedureName, $params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    return $result;
}

// Function to get data for all branches
function getAllBranchesData($conn) {
    $branches = [1, 2, 3];
    $allData = [];
    $limit = 10; // For SP_GetTopBestsellers
    
    foreach ($branches as $branch_id) {
        $branchData = [];
        
        // Today's Sales
        $today_sales = getSingleResult($conn, 'SP_GetDashboardKPIs', [$branch_id]);
        $branchData['today_sales_total'] = $today_sales['today_sales_total'] ?? 0;
        $branchData['today_transactions'] = $today_sales['today_transactions'] ?? 0;
        
        // This Week's Sales
        $week_sales = getSingleResult($conn, 'SP_GetWeeklySales', [$branch_id]);
        $branchData['week_sales_total'] = $week_sales['week_sales_total'] ?? 0;
        
        // Previous Week's Sales
        $prev_week_sales = getSingleResult($conn, 'SP_GetPreviousWeekSales', [$branch_id]);
        $branchData['prev_week_sales_total'] = $prev_week_sales['prev_week_sales_total'] ?? 0;
        
        // This Month's Sales
        $month_sales = getSingleResult($conn, 'SP_GetMonthlySales', [$branch_id]);
        $branchData['month_sales_total'] = $month_sales['month_sales_total'] ?? 0;
        
        // Previous Month's Sales
        $prev_month_sales = getSingleResult($conn, 'SP_GetPreviousMonthSales', [$branch_id]);
        $branchData['prev_month_sales_total'] = $prev_month_sales['prev_month_sales_total'] ?? 0;
        
        // Inventory Counts
        $inventory_stmt = executeStoredProcedure($conn, 'SP_GetInventoryCounts', [$branch_id]);
        $branchData['low_stock'] = $inventory_stmt->fetch(PDO::FETCH_ASSOC)['low_stock_count'] ?? 0;
        $inventory_stmt->nextRowset();
        $branchData['out_of_stock'] = $inventory_stmt->fetch(PDO::FETCH_ASSOC)['out_of_stock_count'] ?? 0;
        $inventory_stmt->nextRowset();
        $branchData['expiring_soon'] = $inventory_stmt->fetch(PDO::FETCH_ASSOC)['expiring_soon_count'] ?? 0;
        $inventory_stmt->nextRowset();
        $branchData['expired'] = $inventory_stmt->fetch(PDO::FETCH_ASSOC)['expired_count'] ?? 0;
        $inventory_stmt->nextRowset();
        $branchData['total_active'] = $inventory_stmt->fetch(PDO::FETCH_ASSOC)['total_active_count'] ?? 0;
        $inventory_stmt->closeCursor();
        
        // Top Selling Medicines (5 items)
        try {
            $top_sellers_stmt = executeStoredProcedure($conn, 'SP_GetTopSellers', [$branch_id, 5]);
            $branchData['top_sellers'] = $top_sellers_stmt->fetchAll(PDO::FETCH_ASSOC);
            $top_sellers_stmt->closeCursor();
        } catch (PDOException $e) {
            error_log("Top sellers error for branch $branch_id: " . $e->getMessage());
            $branchData['top_sellers'] = [];
        }
        
        // Payment Method Data
        $payment_method_stmt = executeStoredProcedure($conn, 'SP_GetPaymentMethods', [$branch_id]);
        $branchData['payment_methods'] = $payment_method_stmt->fetchAll(PDO::FETCH_ASSOC);
        $payment_method_stmt->closeCursor();
        
        // Weekly Sales Data
        $weekly_sales_stmt = executeStoredProcedure($conn, 'SP_GetWeeklySalesData', [$branch_id]);
        $branchData['weekly_sales_data'] = $weekly_sales_stmt->fetchAll(PDO::FETCH_ASSOC);
        $weekly_sales_stmt->closeCursor();
        
        // Top Bestsellers (10 items)
        try {
            $top_bestsellers_stmt = executeStoredProcedure($conn, 'SP_GetTopBestsellers', [$branch_id, $limit]);
            $branchData['top_bestsellers'] = $top_bestsellers_stmt->fetchAll(PDO::FETCH_ASSOC);
            $top_bestsellers_stmt->closeCursor();
        } catch (PDOException $e) {
            error_log("Top bestsellers error for branch $branch_id: " . $e->getMessage());
            $branchData['top_bestsellers'] = [];
        }
        
        $allData[$branch_id] = $branchData;
    }
    
    return $allData;
}

// Get data for all branches
$branchesData = getAllBranchesData($conn);

// Calculate consolidated totals
$consolidated = [
    'today_sales_total' => 0,
    'today_transactions' => 0,
    'week_sales_total' => 0,
    'prev_week_sales_total' => 0,
    'month_sales_total' => 0,
    'prev_month_sales_total' => 0,
    'low_stock' => 0,
    'out_of_stock' => 0,
    'expiring_soon' => 0,
    'expired' => 0,
    'total_active' => 0,
    'payment_data' => [
        'Cash' => ['amount' => 0, 'count' => 0],
        'Card' => ['amount' => 0, 'count' => 0],
        'Credit' => ['amount' => 0, 'count' => 0]
    ],
    'weeklyData' => [],
    'topSellers' => [],
    'top_bestsellers' => []
];

// Process payment methods and consolidate data
foreach ($branchesData as $branch_id => $data) {
    $consolidated['today_sales_total'] += $data['today_sales_total'];
    $consolidated['today_transactions'] += $data['today_transactions'];
    $consolidated['week_sales_total'] += $data['week_sales_total'];
    $consolidated['prev_week_sales_total'] += $data['prev_week_sales_total'];
    $consolidated['month_sales_total'] += $data['month_sales_total'];
    $consolidated['prev_month_sales_total'] += $data['prev_month_sales_total'];
    $consolidated['low_stock'] += $data['low_stock'];
    $consolidated['out_of_stock'] += $data['out_of_stock'];
    $consolidated['expiring_soon'] += $data['expiring_soon'];
    $consolidated['expired'] += $data['expired'];
    $consolidated['total_active'] += $data['total_active'];
    
    // Process payment methods
    foreach ($data['payment_methods'] as $method) {
        $payment_method = $method['PaymentMethod'];
        if (isset($consolidated['payment_data'][$payment_method])) {
            $consolidated['payment_data'][$payment_method]['amount'] += $method['total'] ?? 0;
            $consolidated['payment_data'][$payment_method]['count'] += $method['count'] ?? 0;
        }
    }
    
    // Merge top sellers
    $consolidated['top_bestsellers'] = array_merge($consolidated['top_bestsellers'], $data['top_bestsellers']);
}

// Build consolidated weekly data
$now = new DateTime();
$weeklyDataConsolidated = [];
for ($i = 6; $i >= 0; $i--) {
    $dt = (clone $now)->modify("-{$i} days");
    $date = $dt->format('Y-m-d');
    $day = $dt->format('D');
    
    $daily_sales = 0;
    foreach ($branchesData as $branchData) {
        foreach ($branchData['weekly_sales_data'] as $sale) {
            if ($sale['sale_date'] == $date) {
                $daily_sales += (float)$sale['daily_total'];
                break;
            }
        }
    }
    
    $weeklyDataConsolidated[] = [
        'date' => $date,
        'day' => $day,
        'sales' => $daily_sales
    ];
}
$consolidated['weeklyData'] = $weeklyDataConsolidated;

// Aggregate top sellers across all branches
$topSellersAggregated = [];
foreach ($consolidated['top_bestsellers'] as $item) {
    $medicineName = $item['MedicineName'];
    if (!isset($topSellersAggregated[$medicineName])) {
        $topSellersAggregated[$medicineName] = [
            'medicine' => $medicineName,
            'quantity' => 0,
            'sales' => 0
        ];
    }
    $topSellersAggregated[$medicineName]['quantity'] += (int)$item['total_qty'];
    $topSellersAggregated[$medicineName]['sales'] += (float)$item['total_sales'];
}

// Sort by quantity and take top 10
usort($topSellersAggregated, function($a, $b) {
    return $b['quantity'] - $a['quantity'];
});
$consolidated['topSellers'] = array_slice($topSellersAggregated, 0, 10);

// Calculate comparisons
$week_comparison = '';
$week_comparison_class = '';
if ($consolidated['prev_week_sales_total'] > 0) {
    $week_change = (($consolidated['week_sales_total'] - $consolidated['prev_week_sales_total']) / $consolidated['prev_week_sales_total']) * 100;
    $week_comparison = ($week_change >= 0 ? '+' : '') . number_format($week_change, 1) . '%';
    $week_comparison_class = $week_change >= 0 ? 'text-success-green' : 'text-danger-red';
} else {
    $week_comparison = 'N/A';
    $week_comparison_class = 'text-text-light';
}

$month_comparison = '';
$month_comparison_class = '';
if ($consolidated['prev_month_sales_total'] > 0) {
    $month_change = (($consolidated['month_sales_total'] - $consolidated['prev_month_sales_total']) / $consolidated['prev_month_sales_total']) * 100;
    $month_comparison = ($month_change >= 0 ? '+' : '') . number_format($month_change, 1) . '%';
    $month_comparison_class = $month_change >= 0 ? 'text-success-green' : 'text-danger-red';
} else {
    $month_comparison = 'N/A';
    $month_comparison_class = 'text-text-light';
}

$alerts_count = $consolidated['low_stock'] + $consolidated['out_of_stock'] + $consolidated['expiring_soon'] + $consolidated['expired'];

// Prepare data for JavaScript
$weeklyData = $consolidated['weeklyData'];
$topSellersData = $consolidated['topSellers'];
$paymentData = $consolidated['payment_data'];

// Calculate max values for charts
$maxWeekly = 1;
$weeklySales = array_column($weeklyData, 'sales');
if (!empty($weeklySales)) {
    $maxWeekly = max($weeklySales);
    $maxWeekly = ceil(($maxWeekly * 1.2) / 50000) * 50000;
    if ($maxWeekly < 50000) $maxWeekly = 50000;
}

$maxSellersQty = 1;
$qtys = array_map(function($t){ return $t['quantity']; }, $topSellersData);
if (!empty($qtys)) {
    $maxSellersQty = max(max($qtys), 1);
    $maxSellersQty = ceil($maxSellersQty * 1.1);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mercury Admin Dashboard</title>
    
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

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
        :root {
            font-family: 'Inter', sans-serif;
        }
        
        /* Utility styles for inventory breakdown card icons */
        .inventory-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 0.5rem;
            margin-right: 0.75rem;
            flex-shrink: 0;
        }
        .icon-success { background-color: #D1FAE5; color: #059669; }
        .icon-warning { background-color: #FEF3C7; color: #D97706; }
        .icon-danger { background-color: #FEE2E2; color: #DC2626; }
        .icon-info { background-color: #DBEAFE; color: #2563EB; }

        /* Bar Chart Tooltip Styles */
        .bar-tooltip {
            position: absolute;
            background-color: rgba(0,0,0,0.8);
            color: white;
            padding: 0.5rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.75rem;
            white-space: nowrap;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.2s;
            z-index: 20;
            transform: translate(-50%, -110%);
        }
        .bar-chart-bar:hover .bar-tooltip {
            opacity: 1;
        }
    </style>
    
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
                <img src="https://placehold.co/40x40/fff/1E3A8A?text=Admin" alt="Mercury Logo" class="rounded-full border-2 border-white">
                <span class="text-2xl font-bold text-gray-800 tracking-wider">
                    <span class="text-white">MERCURY ADMIN</span>
                </span>
            </div>

            <!-- Icons Section -->
            <div id="icons-section" class="flex items-center space-x-4">
                <?php include __DIR__ . '/includes/notification_bell.php'; ?>
                <!-- Hamburger Menu Icon (OPEN BUTTON) -->
                <button id="open-sidebar-btn" aria-label="Menu" onclick="toggleSidebar()" class="p-2 bg-slate-700 hover:bg-slate-600 rounded-lg transition duration-150">
                    <svg class="lucide" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/></svg>
                </button>
            </div>
        </header>

        <!-- 2. SECONDARY NAVIGATION BAR (Light Cream/White) -->
        <nav class="bg-[#F4F6FA] border-b border-gray-200 flex justify-between items-center px-6 py-3 shadow-sm sticky top-16 z-20">

            <!-- Navigation Links - INCREASED TEXT SIZE to text-base -->
            <div class="flex space-x-8 text-base font-medium">
                <a href="admin1b<?php echo $current_branch_id; ?>.php" class="py-2 px-3 rounded-md bg-blue-100 text-blue-800 font-medium transition-all duration-300">Dashboard</a>
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
                <p class="text-sm text-text-light">System-wide Performance Overview - All Branches</p>
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
                        <button onclick="switchBranch('all')" data-target="all" class="nav-btn branch-btn px-4 py-2 rounded-lg border text-sm font-medium hover:bg-gray-50 focus:outline-none bg-blue-100 text-blue-800">All Branches</button>
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
                        <p id="val-sales" class="text-3xl font-extrabold transition-opacity duration-200">₱<?php echo number_format($consolidated['today_sales_total'], 2); ?></p>
                        <p id="sub-sales" class="text-xs text-white text-opacity-80"><?php echo $consolidated['today_transactions']; ?> transactions today</p>
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
                        <p id="val-revenue" class="text-3xl font-bold text-text-dark transition-opacity duration-200">₱<?php echo number_format($consolidated['month_sales_total'], 2); ?></p>
                        <p class="text-xs <?php echo $month_comparison_class; ?> font-medium"><?php echo $month_comparison; ?> compared to last month</p>
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
                        <p id="val-alerts" class="text-3xl font-bold text-danger-red transition-opacity duration-200"><?php echo $alerts_count; ?></p>
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
                        <p id="val-weekly-sales" class="text-3xl font-bold text-pharmacy-primary">₱<?php echo number_format($consolidated['week_sales_total'], 2); ?></p>
                        <p class="text-xs <?php echo $week_comparison_class; ?> font-medium"><?php echo $week_comparison; ?> compared to last week</p>
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
                                    <span id="pay-cash-amt" class="font-bold block">₱<?php echo number_format($paymentData['Cash']['amount'], 2); ?></span>
                                    <span id="pay-cash-count" class="text-xs text-text-light"><?php echo $paymentData['Cash']['count']; ?> transactions</span>
                                </div>
                            </div>
                            <div class="flex justify-between items-center text-sm">
                                <div class="flex items-center text-text-dark"><span class="w-2 h-2 bg-blue-500 rounded-full mr-2"></span>Card:</div>
                                <div class="text-right">
                                    <span id="pay-card-amt" class="font-bold block">₱<?php echo number_format($paymentData['Card']['amount'], 2); ?></span>
                                    <span id="pay-card-count" class="text-xs text-text-light"><?php echo $paymentData['Card']['count']; ?> transactions</span>
                                </div>
                            </div>
                            <div class="flex justify-between items-center text-sm">
                                <div class="flex items-center text-text-dark"><span class="w-2 h-2 bg-purple-500 rounded-full mr-2"></span>Credit:</div>
                                <div class="text-right">
                                    <span id="pay-credit-amt" class="font-bold block">₱<?php echo number_format($paymentData['Credit']['amount'], 2); ?></span>
                                    <span id="pay-credit-count" class="text-xs text-text-light"><?php echo $paymentData['Credit']['count']; ?> transactions</span>
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
                        <p class="text-sm mb-1 text-text-light">Total Active Medicines</p>
                        <p id="val-inv-value" class="text-3xl font-bold text-pharmacy-primary"><?php echo $consolidated['total_active']; ?></p>
                        <p class="text-xs text-text-medium font-medium">Across all branches</p>
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
                                <span id="inv-active" class="text-2xl font-bold text-success-green"><?php echo $consolidated['total_active']; ?></span>
                            </div>
                            <div class="flex justify-between items-center border-b border-gray-100 pb-2">
                                <div class="flex items-center"><div class="inventory-icon icon-warning"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg></div><span class="text-base font-medium text-text-medium">Low Stock</span></div>
                                <span id="inv-low" class="text-2xl font-bold text-warning-yellow"><?php echo $consolidated['low_stock']; ?></span>
                            </div>
                            <div class="flex justify-between items-center border-b border-gray-100 pb-2">
                                <div class="flex items-center"><div class="inventory-icon icon-danger"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg></div><span class="text-base font-medium text-text-medium">Out of Stock</span></div>
                                <span id="inv-out" class="text-2xl font-bold text-danger-red"><?php echo $consolidated['out_of_stock']; ?></span>
                            </div>
                            <div class="flex justify-between items-center border-b border-gray-100 pb-2">
                                <div class="flex items-center"><div class="inventory-icon icon-warning"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h.01M3 21h18a2 2 0 002-2V7a2 2 0 00-2-2H3a2 2 0 00-2 2v12a2 2 0 002 2zm7-9L7 16h6l-3-5z"></path></svg></div><span class="text-base font-medium text-text-medium">Expiring Soon</span></div>
                                <span id="inv-exp" class="text-2xl font-bold text-text-alert"><?php echo $consolidated['expiring_soon']; ?></span>
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
                    <div class="bg-card-bg p-6 rounded-xl shadow-lg">
                        <h2 class="text-xl font-semibold text-text-dark mb-6">Weekly Sales Trend (Daily)</h2>
                        <!-- Set chart container to fixed height (h-72) for consistent sizing -->
                        <div class="relative h-72 w-full">
                            
                            <!-- Y-Axis Labels - Aligned with grid lines using flex-col justify-between.
                                 Bottom margin matches grid bottom offset. -->
                            <div class="absolute left-0 top-0 bottom-6 w-14 flex flex-col justify-between text-xs text-text-light text-right pr-2">
                                <span>₱<?php echo number_format($maxWeekly, 0); ?></span>
                                <span>₱<?php echo number_format($maxWeekly * 0.75, 0); ?></span>
                                <span>₱<?php echo number_format($maxWeekly * 0.5, 0); ?></span>
                                <span>₱<?php echo number_format($maxWeekly * 0.25, 0); ?></span>
                                <span>0</span>
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

                    <!-- Branch Performance Comparison -->
                    <div class="bg-card-bg p-6 rounded-xl shadow-lg">
                        <h2 class="text-xl font-semibold text-text-dark mb-6">Branch Performance Comparison</h2>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h3 class="font-semibold text-text-dark mb-2">Branch 1 - Lipa</h3>
                                <p class="text-2xl font-bold text-pharmacy-primary">₱<?php echo number_format($branchesData[1]['month_sales_total'], 2); ?></p>
                                <p class="text-sm text-text-light">Monthly Sales</p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h3 class="font-semibold text-text-dark mb-2">Branch 2 - Sto Tomas</h3>
                                <p class="text-2xl font-bold text-pharmacy-primary">₱<?php echo number_format($branchesData[2]['month_sales_total'], 2); ?></p>
                                <p class="text-sm text-text-light">Monthly Sales</p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h3 class="font-semibold text-text-dark mb-2">Branch 3 - Malvar</h3>
                                <p class="text-2xl font-bold text-pharmacy-primary">₱<?php echo number_format($branchesData[3]['month_sales_total'], 2); ?></p>
                                <p class="text-sm text-text-light">Monthly Sales</p>
                            </div>
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

    <!-- JavaScript Configuration and Functions -->
    <script>
        // Tailwind Configuration
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
        }

        // --- Data from server ---
        const weeklyData = <?php echo json_encode(array_values($weeklyData), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;
        const topSellersData = <?php echo json_encode($topSellersData, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;
        const paymentData = <?php echo json_encode($paymentData, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;
        const branchesData = <?php echo json_encode($branchesData, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;

        const MAX_WEEKLY_VALUE = <?php echo (int)$maxWeekly; ?>;
        const MAX_SELLERS_QTY = <?php echo (int)$maxSellersQty; ?>;

// --- Functions ---
function renderWeeklySalesChart() {
    const chartContainer = document.getElementById('weekly-sales-chart');
    if (!chartContainer) return;
    chartContainer.innerHTML = '';
    
    function getBarHeight(value, max) {
        return (value / max) * 100;
    }

    weeklyData.forEach(data => {
        const barHeight = getBarHeight(data.sales, MAX_WEEKLY_VALUE);
        
        const group = document.createElement('div');
        group.className = 'bar-group flex flex-col items-center justify-end h-full px-1 relative';
        
        group.innerHTML = `
            <div class="relative w-8 bg-pharmacy-primary rounded-t-sm bar-chart-bar group" style="height: ${barHeight}%">
                <div class="bar-tooltip">₱${data.sales.toLocaleString()}</div>
            </div>
            <span class="absolute -bottom-6 text-xs text-text-light whitespace-nowrap">${data.day}</span>
        `;
        chartContainer.appendChild(group);
    });
}

function renderTopMedicinesChart() {
    const chartContainer = document.getElementById('top-medicines-chart');
    if (!chartContainer) return;

    chartContainer.innerHTML = '';
    if (!Array.isArray(topSellersData) || topSellersData.length === 0) {
        chartContainer.innerHTML = '<div class="text-sm text-text-light">No sales data available.</div>';
        return;
    }

    const list = document.createElement('div');
    list.className = 'space-y-2';

    topSellersData.forEach((item, idx) => {
        const pct = Math.min(100, (item.quantity / MAX_SELLERS_QTY) * 100);
        const row = document.createElement('div');
        row.className = 'flex items-center space-x-4';

        const name = document.createElement('div');
        name.className = 'w-1/3 text-sm text-text-dark truncate';
        name.textContent = `${idx + 1}. ${item.medicine}`;

        const barWrap = document.createElement('div');
        barWrap.className = 'flex-1 bg-gray-100 rounded-full h-4 relative';

        const bar = document.createElement('div');
        bar.className = 'h-4 rounded-full bg-pharmacy-primary';
        bar.style.width = pct + '%';

        const qtyLabel = document.createElement('div');
        qtyLabel.className = 'w-20 text-right text-sm font-semibold text-pharmacy-primary';
        qtyLabel.textContent = `${item.quantity} pcs`;

        barWrap.appendChild(bar);
        row.appendChild(name);
        row.appendChild(barWrap);
        row.appendChild(qtyLabel);

        list.appendChild(row);
    });

    chartContainer.appendChild(list);
}

function updateClockDynamic() {
    const now = new Date();
    const clockElement = document.getElementById('current-time');
    const dateElement = document.getElementById('current-date');
    
    const hours = now.getHours();
    const minutes = now.getMinutes().toString().padStart(2, '0');
    const ampm = hours >= 12 ? 'PM' : 'AM';
    const displayHours = hours % 12 || 12;
    const timeString = `${displayHours}:${minutes} ${ampm}`;
    
    const weekdays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    const weekday = weekdays[now.getDay()];
    const month = months[now.getMonth()];
    const day = now.getDate();
    const year = now.getFullYear();
    const dateString = `${weekday}, ${month} ${day}, ${year}`;
    
    clockElement.textContent = timeString;
    dateElement.textContent = dateString;
}

function switchToSingleBranchView() {
    // Show single branch view and hide comparison view
    document.getElementById('single-branch-view').classList.remove('hidden');
    document.getElementById('comparison-view').classList.add('hidden');
    
    // Reset comparison buttons
    document.querySelectorAll('.comp-btn').forEach(btn => {
        btn.classList.remove('bg-blue-100', 'text-blue-800');
        btn.classList.add('bg-white', 'text-text-medium', 'border-gray-200');
    });
}

function switchBranch(branch) {
    // Always switch to single branch view when clicking branch buttons
    switchToSingleBranchView();
    
    // Update active branch button
    document.querySelectorAll('.branch-btn').forEach(btn => {
        btn.classList.remove('bg-blue-100', 'text-blue-800');
    });
    document.querySelector(`[data-target="${branch}"]`).classList.add('bg-blue-100', 'text-blue-800');
    
    // Update view title
    const viewTitle = document.getElementById('view-title');
    if (branch === 'all') {
        viewTitle.textContent = 'Viewing: All Branches (Consolidated)';
        viewTitle.classList.remove('text-pharmacy-secondary');
        viewTitle.classList.add('text-pharmacy-primary');
        
        // Show consolidated data
        document.getElementById('val-sales').textContent = '₱<?php echo number_format($consolidated['today_sales_total'], 2); ?>';
        document.getElementById('sub-sales').textContent = '<?php echo $consolidated['today_transactions']; ?> transactions today';
        document.getElementById('val-revenue').textContent = '₱<?php echo number_format($consolidated['month_sales_total'], 2); ?>';
        document.getElementById('val-alerts').textContent = '<?php echo $alerts_count; ?>';
        document.getElementById('val-weekly-sales').textContent = '₱<?php echo number_format($consolidated['week_sales_total'], 2); ?>';
        
        // Update payment methods
        document.getElementById('pay-cash-amt').textContent = '₱<?php echo number_format($paymentData['Cash']['amount'], 2); ?>';
        document.getElementById('pay-cash-count').textContent = '<?php echo $paymentData['Cash']['count']; ?> transactions';
        document.getElementById('pay-card-amt').textContent = '₱<?php echo number_format($paymentData['Card']['amount'], 2); ?>';
        document.getElementById('pay-card-count').textContent = '<?php echo $paymentData['Card']['count']; ?> transactions';
        document.getElementById('pay-credit-amt').textContent = '₱<?php echo number_format($paymentData['Credit']['amount'], 2); ?>';
        document.getElementById('pay-credit-count').textContent = '<?php echo $paymentData['Credit']['count']; ?> transactions';
        
        // Update inventory
        document.getElementById('inv-active').textContent = '<?php echo $consolidated['total_active']; ?>';
        document.getElementById('inv-low').textContent = '<?php echo $consolidated['low_stock']; ?>';
        document.getElementById('inv-out').textContent = '<?php echo $consolidated['out_of_stock']; ?>';
        document.getElementById('inv-exp').textContent = '<?php echo $consolidated['expiring_soon']; ?>';
        
        // Render consolidated charts
        renderWeeklySalesChart();
        renderTopMedicinesChart();
        
    } else {
        const branchNum = branch.replace('branch', '');
        viewTitle.textContent = `Viewing: Branch ${branchNum} - ${<?php echo json_encode($branch_names); ?>[branchNum]}`;
        viewTitle.classList.remove('text-pharmacy-secondary');
        viewTitle.classList.add('text-pharmacy-primary');
        
        // Show individual branch data
        const branchData = branchesData[branchNum];
        document.getElementById('val-sales').textContent = '₱' + branchData.today_sales_total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('sub-sales').textContent = branchData.today_transactions + ' transactions today';
        document.getElementById('val-revenue').textContent = '₱' + branchData.month_sales_total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('val-alerts').textContent = (branchData.low_stock + branchData.out_of_stock + branchData.expiring_soon + branchData.expired);
        document.getElementById('val-weekly-sales').textContent = '₱' + branchData.week_sales_total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        
        // Update payment methods for individual branch
        let cashAmt = 0, cashCount = 0, cardAmt = 0, cardCount = 0, creditAmt = 0, creditCount = 0;
        branchData.payment_methods.forEach(method => {
            if (method.PaymentMethod === 'Cash') {
                cashAmt = method.total || 0;
                cashCount = method.count || 0;
            } else if (method.PaymentMethod === 'Card') {
                cardAmt = method.total || 0;
                cardCount = method.count || 0;
            } else if (method.PaymentMethod === 'Credit') {
                creditAmt = method.total || 0;
                creditCount = method.count || 0;
            }
        });
        
        document.getElementById('pay-cash-amt').textContent = '₱' + cashAmt.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('pay-cash-count').textContent = cashCount + ' transactions';
        document.getElementById('pay-card-amt').textContent = '₱' + cardAmt.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('pay-card-count').textContent = cardCount + ' transactions';
        document.getElementById('pay-credit-amt').textContent = '₱' + creditAmt.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('pay-credit-count').textContent = creditCount + ' transactions';
        
        // Update inventory for individual branch
        document.getElementById('inv-active').textContent = branchData.total_active;
        document.getElementById('inv-low').textContent = branchData.low_stock;
        document.getElementById('inv-out').textContent = branchData.out_of_stock;
        document.getElementById('inv-exp').textContent = branchData.expiring_soon;
        
        // Render individual branch charts
        renderIndividualBranchWeeklyChart(branchData.weekly_sales_data);
        renderIndividualBranchTopMedicines(branchData.top_bestsellers);
    }
}

function renderIndividualBranchWeeklyChart(weeklySalesData) {
    const chartContainer = document.getElementById('weekly-sales-chart');
    if (!chartContainer) return;
    chartContainer.innerHTML = '';
    
    // Build complete weekly data array with all days
    const now = new Date();
    const weeklyDataIndividual = [];
    for (let i = 6; i >= 0; i--) {
        const dt = new Date(now);
        dt.setDate(now.getDate() - i);
        const date = dt.toISOString().split('T')[0];
        const day = dt.toLocaleDateString('en-US', { weekday: 'short' });
        
        let daily_sales = 0;
        weeklySalesData.forEach(sale => {
            if (sale.sale_date === date) {
                daily_sales = parseFloat(sale.daily_total) || 0;
            }
        });
        
        weeklyDataIndividual.push({
            date: date,
            day: day,
            sales: daily_sales
        });
    }
    
    // Calculate max value for scaling
    const maxWeeklyIndividual = Math.max(...weeklyDataIndividual.map(d => d.sales), 1);
    const maxScale = Math.ceil((maxWeeklyIndividual * 1.2) / 50000) * 50000;
    
    function getBarHeight(value, max) {
        return (value / max) * 100;
    }

    weeklyDataIndividual.forEach(data => {
        const barHeight = getBarHeight(data.sales, maxScale);
        
        const group = document.createElement('div');
        group.className = 'bar-group flex flex-col items-center justify-end h-full px-1 relative';
        
        group.innerHTML = `
            <div class="relative w-8 bg-pharmacy-primary rounded-t-sm bar-chart-bar group" style="height: ${barHeight}%">
                <div class="bar-tooltip">₱${data.sales.toLocaleString()}</div>
            </div>
            <span class="absolute -bottom-6 text-xs text-text-light whitespace-nowrap">${data.day}</span>
        `;
        chartContainer.appendChild(group);
    });
}

function renderIndividualBranchTopMedicines(topBestsellers) {
    const chartContainer = document.getElementById('top-medicines-chart');
    if (!chartContainer) return;

    chartContainer.innerHTML = '';
    if (!Array.isArray(topBestsellers) || topBestsellers.length === 0) {
        chartContainer.innerHTML = '<div class="text-sm text-text-light">No sales data available.</div>';
        return;
    }

    const list = document.createElement('div');
    list.className = 'space-y-2';

    // Calculate max quantity for scaling
    const maxQty = Math.max(...topBestsellers.map(m => parseInt(m.total_qty)), 1);

    topBestsellers.forEach((item, idx) => {
        const pct = Math.min(100, (parseInt(item.total_qty) / maxQty) * 100);
        const row = document.createElement('div');
        row.className = 'flex items-center space-x-4';

        const name = document.createElement('div');
        name.className = 'w-1/3 text-sm text-text-dark truncate';
        name.textContent = `${idx + 1}. ${item.MedicineName}`;

        const barWrap = document.createElement('div');
        barWrap.className = 'flex-1 bg-gray-100 rounded-full h-4 relative';

        const bar = document.createElement('div');
        bar.className = 'h-4 rounded-full bg-pharmacy-primary';
        bar.style.width = pct + '%';

        const qtyLabel = document.createElement('div');
        qtyLabel.className = 'w-20 text-right text-sm font-semibold text-pharmacy-primary';
        qtyLabel.textContent = `${item.total_qty} pcs`;

        barWrap.appendChild(bar);
        row.appendChild(name);
        row.appendChild(barWrap);
        row.appendChild(qtyLabel);

        list.appendChild(row);
    });

    chartContainer.appendChild(list);
}

function compareBranches(type) {
    // Update active comparison button
    document.querySelectorAll('.comp-btn').forEach(btn => {
        btn.classList.remove('bg-blue-100', 'text-blue-800');
    });
    document.querySelector(`[data-target="${type}"]`).classList.add('bg-blue-100', 'text-blue-800');
    
    // Show comparison view and hide single branch view
    document.getElementById('single-branch-view').classList.add('hidden');
    document.getElementById('comparison-view').classList.remove('hidden');
    
    // Update comparison results based on type
    const comparisonResults = document.getElementById('comparison-results');
    comparisonResults.innerHTML = '';
    
    document.getElementById('view-title').textContent = `Mode: Comparing ${type.charAt(0).toUpperCase() + type.slice(1)}`;
    document.getElementById('view-title').classList.remove('text-pharmacy-primary');
    document.getElementById('view-title').classList.add('text-pharmacy-secondary');

    const metricsConfig = {
        'sales': {
            cards: [
                { label: 'Today', key: 'today_sales_total', format: 'currency', color: 'bg-pharmacy-primary' },
                { label: 'This Week', key: 'week_sales_total', format: 'currency', color: 'bg-pharmacy-secondary' },
                { label: 'This Month', key: 'month_sales_total', format: 'currency', color: 'bg-success-green' }
            ]
        },
        'stock': {
            cards: [
                { label: 'Low Stock', key: 'low_stock', format: 'number', color: 'bg-warning-yellow' },
                { label: 'Out of Stock', key: 'out_of_stock', format: 'number', color: 'bg-danger-red' }
            ]
        },
        'alerts': {
            cards: [
                { label: 'Will Expire Soon', key: 'expiring_soon', format: 'number', color: 'bg-text-alert' },
                { label: 'Expired', key: 'expired', format: 'number', color: 'bg-text-danger' }
            ]
        }
    };

    const config = metricsConfig[type];
    const branches = [1, 2, 3];

    branches.forEach(branchId => {
        const branch = branchesData[branchId];
        const branchRow = document.createElement('div');
        branchRow.className = 'mb-6 pb-6 border-b border-gray-100 last:border-0 last:mb-0 last:pb-0';
        
        const titleHtml = `<h4 class="text-md font-bold text-text-dark mb-3 flex items-center">
            <span class="w-2 h-6 bg-pharmacy-primary rounded-r mr-2"></span>
            Branch ${branchId} - ${<?php echo json_encode($branch_names); ?>[branchId]}
        </h4>`;
        
        const gridClass = type === 'sales' ? 'grid-cols-1 md:grid-cols-3' : 'grid-cols-1 md:grid-cols-2';
        let cardsHtml = `<div class="grid ${gridClass} gap-4">`;

        config.cards.forEach(cardConfig => {
            const value = branch[cardConfig.key];
            let displayValue = cardConfig.format === 'currency' 
                ? '₱' + value.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})
                : value;
            
            // Calculate percentage for progress bar (using reasonable max values)
            let percentage = 0;
            let maxValue = 0;
            
            if (type === 'sales') {
                maxValue = 1000000; // 1 million as max for sales
                percentage = Math.min((value / maxValue) * 100, 100);
            } else if (type === 'stock') {
                maxValue = 100; // 100 as max for stock items
                percentage = Math.min((value / maxValue) * 100, 100);
            } else if (type === 'alerts') {
                maxValue = 50; // 50 as max for alerts
                percentage = Math.min((value / maxValue) * 100, 100);
            }

            cardsHtml += `
                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                    <p class="text-xs text-text-light mb-1 uppercase font-semibold">${cardConfig.label}</p>
                    <p class="text-xl font-bold text-text-dark mb-2">${displayValue}</p>
                    <div class="w-full bg-gray-200 rounded-full h-1.5">
                        <div class="${cardConfig.color} h-1.5 rounded-full" style="width: ${percentage > 0 ? percentage : 5}%"></div>
                    </div>
                </div>
            `;
        });

        cardsHtml += `</div>`;
        branchRow.innerHTML = titleHtml + cardsHtml;
        comparisonResults.appendChild(branchRow);
    });
}

function exportDashboardData() {
    // Get current view data
    let dataToExport;
    const currentView = document.getElementById('view-title').textContent;
    
    if (currentView.includes('All Branches')) {
        dataToExport = {
            name: 'All Branches (Consolidated)',
            salesToday: <?php echo $consolidated['today_sales_total']; ?>,
            transactions: <?php echo $consolidated['today_transactions']; ?>,
            revenueMonth: <?php echo $consolidated['month_sales_total']; ?>,
            alerts: <?php echo $alerts_count; ?>,
            weeklySales: <?php echo $consolidated['week_sales_total']; ?>,
            inventory: {
                active: <?php echo $consolidated['total_active']; ?>,
                low: <?php echo $consolidated['low_stock']; ?>,
                out: <?php echo $consolidated['out_of_stock']; ?>,
                expiring: <?php echo $consolidated['expiring_soon']; ?>,
                expired: <?php echo $consolidated['expired']; ?>
            }
        };
    } else if (currentView.includes('Comparing')) {
        // Export comparison data
        const comparisonType = currentView.replace('Mode: Comparing ', '').toLowerCase();
        const rows = [];
        
        rows.push(['Branch Comparison Report - ' + currentView]);
        rows.push(['Date Generated', new Date().toLocaleString()]);
        rows.push([]);
        
        if (comparisonType === 'sales') {
            rows.push(['BRANCH SALES COMPARISON']);
            rows.push(['Branch', 'Today Sales', 'Weekly Sales', 'Monthly Sales']);
            [1, 2, 3].forEach(branchId => {
                const branch = branchesData[branchId];
                rows.push([
                    `Branch ${branchId}`,
                    '₱' + branch.today_sales_total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}),
                    '₱' + branch.week_sales_total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}),
                    '₱' + branch.month_sales_total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})
                ]);
            });
        } else if (comparisonType === 'stock') {
            rows.push(['BRANCH STOCK COMPARISON']);
            rows.push(['Branch', 'Low Stock', 'Out of Stock']);
            [1, 2, 3].forEach(branchId => {
                const branch = branchesData[branchId];
                rows.push([
                    `Branch ${branchId}`,
                    branch.low_stock,
                    branch.out_of_stock
                ]);
            });
        } else if (comparisonType === 'alerts') {
            rows.push(['BRANCH ALERTS COMPARISON']);
            rows.push(['Branch', 'Expiring Soon', 'Expired']);
            [1, 2, 3].forEach(branchId => {
                const branch = branchesData[branchId];
                rows.push([
                    `Branch ${branchId}`,
                    branch.expiring_soon,
                    branch.expired
                ]);
            });
        }
        
        // Convert to CSV and download
        const csvContent = "data:text/csv;charset=utf-8," + rows.map(e => e.join(",")).join("\n");
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", `Branch_Comparison_${comparisonType}_${new Date().toISOString().slice(0,10)}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        return;
    } else {
        // Extract branch number from view title
        const branchMatch = currentView.match(/Branch (\d+)/);
        if (branchMatch) {
            const branchId = parseInt(branchMatch[1]);
            const branchData = branchesData[branchId];
            dataToExport = {
                name: `Branch ${branchId} - ${<?php echo json_encode($branch_names); ?>[branchId]}`,
                salesToday: branchData.today_sales_total,
                transactions: branchData.today_transactions,
                revenueMonth: branchData.month_sales_total,
                alerts: branchData.low_stock + branchData.out_of_stock + branchData.expiring_soon + branchData.expired,
                weeklySales: branchData.week_sales_total,
                inventory: {
                    active: branchData.total_active,
                    low: branchData.low_stock,
                    out: branchData.out_of_stock,
                    expiring: branchData.expiring_soon,
                    expired: branchData.expired
                }
            };
        }
    }
    
    if (!dataToExport) return;
    
    const rows = [];
    
    // 1. Header Information
    rows.push(['Exported Dashboard Report']);
    rows.push(['Date Generated', new Date().toLocaleString()]);
    rows.push(['Scope', dataToExport.name]);
    rows.push([]); // Empty row for spacing
    
    // 2. Key Performance Indicators (KPIs)
    rows.push(['KPI METRICS']);
    rows.push(['Sales Today', '₱' + dataToExport.salesToday.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})]);
    rows.push(['Transactions Today', dataToExport.transactions]);
    rows.push(['Monthly Revenue', '₱' + dataToExport.revenueMonth.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})]);
    rows.push(['Active Alerts', dataToExport.alerts]);
    rows.push(['Weekly Sales Total', '₱' + dataToExport.weeklySales.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})]);
    rows.push([]);
    
    // 3. Inventory Status
    rows.push(['INVENTORY STATUS']);
    rows.push(['Active Medicines', dataToExport.inventory.active]);
    rows.push(['Low Stock', dataToExport.inventory.low]);
    rows.push(['Out of Stock', dataToExport.inventory.out]);
    rows.push(['Expiring Soon', dataToExport.inventory.expiring]);
    rows.push(['Expired', dataToExport.inventory.expired]);
    
    // Convert to CSV string
    const csvContent = "data:text/csv;charset=utf-8," 
        + rows.map(e => e.join(",")).join("\n");
    
    // Create download link and trigger click
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `Pharmacy_Report_${dataToExport.name.replace(/\s+/g, '_')}_${new Date().toISOString().slice(0,10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// --- Initialization ---
window.onload = function() {
    // Render Charts
    renderWeeklySalesChart();
    renderTopMedicinesChart();

    // Initialize clock dynamically and update every second
    updateClockDynamic();
    setInterval(updateClockDynamic, 1000);
};
</script>
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <script src="js/report_table.js"></script>
    <script src="js/chat.js?v=<?php echo time(); ?>"></script>
    <script src="js/notifications_bell.js" defer></script>
    <script src="js/script.js"></script>
</body>
</html>