<?php
// pos_api.php: Backend API for POS sales transactions management

session_start();
require_once '../../dbconnection.php';

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = new Database();
    $conn = $db->getConnection();

    if ($method === 'POST') {
        // Handle creating a new sales transaction with items

        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || !isset($input['items']) || !isset($input['total_amount']) || !isset($input['payment_method'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid input']);
            exit;
        }

        $items = $input['items'];
        $total_amount = $input['total_amount'];
        $payment_method = $input['payment_method'];
        $customer_name = isset($input['customer_name']) ? trim($input['customer_name']) : null;

        // Validate payment method
        $valid_payment_methods = ['Cash', 'Card', 'Credit'];
        if (!in_array($payment_method, $valid_payment_methods)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid payment method']);
            exit;
        }

        // Start transaction
        $conn->beginTransaction();

        // Insert into SalesTransactions
        $sqlHeader = "INSERT INTO SalesTransactions (BranchID, UserID, TotalAmount, PaymentMethod, CustomerName)
                      VALUES (:branch_id, :user_id, :total_amount, :payment_method, :customer_name)";
        $stmtHeader = $conn->prepare($sqlHeader);
        $stmtHeader->bindValue(':branch_id', $_SESSION['branch_id'], PDO::PARAM_INT);
        $stmtHeader->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmtHeader->bindValue(':total_amount', $total_amount);
        $stmtHeader->bindValue(':payment_method', $payment_method);
        $stmtHeader->bindValue(':customer_name', $customer_name);
        $stmtHeader->execute();

        $transactionID = $conn->lastInsertId();

        // Prepare insert for TransactionItems
        $sqlItem = "INSERT INTO TransactionItems (TransactionID, BranchInventoryID, MedicineNameSnapshot, Quantity, PricePerUnit, Subtotal)
                    VALUES (:transaction_id, :branch_inventory_id, :med_name_snapshot, :qty, :price_unit, :subtotal)";
        $stmtItem = $conn->prepare($sqlItem);

        foreach ($items as $item) {
            // Validate item fields
            if (!isset($item['id'], $item['name'], $item['qty'], $item['price'])) {
                $conn->rollBack();
                http_response_code(400);
                echo json_encode(['error' => 'Invalid item data']);
                exit;
            }
            $branchInventoryID = $item['id'];
            $medicineNameSnapshot = $item['name'];
            $qty = (int)$item['qty'];
            $pricePerUnit = (float)$item['price'];
            $subtotal = $qty * $pricePerUnit;

            // Check stock availability for each item
            $stockCheckSql = "SELECT Stocks FROM BranchInventory WHERE BranchInventoryID = :branch_inventory_id FOR UPDATE";
            $stockCheckStmt = $conn->prepare($stockCheckSql);
            $stockCheckStmt->bindValue(':branch_inventory_id', $branchInventoryID, PDO::PARAM_INT);
            $stockCheckStmt->execute();
            $stockRow = $stockCheckStmt->fetch(PDO::FETCH_ASSOC);
            if (!$stockRow || $stockRow['Stocks'] < $qty) {
                $conn->rollBack();
                http_response_code(400);
                echo json_encode(['error' => "Insufficient stock for medicine ID $branchInventoryID"]);
                exit;
            }

            // Deduct the stock for this item
            $updateStockSql = "UPDATE BranchInventory SET Stocks = Stocks - :qty WHERE BranchInventoryID = :branch_inventory_id";
            $updateStockStmt = $conn->prepare($updateStockSql);
            $updateStockStmt->bindValue(':qty', $qty, PDO::PARAM_INT);
            $updateStockStmt->bindValue(':branch_inventory_id', $branchInventoryID, PDO::PARAM_INT);
            $updateStockStmt->execute();

            $stmtItem->bindValue(':transaction_id', $transactionID, PDO::PARAM_INT);
            $stmtItem->bindValue(':branch_inventory_id', $branchInventoryID, PDO::PARAM_INT);
            $stmtItem->bindValue(':med_name_snapshot', $medicineNameSnapshot);
            $stmtItem->bindValue(':qty', $qty, PDO::PARAM_INT);
            $stmtItem->bindValue(':price_unit', $pricePerUnit);
            $stmtItem->bindValue(':subtotal', $subtotal);
            $stmtItem->execute();
        }

        // Commit transaction
        $conn->commit();

        echo json_encode([
            'success' => true,
            'transaction_id' => $transactionID,
            'message' => 'Sales transaction recorded successfully'
        ]);

    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
    }
} catch (Exception $e) {
    if ($conn && $conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
}
?>
