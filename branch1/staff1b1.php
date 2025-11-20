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

// 1. Today's Sales
$today_sales_stmt = $conn->prepare("
    SELECT SUM(TotalAmount) as total_sales, COUNT(*) as transaction_count 
    FROM SalesTransactions 
    WHERE BranchID = ? AND DATE(TransactionDateTime) = CURDATE()
");
$today_sales_stmt->execute([$current_branch_id]);
$today_sales = $today_sales_stmt->fetch(PDO::FETCH_ASSOC);
$today_sales_total = $today_sales['total_sales'] ?? 0;
$today_transactions = $today_sales['transaction_count'] ?? 0;

// 2. This Week's Sales
$week_sales_stmt = $conn->prepare("
    SELECT SUM(TotalAmount) as total_sales 
    FROM SalesTransactions 
    WHERE BranchID = ? AND WEEK(TransactionDateTime) = WEEK(CURDATE()) AND YEAR(TransactionDateTime) = YEAR(CURDATE())
");
$week_sales_stmt->execute([$current_branch_id]);
$week_sales = $week_sales_stmt->fetch(PDO::FETCH_ASSOC);
$week_sales_total = $week_sales['total_sales'] ?? 0;

// 3. This Month's Sales
$month_sales_stmt = $conn->prepare("
    SELECT SUM(TotalAmount) as total_sales 
    FROM SalesTransactions 
    WHERE BranchID = ? AND MONTH(TransactionDateTime) = MONTH(CURDATE()) AND YEAR(TransactionDateTime) = YEAR(CURDATE())
");
$month_sales_stmt->execute([$current_branch_id]);
$month_sales = $month_sales_stmt->fetch(PDO::FETCH_ASSOC);
$month_sales_total = $month_sales['total_sales'] ?? 0;

// 4. Stock Status
$low_stock_stmt = $conn->prepare("
    SELECT COUNT(*) as count FROM BranchInventory 
    WHERE BranchID = ? AND Stocks > 0 AND Stocks <= 10
");
$low_stock_stmt->execute([$current_branch_id]);
$low_stock = $low_stock_stmt->fetch(PDO::FETCH_ASSOC)['count'];

$out_of_stock_stmt = $conn->prepare("
    SELECT COUNT(*) as count FROM BranchInventory 
    WHERE BranchID = ? AND Stocks = 0
");
$out_of_stock_stmt->execute([$current_branch_id]);
$out_of_stock = $out_of_stock_stmt->fetch(PDO::FETCH_ASSOC)['count'];

$expiring_soon_stmt = $conn->prepare("
    SELECT COUNT(*) as count FROM BranchInventory 
    WHERE BranchID = ? AND ExpiryDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND ExpiryDate > CURDATE()
");
$expiring_soon_stmt->execute([$current_branch_id]);
$expiring_soon = $expiring_soon_stmt->fetch(PDO::FETCH_ASSOC)['count'];

$expired_stmt = $conn->prepare("
    SELECT COUNT(*) as count FROM BranchInventory 
    WHERE BranchID = ? AND ExpiryDate < CURDATE()
");
$expired_stmt->execute([$current_branch_id]);
$expired = $expired_stmt->fetch(PDO::FETCH_ASSOC)['count'];

$total_active_stmt = $conn->prepare("
    SELECT COUNT(*) as count FROM BranchInventory 
    WHERE BranchID = ? AND Stocks > 0 AND ExpiryDate > CURDATE()
");
$total_active_stmt->execute([$current_branch_id]);
$total_active = $total_active_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// 5. Top Selling Medicines
$top_sellers_stmt = $conn->prepare("
    SELECT m.MedicineName, SUM(ti.Quantity) as total_qty, SUM(ti.Subtotal) as total_revenue
    FROM TransactionItems ti
    JOIN BranchInventory bi ON ti.BranchInventoryID = bi.BranchInventoryID
    JOIN medicines m ON bi.MedicineID = m.MedicineID
    WHERE bi.BranchID = ? AND DATE(ti.TransactionID) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY m.MedicineName
    ORDER BY total_revenue DESC
    LIMIT 5
");
$top_sellers_stmt->execute([$current_branch_id]);
$top_sellers = $top_sellers_stmt->fetchAll(PDO::FETCH_ASSOC);

// 6. Recent Transactions
$recent_trans_stmt = $conn->prepare("
    SELECT TransactionID, TransactionDateTime, TotalAmount, PaymentMethod, CustomerName
    FROM SalesTransactions
    WHERE BranchID = ?
    ORDER BY TransactionDateTime DESC
    LIMIT 10
");
$recent_trans_stmt->execute([$current_branch_id]);
$recent_transactions = $recent_trans_stmt->fetchAll(PDO::FETCH_ASSOC);

// 7. Low Stock Medicines
$low_stock_medicines_stmt = $conn->prepare("
    SELECT m.MedicineName, bi.Stocks, bi.Price, bi.Status
    FROM BranchInventory bi
    JOIN medicines m ON bi.MedicineID = m.MedicineID
    WHERE bi.BranchID = ? AND bi.Stocks > 0 AND bi.Stocks <= 15
    ORDER BY bi.Stocks ASC
    LIMIT 8
");
$low_stock_medicines_stmt->execute([$current_branch_id]);
$low_stock_medicines = $low_stock_medicines_stmt->fetchAll(PDO::FETCH_ASSOC);

// 8. Payment Method Data
$payment_method_stmt = $conn->prepare("
    SELECT PaymentMethod, COUNT(*) as count, SUM(TotalAmount) as total
    FROM SalesTransactions
    WHERE BranchID = ? AND TransactionDateTime >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY PaymentMethod
");
$payment_method_stmt->execute([$current_branch_id]);
$payment_methods = $payment_method_stmt->fetchAll(PDO::FETCH_ASSOC);

// 9. Daily Sales Last 7 Days
$daily_sales_stmt = $conn->prepare("
    SELECT DATE(TransactionDateTime) as sale_date, SUM(TotalAmount) as daily_total, COUNT(*) as trans_count
    FROM SalesTransactions
    WHERE BranchID = ? AND TransactionDateTime >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(TransactionDateTime)
    ORDER BY sale_date ASC
");
$daily_sales_stmt->execute([$current_branch_id]);
$daily_sales_data = $daily_sales_stmt->fetchAll(PDO::FETCH_ASSOC);

$alerts_count = $low_stock + $out_of_stock + $expiring_soon + $expired;

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
    <link rel="stylesheet" href="css/chat_window.css?v=<?php echo time(); ?>">
    <script>
        // Make the current user's ID available to the JavaScript files
        window.currentUserId = <?php echo json_encode($_SESSION['user_id'] ?? 0); ?>;
        
    </script>
    <script src="js/medicine.js" defer></script>
    <script src="js/alerts.js" defer></script>
    <!-- Custom CSS Styles are in css/style.css -->
</head>
<body class="overflow-x-hidden">

    <!-- Backdrop for click outside close -->
    <div id="backdrop" class="backdrop" onclick="toggleSidebar()"></div>

    <!-- Outer container takes full screen space -->
    <div class="app-container bg-white">
        
        <!-- 1. TOP HEADER BAR (Dark Slate) -->
        <header id="main-header" class="bg-custom-dark-header text-white p-4 flex justify-between items-center sticky top-0 z-30 shadow-lg">

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
        <nav class="bg-white border-b border-gray-200 flex justify-between items-center px-6 py-3 shadow-sm sticky top-16 z-20">

            <!-- Navigation Links - INCREASED TEXT SIZE to text-base -->
            <div class="flex space-x-8 text-base font-medium">

                <a href="staff1b<?php echo $current_branch_id; ?>.php" class="py-2 px-3 rounded-md bg-blue-100 text-blue-800 font-medium transition-all duration-300">Dashboard</a>
                <a href="med_inventory.php" class="py-2 px-3 rounded-md text-black hover:bg-gray-100 hover:text-black font-medium transition-all duration-300">Med Inventory</a>
                <a href="pos.php" class="py-2 px-3 rounded-md text-black hover:bg-gray-100 hover:text-black font-medium transition-all duration-300">POS</a>
                <a href="reports.php" class="py-2 px-3 rounded-md text-black hover:bg-gray-100 hover:text-black font-medium transition-all duration-300">Reports</a>
                <a href="account.php" class="py-2 px-3 rounded-md text-black hover:bg-gray-100 hover:text-black font-medium transition-all duration-300">Account</a>
            </div>

            <!-- Logout Button -->
            <a href="b-crud/logout.php" class="bg-red-500 hover:bg-red-600 text-white font-medium py-2 px-4 rounded-lg transition duration-150 shadow-md text-sm inline-block">
                Logout
            </a>
        </nav>

        <!-- 3. MAIN CONTENT AREA (Cream Background) -->
        <main class="bg-custom-bg-white p-6 flex-grow h-full relative z-10">
            <div id="dynamic-content" class="main-content flex-1 p-6 lg:p-10 overflow-y-auto bg-main-bg-color">
                <!-- Page Header -->
                <div class="flex justify-between items-start mb-8">
                    <div>
                        <h2 id="page-title" class="text-4xl font-extrabold text-gray-900 mb-2">
                            Branch <?php echo $current_branch_id; ?> Dashboard
                        </h2>
                        <p class="text-gray-600 text-lg">
                            Welcome back, <?php echo $user_full_name; ?>. Managing operations for <?php echo $branch_name; ?>.
                        </p>
                    </div>
                    <button onclick="exportDashboard()" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded-lg transition">
                        📥 Export Report
                    </button>
                </div>

                <!-- Live Clock Section -->
                <div class="bg-white rounded-3xl shadow-lg p-6 mb-6 text-center border border-gray-100">
                    <div class="text-5xl font-bold text-purple-600 mb-2" id="live-time">00:00:00</div>
                    <div class="text-lg text-gray-600" id="live-date"></div>
                </div>

                <!-- KPI Cards Row 1: Sales Metrics -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <!-- Today's Sales -->
                    <div class="bg-gradient-to-br from-green-50 to-green-100 p-6 rounded-3xl shadow-lg border-l-4 border-green-500 hover:shadow-xl transition">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Today's Sales</p>
                                <p class="text-3xl font-bold text-gray-900 mt-2">₱<?php echo number_format($today_sales_total, 2); ?></p>
                                <p class="text-sm text-green-600 mt-2"><?php echo $today_transactions; ?> transactions</p>
                            </div>
                            <div class="text-5xl opacity-20">📈</div>
                        </div>
                    </div>

                    <!-- This Week -->
                    <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-6 rounded-3xl shadow-lg border-l-4 border-blue-500 hover:shadow-xl transition">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-gray-600 uppercase tracking-wide">This Week</p>
                                <p class="text-3xl font-bold text-gray-900 mt-2">₱<?php echo number_format($week_sales_total, 2); ?></p>
                                <p class="text-sm text-blue-600 mt-2">Weekly total</p>
                            </div>
                            <div class="text-5xl opacity-20">📊</div>
                        </div>
                    </div>

                    <!-- This Month -->
                    <div class="bg-gradient-to-br from-indigo-50 to-indigo-100 p-6 rounded-3xl shadow-lg border-l-4 border-indigo-500 hover:shadow-xl transition">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-gray-600 uppercase tracking-wide">This Month</p>
                                <p class="text-3xl font-bold text-gray-900 mt-2">₱<?php echo number_format($month_sales_total, 2); ?></p>
                                <p class="text-sm text-indigo-600 mt-2">Monthly revenue</p>
                            </div>
                            <div class="text-5xl opacity-20">💰</div>
                        </div>
                    </div>

                    <!-- Total Alerts -->
                    <div class="bg-gradient-to-br from-red-50 to-red-100 p-6 rounded-3xl shadow-lg border-l-4 border-red-500 hover:shadow-xl transition">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Alerts</p>
                                <p class="text-3xl font-bold text-red-600 mt-2"><?php echo $alerts_count; ?></p>
                                <p class="text-sm text-red-600 mt-2">Requires attention</p>
                            </div>
                            <div class="text-5xl opacity-20">🔔</div>
                        </div>
                    </div>
                </div>

                <!-- KPI Cards Row 2: Stock Status -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <!-- Active Medicines -->
                    <div class="bg-white p-6 rounded-3xl shadow-lg border border-green-200 hover:shadow-xl transition">
                        <p class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Active Medicines</p>
                        <p class="text-3xl font-bold text-green-600 mt-2"><?php echo $total_active; ?></p>
                        <p class="text-sm text-gray-500 mt-2">✓ In stock</p>
                    </div>

                    <!-- Low Stock -->
                    <div class="bg-white p-6 rounded-3xl shadow-lg border border-yellow-200 hover:shadow-xl transition">
                        <p class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Low Stock</p>
                        <p class="text-3xl font-bold text-yellow-600 mt-2"><?php echo $low_stock; ?></p>
                        <p class="text-sm text-gray-500 mt-2">⚠ Needs reorder</p>
                    </div>

                    <!-- Out of Stock -->
                    <div class="bg-white p-6 rounded-3xl shadow-lg border border-orange-200 hover:shadow-xl transition">
                        <p class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Out of Stock</p>
                        <p class="text-3xl font-bold text-orange-600 mt-2"><?php echo $out_of_stock; ?></p>
                        <p class="text-sm text-gray-500 mt-2">✗ Unavailable</p>
                    </div>

                    <!-- Expiring Soon -->
                    <div class="bg-white p-6 rounded-3xl shadow-lg border border-red-200 hover:shadow-xl transition">
                        <p class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Expiring Soon</p>
                        <p class="text-3xl font-bold text-red-600 mt-2"><?php echo $expiring_soon + $expired; ?></p>
                        <p class="text-sm text-gray-500 mt-2">⏰ Next 30 days</p>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Sales Trend Chart -->
                    <div class="bg-white p-6 rounded-3xl shadow-lg border border-gray-100">
                        <h3 class="text-xl font-bold text-gray-900 mb-4">Sales Trend (Last 7 Days)</h3>
                        <canvas id="salesChart"></canvas>
                    </div>

                    <!-- Payment Method Chart -->
                    <div class="bg-white p-6 rounded-3xl shadow-lg border border-gray-100">
                        <h3 class="text-xl font-bold text-gray-900 mb-4">Payment Methods</h3>
                        <canvas id="paymentChart"></canvas>
                    </div>
                </div>

                <!-- Top Sellers and Low Stock Alerts -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Top Sellers -->
                    <div class="bg-white p-6 rounded-3xl shadow-lg border border-gray-100">
                        <h3 class="text-xl font-bold text-gray-900 mb-4">Top 5 Best Sellers</h3>
                        <div class="space-y-3 max-h-80 overflow-y-auto">
                            <?php foreach ($top_sellers as $seller): ?>
                                <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg border border-gray-200">
                                    <div>
                                        <p class="font-semibold text-gray-900"><?php echo htmlspecialchars(substr($seller['MedicineName'], 0, 20)); ?></p>
                                        <p class="text-sm text-gray-500">Qty: <?php echo $seller['total_qty']; ?></p>
                                    </div>
                                    <p class="text-lg font-bold text-green-600">₱<?php echo number_format($seller['total_revenue'], 2); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Low Stock Alerts -->
                    <div class="bg-white p-6 rounded-3xl shadow-lg border border-gray-100">
                        <h3 class="text-xl font-bold text-gray-900 mb-4">Low Stock Medicines (Top 8)</h3>
                        <div class="space-y-3 max-h-80 overflow-y-auto">
                            <?php foreach ($low_stock_medicines as $med): ?>
                                <div class="p-3 bg-yellow-50 rounded-lg border-l-4 border-yellow-500">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="font-semibold text-gray-900"><?php echo htmlspecialchars(substr($med['MedicineName'], 0, 25)); ?></p>
                                            <p class="text-sm text-gray-600">Stock: <strong><?php echo $med['Stocks']; ?></strong> | Price: ₱<?php echo number_format($med['Price'], 2); ?></p>
                                        </div>
                                        <span class="bg-yellow-200 text-yellow-800 px-3 py-1 rounded-full text-xs font-bold"><?php echo htmlspecialchars($med['Status']); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Transactions Table -->
                <div class="bg-white p-6 rounded-3xl shadow-lg border border-gray-100 mb-6">
                    <h3 class="text-xl font-bold text-gray-900 mb-4">Recent Transactions</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-100 border-b-2 border-gray-300">
                                <tr>
                                    <th class="px-4 py-3 text-left font-bold text-gray-900">Transaction ID</th>
                                    <th class="px-4 py-3 text-left font-bold text-gray-900">Date & Time</th>
                                    <th class="px-4 py-3 text-left font-bold text-gray-900">Amount</th>
                                    <th class="px-4 py-3 text-left font-bold text-gray-900">Payment</th>
                                    <th class="px-4 py-3 text-left font-bold text-gray-900">Customer</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_transactions as $trans): ?>
                                    <tr class="border-b border-gray-200 hover:bg-gray-50">
                                        <td class="px-4 py-3 font-semibold text-purple-600">#<?php echo $trans['TransactionID']; ?></td>
                                        <td class="px-4 py-3 text-gray-700"><?php echo date('M d, Y H:i', strtotime($trans['TransactionDateTime'])); ?></td>
                                        <td class="px-4 py-3 font-bold text-gray-900">₱<?php echo number_format($trans['TotalAmount'], 2); ?></td>
                                        <td class="px-4 py-3"><span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-xs font-bold"><?php echo htmlspecialchars($trans['PaymentMethod']); ?></span></td>
                                        <td class="px-4 py-3 text-gray-700"><?php echo htmlspecialchars($trans['CustomerName'] ?? 'Walk-in'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>

        </div>

        <!-- Zoomed Chat Host -->
       

        <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Combined JavaScript Logic (from script.js and inline functions) -->

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script src="js/chat.js?v=<?php echo time(); ?>"></script>
    <script src="js/notifications_bell.js" defer></script>
    <script src="js/script.js"></script>

    <script>
        // Live Clock Update
        function updateClock() {
            const now = new Date();
            const timeStr = now.toLocaleTimeString('en-US', { hour12: false });
            const dateStr = now.toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            
            document.getElementById('live-time').textContent = timeStr;
            document.getElementById('live-date').textContent = dateStr;
        }
        updateClock();
        setInterval(updateClock, 1000);

        // Sales Chart Data
        const dailySalesData = <?php echo json_encode($daily_sales_data); ?>;
        const salesLabels = dailySalesData.map(d => new Date(d.sale_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
        const salesValues = dailySalesData.map(d => parseFloat(d.daily_total) || 0);

        const salesChartCtx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(salesChartCtx, {
            type: 'line',
            data: {
                labels: salesLabels,
                datasets: [{
                    label: 'Daily Sales (₱)',
                    data: salesValues,
                    borderColor: '#9333ea',
                    backgroundColor: 'rgba(147, 51, 234, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 6,
                    pointBackgroundColor: '#9333ea',
                    pointBorderColor: 'white',
                    pointBorderWidth: 2,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { 
                        display: true,
                        position: 'top'
                    },
                    filler: {
                        propagate: true
                    }
                },
                scales: {
                    y: { 
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Payment Method Chart
        const paymentData = <?php echo json_encode($payment_methods); ?>;
        const paymentLabels = paymentData.map(p => p.PaymentMethod || 'N/A');
        const paymentValues = paymentData.map(p => p.count);
        const colors = ['#9333ea', '#10b981', '#3b82f6'];

        const paymentChartCtx = document.getElementById('paymentChart').getContext('2d');
        const paymentChart = new Chart(paymentChartCtx, {
            type: 'doughnut',
            data: {
                labels: paymentLabels,
                datasets: [{
                    data: paymentValues,
                    backgroundColor: colors.slice(0, paymentLabels.length),
                    borderColor: 'white',
                    borderWidth: 2,
                    hoverBorderColor: '#f0f0f0',
                    hoverBorderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { 
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: { size: 12, weight: 'bold' }
                        }
                    }
                }
            }
        });

        function exportDashboard() {
            alert('Export functionality will be implemented soon. Supported formats: PDF, Excel');
        }
    </script>
</body>
</html>
