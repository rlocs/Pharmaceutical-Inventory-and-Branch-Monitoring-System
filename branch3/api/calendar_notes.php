<?php
session_start();
require_once '../../dbconnection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userID = $_SESSION['user_id'];
$branchID = $_SESSION['branch_id'];

header('Content-Type: application/json');

try {
    $db = new Database();
    $conn = $db->getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // 1. GET Request - Fetch Notes using PDO
        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-t');

        // Sanitize
        $startDate = filter_var($startDate, FILTER_SANITIZE_STRING);
        $endDate = filter_var($endDate, FILTER_SANITIZE_STRING);

        // We use DATE_FORMAT to ensure the key is always "YYYY-MM-DD" matching JS
        $sql = "SELECT NoteID, DATE_FORMAT(NoteDate, '%Y-%m-%d') as NoteDate, NoteText, CreatedAt
                FROM CalendarNotes
                WHERE UserID = ? AND BranchID = ? AND NoteDate BETWEEN ? AND ?
                ORDER BY NoteDate ASC";

        $stmt = $conn->prepare($sql);
        $stmt->execute([$userID, $branchID, $startDate, $endDate]);

        // Fetch all rows as an associative array
        $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($notes);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // 2. POST Request - Save Note using PDO
        $input = json_decode(file_get_contents('php://input'), true);
        $noteDate = $input['note_date'] ?? '';
        $noteText = $input['note_text'] ?? '';

        // Sanitize
        $noteDate = filter_var($noteDate, FILTER_SANITIZE_STRING);
        $noteText = filter_var($noteText, FILTER_SANITIZE_STRING);

        if (empty($noteDate)) {
            http_response_code(400);
            echo json_encode(['error' => 'Note date is required']);
            exit;
        }

        // Use ON DUPLICATE KEY UPDATE to handle edits
        $sql = "INSERT INTO CalendarNotes (UserID, BranchID, NoteDate, NoteText)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE NoteText = VALUES(NoteText), CreatedAt = CURRENT_TIMESTAMP";

        $stmt = $conn->prepare($sql);
        // In PDO, we pass parameters directly to execute
        $result = $stmt->execute([$userID, $branchID, $noteDate, $noteText]);

        if ($result) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save note']);
        }

    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }

} catch (Exception $e) {
    error_log("Database error in calendar_notes.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
