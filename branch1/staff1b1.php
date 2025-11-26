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

// 2a. Previous Week's Sales for Comparison
$prev_week_sales_stmt = $conn->prepare("
    SELECT SUM(TotalAmount) as total_sales
    FROM SalesTransactions
    WHERE BranchID = ? AND WEEK(TransactionDateTime) = WEEK(DATE_SUB(CURDATE(), INTERVAL 1 WEEK)) AND YEAR(TransactionDateTime) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 WEEK))
");
$prev_week_sales_stmt->execute([$current_branch_id]);
$prev_week_sales = $prev_week_sales_stmt->fetch(PDO::FETCH_ASSOC);
$prev_week_sales_total = $prev_week_sales['total_sales'] ?? 0;

// Calculate Weekly Comparison
$week_comparison = '';
$week_comparison_class = '';
if ($prev_week_sales_total > 0) {
    $week_change = (($week_sales_total - $prev_week_sales_total) / $prev_week_sales_total) * 100;
    $week_comparison = ($week_change >= 0 ? '+' : '') . number_format($week_change, 1) . '%';
    $week_comparison_class = $week_change >= 0 ? 'text-success-green' : 'text-danger-red';
} else {
    $week_comparison = 'N/A';
    $week_comparison_class = 'text-text-light';
}

