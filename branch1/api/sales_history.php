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

    // 1. Fetch Last 50 Transactions (Newest First)
    // Adjust limit as needed or add pagination later
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
            ORDER BY t.TransactionDateTime DESC
            LIMIT 50";
            
    $stmt = $conn->prepare($sql);
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

    // Before sending JSON, check for any accidental output in the buffer and log it
    $buf = ob_get_contents();
    if (trim($buf) !== '') {
        error_log("Unexpected output before JSON in sales_history.php: " . $buf);
    }
    // Clean buffer and send JSON
    ob_clean();
    echo json_encode(['success' => true, 'data' => $transactions]);
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