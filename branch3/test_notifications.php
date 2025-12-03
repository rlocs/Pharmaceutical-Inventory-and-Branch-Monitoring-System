<?php
// Test file to check notifications setup
session_start();
require_once __DIR__ . '/../dbconnection.php';

// Set test session if not logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 2; // Branch 1 staff
    $_SESSION['branch_id'] = 1;
}

$db = new Database();
$pdo = $db->getConnection();

// Check if Notifications table exists and its structure
try {
    $tables = $pdo->query("SHOW TABLES LIKE 'Notifications'")->fetchAll(PDO::FETCH_ASSOC);
    echo "<h2>Table Exists:</h2>";
    echo $tables ? "YES\n" : "NO - Table not created yet\n";
    
    if ($tables) {
        echo "<h2>Table Structure:</h2>";
        $columns = $pdo->query("DESCRIBE Notifications")->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>";
        print_r($columns);
        echo "</pre>";
    }
    
    echo "<h2>Row Count:</h2>";
    $count = $pdo->query("SELECT COUNT(*) as cnt FROM Notifications")->fetch(PDO::FETCH_ASSOC);
    echo "Total rows: " . ($count['cnt'] ?? 'N/A') . "\n";
    
    echo "<h2>Recent Notifications (All):</h2>";
    $rows = $pdo->query("SELECT * FROM Notifications ORDER BY CreatedAt DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($rows);
    echo "</pre>";
    
    echo "<h2>For Current User (ID=" . $_SESSION['user_id'] . ", Branch=" . $_SESSION['branch_id'] . "):</h2>";
    $stmt = $pdo->prepare("SELECT * FROM Notifications WHERE BranchID = ? AND (UserID IS NULL OR UserID = ?) ORDER BY CreatedAt DESC LIMIT 10");
    $stmt->execute([$_SESSION['branch_id'], $_SESSION['user_id']]);
    $userRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($userRows);
    echo "</pre>";
    
    echo "<h2>API Test - Summary:</h2>";
    $stmt = $pdo->prepare("SELECT 
        SUM(CASE WHEN IsRead = 0 THEN 1 ELSE 0 END) AS total,
        SUM(CASE WHEN IsRead = 0 AND Type = 'chat' THEN 1 ELSE 0 END) AS chat,
        SUM(CASE WHEN IsRead = 0 AND Type IN ('inventory','med','pos','reports','account') THEN 1 ELSE 0 END) AS alerts
    FROM Notifications 
    WHERE BranchID = ? AND (UserID IS NULL OR UserID = ?)");
    $stmt->execute([$_SESSION['branch_id'], $_SESSION['user_id']]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($summary);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