// 3. This Month's Sales
$month_sales_stmt = $conn->prepare("
    SELECT SUM(TotalAmount) as total_sales
    FROM SalesTransactions
    WHERE BranchID = ? AND MONTH(TransactionDateTime) = MONTH(CURDATE()) AND YEAR(TransactionDateTime) = YEAR(CURDATE())
");
$month_sales_stmt->execute([$current_branch_id]);
$month_sales = $month_sales_stmt->fetch(PDO::FETCH_ASSOC);
$month_sales_total = $month_sales['total_sales'] ?? 0;

// 3a. Previous Month's Sales for Comparison
$prev_month_sales_stmt = $conn->prepare("
    SELECT SUM(TotalAmount) as total_sales
    FROM SalesTransactions
    WHERE BranchID = ? AND MONTH(TransactionDateTime) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(TransactionDateTime) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
");
$prev_month_sales_stmt->execute([$current_branch_id]);
$prev_month_sales = $prev_month_sales_stmt->fetch(PDO::FETCH_ASSOC);
$prev_month_sales_total = $prev_month_sales['total_sales'] ?? 0;

// Calculate Monthly Comparison
$month_comparison = '';
$month_comparison_class = '';
if ($prev_month_sales_total > 0) {
    $month_change = (($month_sales_total - $prev_month_sales_total) / $prev_month_sales_total) * 100;
    $month_comparison = ($month_change >= 0 ? '+' : '') . number_format($month_change, 1) . '%';
    $month_comparison_class = $month_change >= 0 ? 'text-success-green' : 'text-danger-red';
} else {
    $month_comparison = 'N/A';
    $month_comparison_class = 'text-text-light';
}

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
    WHERE BranchID = ? AND ExpiryDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
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
$top_sellers_stmt = $conn->prepare(
    "SELECT m.MedicineName, SUM(ti.Quantity) as total_qty, SUM(ti.Subtotal) as total_revenue
    FROM TransactionItems ti
    JOIN SalesTransactions st ON ti.TransactionID = st.TransactionID
    JOIN BranchInventory bi ON ti.BranchInventoryID = bi.BranchInventoryID
    JOIN medicines m ON bi.MedicineID = m.MedicineID
    WHERE st.BranchID = :branch_id AND st.TransactionDateTime >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY m.MedicineName
    ORDER BY total_revenue DESC
    LIMIT 5
");
$top_sellers_stmt->bindValue(':branch_id', $current_branch_id, PDO::PARAM_INT);
$top_sellers_stmt->execute();
$top_sellers = $top_sellers_stmt->fetchAll(PDO::FETCH_ASSOC);

// 6. Recent Transactions
$recent_trans_stmt = $conn->prepare("
    SELECT TransactionID, TransactionDateTime, TotalAmount, PaymentMethod
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

// 10. Monthly Prescription vs OTC Trends (last 7 months) - classify by simple keyword regex on medicine name/form
$keywords = ['amox','amoxi','insulin','injection','syrup','tablet','capsule','antibiotic','antidepress','anticonvuls','antihypertens','prescription'];
$regex = implode('|', array_map(function($k){ return preg_quote(strtolower($k), '/'); }, $keywords));

$monthly_stmt = $conn->prepare(
    "SELECT DATE_FORMAT(st.TransactionDateTime, '%Y-%m') as ym,
            DATE_FORMAT(st.TransactionDateTime, '%b') as month_label,
            SUM(CASE WHEN LOWER(m.MedicineName) REGEXP :regex OR LOWER(m.Form) REGEXP :regex THEN ti.Subtotal ELSE 0 END) as prescriptions,
            SUM(CASE WHEN NOT (LOWER(m.MedicineName) REGEXP :regex OR LOWER(m.Form) REGEXP :regex) THEN ti.Subtotal ELSE 0 END) as otc
     FROM TransactionItems ti
     JOIN SalesTransactions st ON ti.TransactionID = st.TransactionID
     JOIN BranchInventory bi ON ti.BranchInventoryID = bi.BranchInventoryID
     JOIN medicines m ON bi.MedicineID = m.MedicineID
        WHERE st.BranchID = :branch_id AND st.TransactionDateTime >= DATE_SUB(CURDATE(), INTERVAL 7 MONTH)
        GROUP BY ym, month_label
     ORDER BY ym ASC"
);
$monthly_stmt->bindValue(':branch_id', $current_branch_id, PDO::PARAM_INT);
$monthly_stmt->bindValue(':regex', $regex);
$monthly_stmt->execute();
$monthly_raw = $monthly_stmt->fetchAll(PDO::FETCH_ASSOC);

// Build ordered last-7-months array including months with zero values
$monthlyData = [];
$now = new DateTime();
for ($i = 6; $i >= 0; $i--) {
    $dt = (clone $now)->modify("-{$i} months");
    $ym = $dt->format('Y-m');
    $label = $dt->format('M');
    $monthlyData[$ym] = [ 'month' => $label, 'prescriptions' => 0.0, 'otc' => 0.0 ];
}
foreach ($monthly_raw as $r) {
    $ym = $r['ym'];
    if (isset($monthlyData[$ym])) {
        $monthlyData[$ym]['prescriptions'] = (float)$r['prescriptions'];
        $monthlyData[$ym]['otc'] = (float)$r['otc'];
    }
}

// 11. Top 10 Bestselling Medicines (by total quantity sold)
// If $current_branch_id is set (>0) we filter to that branch, otherwise across all branches
if (!empty($current_branch_id)) {
    $top_sellers_sql = "SELECT m.MedicineID, m.MedicineName, SUM(ti.Quantity) AS total_qty, SUM(ti.Subtotal) AS total_sales
        FROM TransactionItems ti
        JOIN SalesTransactions st ON ti.TransactionID = st.TransactionID
        JOIN BranchInventory bi ON ti.BranchInventoryID = bi.BranchInventoryID
        JOIN medicines m ON bi.MedicineID = m.MedicineID
        WHERE st.BranchID = :branch_id
        GROUP BY m.MedicineID, m.MedicineName
        ORDER BY total_qty DESC
        LIMIT 10";
    $top_stmt = $conn->prepare($top_sellers_sql);
    $top_stmt->bindValue(':branch_id', $current_branch_id, PDO::PARAM_INT);
    $top_stmt->execute();
} else {
    $top_sellers_sql = "SELECT m.MedicineID, m.MedicineName, SUM(ti.Quantity) AS total_qty, SUM(ti.Subtotal) AS total_sales
        FROM TransactionItems ti
        JOIN SalesTransactions st ON ti.TransactionID = st.TransactionID
        JOIN BranchInventory bi ON ti.BranchInventoryID = bi.BranchInventoryID
        JOIN medicines m ON bi.MedicineID = m.MedicineID
        GROUP BY m.MedicineID, m.MedicineName
        ORDER BY total_qty DESC
        LIMIT 10";
    $top_stmt = $conn->prepare($top_sellers_sql);
    $top_stmt->execute();
}
$top_raw = $top_stmt->fetchAll(PDO::FETCH_ASSOC);

// Build top sellers array
$topSellers = [];
foreach ($top_raw as $r) {
    $topSellers[] = [
        'medicine' => $r['MedicineName'],
        'quantity' => (int)$r['total_qty'],
        'sales' => (float)$r['total_sales']
    ];
}

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

    
    <!-- Custom styles and Tailwind configuration -->
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
    <script>
        // Tailwind Configuration
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'pharmacy-primary': '#1f2937',
                        'pharmacy-secondary': '#14B8A6',
                        'pharmacy-bg': '[#F4F6FA]',
                        'card-bg': '[#F4F6FA]',
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

        // --- Data from server (Monthly Prescription vs OTC Trends and Top Sellers) ---
        const monthlyData = <?php echo json_encode(array_values($monthlyData), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;
        const topSellersData = <?php echo json_encode($topSellers, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;

        // Compute sensible maxima for chart scaling (fallback to 1 to avoid divide-by-zero)
        <?php
            $maxTrend = 1;
            $prescVals = array_column($monthlyData, 'prescriptions');
            $otcVals = array_column($monthlyData, 'otc');
            if (!empty($prescVals) || !empty($otcVals)) {
                $maxTrend = max(max($prescVals ?: [0]), max($otcVals ?: [0]), 1);
                // add 10% headroom
                $maxTrend = ceil($maxTrend * 1.1);
            }
            $maxSellersQty = 1;
            $qtys = array_map(function($t){ return $t['quantity']; }, $topSellers);
            if (!empty($qtys)) {
                $maxSellersQty = max(max($qtys), 1);
                $maxSellersQty = ceil($maxSellersQty * 1.1);
            }
        ?>
        const MAX_TREND_VALUE = <?php echo (int)$maxTrend; ?>;
        const MAX_SELLERS_QTY = <?php echo (int)$maxSellersQty; ?>;

        // --- Functions ---

        /**
         * Renders the Monthly Prescription Trends Bar Chart.
         */
        function renderMonthlyTrendsChart() {
            const chartContainer = document.getElementById('prescription-trends-chart');
            if (!chartContainer) return;
            chartContainer.innerHTML = '';
            
            function getBarHeight(value, max) {
                return (value / max) * 100;
            }

            monthlyData.forEach(data => {
                const prescriptionHeight = getBarHeight(data.prescriptions, MAX_TREND_VALUE) * 0.8;
                const otcHeight = getBarHeight(data.otc, MAX_TREND_VALUE) * 0.8;

                const group = document.createElement('div');
                group.className = 'bar-group flex w-1/7 h-full items-end justify-center px-1';
                
                group.innerHTML = `
                    <div class="relative w-5 h-[${otcHeight}%] bg-gray-300 rounded-t-sm mx-1 bar-chart-bar group">
                        <div class="bar-tooltip group-hover:opacity-100" style="left: 50%; top: 0px;">${data.otc} OTC</div>
                    </div>
                    <div class="relative w-5 h-[${prescriptionHeight}%] bg-pharmacy-primary rounded-t-sm mx-1 bar-chart-bar group">
                        <div class="bar-tooltip group-hover:opacity-100" style="left: 50%; top: 0px;">${data.prescriptions} Presc.</div>
                    </div>
                    <span class="absolute -bottom-6 text-xs text-text-light">${data.month}</span>
                `;
                chartContainer.appendChild(group);
            });
        }
        
        /**
         * Renders the Top Sellers Horizontal Bar Chart.
         */
        function renderFootTrafficChart() {
            const chartContainer = document.getElementById('foot-traffic-chart');
            if (!chartContainer) return;

            chartContainer.innerHTML = '';
            if (!Array.isArray(topSellersData) || topSellersData.length === 0) {
                chartContainer.innerHTML = '<div class="text-sm text-text-light">No sales data available.</div>';
                const labelContainerEmpty = document.getElementById('traffic-x-labels');
                if (labelContainerEmpty) labelContainerEmpty.innerHTML = '';
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

            // Clear any old X-axis labels
            const labelContainer = document.getElementById('traffic-x-labels');
            if (labelContainer) labelContainer.innerHTML = '';
        }
        
        /**
         * Updates the clock and shift status dynamically with real-time data.
         */
        function updateClockDynamic() {
            const now = new Date();
            const clockElement = document.getElementById('current-time');
            const dateElement = document.getElementById('current-date');
            const statusElement = document.getElementById('shift-status');
            
            // Format time as HH:MM AM/PM
            const hours = now.getHours();
            const minutes = now.getMinutes().toString().padStart(2, '0');
            const ampm = hours >= 12 ? 'PM' : 'AM';
            const displayHours = hours % 12 || 12;
            const timeString = `${displayHours}:${minutes} ${ampm}`;
            
            // Format date as "Weekday, Month DD, YYYY"
            const weekdays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            const weekday = weekdays[now.getDay()];
            const month = months[now.getMonth()];
            const day = now.getDate();
            const year = now.getFullYear();
            const dateString = `${weekday}, ${month} ${day}, ${year}`;
            
            // Determine shift status based on current hour
            let shiftStatus = '';
            let shiftClass = '';
            if (hours >= 6 && hours < 14) {
                shiftStatus = 'Morning Shift';
                shiftClass = 'bg-success-green text-white';
            } else if (hours >= 14 && hours < 22) {
                shiftStatus = 'Afternoon Shift';
                shiftClass = 'bg-blue-500 text-white';
            } else {
                shiftStatus = 'Night/Closing Shift';
                shiftClass = 'bg-gray-200 text-gray-700';
            }
            
            clockElement.textContent = timeString;
            dateElement.textContent = dateString;
            statusElement.textContent = shiftStatus;
            
            // Update classes for shift status styling
            statusElement.className = `text-sm text-text-medium mt-1 ${shiftClass}`;
        }


        // --- Initialization ---
        window.onload = function() {
            // Render Charts
            renderMonthlyTrendsChart();
            renderFootTrafficChart();

            // Initialize clock dynamically and update every second
            updateClockDynamic();
            setInterval(updateClockDynamic, 1000);
        };

    </script>
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
        <main class="bg-custom-bg-[#FFFFFF] p-6 flex-grow h-full relative z-10">
    <div class="max-w-7xl mx-auto p-4 md:p-8">
        
        <!-- Header Section (Simplified) -->
        <header class="flex justify-between items-center mb-10">
            <div>
                <h1 class="text-3xl font-extrabold text-pharmacy-primary">Pharmacy Operations Dashboard</h1>
                <p class="text-sm text-text-light">Current Operational Overview</p>
            </div>
        </header>

        <!-- Top KPI Row (4 Cards) - Clock is now Card #1 -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            
            <!-- 1. Current Operations & Time (MOVED & MODIFIED) -->
            <div class="bg-card-bg p-6 rounded-xl shadow-lg flex flex-col justify-between">
                <div class="flex flex-col items-start">
                    <p id="current-time" class="text-5xl font-extrabold text-pharmacy-primary"></p>
                    <p id="current-date" class="text-sm text-text-light mt-1"></p>
                    <p id="shift-status" class="text-sm text-text-medium mt-1"></p>
                    <div class="mt-4 pt-4 border-t border-gray-100 w-full">
                        <p class="text-xs text-text-medium font-medium">Local Time & Staff Status</p>
                    </div>
                </div>
            </div>

            <!-- 2. Today's Sales (Live Data) -->
            <div class="bg-pharmacy-primary text-white p-6 rounded-xl shadow-lg flex flex-col justify-between">
                <div class="flex justify-between items-start mb-4">
                    <div class="p-2 bg-white bg-opacity-20 rounded-lg">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                </div>
                <div class="flex flex-col">
                    <p class="text-sm mb-1">Today's Sales</p>
                    <p class="text-3xl font-extrabold">₱<?php echo number_format($today_sales_total,2); ?></p>
                    <p class="text-xs text-white text-opacity-80"><?php echo $today_transactions; ?> transactions today</p>
                </div>
            </div>

            <!-- 3. Monthly Revenue (Live Data) -->
            <div class="bg-card-bg p-6 rounded-xl shadow-lg flex flex-col justify-between">
                <div class="flex justify-between items-start mb-4">
                    <div class="p-2 icon-success rounded-lg">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                    </div>
                    <span class="text-text-medium text-sm font-semibold">This Month</span>
                </div>
                <div class="flex flex-col">
                    <p class="text-sm mb-1 text-text-light">Monthly Revenue</p>
                    <p class="text-3xl font-bold text-text-dark">₱<?php echo number_format($month_sales_total,2); ?></p>
                    <p class="text-xs <?php echo $month_comparison_class; ?> font-medium"><?php echo $month_comparison; ?> compared to last month</p>
                </div>
            </div>

            <!-- 4. Alerts (Live Data) -->
            <div class="bg-card-bg p-6 rounded-xl shadow-lg flex flex-col justify-between">
                <div class="flex justify-between items-start mb-4">
                    <div class="p-2 icon-danger rounded-lg">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    </div>
                </div>
                <div class="flex flex-col">
                    <p class="text-sm mb-1 text-text-light">System Alerts</p>
                    <p class="text-3xl font-bold text-danger-red"><?php echo $alerts_count; ?></p>
                    <p class="text-xs text-danger-red font-medium">Requires attention</p>
                </div>
            </div>
        </div>
        
        <!-- Additional Operational Metrics (New Row) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            
            <!-- 1. Weekly Sales (Live Data) -->
            <div class="bg-card-bg p-6 rounded-xl shadow-lg flex flex-col justify-between border-t-4 border-warning-yellow">
                <div class="flex justify-between items-start mb-4">
                    <div class="p-2 icon-warning rounded-lg">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                </div>
                <div class="flex flex-col">
                    <p class="text-sm mb-1 text-text-light">This Week's Sales</p>
                    <p class="text-3xl font-bold text-text-alert">₱<?php echo number_format($week_sales_total,2); ?></p>
                    <p class="text-xs <?php echo $week_comparison_class; ?> font-medium"><?php echo $week_comparison; ?> compared to last week</p>
                </div>
            </div>

            <!-- 2. Payment Method Breakdown (Live Data) -->
            <div class="bg-card-bg p-6 rounded-xl shadow-lg flex flex-col justify-between border-t-4 border-pharmacy-primary">
                <div class="flex justify-between items-start mb-4">
                    <div class="p-2 icon-info rounded-lg">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                    </div>
                </div>
                <div class="flex flex-col">
                    <p class="text-sm mb-1 text-text-light">Payment Methods (30 days)</p>
                    <ul class="text-xs text-text-medium font-medium">
                        <?php foreach($payment_methods as $pm): ?>
                        <li><?php echo htmlspecialchars($pm['PaymentMethod']); ?>: ₱<?php echo number_format($pm['total'],2); ?> (<?php echo $pm['count']; ?> txns)</li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- 3. Expiring Soon (Live Data) -->
            <div class="bg-card-bg p-6 rounded-xl shadow-lg flex flex-col justify-between border-t-4 border-success-green">
                <div class="flex justify-between items-start mb-4">
                    <div class="p-2 icon-success rounded-lg">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.047m17.236 0c.264.495.385 1.05.385 1.638v10.518c0 1.24-.766 2.302-1.92 2.721L12 21.052l-9.357-3.23c-1.154-.419-1.92-1.481-1.92-2.721V7.629c0-.588.121-1.143.385-1.638"></path></svg>
                    </div>
                </div>
                <div class="flex flex-col">
                    <p class="text-sm mb-1 text-text-light">Expiring Soon</p>
                    <p class="text-3xl font-bold text-success-green"><?php echo $expiring_soon; ?></p>
                    <p class="text-xs text-text-medium font-medium">Medicines expiring in 30 days</p>
                </div>
            </div>
            
        </div>
        
        <!-- Middle Section: Trends and Inventory Status (2/3 vs 1/3 split) -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">

            <!-- 1. Prescription Trends Chart (2/3 width) -->
            <div class="lg:col-span-2 bg-card-bg p-6 rounded-xl shadow-lg">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-semibold text-text-dark">Monthly Prescription & OTC Trends (Last 7 Months)</h2>
                    <div class="flex items-center space-x-4">
                        <div class="flex items-center text-sm text-text-light">
                            <span class="w-3 h-3 bg-gray-400 rounded-full mr-2"></span> OTC Sales
                        </div>
                        <div class="flex items-center text-sm text-text-light">
                            <span class="w-3 h-3 bg-pharmacy-primary rounded-full mr-2"></span> Prescriptions
                        </div>
                    </div>
                </div>

                <!-- Bar Chart Implementation -->
                <div class="relative h-64 w-full flex items-end justify-around pb-4">
                    <!-- Y-axis labels -->
                    <div class="absolute left-0 top-0 bottom-0 w-10 flex flex-col justify-between text-xs text-text-light text-right pr-2">
                        <span>8K</span>
                        <span>6K</span>
                        <span>4K</span>
                        <span>2K</span>
                        <span>0K</span>
                    </div>
                    <!-- Horizontal lines -->
                    <div class="absolute left-10 right-0 top-0 bottom-0 border-l border-gray-200">
                        <div class="absolute w-full border-b border-gray-200" style="top: 0%"></div>
                        <div class="absolute w-full border-b border-gray-200" style="top: 25%"></div>
                        <div class="absolute w-full border-b border-gray-200" style="top: 50%"></div>
                        <div class="absolute w-full border-b border-gray-200" style="top: 75%"></div>
                        <div class="absolute w-full border-b border-gray-200" style="top: 100%"></div>
                    </div>

                    <!-- Chart Container for JavaScript to populate -->
                    <div id="prescription-trends-chart" class="flex justify-around items-end h-full w-[calc(100%-40px)] ml-auto relative">
                        <!-- Content populated by JavaScript -->
                    </div>
                </div>
            </div>

            <!-- 2. Inventory Status & Stock Alerts (1/3 width) -->
            <div class="lg:col-span-1 bg-card-bg p-6 rounded-xl shadow-lg">
                <h2 class="text-xl font-semibold text-text-dark mb-6">Inventory Stock Status</h2>
                
                <!-- Inventory Breakdown List -->
                <div class="space-y-4">
                    
                    <!-- Active Medicines -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="inventory-icon icon-success">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.047m17.236 0c.264.495.385 1.05.385 1.638v10.518c0 1.24-.766 2.302-1.92 2.721L12 21.052l-9.357-3.23c-1.154-.419-1.92-1.481-1.92-2.721V7.629c0-.588.121-1.143.385-1.638"></path></svg>
                            </div>
                            <span class="text-base font-medium text-text-medium">Active Medicines</span>
                        </div>
                        <span class="text-2xl font-bold text-success-green"><?php echo $total_active; ?></span>
                    </div>

                    <!-- Low Stock -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="inventory-icon icon-warning">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            </div>
                            <span class="text-base font-medium text-text-medium">Low Stock</span>
                        </div>
                        <span class="text-2xl font-bold text-text-alert"><?php echo $low_stock; ?></span>
                    </div>

                    <!-- Out of Stock -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="inventory-icon icon-danger">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77-1.333.192 3 1.732 3z"></path></svg>
                            </div>
                            <span class="text-base font-medium text-text-medium">Out of Stock</span>
                        </div>
                        <span class="text-2xl font-bold text-text-danger"><?php echo $out_of_stock; ?></span>
                    </div>

                    <!-- Expiring Soon -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="inventory-icon icon-warning">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h.01M3 21h18a2 2 0 002-2V7a2 2 0 00-2-2H3a2 2 0 00-2 2v12a2 2 0 002 2zm7-9L7 16h6l-3-5z"></path></svg>
                            </div>
                            <span class="text-base font-medium text-text-medium">Expiring Soon</span>
                        </div>
                        <span class="text-2xl font-bold text-text-alert"><?php echo $expiring_soon; ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Bottom Section: Critical Inventory & Best Sellers (2-column layout) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            
            <!-- 1. Critical Inventory Summary (Live Data) -->
            <div class="bg-card-bg p-6 rounded-xl shadow-lg flex flex-col justify-between">
                <div class="flex justify-between items-start mb-4">
                    <div class="p-2 icon-warning rounded-lg">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77-1.333.192 3 1.732 3z"></path></svg>
                    </div>
                </div>
                <div class="flex flex-col">
                    <p class="text-sm mb-1 text-text-light">Critical Inventory Items</p>
                    <p class="text-3xl font-bold text-text-dark"><?php echo ($low_stock + $out_of_stock); ?></p>
                    <p class="text-xs text-warning-yellow font-medium"><?php echo $low_stock; ?> Low Stock + <?php echo $out_of_stock; ?> Out of Stock</p>
                </div>
            </div>
            
            <!-- 2. Top 5 Best Sellers (Live Data) -->
            <div class="bg-card-bg p-6 rounded-xl shadow-lg">
                <h2 class="text-xl font-semibold text-text-dark mb-6">Top 5 Best Sellers</h2>
                <div class="space-y-4">
                    <?php foreach($top_sellers as $idx => $med): ?>
                    <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                        <div class="text-sm font-medium text-text-dark truncate w-2/3"><?php echo ($idx+1) . '. ' . htmlspecialchars($med['MedicineName']); ?></div>
                        <span class="text-sm font-semibold text-pharmacy-primary"><?php echo number_format($med['total_qty']); ?> units</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Foot Traffic Chart (Bottom Full Width) -->
        <div class="mt-6 bg-card-bg p-6 rounded-xl shadow-lg">
            <h2 class="text-xl font-semibold text-text-dark mb-6">Top 10 Bestselling Medicines (by Quantity)</h2>
            <div class="relative min-h-[170px] h-auto w-full flex flex-col justify-end pb-4">
                <!-- Y-axis labels -->
                <div class="absolute left-0 top-0 bottom-0 w-10 flex flex-col justify-between text-xs text-text-light text-right pr-2 min-h-[170px] h-auto mt-auto mb-4">
                    <span>60+</span>
                    <span>40</span>
                    <span>20</span>
                    <span>0</span>
                </div>
                <!-- Chart Area -->
                <div class="flex-grow relative min-h-[170px] h-auto w-full pl-10">
                    <!-- Horizontal lines -->
                    <div class="absolute inset-0 border-l border-gray-200">
                        <div class="absolute w-full border-b border-gray-200" style="top: 0%"></div>
                        <div class="absolute w-full border-b border-gray-200" style="top: 33%"></div>
                        <div class="absolute w-full border-b border-gray-200" style="top: 66%"></div>
                        <div class="absolute w-full border-b border-gray-200" style="top: 99%"></div>
                    </div>
                    <!-- Chart Container for JavaScript to populate -->
                    <div id="foot-traffic-chart" class="h-full w-full relative">
                        <!-- SVG content populated by JavaScript -->
                    </div>
                </div>
                <!-- X-axis labels -->
                <div id="traffic-x-labels" class="flex justify-between w-full pl-10 pt-2">
                    <!-- Labels populated by JavaScript -->
                </div>
            </div>
        </div>


    </div>
</main>

    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <script src="js/report_table.js"></script>
    <!-- Combined JavaScript Logic (from script.js and inline functions) -->

    <script src="js/chat.js?v=<?php echo time(); ?>"></script>
        <script src="js/notifications_bell.js" defer></script>
        <script src="js/script.js"></script>
</body>
</html>
