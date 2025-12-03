<?php
session_start();
require_once '../../dbconnection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userID = $_SESSION['user_id'];
$branchID = $_SESSION['branch_id'];

try {
    $db = new Database();
    $conn = $db->getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $conn->prepare("CALL SP_GetToDoList(?, ?)");
        $stmt->execute([$userID, $branchID]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($tasks);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';

        if ($action === 'add') {
            $taskText = filter_var($input['text'] ?? '', FILTER_SANITIZE_STRING);
            if (!$taskText) {
                echo json_encode(['error' => 'Task text required']);
                exit;
            }
            $stmt = $conn->prepare("CALL SP_AddToDoItem(?, ?, ?)");
            $stmt->execute([$userID, $branchID, $taskText]);
            echo json_encode(['success' => true]);
        } elseif ($action === 'delete') {
            $taskID = $input['id'] ?? 0;
            $stmt = $conn->prepare("CALL SP_DeleteToDoItem(?, ?)");
            $stmt->execute([$taskID, $userID]);
            echo json_encode(['success' => true]);
        } elseif ($action === 'toggle') {
            $taskID = $input['id'] ?? 0;
            $isDone = $input['is_done'] ? 1 : 0;
            $stmt = $conn->prepare("CALL SP_ToggleToDoItem(?, ?, ?)");
            $stmt->execute([$taskID, $userID, $isDone]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Invalid action']);
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
