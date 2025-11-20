<?php
// notifications.php - Backend API for bell notifications
// Provides list/summary, mark read, and creation hook

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../dbconnection.php';

function json_response($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

try {
    // For testing purposes, allow access without authentication if no session exists
    $userId = (int)($_SESSION['user_id'] ?? 1); // Default to user ID 1 for testing
    $branchId = (int)($_SESSION['branch_id'] ?? 1); // Default to branch ID 1 for testing

    $db = new Database();
    $pdo = $db->getConnection();

    // Ensure Notifications table exists (idempotent) - matches pharmaceutical_db.sql schema
    $pdo->exec("CREATE TABLE IF NOT EXISTS Notifications (
        NotificationID INT AUTO_INCREMENT PRIMARY KEY,
        UserID INT NULL,
        BranchID INT NOT NULL DEFAULT 1,
        Type VARCHAR(50) NOT NULL DEFAULT 'system',
        Category VARCHAR(100) NULL,
        Title VARCHAR(255) NOT NULL,
        Message TEXT NOT NULL,
        Link VARCHAR(255) NULL,
        ResourceType VARCHAR(50) NULL,
        ResourceID INT NULL,
        Severity VARCHAR(20) DEFAULT 'info',
        IsRead TINYINT(1) NOT NULL DEFAULT 0,
        CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UpdatedAt TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user_isread (UserID, IsRead, CreatedAt),
        INDEX idx_branch_created (BranchID, CreatedAt),
        INDEX idx_type_category (Type, Category, CreatedAt),
        INDEX idx_resource (ResourceType, ResourceID)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $action = $_GET['action'] ?? $_POST['action'] ?? 'list';

    switch ($action) {
        case 'summary':
            // Count unread for current user in this branch
            $stmt = $pdo->prepare("SELECT 
                    SUM(CASE WHEN IsRead = 0 THEN 1 ELSE 0 END) AS total,
                    SUM(CASE WHEN IsRead = 0 AND Type = 'chat' THEN 1 ELSE 0 END) AS chat,
                    SUM(CASE WHEN IsRead = 0 AND Type IN ('inventory','med','pos','reports','account') THEN 1 ELSE 0 END) AS alerts
                FROM Notifications 
                WHERE BranchID = ? AND (UserID IS NULL OR UserID = ?) ");
            $stmt->execute([$branchId, $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total'=>0,'chat'=>0,'alerts'=>0];
            json_response(['success'=>true,'summary'=>[
                'total'=>(int)$row['total'],
                'chat'=>(int)$row['chat'],
                'alerts'=>(int)$row['alerts']
            ]]);
            break;

        case 'list':
            $type = $_GET['type'] ?? 'all';
            $limit = min(max((int)($_GET['limit'] ?? 50), 1), 200);
            $where = "BranchID = :bid AND (UserID IS NULL OR UserID = :uid)";
            $params = [':bid' => $branchId, ':uid' => $userId];
            if ($type === 'alerts') {
                $where .= " AND Type IN ('inventory','med','pos','reports','account')";
            } elseif ($type === 'chat') {
                $where .= " AND Type = 'chat'";
            }
            $sql = "SELECT NotificationID, Type, Category, Title, Message, Link, IsRead, CreatedAt
                    FROM Notifications WHERE $where
                    ORDER BY IsRead ASC, CreatedAt DESC
                    LIMIT :lim";
            $stmt = $pdo->prepare($sql);
            foreach ($params as $k=>$v) $stmt->bindValue($k, $v, PDO::PARAM_INT);
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            json_response(['success'=>true,'notifications'=>$rows]);
            break;

        case 'mark_read':
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $nid = (int)($input['notification_id'] ?? 0);
            if ($nid <= 0) json_response(['success'=>false,'error'=>'Notification ID required'], 400);
            $stmt = $pdo->prepare("UPDATE Notifications SET IsRead = 1 WHERE NotificationID = ? AND BranchID = ? AND (UserID IS NULL OR UserID = ?)");
            $stmt->execute([$nid, $branchId, $userId]);
            json_response(['success'=>true]);
            break;

        case 'mark_all_read':
            $stmt = $pdo->prepare("UPDATE Notifications SET IsRead = 1 WHERE BranchID = ? AND (UserID IS NULL OR UserID = ?)");
            $stmt->execute([$branchId, $userId]);
            json_response(['success'=>true]);
            break;

        case 'create':
            // Hook to create notification from other modules
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $type = trim($input['type'] ?? 'system');
            $category = trim($input['category'] ?? '');
            $title = trim($input['title'] ?? 'Notification');
            $message = trim($input['message'] ?? '');
            $link = trim($input['link'] ?? '');
            $targetUserId = isset($input['user_id']) ? (int)$input['user_id'] : $userId;
            $targetBranchId = isset($input['branch_id']) ? (int)$input['branch_id'] : $branchId;

            $stmt = $pdo->prepare("INSERT INTO Notifications (UserID, BranchID, Type, Category, Title, Message, Link, IsRead) VALUES (?, ?, ?, ?, ?, ?, ?, 0)");
            $stmt->execute([$targetUserId, $targetBranchId, $type, $category, $title, $message, $link]);
            json_response(['success'=>true, 'id' => (int)$pdo->lastInsertId()]);
            break;

        case 'debug_recent':
            // Return latest 50 notifications for debugging (no session filter)
            $limit = min(max((int)($_GET['limit'] ?? 50), 1), 200);
            $stmt = $pdo->prepare("SELECT * FROM Notifications ORDER BY CreatedAt DESC LIMIT :lim");
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            json_response(['success'=>true, 'count'=>count($rows), 'notifications'=>$rows]);
            break;

        default:
            json_response(['success'=>false,'error'=>'Unknown action'], 400);
    }

} catch (Throwable $e) {
    error_log('Notifications API error: ' . $e->getMessage());
    json_response(['success'=>false,'error'=>'Server error: ' . $e->getMessage()], 500);
}