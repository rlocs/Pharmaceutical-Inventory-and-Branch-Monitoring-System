<?php
// api/sales_history.php
session_start();
require_once '../dbconnection.php';

header('Content-Type: application/json');

// Access Control
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
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
                t.TransactionDate,
                t.TotalAmount,
                t.PaymentAmount,
                t.ChangeAmount,
                CONCAT(u.FirstName, ' ', u.LastName) as CashierName,
                b.BranchName
            FROM SalesTransactions t
            LEFT JOIN users u ON t.UserID = u.UserID
            LEFT JOIN branches b ON t.BranchID = b.BranchID
            ORDER BY t.TransactionDate DESC
            LIMIT 50";
            
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Attach Items to each transaction
    // This is a simple loop approach. For millions of records, we would use a JOIN, 
    // but for a POS history view, this is cleaner to format for JSON.
    foreach ($transactions as &$trans) {
        $sqlItems = "SELECT 
                        MedicineName as name,
                        Quantity as qty,
                        PricePerUnit as price,
                        Subtotal as subtotal
                     FROM SalesTransactionItems
                     WHERE TransactionID = :tid";
        
        $stmtItems = $conn->prepare($sqlItems);
        $stmtItems->execute([':tid' => $trans['TransactionID']]);
        $trans['items'] = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
        
        // Format numbers for JS
        $trans['total_amount'] = (float)$trans['TotalAmount'];
        $trans['payment_amount'] = (float)$trans['PaymentAmount'];
        $trans['change_amount'] = (float)$trans['ChangeAmount'];
        $trans['transaction_id'] = $trans['TransactionID'];
        $trans['cashier'] = $trans['CashierName'];
        $trans['branch'] = $trans['BranchName'];
        $trans['date'] = $trans['TransactionDate'];
    }

    echo json_encode(['success' => true, 'data' => $transactions]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>