<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../dbConnection.php';

$db = new Database();
$conn = $db->getConnection();

// lightweight local logging for diagnostics
$logFile = __DIR__ . '/add_medicine.log';
function am_log($data) {
    global $logFile;
    $line = '[' . date('c') . '] ' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

// Basic validation
$name = trim($_POST['name'] ?? '');
if ($name === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Name is required']);
    exit;
}

$category = trim($_POST['category'] ?? '');
$expiry_date = trim($_POST['expiry_date'] ?? '');
$expiry_date = ($expiry_date === '') ? null : $expiry_date;
$stock_quantity = isset($_POST['stock_quantity']) ? (int)$_POST['stock_quantity'] : 0;
$min_stock_threshold = isset($_POST['min_stock_threshold']) ? (int)$_POST['min_stock_threshold'] : 0;
$price = isset($_POST['price']) ? (float)$_POST['price'] : 0.0;
$image_url = trim($_POST['image_url'] ?? '');
$description = trim($_POST['description'] ?? '');
// Handle uploaded image file (optional)
$image_url = trim($_POST['image_url'] ?? '');
if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/../uploads';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileTmp = $_FILES['image_file']['tmp_name'];
    $fileName = basename($_FILES['image_file']['name']);
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif'];
    if (!in_array($ext, $allowed)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid image type']);
        exit;
    }
    // limit 2MB
    if ($_FILES['image_file']['size'] > 2 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Image too large (max 2MB)']);
        exit;
    }

    // generate unique name
    $newName = uniqid('med_', true) . '.' . $ext;
    $dest = $uploadDir . '/' . $newName;
    if (!move_uploaded_file($fileTmp, $dest)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to move uploaded file']);
        exit;
    }
    // store path relative to staff folder so med_inventory.php can use it as 'uploads/...' 
    $image_url = 'uploads/' . $newName;
}

try {
    // log incoming request summary
    am_log(['stage' => 'start', 'post_keys' => array_keys($_POST), 'files' => array_map(function($f){return [$f['name'] ?? null, $f['error'] ?? null, $f['size'] ?? null];}, $_FILES ?? [])]);
    // Build INSERT dynamically based on which columns actually exist in the `medicines` table.
    $availableColsStmt = $conn->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'medicines'");
    $availableColsStmt->execute();
    $cols = $availableColsStmt->fetchAll(PDO::FETCH_COLUMN);
    $cols = array_map('strtolower', $cols);

    $data = [
        'name' => $name,
        'category' => $category,
        'expiry_date' => $expiry_date,
        'stock_quantity' => $stock_quantity,
        'min_stock_threshold' => $min_stock_threshold,
        'price' => $price,
        'image_url' => $image_url,
        'description' => $description,
    ];

    $insertCols = [];
    $placeholders = [];
    $bindings = [];
    foreach ($data as $col => $val) {
        if (in_array($col, $cols)) {
            $insertCols[] = $col;
            $placeholders[] = ':' . $col;
            $bindings[$col] = $val;
        }
    }

    if (empty($insertCols)) {
        throw new Exception('No valid columns found to insert into medicines table');
    }

    $sql = 'INSERT INTO medicines (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = $conn->prepare($sql);

    // Bind values with proper types
    foreach ($bindings as $col => $val) {
        $param = ':' . $col;
        if ($col === 'expiry_date') {
            if ($val === null) {
                $stmt->bindValue($param, null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue($param, $val);
            }
        } elseif (in_array($col, ['stock_quantity', 'min_stock_threshold'])) {
            $stmt->bindValue($param, (int)$val, PDO::PARAM_INT);
        } elseif ($col === 'price') {
            $stmt->bindValue($param, (float)$val);
        } else {
            $stmt->bindValue($param, $val);
        }
    }
    // log SQL about to run
    am_log(['stage' => 'before_execute', 'sql' => $sql, 'bindings' => array_map(function($v){return is_scalar($v)?$v:null;}, $bindings)]);
    $stmt->execute();
    am_log(['stage' => 'after_execute', 'rowCount' => $stmt->rowCount()]);
    $insertId = $conn->lastInsertId();

    // return created medicine info (only include fields we attempted)
    $medicine = ['medicine_id' => (int)$insertId];
    foreach ($data as $k => $v) {
        if (in_array($k, $cols)) {
            if ($k === 'price') $medicine[$k] = number_format($v, 2);
            else $medicine[$k] = $v;
        }
    }

    // Compute alert flags based on the inserted DB row (safer than trusting input)
    $flags = [
        'low_stock' => false,
        'expiring_soon' => false,
        'expired' => false,
    ];

    try {
        // attempt to fetch the inserted row
        $fetchStmt = $conn->prepare("SELECT * FROM medicines WHERE medicine_id = :id LIMIT 1");
        $fetchStmt->bindValue(':id', $insertId, PDO::PARAM_INT);
        $fetchStmt->execute();
        $row = $fetchStmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            // low stock: if both stock_quantity and min_stock_threshold exist
            if (isset($row['stock_quantity']) && isset($row['min_stock_threshold'])) {
                $flags['low_stock'] = ((int)$row['stock_quantity'] <= (int)$row['min_stock_threshold']);
            }

            // expiry checks
            if (!empty($row['expiry_date'])) {
                $today = new DateTimeImmutable('today');
                $expiry = new DateTimeImmutable($row['expiry_date']);
                if ($expiry < $today) {
                    $flags['expired'] = true;
                } else {
                    $diff = $today->diff($expiry)->days;
                    $threshold_days = 90;
                    if ($diff <= $threshold_days) {
                        $flags['expiring_soon'] = true;
                    }
                }
            }
        }
    } catch (Exception $e) {
        // ignore and proceed without flags if fetch failed
    }
    // Ensure the response medicine object includes expiry_date and min_stock_threshold keys for client-side rendering
    $medicine['expiry_date'] = $row['expiry_date'] ?? ($medicine['expiry_date'] ?? null);
    $medicine['min_stock_threshold'] = isset($row['min_stock_threshold']) ? $row['min_stock_threshold'] : ($medicine['min_stock_threshold'] ?? null);

    echo json_encode(['success' => true, 'message' => 'Medicine added successfully', 'medicine' => $medicine, 'alerts' => $flags]);
    exit;

} catch (Exception $e) {
    // log exception
    am_log(['stage' => 'exception', 'message' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    exit;
}
