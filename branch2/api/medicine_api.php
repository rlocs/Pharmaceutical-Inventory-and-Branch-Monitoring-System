<?php
// medicine_api.php - API for medicine inventory management
session_start();

// ------------------------------------------------------------------
// ACCESS CONTROL CHECK
// ------------------------------------------------------------------

// 1. Check if the user is not logged in (allow for testing)
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    // For testing purposes, allow access without authentication
    $_SESSION["loggedin"] = true;
    $_SESSION["user_role"] = "Staff";
    $_SESSION["branch_id"] = 2;
    $_SESSION["user_id"] =2;
}

// 2. Check Role: Only Staff or Admin can access this API.
if ($_SESSION["user_role"] !== 'Staff' && $_SESSION["user_role"] !== 'Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

// 3. Check Branch (Crucial for Staff access). This API is for Branch 1.
// Admins are not restricted by BranchID, but Staff MUST be from Branch 1.
$required_branch_id = 2;
if ($required_branch_id > 0 && $_SESSION["user_role"] === 'Staff' && $_SESSION["branch_id"] != $required_branch_id) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Branch access denied']);
    exit;
}

// ------------------------------------------------------------------
// DATABASE CONNECTION
// ------------------------------------------------------------------

require_once '../../dbconnection.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

header('Content-Type: application/json');

// Get the action from GET or POST
$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        case 'get_medicines':
            getMedicines($pdo);
            break;
        case 'get_medicine':
            getMedicine($pdo);
            break;
        case 'get_categories':
            getCategories($pdo);
            break;
        case 'add_medicine':
            addMedicine($pdo);
            break;
        case 'update_medicine':
            updateMedicine($pdo);
            break;
        case 'delete_medicine':
            deleteMedicine($pdo);
            break;
        case 'get_alerts':
            getAlerts($pdo);
            break;
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// Helper function to create notifications directly in the database (avoids unreliable curl)
function createNotification($pdo, $type, $category, $title, $message, $link = '', $userId = null) {
    try {
        if ($userId === null) {
            $userId = $_SESSION['user_id'] ?? null;
        }
        $branchId = $_SESSION['branch_id'] ?? 1;
        $stmt = $pdo->prepare("INSERT INTO Notifications (UserID, BranchID, Type, Category, Title, Message, Link, IsRead) VALUES (?, ?, ?, ?, ?, ?, ?, 0)");
        $stmt->execute([$userId, $branchId, $type, $category, $title, $message, $link]);
        return true;
    } catch (Exception $e) {
        error_log("Notification creation failed: " . $e->getMessage());
        return false;
    }
}

function getCategories($pdo) {
    $sql = "SELECT CategoryID, CategoryName FROM Categories ORDER BY CategoryName";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'categories' => $categories
    ]);
}

