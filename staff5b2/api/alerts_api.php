<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../dbconnection.php';

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Assuming branch1, BranchID = 1
    $branchID = 1;

    // Use direct SQL instead of stored procedure
    $sql = "SELECT m.MedicineName, bi.Stocks, bi.ExpiryDate,
                   CASE
                       WHEN bi.Stocks = 0 THEN 'Out of Stock'
                       WHEN bi.Stocks > 0 AND bi.Stocks <= 10 THEN 'Low Stock'
                       WHEN bi.ExpiryDate < CURDATE() THEN 'Expired'
                       WHEN bi.ExpiryDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Expiring Soon'
                       ELSE 'Active'
                   END AS AlertType
            FROM BranchInventory bi
            JOIN medicines m ON bi.MedicineID = m.MedicineID
            WHERE bi.BranchID = ?
            AND (
                bi.Stocks = 0 OR
                (bi.Stocks > 0 AND bi.Stocks <= 10) OR
                bi.ExpiryDate < CURDATE() OR
                bi.ExpiryDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            )
            ORDER BY
                CASE AlertType
                    WHEN 'Out of Stock' THEN 1
                    WHEN 'Low Stock' THEN 2
                    WHEN 'Expiring Soon' THEN 3
                    WHEN 'Expired' THEN 4
                END,
                m.MedicineName";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$branchID]);

    $alerts = [
        'lowStock' => [],
        'outOfStock' => [],
        'expiringSoon' => [],
        'expired' => []
    ];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $item = [
            'name' => $row['MedicineName'],
            'stocks' => $row['Stocks'],
            'expiry' => date('M Y', strtotime($row['ExpiryDate']))
        ];

        switch ($row['AlertType']) {
            case 'Low Stock':
                $alerts['lowStock'][] = $item;
                break;
            case 'Out of Stock':
                $alerts['outOfStock'][] = $item;
                break;
            case 'Expiring Soon':
                $alerts['expiringSoon'][] = $item;
                break;
            case 'Expired':
                $alerts['expired'][] = $item;
                break;
        }
    }

    echo json_encode($alerts);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
