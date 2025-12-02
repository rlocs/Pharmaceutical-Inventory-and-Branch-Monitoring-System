<?php
// admin1/api/dashboard_api.php
// API endpoint for centralized admin dashboard analytics
session_start();
require_once __DIR__ . '/../../dbconnection.php';

// Start output buffering
ob_start();

// Configure error reporting
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/dashboard_api_errors.log');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

// Access Control - Only Admin users can access this
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SESSION['user_role'] !== 'Admin') {
    ob_clean();
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Admin role required.']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $response = [];

    // ========== TODAY'S SALES (All Branches) ==========
    $todaySalesSql = "SELECT
        COALESCE(SUM(TotalAmount), 0) as total_sales,
        COUNT(*) as transaction_count
        FROM SalesTransactions
        WHERE DATE(TransactionDateTime) = CURDATE()";

    $stmt = $conn->prepare($todaySalesSql);
    $stmt->execute();
    $todayData = $stmt->fetch(PDO::FETCH_ASSOC);

    $response['salesToday'] = '₱' . number_format($todayData['total_sales'], 2);
    $response['transactions'] = $todayData['transaction_count'] . ' transactions today';

    // ========== MONTHLY REVENUE (All Branches) ==========
    $monthlyRevenueSql = "SELECT
        COALESCE(SUM(TotalAmount), 0) as total_revenue
        FROM SalesTransactions
        WHERE MONTH(TransactionDateTime) = MONTH(CURDATE())
        AND YEAR(TransactionDateTime) = YEAR(CURDATE())";

    $stmt = $conn->prepare($monthlyRevenueSql);
    $stmt->execute();
    $monthlyData = $stmt->fetch(PDO::FETCH_ASSOC);

    $response['revenueMonth'] = '₱' . number_format($monthlyData['total_revenue'], 2);

    // ========== ALERTS COUNT (All Branches) ==========
    $alertsSql = "SELECT COUNT(*) as alert_count FROM (
        SELECT BranchInventoryID FROM BranchInventory
        WHERE (Stocks = 0 OR (Stocks > 0 AND Stocks <= 10) OR
               ExpiryDate < CURDATE() OR
               ExpiryDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY))
    ) as alerts";

    $stmt = $conn->prepare($alertsSql);
    $stmt->execute();
    $alertsData = $stmt->fetch(PDO::FETCH_ASSOC);

    $response['alerts'] = $alertsData['alert_count'];

    // ========== WEEKLY SALES (All Branches) ==========
    $weeklySalesSql = "SELECT
        COALESCE(SUM(TotalAmount), 0) as weekly_sales
        FROM SalesTransactions
        WHERE WEEK(TransactionDateTime) = WEEK(CURDATE())
        AND YEAR(TransactionDateTime) = YEAR(CURDATE())";

    $stmt = $conn->prepare($weeklySalesSql);
    $stmt->execute();
    $weeklyData = $stmt->fetch(PDO::FETCH_ASSOC);

    $response['weeklySales'] = '₱' . number_format($weeklyData['weekly_sales'], 2);

    // ========== INVENTORY VALUE (All Branches) ==========
    $inventoryValueSql = "SELECT
        COALESCE(SUM(bi.Stocks * bi.Price), 0) as total_value
        FROM BranchInventory bi";

    $stmt = $conn->prepare($inventoryValueSql);
    $stmt->execute();
    $inventoryData = $stmt->fetch(PDO::FETCH_ASSOC);

    $response['inventoryValue'] = '₱' . number_format($inventoryData['total_value'], 2);

    // ========== PAYMENT METHODS (Last 30 days, All Branches) ==========
    $paymentSql = "SELECT
        PaymentMethod,
        COUNT(*) as count,
        SUM(TotalAmount) as total
        FROM SalesTransactions
        WHERE TransactionDateTime >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY PaymentMethod";

    $stmt = $conn->prepare($paymentSql);
    $stmt->execute();
    $paymentData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['paymentStats'] = [
        'cash' => ['amt' => '₱0.00', 'count' => 0],
        'card' => ['amt' => '₱0.00', 'count' => 0],
        'credit' => ['amt' => '₱0.00', 'count' => 0]
    ];

    foreach ($paymentData as $payment) {
        $method = strtolower($payment['PaymentMethod']);
        if (isset($response['paymentStats'][$method])) {
            $response['paymentStats'][$method]['amt'] = '₱' . number_format($payment['total'], 2);
            $response['paymentStats'][$method]['count'] = $payment['count'];
        }
    }

    // ========== INVENTORY STATUS (All Branches) ==========
    $inventoryStatusSql = "SELECT
        SUM(CASE WHEN Stocks > 10 AND ExpiryDate > CURDATE() THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN Stocks > 0 AND Stocks <= 10 THEN 1 ELSE 0 END) as low,
        SUM(CASE WHEN Stocks = 0 THEN 1 ELSE 0 END) as out_of_stock,
        SUM(CASE WHEN ExpiryDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as expiring_soon,
        SUM(CASE WHEN ExpiryDate < CURDATE() THEN 1 ELSE 0 END) as expired
        FROM BranchInventory";

    $stmt = $conn->prepare($inventoryStatusSql);
    $stmt->execute();
    $inventoryStatus = $stmt->fetch(PDO::FETCH_ASSOC);

    $response['inventory'] = [
        'active' => (int)$inventoryStatus['active'],
        'low' => (int)$inventoryStatus['low'],
        'out' => (int)$inventoryStatus['out_of_stock'],
        'expiring' => (int)$inventoryStatus['expiring_soon'],
        'expired' => (int)$inventoryStatus['expired']
    ];

    // ========== TOP 10 BESTSELLING MEDICINES (All Branches, Last 30 days) ==========
    $topMedicinesSql = "SELECT
        m.MedicineName,
        SUM(ti.Quantity) as total_qty
        FROM TransactionItems ti
        JOIN SalesTransactions st ON ti.TransactionID = st.TransactionID
        JOIN BranchInventory bi ON ti.BranchInventoryID = bi.BranchInventoryID
        JOIN medicines m ON bi.MedicineID = m.MedicineID
        WHERE st.TransactionDateTime >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY m.MedicineID, m.MedicineName
        ORDER BY total_qty DESC
        LIMIT 10";

    $stmt = $conn->prepare($topMedicinesSql);
    $stmt->execute();
    $topMedicines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['topMedicines'] = array_map(function($med) {
        return [
            'name' => $med['MedicineName'],
            'qty' => (int)$med['total_qty']
        ];
    }, $topMedicines);

    // ========== WEEKLY SALES TREND (Last 7 days, All Branches) ==========
    $weeklyTrendSql = "SELECT
        DATE(TransactionDateTime) as sale_date,
        DAYNAME(TransactionDateTime) as day_name,
        SUM(TotalAmount) as daily_total
        FROM SalesTransactions
        WHERE TransactionDateTime >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(TransactionDateTime), DAYNAME(TransactionDateTime)
        ORDER BY sale_date ASC";

    $stmt = $conn->prepare($weeklyTrendSql);
    $stmt->execute();
    $weeklyTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Map to the expected format (Mon, Tue, etc.)
    $dayMap = [
        'Monday' => 'Mon',
        'Tuesday' => 'Tue',
        'Wednesday' => 'Wed',
        'Thursday' => 'Thu',
        'Friday' => 'Fri',
        'Saturday' => 'Sat',
        'Sunday' => 'Sun'
    ];

    $response['weeklyTrend'] = array_map(function($day) use ($dayMap) {
        return [
            'day' => $dayMap[$day['day_name']] ?? $day['day_name'],
            'sales' => (float)$day['daily_total']
        ];
    }, $weeklyTrend);

    // ========== SALES BY PRODUCT CATEGORY (Last 30 days, All Branches) ==========
    $categorySalesSql = "SELECT
        COALESCE(m.CustomCategory, c.CategoryName) as category,
        SUM(ti.Subtotal) as total_sales
        FROM TransactionItems ti
        JOIN SalesTransactions st ON ti.TransactionID = st.TransactionID
        JOIN BranchInventory bi ON ti.BranchInventoryID = bi.BranchInventoryID
        JOIN medicines m ON bi.MedicineID = m.MedicineID
        LEFT JOIN Categories c ON m.CategoryID = c.CategoryID
        WHERE st.TransactionDateTime >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY COALESCE(m.CustomCategory, c.CategoryName)
        ORDER BY total_sales DESC
        LIMIT 5";

    $stmt = $conn->prepare($categorySalesSql);
    $stmt->execute();
    $categorySales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Map categories to colors and format
    $categoryColors = [
        'bg-blue-500',
        'bg-green-500',
        'bg-yellow-500',
        'bg-purple-500',
        'bg-red-500'
    ];

    $response['categorySales'] = array_map(function($cat, $index) use ($categoryColors) {
        return [
            'label' => $cat['category'] ?: 'Other',
            'value' => (float)$cat['total_sales'],
            'color' => $categoryColors[$index % count($categoryColors)]
        ];
    }, $categorySales, array_keys($categorySales));

    // Clean buffer and send response
    $buf = ob_get_contents();
    if (trim($buf) !== '') {
        error_log("Unexpected output in dashboard_api.php: " . $buf);
    }
    ob_clean();

    echo json_encode([
        'success' => true,
        'data' => $response
    ]);

} catch (Exception $e) {
    error_log("Exception in dashboard_api.php: " . $e->getMessage());
    $buf = ob_get_contents();
    if (trim($buf) !== '') {
        error_log("Unexpected output during exception: " . $buf);
    }
    ob_clean();
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
