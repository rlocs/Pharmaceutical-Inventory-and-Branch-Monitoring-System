<?php
session_start();
require_once 'dbconnection.php';

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: b-login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$branch_id = $_SESSION['branch_id'];
$user_role = $_SESSION['role'];

// Initialize database connection
$db = new Database();
$conn = $db->getConnection();

// Fetch user information
$user_stmt = $conn->prepare("SELECT FirstName, LastName FROM Accounts WHERE UserID = ?");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);
$user_name = $user['FirstName'] . ' ' . $user['LastName'];

// ========== KPI CALCULATIONS ==========

// 1. Today's Sales
$today_sales_stmt = $conn->prepare("
    SELECT SUM(TotalAmount) as total_sales, COUNT(*) as transaction_count 
    FROM SalesTransactions 
    WHERE BranchID = ? AND DATE(TransactionDateTime) = CURDATE()
");
$today_sales_stmt->execute([$branch_id]);
$today_sales = $today_sales_stmt->fetch(PDO::FETCH_ASSOC);
$today_sales_total = $today_sales['total_sales'] ?? 0;
$today_transactions = $today_sales['transaction_count'] ?? 0;

// 2. This Week's Sales
$week_sales_stmt = $conn->prepare("
    SELECT SUM(TotalAmount) as total_sales 
    FROM SalesTransactions 
    WHERE BranchID = ? AND WEEK(TransactionDateTime) = WEEK(CURDATE()) AND YEAR(TransactionDateTime) = YEAR(CURDATE())
");
$week_sales_stmt->execute([$branch_id]);
$week_sales = $week_sales_stmt->fetch(PDO::FETCH_ASSOC);
$week_sales_total = $week_sales['total_sales'] ?? 0;

// 3. This Month's Sales
$month_sales_stmt = $conn->prepare("
    SELECT SUM(TotalAmount) as total_sales 
    FROM SalesTransactions 
    WHERE BranchID = ? AND MONTH(TransactionDateTime) = MONTH(CURDATE()) AND YEAR(TransactionDateTime) = YEAR(CURDATE())
");
$month_sales_stmt->execute([$branch_id]);
$month_sales = $month_sales_stmt->fetch(PDO::FETCH_ASSOC);
$month_sales_total = $month_sales['total_sales'] ?? 0;

// 4. Stock Status
$low_stock_stmt = $conn->prepare("
    SELECT COUNT(*) as count FROM BranchInventory 
    WHERE BranchID = ? AND Stocks > 0 AND Stocks <= 10
");
$low_stock_stmt->execute([$branch_id]);
$low_stock = $low_stock_stmt->fetch(PDO::FETCH_ASSOC)['count'];

$out_of_stock_stmt = $conn->prepare("
    SELECT COUNT(*) as count FROM BranchInventory 
    WHERE BranchID = ? AND Stocks = 0
");
$out_of_stock_stmt->execute([$branch_id]);
$out_of_stock = $out_of_stock_stmt->fetch(PDO::FETCH_ASSOC)['count'];

$expiring_soon_stmt = $conn->prepare("
    SELECT COUNT(*) as count FROM BranchInventory 
    WHERE BranchID = ? AND ExpiryDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND ExpiryDate > CURDATE()
");
$expiring_soon_stmt->execute([$branch_id]);
$expiring_soon = $expiring_soon_stmt->fetch(PDO::FETCH_ASSOC)['count'];

$expired_stmt = $conn->prepare("
    SELECT COUNT(*) as count FROM BranchInventory 
    WHERE BranchID = ? AND ExpiryDate < CURDATE()
");
$expired_stmt->execute([$branch_id]);
$expired = $expired_stmt->fetch(PDO::FETCH_ASSOC)['count'];

$total_active_stmt = $conn->prepare("
    SELECT COUNT(*) as count FROM BranchInventory 
    WHERE BranchID = ? AND Stocks > 0 AND ExpiryDate > CURDATE()
");
$total_active_stmt->execute([$branch_id]);
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
$top_sellers_stmt->execute([$branch_id]);
$top_sellers = $top_sellers_stmt->fetchAll(PDO::FETCH_ASSOC);

// 6. Recent Transactions
$recent_trans_stmt = $conn->prepare("
    SELECT TransactionID, TransactionDateTime, TotalAmount, PaymentMethod, CustomerName
    FROM SalesTransactions
    WHERE BranchID = ?
    ORDER BY TransactionDateTime DESC
    LIMIT 10
");
$recent_trans_stmt->execute([$branch_id]);
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
$low_stock_medicines_stmt->execute([$branch_id]);
$low_stock_medicines = $low_stock_medicines_stmt->fetchAll(PDO::FETCH_ASSOC);

// 8. Sales by Payment Method (Last 30 days)
$payment_method_stmt = $conn->prepare("
    SELECT PaymentMethod, COUNT(*) as count, SUM(TotalAmount) as total
    FROM SalesTransactions
    WHERE BranchID = ? AND TransactionDateTime >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY PaymentMethod
");
$payment_method_stmt->execute([$branch_id]);
$payment_methods = $payment_method_stmt->fetchAll(PDO::FETCH_ASSOC);

// 9. Daily Sales Last 7 Days
$daily_sales_stmt = $conn->prepare("
    SELECT DATE(TransactionDateTime) as sale_date, SUM(TotalAmount) as daily_total, COUNT(*) as trans_count
    FROM SalesTransactions
    WHERE BranchID = ? AND TransactionDateTime >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(TransactionDateTime)
    ORDER BY sale_date ASC
");
$daily_sales_stmt->execute([$branch_id]);
$daily_sales_data = $daily_sales_stmt->fetchAll(PDO::FETCH_ASSOC);

// 10. Branch Comparison (if admin)
$branch_comparison = [];
if ($user_role === 'Admin') {
    $branch_comp_stmt = $conn->prepare("
        SELECT b.BranchID, b.BranchName, 
               SUM(st.TotalAmount) as branch_sales,
               COUNT(st.TransactionID) as trans_count
        FROM Branches b
        LEFT JOIN SalesTransactions st ON b.BranchID = st.BranchID AND DATE(st.TransactionDateTime) = CURDATE()
        GROUP BY b.BranchID, b.BranchName
        ORDER BY branch_sales DESC
    ");
    $branch_comp_stmt->execute();
    $branch_comparison = $branch_comp_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 11. Get current branch name
$branch_stmt = $conn->prepare("SELECT BranchName FROM Branches WHERE BranchID = ?");
$branch_stmt->execute([$branch_id]);
$branch = $branch_stmt->fetch(PDO::FETCH_ASSOC);
$branch_name = $branch['BranchName'];

// 12. Alerts/Notifications Count
$alerts_count = $low_stock + $out_of_stock + $expiring_soon + $expired;

// 13. Unread Messages
$unread_messages_stmt = $conn->prepare("
    SELECT COUNT(DISTINCT ConversationID) as unread_convs
    FROM ChatMessages cm
    WHERE cm.ConversationID IN (
        SELECT ConversationID FROM ChatParticipants WHERE UserID = ?
    )
    AND cm.Timestamp > (
        SELECT COALESCE(MAX(LastReadTimestamp), '2000-01-01')
        FROM ChatParticipants 
        WHERE UserID = ? AND ConversationID = cm.ConversationID
    )
");
$unread_messages_stmt->execute([$user_id, $user_id]);
$unread_messages = $unread_messages_stmt->fetch(PDO::FETCH_ASSOC)['unread_convs'] ?? 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmaceutical Dashboard - <?php echo htmlspecialchars($branch_name); ?></title>
    
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            font-family: 'Poppins', sans-serif;
        }

        :root {
            --primary: #7c3aed;
            --secondary: #a78bfa;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --light: #f3f4f6;
            --dark: #1f2937;
        }

        body {
            background: #f9fafb;
            color: var(--dark);
        }

        .navbar-custom {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            box-shadow: 0 2px 8px rgba(124, 58, 237, 0.15);
            padding: 1rem 2rem;
        }

        .navbar-custom .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: white;
        }

        .navbar-custom .nav-link {
            color: white !important;
            margin: 0 0.5rem;
            transition: all 0.3s ease;
        }

        .navbar-custom .nav-link:hover {
            opacity: 0.8;
            transform: translateY(-2px);
        }

        .user-info {
            color: white;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .clock {
            font-size: 0.9rem;
            color: white;
            font-weight: 600;
        }

        .sidebar {
            background: white;
            height: calc(100vh - 70px);
            overflow-y: auto;
            box-shadow: 2px 0 8px rgba(0,0,0,0.08);
        }

        .sidebar .nav-link {
            color: var(--dark);
            border-left: 3px solid transparent;
            margin: 0.5rem 0;
            padding: 0.75rem 1.5rem;
            transition: all 0.3s ease;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: var(--light);
            border-left-color: var(--primary);
            color: var(--primary);
        }

        .main-content {
            padding: 2rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
        }

        .kpi-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid var(--primary);
            transition: all 0.3s ease;
            margin-bottom: 1rem;
        }

        .kpi-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
        }

        .kpi-card.success {
            border-left-color: var(--success);
        }

        .kpi-card.danger {
            border-left-color: var(--danger);
        }

        .kpi-card.warning {
            border-left-color: var(--warning);
        }

        .kpi-card.info {
            border-left-color: var(--info);
        }

        .kpi-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .kpi-card.success .kpi-icon {
            color: var(--success);
        }

        .kpi-card.danger .kpi-icon {
            color: var(--danger);
        }

        .kpi-card.warning .kpi-icon {
            color: var(--warning);
        }

        .kpi-card.info .kpi-icon {
            color: var(--info);
        }

        .kpi-label {
            font-size: 0.85rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .kpi-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0.5rem 0;
        }

        .kpi-subtitle {
            font-size: 0.8rem;
            color: #9ca3af;
        }

        .chart-container {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
        }

        .chart-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--dark);
        }

        .table-container {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
        }

        .table-custom {
            font-size: 0.9rem;
        }

        .table-custom thead {
            background: var(--light);
        }

        .table-custom th {
            border: none;
            font-weight: 700;
            color: var(--dark);
            padding: 1rem;
        }

        .table-custom td {
            padding: 1rem;
            border-bottom: 1px solid var(--light);
        }

        .badge-custom {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-active {
            background: #dcfce7;
            color: var(--success);
        }

        .status-low {
            background: #fef08a;
            color: #ca8a04;
        }

        .status-expired {
            background: #fee2e2;
            color: var(--danger);
        }

        .status-expiring {
            background: #fed7aa;
            color: #ea580c;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger);
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .alert-badge {
            display: inline-block;
            background: var(--danger);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .text-muted-custom {
            color: #6b7280;
            font-size: 0.85rem;
        }

        .time-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .current-time {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
            margin: 0.5rem 0;
        }

        .current-date {
            font-size: 1rem;
            color: #6b7280;
        }

        .mini-calendar {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .alert-item {
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            border-left: 4px solid var(--danger);
            background: #fef2f2;
            border-radius: 4px;
            font-size: 0.85rem;
        }

        .alert-item.warning {
            border-left-color: var(--warning);
            background: #fffbeb;
        }

        .btn-export {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-export:hover {
            background: var(--secondary);
            transform: translateY(-2px);
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            width: 100%;
        }

        .search-box i {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }

        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .filter-section select {
            padding: 0.5rem;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            margin-right: 0.5rem;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .kpi-value {
                font-size: 1.25rem;
            }

            .current-time {
                font-size: 1.75rem;
            }
        }

        .gradient-text {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .scroll-feed {
            max-height: 400px;
            overflow-y: auto;
        }

        .scroll-feed::-webkit-scrollbar {
            width: 6px;
        }

        .scroll-feed::-webkit-scrollbar-track {
            background: var(--light);
            border-radius: 10px;
        }

        .scroll-feed::-webkit-scrollbar-thumb {
            background: var(--secondary);
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class='bx bx-hospital'></i> Pharmaceutical System
            </a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item">
                        <span class="clock" id="navbar-clock"></span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="branch1/med_inventory.php">
                            <i class='bx bx-pill'></i> Inventory
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="branch1/pos.php">
                            <i class='bx bx-shopping-bag'></i> POS
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="branch1/includes/cross_branch_chat.php" style="position: relative;">
                            <i class='bx bx-chat'></i> Chat
                            <?php if ($unread_messages > 0): ?>
                                <span class="notification-badge"><?php echo $unread_messages; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown">
                            <i class='bx bx-user-circle'></i> <?php echo htmlspecialchars($user_name); ?>
                        </a>
                        <div class="dropdown-menu" aria-labelledby="userDropdown">
                            <a class="dropdown-item" href="branch1/account.php">My Account</a>
                            <a class="dropdown-item" href="forgot.php">Change Password</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="logout.php">Logout</a>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Main Content -->
            <div class="col-md-12">
                <div class="main-content">
                    <!-- Page Header -->
                    <div class="page-header">
                        <div>
                            <h1 class="page-title gradient-text">Dashboard</h1>
                            <p class="text-muted-custom">Welcome back, <?php echo htmlspecialchars($user_name); ?> | <?php echo htmlspecialchars($branch_name); ?></p>
                        </div>
                        <div>
                            <button class="btn-export" onclick="exportToPDF()">
                                <i class='bx bx-download'></i> Export Report
                            </button>
                        </div>
                    </div>

                    <!-- Time and Calendar Section -->
                    <div class="row">
                        <div class="col-md-4 col-sm-12">
                            <div class="time-section">
                                <div class="current-time" id="live-time">00:00:00</div>
                                <div class="current-date" id="live-date"></div>
                            </div>
                        </div>
                        <div class="col-md-8 col-sm-12">
                            <!-- Filter Section -->
                            <div class="filter-section">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="search-box">
                                            <i class='bx bx-search'></i>
                                            <input type="text" id="medicine-search" placeholder="Search medicines...">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <select id="category-filter" onchange="filterDashboard()">
                                            <option value="">All Categories</option>
                                            <option value="active">Active</option>
                                            <option value="low_stock">Low Stock</option>
                                            <option value="expired">Expired</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <select id="date-filter" onchange="filterDashboard()">
                                            <option value="today">Today</option>
                                            <option value="week">This Week</option>
                                            <option value="month">This Month</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- KPI Cards -->
                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="kpi-card success">
                                <div class="kpi-icon"><i class='bx bx-trending-up'></i></div>
                                <div class="kpi-label">Today's Sales</div>
                                <div class="kpi-value">₱<?php echo number_format($today_sales_total, 2); ?></div>
                                <div class="kpi-subtitle"><?php echo $today_transactions; ?> transactions</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="kpi-card info">
                                <div class="kpi-icon"><i class='bx bx-calendar'></i></div>
                                <div class="kpi-label">This Week</div>
                                <div class="kpi-value">₱<?php echo number_format($week_sales_total, 2); ?></div>
                                <div class="kpi-subtitle">Weekly revenue</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="kpi-card info">
                                <div class="kpi-icon"><i class='bx bx-bar-chart-alt-2'></i></div>
                                <div class="kpi-label">This Month</div>
                                <div class="kpi-value">₱<?php echo number_format($month_sales_total, 2); ?></div>
                                <div class="kpi-subtitle">Monthly revenue</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="kpi-card warning">
                                <div class="kpi-icon"><i class='bx bx-bell'></i></div>
                                <div class="kpi-label">Total Alerts</div>
                                <div class="kpi-value"><?php echo $alerts_count; ?></div>
                                <div class="kpi-subtitle">Requires attention</div>
                            </div>
                        </div>
                    </div>

                    <!-- Stock Status Cards -->
                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="kpi-card success">
                                <div class="kpi-icon"><i class='bx bx-check-circle'></i></div>
                                <div class="kpi-label">Active Medicines</div>
                                <div class="kpi-value"><?php echo $total_active; ?></div>
                                <div class="kpi-subtitle">In stock</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="kpi-card warning">
                                <div class="kpi-icon"><i class='bx bx-exclamation-circle'></i></div>
                                <div class="kpi-label">Low Stock</div>
                                <div class="kpi-value"><?php echo $low_stock; ?></div>
                                <div class="kpi-subtitle">Needs reorder</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="kpi-card danger">
                                <div class="kpi-icon"><i class='bx bx-trash'></i></div>
                                <div class="kpi-label">Out of Stock</div>
                                <div class="kpi-value"><?php echo $out_of_stock; ?></div>
                                <div class="kpi-subtitle">Unavailable</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="kpi-card danger">
                                <div class="kpi-icon"><i class='bx bx-time'></i></div>
                                <div class="kpi-label">Expiring Soon</div>
                                <div class="kpi-value"><?php echo $expiring_soon + $expired; ?></div>
                                <div class="kpi-subtitle">Next 30 days</div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Section -->
                    <div class="row">
                        <!-- Sales Chart -->
                        <div class="col-md-6">
                            <div class="chart-container">
                                <h5 class="chart-title">Sales Trend (Last 7 Days)</h5>
                                <canvas id="salesChart"></canvas>
                            </div>
                        </div>

                        <!-- Payment Method Chart -->
                        <div class="col-md-6">
                            <div class="chart-container">
                                <h5 class="chart-title">Payment Methods</h5>
                                <canvas id="paymentChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Top Sellers and Low Stock -->
                    <div class="row">
                        <!-- Top Sellers -->
                        <div class="col-md-6">
                            <div class="chart-container">
                                <h5 class="chart-title">Top 5 Best Sellers</h5>
                                <div class="table-container">
                                    <table class="table table-custom">
                                        <thead>
                                            <tr>
                                                <th>Medicine</th>
                                                <th>Qty Sold</th>
                                                <th>Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($top_sellers as $seller): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($seller['MedicineName']); ?></td>
                                                    <td><strong><?php echo $seller['total_qty']; ?></strong></td>
                                                    <td>₱<?php echo number_format($seller['total_revenue'], 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Low Stock Alerts -->
                        <div class="col-md-6">
                            <div class="chart-container">
                                <h5 class="chart-title">Low Stock Medicines (Top 8)</h5>
                                <div class="scroll-feed">
                                    <?php foreach ($low_stock_medicines as $med): ?>
                                        <div class="alert-item warning">
                                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($med['MedicineName']); ?></strong>
                                                    <br>
                                                    <span class="text-muted-custom">Stock: <?php echo $med['Stocks']; ?> | Price: ₱<?php echo number_format($med['Price'], 2); ?></span>
                                                </div>
                                                <span class="badge-custom status-low"><?php echo htmlspecialchars($med['Status']); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Transactions -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="table-container">
                                <h5 class="chart-title">Recent Transactions</h5>
                                <table class="table table-custom">
                                    <thead>
                                        <tr>
                                            <th>Transaction ID</th>
                                            <th>Date & Time</th>
                                            <th>Amount</th>
                                            <th>Payment Method</th>
                                            <th>Customer</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_transactions as $trans): ?>
                                            <tr>
                                                <td><strong>#<?php echo $trans['TransactionID']; ?></strong></td>
                                                <td><?php echo date('M d, Y H:i', strtotime($trans['TransactionDateTime'])); ?></td>
                                                <td><strong>₱<?php echo number_format($trans['TotalAmount'], 2); ?></strong></td>
                                                <td><span class="badge badge-info"><?php echo htmlspecialchars($trans['PaymentMethod']); ?></span></td>
                                                <td><?php echo htmlspecialchars($trans['CustomerName'] ?? 'Walk-in'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Branch Comparison (Admin Only) -->
                    <?php if ($user_role === 'Admin' && !empty($branch_comparison)): ?>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="table-container">
                                    <h5 class="chart-title">Branch Performance Comparison (Today)</h5>
                                    <table class="table table-custom">
                                        <thead>
                                            <tr>
                                                <th>Branch</th>
                                                <th>Total Sales</th>
                                                <th>Transactions</th>
                                                <th>Avg Transaction</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($branch_comparison as $comp): ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($comp['BranchName']); ?></strong></td>
                                                    <td>₱<?php echo number_format($comp['branch_sales'] ?? 0, 2); ?></td>
                                                    <td><?php echo $comp['trans_count'] ?? 0; ?></td>
                                                    <td>
                                                        <?php 
                                                            $avg = ($comp['trans_count'] > 0) ? ($comp['branch_sales'] / $comp['trans_count']) : 0;
                                                            echo '₱' . number_format($avg, 2);
                                                        ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.2.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.12.9/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/js/bootstrap.min.js"></script>

    <script>
        // Live Clock
        function updateClock() {
            const now = new Date();
            const timeStr = now.toLocaleTimeString('en-US', { hour12: false });
            const dateStr = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            
            document.getElementById('live-time').textContent = timeStr;
            document.getElementById('live-date').textContent = dateStr;
            document.getElementById('navbar-clock').textContent = timeStr;
        }
        updateClock();
        setInterval(updateClock, 1000);

        // Sales Chart Data
        const salesData = <?php echo json_encode($daily_sales_data); ?>;
        const salesLabels = salesData.map(d => new Date(d.sale_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
        const salesValues = salesData.map(d => parseFloat(d.daily_total) || 0);

        const salesChart = new Chart(document.getElementById('salesChart'), {
            type: 'line',
            data: {
                labels: salesLabels,
                datasets: [{
                    label: 'Daily Sales (₱)',
                    data: salesValues,
                    borderColor: '#7c3aed',
                    backgroundColor: 'rgba(124, 58, 237, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 6,
                    pointBackgroundColor: '#7c3aed',
                    pointBorderColor: 'white',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        // Payment Method Chart
        const paymentData = <?php echo json_encode($payment_methods); ?>;
        const paymentLabels = paymentData.map(p => p.PaymentMethod);
        const paymentValues = paymentData.map(p => p.count);

        const paymentChart = new Chart(document.getElementById('paymentChart'), {
            type: 'doughnut',
            data: {
                labels: paymentLabels,
                datasets: [{
                    data: paymentValues,
                    backgroundColor: ['#7c3aed', '#10b981', '#3b82f6'],
                    borderColor: 'white',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });

        function filterDashboard() {
            // Add filter functionality here
            console.log('Filter applied');
        }

        function exportToPDF() {
            alert('Export to PDF functionality will be implemented.');
            // Add PDF export logic here
        }
    </script>
</body>
</html>
