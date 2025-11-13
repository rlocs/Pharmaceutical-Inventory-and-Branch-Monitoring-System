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

switch ($action) {
    // --- Get all conversations for the current user ---
    case 'get_conversations':
        try {
            $stmt = $conn->prepare("CALL SP_GetConversations(:p_UserID)");
            $stmt->bindParam(':p_UserID', $current_user_id, PDO::PARAM_INT);
            $stmt->execute();
            $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            send_response(['success' => true, 'conversations' => $conversations]);
        } catch (PDOException $e) {
            error_log("SP_GetConversations error: " . $e->getMessage());
            send_response(['success' => false, 'error' => 'Failed to fetch conversations.']);
        }
        break;

    // --- Get all messages for a specific conversation ---
    case 'get_messages':
        $conversation_id = $_GET['conversation_id'] ?? 0;
        if (empty($conversation_id)) {
            send_response(['success' => false, 'error' => 'Conversation ID is required.']);
        }

        try {
            // First, update the last read timestamp for the user in this conversation
            $updateStmt = $conn->prepare("CALL SP_UpdateLastRead(:p_ConversationID, :p_UserID)");
            $updateStmt->bindParam(':p_ConversationID', $conversation_id, PDO::PARAM_INT);
            $updateStmt->bindParam(':p_UserID', $current_user_id, PDO::PARAM_INT);
            $updateStmt->execute();

            // Then, fetch the messages
            $stmt = $conn->prepare("CALL SP_GetMessages(:p_ConversationID)");
            $stmt->bindParam(':p_ConversationID', $conversation_id, PDO::PARAM_INT);
            $stmt->execute();
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            send_response(['success' => true, 'messages' => $messages]);
        } catch (PDOException $e) {
            error_log("SP_GetMessages error: " . $e->getMessage());
            send_response(['success' => false, 'error' => 'Failed to fetch messages.']);
        }
        break;

    // --- Send a new message ---
    case 'send_message':
        $conversation_id = $_POST['conversation_id'] ?? 0;
        $message_content = trim($_POST['message'] ?? '');

        if (empty($conversation_id) || empty($message_content)) {
            send_response(['success' => false, 'error' => 'Conversation ID and message content are required.']);
        }

        try {
            $stmt = $conn->prepare("CALL SP_SendMessage(:p_ConversationID, :p_SenderUserID, :p_MessageContent)");
            $stmt->bindParam(':p_ConversationID', $conversation_id, PDO::PARAM_INT);
            $stmt->bindParam(':p_SenderUserID', $current_user_id, PDO::PARAM_INT);
            $stmt->bindParam(':p_MessageContent', $message_content, PDO::PARAM_STR);
            $stmt->execute();
            
            // Fetch the newly created message to return to the client
            $newMessage = $stmt->fetch(PDO::FETCH_ASSOC);

            send_response(['success' => true, 'message' => $newMessage]);
        } catch (PDOException $e) {
            error_log("SP_SendMessage error: " . $e->getMessage());
            send_response(['success' => false, 'error' => 'Failed to send message.']);
        }
        break;

    // --- Get all users to start a new chat ---
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

    // --- Create a new conversation with another user ---
    case 'create_conversation':
        $recipient_id = $_POST['recipient_id'] ?? 0;
        if (empty($recipient_id) || $recipient_id == $current_user_id) {
            send_response(['success' => false, 'error' => 'Valid recipient ID is required.']);
        }

        try {
            // Use the stored procedure to find or create a conversation
            $stmt = $conn->prepare("CALL SP_FindOrCreateConversation(:p_User1_ID, :p_User2_ID, @p_ConversationID)");
            $stmt->bindParam(':p_User1_ID', $current_user_id, PDO::PARAM_INT);
            $stmt->bindParam(':p_User2_ID', $recipient_id, PDO::PARAM_INT);
            $stmt->execute();
            $stmt->closeCursor();

            // Fetch the output parameter
            $result = $conn->query("SELECT @p_ConversationID AS conversationId")->fetch(PDO::FETCH_ASSOC);
            $conversation_id = $result['conversationId'];

            if ($conversation_id) {
                send_response(['success' => true, 'conversation_id' => $conversation_id]);
            } else {
                send_response(['success' => false, 'error' => 'Could not create or find conversation.']);
            }
        } catch (PDOException $e) {
            error_log("SP_FindOrCreateConversation error: " . $e->getMessage());
            send_response(['success' => false, 'error' => 'Database error while creating conversation.']);
        }
        break;

    default:
        send_response(['success' => false, 'error' => 'Invalid action specified.']);
        break;
}
?>