function getMedicine($pdo) {
    $branch_id = $_SESSION['branch_id'];
    $medicine_id = $_GET['medicineId'] ?? $_POST['medicineId'] ?? '';
    
    if (empty($medicine_id)) {
        echo json_encode(['success' => false, 'error' => 'Medicine ID required']);
        return;
    }
    
    try {
        // Get medicine details with category
        $sql = "SELECT 
                    bi.BranchInventoryID,
                    m.MedicineID,
                    m.MedicineName,
                    m.CategoryID,
                    c.CategoryName,
                    m.Form,
                    m.Unit,
                    bi.Stocks,
                    bi.Price,
                    bi.ExpiryDate
                FROM BranchInventory bi
                JOIN medicines m ON bi.MedicineID = m.MedicineID
                LEFT JOIN Categories c ON m.CategoryID = c.CategoryID
                WHERE bi.BranchInventoryID = ? AND bi.BranchID = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$medicine_id, $branch_id]);
        $medicine = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$medicine) {
            echo json_encode(['success' => false, 'error' => 'Medicine not found']);
            return;
        }
        
        echo json_encode([
            'success' => true,
            'medicine' => $medicine
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getMedicines($pdo) {
    $branch_id = $_SESSION['branch_id'];
    $category_filter = $_GET['category'] ?? '';
    $page = (int)($_GET['page'] ?? 1);
    $limit = 10; // Items per page
    $offset = ($page - 1) * $limit;

    // Build query
    $where = "bi.BranchID = ?";
    $params = [$branch_id];

    if (!empty($category_filter)) {
        // Category filter is now a category name, not ID
        $where .= " AND c.CategoryName = ?";
        $params[] = $category_filter;
    }

    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM BranchInventory bi
                  JOIN medicines m ON bi.MedicineID = m.MedicineID
                  LEFT JOIN Categories c ON m.CategoryID = c.CategoryID
                  WHERE $where";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];

    // Get medicines with pagination and calculated status (can have multiple values)
    $sql = "SELECT bi.BranchInventoryID, m.MedicineName, c.CategoryName AS Category, m.Form, m.Unit,
                   bi.Stocks, bi.Price, bi.ExpiryDate,
                   CASE
                       WHEN bi.ExpiryDate < CURDATE() AND bi.Stocks = 0 THEN 'Expired, Out of Stock'
                       WHEN bi.ExpiryDate < CURDATE() AND bi.Stocks > 0 AND bi.Stocks <= 10 THEN 'Expired, Low Stock'
                       WHEN bi.ExpiryDate < CURDATE() AND bi.ExpiryDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Expired, Expiring Soon'
                       WHEN bi.Stocks = 0 AND bi.ExpiryDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Out of Stock, Expiring Soon'
                       WHEN bi.Stocks > 0 AND bi.Stocks <= 10 AND bi.ExpiryDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Low Stock, Expiring Soon'
                       WHEN bi.ExpiryDate < CURDATE() THEN 'Expired'
                       WHEN bi.Stocks = 0 THEN 'Out of Stock'
                       WHEN bi.Stocks > 0 AND bi.Stocks <= 10 THEN 'Low Stock'
                       WHEN bi.ExpiryDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Expiring Soon'
                       ELSE 'Active'
                   END AS Status
            FROM BranchInventory bi
            JOIN medicines m ON bi.MedicineID = m.MedicineID
            LEFT JOIN Categories c ON m.CategoryID = c.CategoryID
            WHERE $where
            ORDER BY m.MedicineName
            LIMIT $limit OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'medicines' => $medicines,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => ceil($total / $limit)
    ]);
}

