<?php
session_start();
// API endpoint for fetching inventory alerts categorized for the sidebar.

header('Content-Type: application/json');

// DEV: quick test mode to return sample alerts when ?test=1 (useful for UI debugging)
if (!isset($_SESSION['user_id'])) {
    if (isset($_GET['test']) && $_GET['test'] === '1') {
        echo json_encode(['success' => true, 'alerts' => [
            'low_stock' => [
                ['name' => 'Paracetamol', 'stock_quantity' => 2],
                ['name' => 'Ibuprofen', 'stock_quantity' => 0],
            ],
            'expiring_soon' => [
                ['name' => 'Amoxicillin', 'days_remaining' => 5],
                ['name' => 'Vitamin C', 'days_remaining' => 9],
            ],
            'expired' => [
                ['name' => 'Cough Syrup', 'expiry_date' => '2024-01-01']
            ]
        ]]);
        exit;
    }

    // Normal auth enforcement
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit;
}

// DATABASE CONNECTION
require_once __DIR__ . '/../../dbConnection.php';

$db = new Database();
$conn = $db->getConnection();

$alerts = [
    'low_stock' => [],
    'expiring_soon' => [],
    'expired' => [],
];
$threshold_days = 90; // Alert if medicine expires in 90 days or less

try {
    // --- 1. EXPIRED CHECK ---
    $stmt_expired = $conn->prepare("SELECT name, expiry_date FROM medicines WHERE expiry_date < CURDATE() ORDER BY expiry_date ASC");
    $stmt_expired->execute();
    $alerts['expired'] = $stmt_expired->fetchAll(PDO::FETCH_ASSOC);

    // --- 2. EXPIRING SOON CHECK ---
    // Include items with any stock level so the sidebar shows expiring items
    $stmt_soon = $conn->prepare("SELECT name, expiry_date, stock_quantity 
                                FROM medicines 
                                WHERE expiry_date >= CURDATE() 
                                AND expiry_date < DATE_ADD(CURDATE(), INTERVAL :days DAY)
                                ORDER BY expiry_date ASC");
    $stmt_soon->bindParam(':days', $threshold_days, PDO::PARAM_INT);
    $stmt_soon->execute();
    $expiring_soon_meds = $stmt_soon->fetchAll(PDO::FETCH_ASSOC);

    foreach ($expiring_soon_meds as $med) {
        $now = time();
        $expiry_time = strtotime($med['expiry_date']);
        $days_remaining = max(0, (int) floor(($expiry_time - $now) / (60 * 60 * 24)));

        $alerts['expiring_soon'][] = [
            'name' => $med['name'],
            'days_remaining' => $days_remaining,
        ];
    }

    // --- 3. LOW/OUT OF STOCK CHECK ---
    // Include items that are out of stock (stock_quantity = 0) OR have a defined min_stock_threshold and are <= that threshold.
    $stmt_stock = $conn->prepare(
        "SELECT name, stock_quantity, min_stock_threshold FROM medicines 
         WHERE stock_quantity = 0 OR (min_stock_threshold IS NOT NULL AND stock_quantity <= min_stock_threshold)
         ORDER BY stock_quantity ASC"
    );
    $stmt_stock->execute();
    $low_stock_meds = $stmt_stock->fetchAll(PDO::FETCH_ASSOC);

    foreach ($low_stock_meds as $med) {
        $alerts['low_stock'][] = [
            'name' => $med['name'],
            'stock_quantity' => isset($med['stock_quantity']) ? (int)$med['stock_quantity'] : 0,
        ];
    }

    // If debug=1 is passed, include raw SQL counts and rows for troubleshooting
    if (isset($_GET['debug']) && $_GET['debug'] === '1') {
        // Include server time to help diagnose date/time-related issues
        $debug = [
            'server_date_iso' => date('c'),
            'server_date_readable' => date('Y-m-d H:i:s'),
            'expired_count' => count($alerts['expired']),
            'expiring_soon_count' => count($alerts['expiring_soon']),
            'low_stock_count' => count($alerts['low_stock']),
            'expired_rows' => $alerts['expired'],
            'expiring_soon_rows' => $expiring_soon_meds,
            'low_stock_rows' => $low_stock_meds,
        ];

        echo json_encode(['success' => true, 'alerts' => $alerts, 'debug' => $debug]);
        exit;
    }

    echo json_encode(['success' => true, 'alerts' => $alerts]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    exit;
}
