<?php
/**
 * Notification API - Integrates with ntfy for push notifications
 * Handles inventory alerts and chat message notifications
 */

session_start();
require_once __DIR__ . '/../../dbconnection.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['user_id']) || !isset($_SESSION['branch_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    $db = new Database();
    $conn = $db->getConnection();

    switch ($action) {
        case 'get_notifications':
            getNotifications($conn);
            break;
        case 'mark_read':
            markNotificationAsRead($conn);
            break;
        case 'mark_all_read':
            markAllNotificationsAsRead($conn);
            break;
        case 'send_notification':
            sendNotification($conn);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("Notification API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}

function getNotifications($conn) {
    $user_id = $_SESSION['user_id'];
    $branch_id = $_SESSION['branch_id'];
    
    // Get unread notifications
    $sql = "SELECT 
                NotificationID,
                Type,
                Title,
                Message,
                Link,
                IsRead,
                CreatedAt
            FROM Notifications
            WHERE UserID = ?
            ORDER BY CreatedAt DESC
            LIMIT 50";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unread count
    $countSql = "SELECT COUNT(*) as unread_count 
                 FROM Notifications 
                 WHERE UserID = ? AND IsRead = 0";
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute([$user_id]);
    $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
    $unreadCount = (int)($countResult['unread_count'] ?? 0);
    
    // Also get alerts count from medicine API
    $alertsCount = getAlertsCount($conn, $branch_id);
    
    // Get unread chat messages count
    $chatCount = getUnreadChatCount($conn, $user_id);
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => $unreadCount,
        'alerts_count' => $alertsCount,
        'chat_count' => $chatCount,
        'total_count' => $unreadCount + $alertsCount + $chatCount
    ]);
}

function getAlertsCount($conn, $branch_id) {
    $sql = "SELECT 
                COUNT(*) as count
            FROM BranchInventory bi
            JOIN medicines m ON bi.MedicineID = m.MedicineID
            WHERE bi.BranchID = ?
              AND (
                bi.ExpiryDate < CURDATE() OR
                bi.ExpiryDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) OR
                bi.Stocks = 0 OR
                (bi.Stocks > 0 AND bi.Stocks <= 10)
              )";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$branch_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int)($result['count'] ?? 0);
}

function getUnreadChatCount($conn, $user_id) {
    $sql = "SELECT COUNT(*) as count
            FROM ChatMessages cm
            INNER JOIN ChatParticipants cp ON cm.ConversationID = cp.ConversationID
            WHERE cp.UserID = ?
              AND cm.SenderID != ?
              AND cm.Timestamp > COALESCE(cp.LastReadTimestamp, '1970-01-01')";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$user_id, $user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int)($result['count'] ?? 0);
}

function markNotificationAsRead($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $notification_id = $data['notification_id'] ?? null;
    
    if (!$notification_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Notification ID required']);
        return;
    }
    
    $user_id = $_SESSION['user_id'];
    
    $sql = "UPDATE Notifications 
            SET IsRead = 1, ReadAt = CURRENT_TIMESTAMP
            WHERE NotificationID = ? AND UserID = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$notification_id, $user_id]);
    
    echo json_encode(['success' => true]);
}

function markAllNotificationsAsRead($conn) {
    $user_id = $_SESSION['user_id'];
    
    $sql = "UPDATE Notifications 
            SET IsRead = 1, ReadAt = CURRENT_TIMESTAMP
            WHERE UserID = ? AND IsRead = 0";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$user_id]);
    
    echo json_encode(['success' => true]);
}

function sendNotification($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $user_id = $data['user_id'] ?? $_SESSION['user_id'];
    $type = $data['type'] ?? 'info'; // 'alert', 'chat', 'info', 'warning', 'error'
    $title = $data['title'] ?? 'Notification';
    $message = $data['message'] ?? '';
    $link = $data['link'] ?? null;
    
    // Save to database
    $sql = "INSERT INTO Notifications (UserID, Type, Title, Message, Link, IsRead, CreatedAt)
            VALUES (?, ?, ?, ?, ?, 0, CURRENT_TIMESTAMP)";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$user_id, $type, $title, $message, $link]);
    
    $notification_id = $conn->lastInsertId();
    
    // Send via ntfy if configured
    $ntfy_enabled = getenv('NTFY_ENABLED') === 'true' || file_exists(__DIR__ . '/../config/ntfy_enabled.txt');
    if ($ntfy_enabled) {
        sendNtfyNotification($user_id, $title, $message, $type);
    }
    
    echo json_encode([
        'success' => true,
        'notification_id' => $notification_id
    ]);
}

function sendNtfyNotification($user_id, $title, $message, $type = 'info') {
    // Get user's ntfy topic (if configured)
    // For now, we'll use a default topic or user-specific topic
    $ntfy_server = getenv('NTFY_SERVER') ?: 'https://ntfy.sh';
    $ntfy_topic = getenv('NTFY_TOPIC') ?: 'pharma-notifications-' . $user_id;
    
    // Priority mapping
    $priority_map = [
        'error' => 5,
        'warning' => 4,
        'alert' => 3,
        'chat' => 2,
        'info' => 1
    ];
    $priority = $priority_map[$type] ?? 3;
    
    // Tags/emoji mapping
    $tags_map = [
        'error' => ['rotating_light', 'red_circle'],
        'warning' => ['warning', 'yellow_circle'],
        'alert' => ['bell', 'orange_circle'],
        'chat' => ['speech_balloon', 'blue_circle'],
        'info' => ['information_source', 'green_circle']
    ];
    $tags = $tags_map[$type] ?? ['bell'];
    
    $url = rtrim($ntfy_server, '/') . '/' . $ntfy_topic;
    
    $payload = [
        'topic' => $ntfy_topic,
        'title' => $title,
        'message' => $message,
        'priority' => $priority,
        'tags' => $tags
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $message);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Title: ' . $title,
        'Priority: ' . $priority,
        'Tags: ' . implode(',', $tags)
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("ntfy notification failed: HTTP $httpCode - $response");
    }
    
    return $httpCode === 200;
}
?>