function addMedicine($pdo) {
    // Staff and Admin can add medicines
    if ($_SESSION["user_role"] !== 'Admin' && $_SESSION["user_role"] !== 'Staff') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    $required = ['medicineName', 'category', 'form', 'unit', 'stocks', 'price', 'expiryDate'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
            return;
        }
    }

    $pdo->beginTransaction();

    try {
        // Handle category - category is now always a category name (not ID)
        $category_id = null;
        if (isset($data['category']) && !empty($data['category'])) {
            $category_name = trim($data['category']);
            
            // Find existing category or create new one
            $stmt = $pdo->prepare("SELECT CategoryID FROM Categories WHERE CategoryName = ?");
            $stmt->execute([$category_name]);
            $category = $stmt->fetch();
            
            if ($category) {
                $category_id = $category['CategoryID'];
            } else {
                // Create new category
                $stmt = $pdo->prepare("INSERT INTO Categories (CategoryName) VALUES (?)");
                $stmt->execute([$category_name]);
                $category_id = $pdo->lastInsertId();
            }
        }
        
        if ($category_id === null) {
            throw new Exception('Category is required');
        }

        // Check if medicine already exists globally
        $stmt = $pdo->prepare("SELECT MedicineID FROM medicines WHERE MedicineName = ?");
        $stmt->execute([$data['medicineName']]);
        $existing = $stmt->fetch();

        if ($existing) {
            $medicine_id = $existing['MedicineID'];
        } else {
            // Add to global catalog
            $stmt = $pdo->prepare("INSERT INTO medicines (MedicineName, CategoryID, Form, Unit) VALUES (?, ?, ?, ?)");
            $stmt->execute([$data['medicineName'], $category_id, $data['form'], $data['unit']]);
            $medicine_id = $pdo->lastInsertId();
        }

        // Check if this medicine already exists in this branch's inventory
        $stmt = $pdo->prepare("SELECT BranchInventoryID FROM BranchInventory WHERE BranchID = ? AND MedicineID = ?");
        $stmt->execute([$_SESSION['branch_id'], $medicine_id]);
        $existing_inventory = $stmt->fetch();

        if ($existing_inventory) {
            // Medicine already exists in branch inventory - update it instead
            $stmt = $pdo->prepare("UPDATE BranchInventory SET 
                                  Stocks = Stocks + ?, 
                                  Price = ?, 
                                  ExpiryDate = ?, 
                                  UpdatedBy = ?,
                                  UpdatedAt = CURRENT_TIMESTAMP
                                  WHERE BranchInventoryID = ?");
            $stmt->execute([
                $data['stocks'], // Add to existing stock
                $data['price'],
                $data['expiryDate'],
                $_SESSION['user_id'],
                $existing_inventory['BranchInventoryID']
            ]);
        } else {
            // Add new entry to branch inventory with audit fields
            $stmt = $pdo->prepare("INSERT INTO BranchInventory (BranchID, MedicineID, Stocks, Price, ExpiryDate, CreatedBy)
                                  VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_SESSION['branch_id'],
                $medicine_id,
                $data['stocks'],
                $data['price'],
                $data['expiryDate'],
                $_SESSION['user_id']
            ]);
        }

        $pdo->commit();

        // Create notification: Medicine added BEFORE response
        try {
            $medicineName = $data['medicineName'];
            createNotification($pdo, 'med', 'Add', 'Medicine added', $medicineName . ' has been added to inventory', 'med_inventory.php', $_SESSION['user_id'] ?? null);
        } catch (Throwable $e) { 
            error_log("Add notification error: " . $e->getMessage());
        }

        // Create inventory alerts based on new values BEFORE response
        try {
            $stocks = (int)$data['stocks'];
            $expiry = $data['expiryDate'];
            $medicineNameLocal = $data['medicineName'];

            $statusList = [];
            $today = new DateTime('today');
            $expiryDt = new DateTime($expiry);
            if ($expiryDt < $today) $statusList[] = 'Expired';
            $soon = (clone $today)->modify('+30 days');
            if ($expiryDt >= $today && $expiryDt <= $soon) $statusList[] = 'Expiring Soon';
            if ($stocks === 0) $statusList[] = 'Out of Stock';
            if ($stocks > 0 && $stocks <= 10) $statusList[] = 'Low Stock';

            foreach ($statusList as $status) {
                $msg = ($status === 'Expiring Soon' || $status === 'Expired') ? ('Expiry: ' . $expiry) : ('Current stock: ' . $stocks);
                createNotification($pdo, 'inventory', $status, $status . ': ' . $medicineNameLocal, $msg, 'med_inventory.php', $_SESSION['user_id'] ?? null);
            }
        } catch (Throwable $e) { 
            error_log("Inventory alert notification error: " . $e->getMessage());
        }

        echo json_encode(['success' => true, 'message' => 'Medicine added successfully']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function updateMedicine($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['medicineId'])) {
        echo json_encode(['success' => false, 'error' => 'Medicine ID required']);
        return;
    }

    $pdo->beginTransaction();

    try {
        // Handle category - category is now always a category name (not ID)
        $category_id = null;
        if (isset($data['category']) && !empty($data['category'])) {
            $category_name = trim($data['category']);
            
            // Find existing category or create new one
            $stmt = $pdo->prepare("SELECT CategoryID FROM Categories WHERE CategoryName = ?");
            $stmt->execute([$category_name]);
            $category = $stmt->fetch();
            
            if ($category) {
                $category_id = $category['CategoryID'];
            } else {
                // Create new category
                $stmt = $pdo->prepare("INSERT INTO Categories (CategoryName) VALUES (?)");
                $stmt->execute([$category_name]);
                $category_id = $pdo->lastInsertId();
            }
        }
        
        if ($category_id === null) {
            throw new Exception('Category is required');
        }

        // Update branch inventory with audit fields
        $stmt = $pdo->prepare("UPDATE BranchInventory SET
                              Stocks = ?, Price = ?, ExpiryDate = ?, UpdatedBy = ?
                              WHERE BranchInventoryID = ? AND BranchID = ?");
        $stmt->execute([
            $data['stocks'],
            $data['price'],
            $data['expiryDate'],
            $_SESSION['user_id'],
            $data['medicineId'],
            $_SESSION['branch_id']
        ]);

        // Update global catalog if name changed
        if (isset($data['medicineName']) && $category_id !== null) {
            $stmt = $pdo->prepare("UPDATE medicines m
                                  JOIN BranchInventory bi ON m.MedicineID = bi.MedicineID
                                  SET m.MedicineName = ?, m.CategoryID = ?, m.Form = ?, m.Unit = ?
                                  WHERE bi.BranchInventoryID = ?");
            $stmt->execute([
                $data['medicineName'],
                $category_id,
                $data['form'],
                $data['unit'],
                $data['medicineId']
            ]);
        }

        $pdo->commit();

        // Create notification: Medicine updated BEFORE response
        try {
            $medicineName = $data['medicineName'] ?? '';
            createNotification($pdo, 'med', 'Edit', 'Medicine updated', ($medicineName ? ($medicineName . ' was updated') : 'A medicine was updated'), 'med_inventory.php', $_SESSION['user_id'] ?? null);
        } catch (Throwable $e) { 
            error_log("Update notification error: " . $e->getMessage());
        }

        // Create inventory alerts based on new values BEFORE response (fallback to DB if payload fields missing)
        try {
            $stocks = isset($data['stocks']) ? (int)$data['stocks'] : null;
            $expiry = $data['expiryDate'] ?? null;
            $medicineNameLocal = $medicineName ?? '';

            if ($stocks === null || empty($expiry) || empty($medicineNameLocal)) {
                $stmtChk = $pdo->prepare("SELECT bi.Stocks, bi.ExpiryDate, m.MedicineName
                    FROM BranchInventory bi
                    JOIN medicines m ON m.MedicineID = bi.MedicineID
                    WHERE bi.BranchInventoryID = ? AND bi.BranchID = ?");
                $stmtChk->execute([$data['medicineId'], $_SESSION['branch_id']]);
                $row = $stmtChk->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    if ($stocks === null) $stocks = (int)$row['Stocks'];
                    if (empty($expiry)) $expiry = $row['ExpiryDate'];
                    if (empty($medicineNameLocal)) $medicineNameLocal = $row['MedicineName'];
                }
            }

            if ($stocks !== null && !empty($expiry)) {
                $statusList = [];
                $today = new DateTime('today');
                $expiryDt = new DateTime($expiry);
                if ($expiryDt < $today) $statusList[] = 'Expired';
                $soon = (clone $today)->modify('+30 days');
                if ($expiryDt >= $today && $expiryDt <= $soon) $statusList[] = 'Expiring Soon';
                if ($stocks === 0) $statusList[] = 'Out of Stock';
                if ($stocks > 0 && $stocks <= 10) $statusList[] = 'Low Stock';

                foreach ($statusList as $status) {
                    $msg = ($status === 'Expiring Soon' || $status === 'Expired') ? ('Expiry: ' . $expiry) : ('Current stock: ' . $stocks);
                    createNotification($pdo, 'inventory', $status, $status . ($medicineNameLocal ? (': ' . $medicineNameLocal) : ''), $msg, 'med_inventory.php', $_SESSION['user_id'] ?? null);
                }
            }
        } catch (Throwable $e) { 
            error_log("Inventory alert on update error: " . $e->getMessage());
        }

        echo json_encode(['success' => true, 'message' => 'Medicine updated successfully']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function deleteMedicine($pdo) {
    // Staff and Admin can delete medicines
    if ($_SESSION["user_role"] !== 'Admin' && $_SESSION["user_role"] !== 'Staff') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $medicine_id = $data['medicineId'] ?? $data['medicine_id'] ?? $_POST['medicine_id'] ?? '';

    if (empty($medicine_id)) {
        echo json_encode(['success' => false, 'error' => 'Medicine ID required']);
        return;
    }

    try {
        // Delete from branch inventory only (keep global catalog)
        $stmt = $pdo->prepare("DELETE FROM BranchInventory WHERE BranchInventoryID = ? AND BranchID = ?");
        $stmt->execute([$medicine_id, $_SESSION['branch_id']]);

        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'error' => 'Medicine not found or already deleted']);
            return;
        }

        // Create notification: Medicine deleted BEFORE response
        try {
            createNotification($pdo, 'med', 'Delete', 'Medicine deleted', 'A medicine was removed from inventory', 'med_inventory.php', $_SESSION['user_id'] ?? null);
        } catch (Throwable $e) { 
            error_log("Delete notification error: " . $e->getMessage());
        }

        echo json_encode(['success' => true, 'message' => 'Medicine removed from inventory']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getAlerts($pdo) {
    $branch_id = $_SESSION['branch_id'];

    // Compute non-exclusive flags and a combined status string per medicine
    $sql = "SELECT 
                m.MedicineName,
                bi.Stocks,
                bi.ExpiryDate,
                (bi.ExpiryDate < CURDATE()) AS isExpired,
                (bi.ExpiryDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)) AS isExpiringSoon,
                (bi.Stocks = 0) AS isOutOfStock,
                (bi.Stocks > 0 AND bi.Stocks <= 10) AS isLowStock
            FROM BranchInventory bi
            JOIN medicines m ON bi.MedicineID = m.MedicineID
            WHERE bi.BranchID = ?
              AND (
                bi.ExpiryDate < CURDATE() OR
                bi.ExpiryDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) OR
                bi.Stocks = 0 OR
                (bi.Stocks > 0 AND bi.Stocks <= 10)
              )
            ORDER BY m.MedicineName";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$branch_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $grouped = [
        'expiringSoon' => [],
        'lowStock' => [],
        'outOfStock' => [],
        'expired' => []
    ];

    $items = [];

    foreach ($rows as $r) {
        $flags = [];
        if (!empty($r['isExpired'])) $flags[] = 'Expired';
        if (!empty($r['isExpiringSoon'])) $flags[] = 'Expiring Soon';
        if (!empty($r['isOutOfStock'])) $flags[] = 'Out of Stock';
        if (!empty($r['isLowStock'])) $flags[] = 'Low Stock';

        // Build combined status string in priority order
        $priority = ['Expired', 'Expiring Soon', 'Out of Stock', 'Low Stock'];
        usort($flags, function($a, $b) use ($priority) {
            return array_search($a, $priority) <=> array_search($b, $priority);
        });
        $combined = implode(', ', $flags);

        $item = [
            'name' => $r['MedicineName'],
            'stocks' => (int)$r['Stocks'],
            'expiry' => $r['ExpiryDate'],
            'status' => $combined
        ];
        $items[] = $item;

        // Put into all applicable groups (duplicates allowed across groups)
        if (!empty($r['isExpired'])) $grouped['expired'][] = $item;
        if (!empty($r['isExpiringSoon'])) $grouped['expiringSoon'][] = $item;
        if (!empty($r['isOutOfStock'])) $grouped['outOfStock'][] = $item;
        if (!empty($r['isLowStock'])) $grouped['lowStock'][] = $item;
    }

    echo json_encode([
        'success' => true,
        'alerts' => $grouped,
        'counts' => [
            'expiringSoon' => count($grouped['expiringSoon']),
            'lowStock' => count($grouped['lowStock']),
            'outOfStock' => count($grouped['outOfStock']),
            'expired' => count($grouped['expired'])
        ],
        'items' => $items
    ]);
}
?>
