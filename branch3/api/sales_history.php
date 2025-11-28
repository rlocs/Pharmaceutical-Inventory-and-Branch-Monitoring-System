<?php
// api/sales_history.php
// Use output buffering and logging to ensure JSON responses are not corrupted by warnings/HTML
session_start();
// dbconnection.php lives two levels up from branch1/api (project root)
require_once __DIR__ . '/../../dbconnection.php';

// Start output buffering to capture any accidental output
ob_start();

// Configure error reporting: do not display errors to the client, log them instead
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
// Write errors to a log file next to this script (writeable by webserver)
ini_set('error_log', __DIR__ . '/sales_history_errors.log');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

// Access Control
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    // Clean any buffered output and return a proper JSON response
    $buf = ob_get_clean();
    if (trim($buf) !== '') {
        error_log("Unexpected output before JSON (unauthorized): " . $buf);
    }
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Get branch ID from parameter (for admin access to different branches) or session
    $userBranchID = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : ($_SESSION['branch_id'] ?? null);
    if (!$userBranchID) {
        ob_clean();
        http_response_code(403);
        echo json_encode(['error' => 'Branch ID not found']);
        exit;
    }

    // Get date filter parameters first
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;

    // Get pagination parameters - reset to page 1 if date filters are applied
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    if ($startDate && $endDate) {
        $page = 1; // Force page 1 when date filters are applied
    }
    $limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 20;
    $offset = ($page - 1) * $limit;

    // Build WHERE clause for date filtering
    $whereClause = "WHERE t.BranchID = :branch_id";
    $params = [':branch_id' => $userBranchID];

    if ($startDate && $endDate) {
        $whereClause .= " AND DATE(t.TransactionDateTime) BETWEEN :start_date AND :end_date";
        $params[':start_date'] = $startDate;
        $params[':end_date'] = $endDate;
    }

    // Get total count for pagination
    $countSql = "SELECT COUNT(*) as total FROM SalesTransactions t $whereClause";
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalRecords / $limit);

    // 1. Fetch paginated transactions for the user's branch (Newest First)
    $sql = "SELECT
                t.TransactionID,
                t.TransactionDateTime,
                t.TotalAmount,
                t.PaymentMethod,
                t.TotalTaxAmount,
                t.TotalDiscountAmount,
                CONCAT(a.FirstName, ' ', a.LastName) as CashierName,
                b.BranchName
            FROM SalesTransactions t
            LEFT JOIN Accounts a ON t.UserID = a.UserID
            LEFT JOIN Branches b ON t.BranchID = b.BranchID
            $whereClause
            ORDER BY t.TransactionDateTime DESC
            LIMIT :limit OFFSET :offset";

    $stmt = $conn->prepare($sql);
    $params[':limit'] = $limit;
    $params[':offset'] = $offset;

    foreach ($params as $key => $value) {
        if ($key === ':limit' || $key === ':offset' || $key === ':branch_id') {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
    }
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Attach Items to each transaction
    // This is a simple loop approach. For millions of records, we would use a JOIN, 
    // but for a POS history view, this is cleaner to format for JSON.
    foreach ($transactions as &$trans) {
          $sqlItems = "SELECT 
                                MedicineNameSnapshot as name,
                                Quantity as qty,
                                PricePerUnit as price,
                                Subtotal as subtotal
                            FROM TransactionItems
                            WHERE TransactionID = :tid";
        
        $stmtItems = $conn->prepare($sqlItems);
        $stmtItems->execute([':tid' => $trans['TransactionID']]);
        $trans['items'] = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
        
        // Normalize/format fields for JS consumer
        $trans['TransactionID'] = (int)$trans['TransactionID'];
        $trans['TransactionDateTime'] = $trans['TransactionDateTime'];
        $trans['TotalAmount'] = (float)$trans['TotalAmount'];
        $trans['PaymentMethod'] = $trans['PaymentMethod'];
        $trans['TotalTaxAmount'] = isset($trans['TotalTaxAmount']) ? (float)$trans['TotalTaxAmount'] : 0.0;
        $trans['TotalDiscountAmount'] = isset($trans['TotalDiscountAmount']) ? (float)$trans['TotalDiscountAmount'] : 0.0;
        $trans['CashierName'] = $trans['CashierName'];
        $trans['BranchName'] = $trans['BranchName'];
    }

    // Calculate summary if requested
    $summary = null;
    if (isset($_GET['include_summary']) && $_GET['include_summary'] == '1') {
        $summarySql = "SELECT
                            COUNT(*) as total_transactions,
                            SUM(TotalAmount) as total_sales,
                            SUM(TotalTaxAmount) as total_tax,
                            SUM(TotalDiscountAmount) as total_discount
                        FROM SalesTransactions t
                        $whereClause";

        $summaryStmt = $conn->prepare($summarySql);
        // Use only the relevant parameters for summary (exclude pagination params)
        $summaryParams = [':branch_id' => $userBranchID];
        if ($startDate && $endDate) {
            $summaryParams[':start_date'] = $startDate;
            $summaryParams[':end_date'] = $endDate;
        }
        $summaryStmt->execute($summaryParams);
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

        // Normalize
        $summary['total_transactions'] = (int)$summary['total_transactions'];
        $summary['total_sales'] = (float)$summary['total_sales'];
        $summary['total_tax'] = (float)$summary['total_tax'];
        $summary['total_discount'] = (float)$summary['total_discount'];
    }

    // Before sending JSON, check for any accidental output in the buffer and log it
    $buf = ob_get_contents();
    if (trim($buf) !== '') {
        error_log("Unexpected output before JSON in sales_history.php: " . $buf);
    }
    // Clean buffer and send JSON
    ob_clean();
    $response = [
        'success' => true,
        'data' => $transactions,
        'pagination' => [
            'page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords,
            'limit' => $limit
        ]
    ];
    if ($summary) {
        $response['summary'] = $summary;
    }
    echo json_encode($response);
    exit;

} catch (Exception $e) {
    // Log exception to file and return a JSON error (ensure buffer is clean)
    error_log("Exception in sales_history.php: " . $e->getMessage());
    $buf = ob_get_contents();
    if (trim($buf) !== '') {
        error_log("Unexpected output during exception: " . $buf);
    }
    ob_clean();
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
    exit;
}

// Intentionally omit PHP closing tag to avoid accidental trailing whitespace