<?php
session_start();
header('Content-Type: application/json');

require_once '../../dbconnection.php';

// --- Helper function to send JSON response ---
function send_response($data) {
    echo json_encode($data);
    exit;
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
            // Improved query to prevent duplicates - use DISTINCT on ConversationID only
            $sql = "SELECT DISTINCT
                c.ConversationID,
                c.LastMessageTimestamp,
                -- Other participant details (get the first other participant)
                (SELECT a.FirstName
                 FROM ChatParticipants cp
                 JOIN Accounts a ON cp.UserID = a.UserID
                 WHERE cp.ConversationID = c.ConversationID
                   AND cp.UserID != ?
                 ORDER BY cp.UserID
                 LIMIT 1) AS FirstName,
                (SELECT a.LastName
                 FROM ChatParticipants cp
                 JOIN Accounts a ON cp.UserID = a.UserID
                 WHERE cp.ConversationID = c.ConversationID
                   AND cp.UserID != ?
                 ORDER BY cp.UserID
                 LIMIT 1) AS LastName,
                (SELECT b.BranchName
                 FROM ChatParticipants cp
                 JOIN Accounts a ON cp.UserID = a.UserID
                 JOIN Branches b ON a.BranchID = b.BranchID
                 WHERE cp.ConversationID = c.ConversationID
                   AND cp.UserID != ?
                 ORDER BY cp.UserID
                 LIMIT 1) AS BranchName,
                -- Last message
                (SELECT cm.MessageContent
                 FROM ChatMessages cm
                 WHERE cm.ConversationID = c.ConversationID
                 ORDER BY cm.Timestamp DESC
                 LIMIT 1) AS LastMessage,
                -- Unread message count
                (SELECT COUNT(*)
                 FROM ChatMessages cm
                 WHERE cm.ConversationID = c.ConversationID
                   AND cm.Timestamp > COALESCE((
                       SELECT cp.LastReadTimestamp
                       FROM ChatParticipants cp
                       WHERE cp.ConversationID = c.ConversationID
                         AND cp.UserID = ?
                       LIMIT 1
                   ), '1970-01-01')) AS UnreadCount
            FROM ChatConversations c
            INNER JOIN ChatParticipants p ON c.ConversationID = p.ConversationID
            WHERE p.UserID = ?
            GROUP BY c.ConversationID
            ORDER BY c.LastMessageTimestamp DESC";

            $stmt = $conn->prepare($sql);
            $stmt->execute([$current_user_id, $current_user_id, $current_user_id, $current_user_id, $current_user_id]);
            $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Additional deduplication on PHP side as safety measure
            $uniqueConversations = [];
            $seenIds = [];
            foreach ($conversations as $conv) {
                $convId = $conv['ConversationID'];
                if (!in_array($convId, $seenIds)) {
                    $seenIds[] = $convId;
                    $uniqueConversations[] = $conv;
                }
            }
            
            send_response(['success' => true, 'conversations' => $uniqueConversations]);
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
            $update = $conn->prepare("UPDATE ChatParticipants SET LastReadTimestamp = CURRENT_TIMESTAMP WHERE ConversationID = ? AND UserID = ?");
            $update->execute([$conversation_id, $current_user_id]);

            // Fetch messages
            $stmt = $conn->prepare("SELECT
                m.MessageID,
                m.SenderUserID,
                m.MessageContent,
                m.Timestamp,
                a.FirstName,
                a.LastName,
                b.BranchName
            FROM ChatMessages m
            JOIN Accounts a ON m.SenderUserID = a.UserID
            JOIN Branches b ON a.BranchID = b.BranchID
            WHERE m.ConversationID = ?
            ORDER BY m.Timestamp ASC");
            $stmt->execute([$conversation_id]);
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
            // Get the sender's BranchID
            $branch_stmt = $conn->prepare("SELECT BranchID FROM Accounts WHERE UserID = ?");
            $branch_stmt->execute([$current_user_id]);
            $branch_result = $branch_stmt->fetch(PDO::FETCH_ASSOC);
            $branch_id = $branch_result['BranchID'];

            // Insert the new message
            $stmt = $conn->prepare("INSERT INTO ChatMessages (ConversationID, SenderUserID, BranchID, MessageContent) VALUES (?, ?, ?, ?)");
            $stmt->execute([$conversation_id, $current_user_id, $branch_id, $message_content]);
            $newMessageID = $conn->lastInsertId();

            // Update the conversation's last message timestamp
            $update = $conn->prepare("UPDATE ChatConversations SET LastMessageTimestamp = CURRENT_TIMESTAMP WHERE ConversationID = ?");
            $update->execute([$conversation_id]);

            // Return the newly created message
            $select = $conn->prepare("SELECT
                m.MessageID,
                m.SenderUserID,
                m.MessageContent,
                m.Timestamp,
                a.FirstName,
                a.LastName
            FROM ChatMessages m
            JOIN Accounts a ON m.SenderUserID = a.UserID
            WHERE m.MessageID = ?");
            $select->execute([$newMessageID]);
            $newMessage = $select->fetch(PDO::FETCH_ASSOC);

            send_response(['success' => true, 'message' => $newMessage]);
        } catch (PDOException $e) {
            error_log("Send message error: " . $e->getMessage());
            send_response(['success' => false, 'error' => 'Failed to send message.']);
        }
        break;

    case 'get_users':
        try {
            // Get current user's branch
            $branch_stmt = $conn->prepare("SELECT BranchID FROM Accounts WHERE UserID = ?");
            $branch_stmt->execute([$current_user_id]);
            $current_branch = $branch_stmt->fetch(PDO::FETCH_ASSOC);
            $current_branch_id = $current_branch['BranchID'] ?? null;
            
            // Get all other users with their branch names
            $stmt = $conn->prepare("SELECT 
                a.UserID, 
                a.FirstName, 
                a.LastName, 
                a.Role, 
                a.BranchID,
                b.BranchName
            FROM Accounts a
            LEFT JOIN Branches b ON a.BranchID = b.BranchID
            WHERE a.UserID != ?
            ORDER BY a.Role DESC, a.FirstName, a.LastName");
            $stmt->execute([$current_user_id]);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Filter to show: Admin, and staff from other branches (not same branch)
            $filteredUsers = [];
            foreach ($users as $user) {
                // Always show Admin
                if ($user['Role'] === 'Admin') {
                    $filteredUsers[] = $user;
                }
                // Show staff from other branches only
                else if ($user['Role'] === 'Staff' && $user['BranchID'] != $current_branch_id) {
                    $filteredUsers[] = $user;
                }
            }
            
            send_response(['success' => true, 'users' => $filteredUsers]);
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
            $conversation_id = null;

            // Get BranchIDs for both users
            $branch_stmt = $conn->prepare("SELECT BranchID FROM Accounts WHERE UserID = ?");
            $branch_stmt->execute([$current_user_id]);
            $branch1 = $branch_stmt->fetch(PDO::FETCH_ASSOC)['BranchID'];

            $branch_stmt->execute([$recipient_id]);
            $branch2 = $branch_stmt->fetch(PDO::FETCH_ASSOC)['BranchID'];

            // Try to find existing 1-on-1 conversation between the two users
            $find_stmt = $conn->prepare("SELECT cp1.ConversationID
                FROM ChatParticipants cp1
                JOIN ChatParticipants cp2 ON cp1.ConversationID = cp2.ConversationID
                WHERE cp1.UserID = ? AND cp2.UserID = ?
                GROUP BY cp1.ConversationID
                HAVING COUNT(*) = 2
                LIMIT 1");
            $find_stmt->execute([$current_user_id, $recipient_id]);
            $existing = $find_stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $conversation_id = $existing['ConversationID'];
            } else {
                // Create new conversation and add participants
                $insert_conv = $conn->prepare("INSERT INTO ChatConversations (LastMessageTimestamp) VALUES (CURRENT_TIMESTAMP)");
                $insert_conv->execute();
                $conversation_id = $conn->lastInsertId();

                $insert_part = $conn->prepare("INSERT INTO ChatParticipants (ConversationID, UserID, BranchID, LastReadTimestamp) VALUES (?, ?, ?, CURRENT_TIMESTAMP)");
                $insert_part->execute([$conversation_id, $current_user_id, $branch1]);
                $insert_part->execute([$conversation_id, $recipient_id, $branch2]);
            }

            send_response(['success' => true, 'conversation_id' => $conversation_id]);
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
