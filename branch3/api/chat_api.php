<?php
session_start();
header('Content-Type: application/json');

require_once '../../dbconnection.php';

// --- Helper function to send JSON response ---
function send_response($data) {
    echo json_encode($data);
    exit;
}

// --- Helper function to create notifications for chat messages ---
function createNotification($conn, $type, $category, $title, $message, $link = '', $userId = null) {
    try {
        if ($userId === null) {
            $userId = $_SESSION['user_id'] ?? null;
        }
        $branchId = $_SESSION['branch_id'] ?? 1;
        // Try to get recipient's branch if we have a user_id (they might be in different branch)
        if ($userId) {
            $b = $conn->prepare("SELECT BranchID FROM Accounts WHERE UserID = ? LIMIT 1");
            $b->execute([$userId]);
            $r = $b->fetch(PDO::FETCH_ASSOC);
            if ($r) $branchId = $r['BranchID'];
        }

        $stmt = $conn->prepare("INSERT INTO Notifications (UserID, BranchID, Type, Category, Title, Message, Link, IsRead) VALUES (?, ?, ?, ?, ?, ?, ?, 0)");
        $stmt->execute([$userId, $branchId, $type, $category, $title, $message, $link]);
        $nid = (int)$conn->lastInsertId();
        return $nid > 0 ? $nid : false;
    } catch (Exception $e) {
        error_log("Notification creation failed: " . $e->getMessage());
        return false;
    }
}

// --- Ensure user is logged in ---
if (!isset($_SESSION['loggedin']) || !isset($_SESSION['user_id'])) {
    send_response(['success' => false, 'error' => 'Authentication required.']);
}

$current_user_id = $_SESSION['user_id'];

try {
    $db = new Database();
    $conn = $db->getConnection();
} catch (Exception $e) {
    error_log("Chat API DB connection error: " . $e->getMessage());
    send_response(['success' => false, 'error' => 'Database connection failed.']);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// For this environment we will use direct SQL queries instead of stored procedures.
switch ($action) {
    case 'get_conversations':
        try {
            $stmt = $conn->prepare("CALL SP_GetConversations(:user_id)");
            $stmt->bindParam(':user_id', $current_user_id, PDO::PARAM_INT);
            $stmt->execute();
            $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            send_response(['success' => true, 'conversations' => $conversations]);
        } catch (PDOException $e) {
            error_log("Get conversations error: " . $e->getMessage());
            send_response(['success' => false, 'error' => 'Failed to fetch conversations.']);
        }
        break;

    case 'get_messages':
        $conversation_id = $_GET['conversation_id'] ?? ($_POST['conversation_id'] ?? 0);
        if (empty($conversation_id)) {
            send_response(['success' => false, 'error' => 'Conversation ID is required.']);
        }

        try {
            // Update last read timestamp for this participant
            $update = $conn->prepare("CALL SP_UpdateLastRead(:conv, :uid)");
            $update->bindParam(':conv', $conversation_id, PDO::PARAM_INT);
            $update->bindParam(':uid', $current_user_id, PDO::PARAM_INT);
            $update->execute();

            // Fetch messages
            $stmt = $conn->prepare("CALL SP_GetMessages(:conv)");
            $stmt->bindParam(':conv', $conversation_id, PDO::PARAM_INT);
            $stmt->execute();
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            send_response(['success' => true, 'messages' => $messages]);
        } catch (PDOException $e) {
            error_log("Get messages error: " . $e->getMessage());
            send_response(['success' => false, 'error' => 'Failed to fetch messages.']);
        }
        break;

    case 'send_message':
        $conversation_id = $_POST['conversation_id'] ?? 0;
        $message_content = trim($_POST['message'] ?? '');

        if (empty($conversation_id) || $message_content === '') {
            send_response(['success' => false, 'error' => 'Conversation ID and message content are required.']);
        }

        try {
            $stmt = $conn->prepare("CALL SP_SendMessage(:conv, :sender, :msg)");
            $stmt->bindParam(':conv', $conversation_id, PDO::PARAM_INT);
            $stmt->bindParam(':sender', $current_user_id, PDO::PARAM_INT);
            $stmt->bindParam(':msg', $message_content, PDO::PARAM_STR);
            $stmt->execute();
            $newMessage = $stmt->fetch(PDO::FETCH_ASSOC);

            // Create notification for recipients of this conversation
            try {
                $recipients = $conn->prepare("SELECT UserID FROM ChatParticipants WHERE ConversationID = ? AND UserID != ?");
                $recipients->execute([$conversation_id, $current_user_id]);
                $recipientList = $recipients->fetchAll(PDO::FETCH_ASSOC);
                
                $sender = $conn->prepare("SELECT FirstName, LastName FROM Accounts WHERE UserID = ?");
                $sender->execute([$current_user_id]);
                $senderInfo = $sender->fetch(PDO::FETCH_ASSOC);
                $senderName = trim(($senderInfo['FirstName'] ?? '') . ' ' . ($senderInfo['LastName'] ?? ''));
                
                $created = 0; $failed = 0; $createdIds = [];
                foreach ($recipientList as $recipient) {
                    $link = '/branch1/chat.php?conversation_id=' . intval($conversation_id) . '&open=1';
                    $nid = createNotification($conn, 'chat', 'Message', 'New message from ' . $senderName, substr($message_content, 0, 200) . (strlen($message_content) > 200 ? '...' : ''), $link, $recipient['UserID']);
                    if ($nid) { $created++; $createdIds[] = $nid; }
                    else { $failed++; }
                }
                if ($failed > 0) error_log("Chat notifications: created={$created}, failed={$failed}");
            } catch (Throwable $e) {
                error_log("Chat notification error: " . $e->getMessage());
            }

            send_response(['success' => true, 'message' => $newMessage]);
        } catch (PDOException $e) {
            error_log("Send message error: " . $e->getMessage());
            send_response(['success' => false, 'error' => 'Failed to send message.']);
        }
        break;

    case 'get_users':
        try {
            $stmt = $conn->prepare("SELECT UserID, FirstName, LastName, Role, BranchID FROM Accounts WHERE UserID != :current_user_id ORDER BY FirstName, LastName");
            $stmt->bindParam(':current_user_id', $current_user_id, PDO::PARAM_INT);
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            send_response(['success' => true, 'users' => $users]);
        } catch (PDOException $e) {
            error_log("Get Users error: " . $e->getMessage());
            send_response(['success' => false, 'error' => 'Failed to fetch users.']);
        }
        break;

    case 'create_conversation':
        $recipient_id = $_POST['recipient_id'] ?? 0;
        if (empty($recipient_id) || $recipient_id == $current_user_id) {
            send_response(['success' => false, 'error' => 'Valid recipient ID is required.']);
        }

        try {
            $stmt = $conn->prepare("CALL SP_FindOrCreateConversation(:u1, :u2)");
            $stmt->bindParam(':u1', $current_user_id, PDO::PARAM_INT);
            $stmt->bindParam(':u2', $recipient_id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            send_response(['success' => true, 'conversation_id' => $result['ConversationID']]);
        } catch (PDOException $e) {
            error_log("Create conversation error: " . $e->getMessage());
            send_response(['success' => false, 'error' => 'Database error while creating conversation.']);
        }
        break;

    default:
        send_response(['success' => false, 'error' => 'Invalid action specified.']);
        break;
}
?>