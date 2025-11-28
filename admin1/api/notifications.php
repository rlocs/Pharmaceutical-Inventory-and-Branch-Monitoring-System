<?php
// notifications.php - Backend API for bell notifications
// UPDATED for NotificationReadState table

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../dbconnection.php';

function json_response($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

try {
    $userId = (int)($_SESSION['user_id'] ?? 1);
    $branchId = (int)($_SESSION['branch_id'] ?? 1);

    $db = new Database();
    $pdo = $db->getConnection();

    $action = $_GET['action'] ?? $_POST['action'] ?? 'list';

    switch ($action) {
        case 'summary':
            // Count unread notifications for current user using NotificationReadState
            $stmt = $pdo->prepare("SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN n.Type = 'chat' THEN 1 ELSE 0 END) as chat,
                    SUM(CASE WHEN n.Type IN ('inventory','med','pos','reports','account') THEN 1 ELSE 0 END) as alerts
                FROM Notifications n
                LEFT JOIN NotificationReadState nrs ON n.NotificationID = nrs.NotificationID AND nrs.UserID = ?
                WHERE n.BranchID = ? 
                AND (n.UserID IS NULL OR n.UserID = ?)
                AND (nrs.IsRead = 0 OR nrs.IsRead IS NULL)");
            $stmt->execute([$userId, $branchId, $userId]);
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
            
            $where = "n.BranchID = :bid AND (n.UserID IS NULL OR n.UserID = :uid)";
            $params = [':bid' => $branchId, ':uid' => $userId];
            
            if ($type === 'alerts') {
                $where .= " AND n.Type IN ('inventory','med','pos','reports','account')";
            } elseif ($type === 'chat') {
                $where .= " AND n.Type = 'chat'";
            }
            
            $sql = "SELECT 
                    n.NotificationID,
                    n.Type,
                    n.Category,
                    n.Title,
                    n.Message,
                    n.Link,
                    n.Severity,
                    n.CreatedAt,
                    COALESCE(nrs.IsRead, 0) as IsRead
                FROM Notifications n
                LEFT JOIN NotificationReadState nrs ON n.NotificationID = nrs.NotificationID AND nrs.UserID = :uid
                WHERE $where
                ORDER BY COALESCE(nrs.IsRead, 0) ASC, n.CreatedAt DESC
                LIMIT :lim";
                
            $stmt = $pdo->prepare($sql);
            foreach ($params as $k=>$v) $stmt->bindValue($k, $v, PDO::PARAM_INT);
            $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            json_response(['success'=>true,'notifications'=>$rows]);
            break;

        case 'mark_read':
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $nid = (int)($input['notification_id'] ?? 0);
            if ($nid <= 0) json_response(['success'=>false,'error'=>'Notification ID required'], 400);
            
            // Insert or update in NotificationReadState
            $stmt = $pdo->prepare("INSERT INTO NotificationReadState (NotificationID, UserID, IsRead, ReadAt) 
                                  VALUES (?, ?, 1, NOW()) 
                                  ON DUPLICATE KEY UPDATE IsRead = 1, ReadAt = NOW()");
            $stmt->execute([$nid, $userId]);
            json_response(['success'=>true]);
            break;

        case 'mark_all_read':
            $type = $_GET['type'] ?? $_POST['type'] ?? 'all';
            
            // First get all unread notification IDs for this user
            $where = "n.BranchID = ? AND (n.UserID IS NULL OR n.UserID = ?)";
            $params = [$branchId, $userId];
            
            if ($type === 'alerts') {
                $where .= " AND n.Type IN ('inventory','med','pos','reports','account')";
            } elseif ($type === 'chat') {
                $where .= " AND n.Type = 'chat'";
            }
            
            $sql = "SELECT n.NotificationID 
                    FROM Notifications n
                    LEFT JOIN NotificationReadState nrs ON n.NotificationID = nrs.NotificationID AND nrs.UserID = ?
                    WHERE $where AND (nrs.IsRead = 0 OR nrs.IsRead IS NULL)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $notificationIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Mark each as read
            if (!empty($notificationIds)) {
                $placeholders = str_repeat('?,', count($notificationIds) - 1) . '?';
                $stmt = $pdo->prepare("INSERT INTO NotificationReadState (NotificationID, UserID, IsRead, ReadAt) 
                                      VALUES " . 
                                      implode(',', array_map(function($id) use ($userId) {
                                          return "($id, $userId, 1, NOW())";
                                      }, $notificationIds)) . 
                                      " ON DUPLICATE KEY UPDATE IsRead = 1, ReadAt = NOW()");
                $stmt->execute();
            }
            
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
            $severity = trim($input['severity'] ?? 'info');
            $targetUserId = isset($input['user_id']) ? (int)$input['user_id'] : $userId;
            $targetBranchId = isset($input['branch_id']) ? (int)$input['branch_id'] : $branchId;

            $stmt = $pdo->prepare("INSERT INTO Notifications 
                                  (UserID, BranchID, Type, Category, Title, Message, Link, Severity) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$targetUserId, $targetBranchId, $type, $category, $title, $message, $link, $severity]);
            
            $notificationId = (int)$pdo->lastInsertId();
            
            // For chat messages, create a desktop notification
            if ($type === 'chat') {
                // This will trigger the frontend to show a notification
                json_response([
                    'success' => true, 
                    'id' => $notificationId,
                    'is_chat' => true,
                    'message' => $message,
                    'from' => $title
                ]);
            } else {
                json_response(['success' => true, 'id' => $notificationId]);
            }
            break;

        case 'get_chat_notifications':
            // Specifically get chat notifications for the bell
            $limit = min(max((int)($_GET['limit'] ?? 20), 1), 100);
            
            $stmt = $pdo->prepare("SELECT 
                    n.NotificationID,
                    n.Title,
                    n.Message,
                    n.Link,
                    n.CreatedAt,
                    COALESCE(nrs.IsRead, 0) as IsRead
                FROM Notifications n
                LEFT JOIN NotificationReadState nrs ON n.NotificationID = nrs.NotificationID AND nrs.UserID = ?
                WHERE n.BranchID = ? AND n.Type = 'chat' AND (n.UserID IS NULL OR n.UserID = ?)
                ORDER BY n.CreatedAt DESC
                LIMIT ?");
            $stmt->execute([$userId, $branchId, $userId, $limit]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            json_response(['success'=>true,'notifications'=>$rows]);
            break;

        default:
            json_response(['success'=>false,'error'=>'Unknown action'], 400);
    }

} catch (Throwable $e) {
    error_log('Notifications API error: ' . $e->getMessage());
    json_response(['success'=>false,'error'=>'Server error: ' . $e->getMessage()], 500);
}