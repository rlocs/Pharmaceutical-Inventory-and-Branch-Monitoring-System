<?php
session_start();
require_once '../dbconnection.php';

// Check if user is logged in and is Admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_role"] !== 'Admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    $action = $_POST['action'] ?? '';
    
    switch($action) {
        case 'add':
            // Add new staff member
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $role = trim($_POST['role'] ?? 'Staff');
            $position = trim($_POST['position'] ?? '');
            $branch_id = intval($_POST['branch_id'] ?? 1);
            
            // Validate required fields
            if (empty($first_name) || empty($last_name) || empty($email) || empty($role) || empty($branch_id)) {
                echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
                exit;
            }
            
            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'Invalid email format']);
                exit;
            }
            
            // Check if email already exists
            $checkEmail = $pdo->prepare("SELECT UserID FROM Accounts WHERE Email = ?");
            $checkEmail->execute([$email]);
            if ($checkEmail->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Email already exists']);
                exit;
            }
            
            // Generate unique user code
            $branchPrefix = 'B' . $branch_id;
            $userCode = generateUserCode($pdo, $branchPrefix, $role);
            
            // Generate temporary password
            $temp_password = generateTemporaryPassword();
            $hashed_password = password_hash($temp_password, PASSWORD_BCRYPT);
            
            // Start transaction
            $pdo->beginTransaction();
            
            try {
                // Insert into Accounts table
                $stmt = $pdo->prepare("INSERT INTO Accounts (BranchID, UserCode, FirstName, LastName, Email, HashedPassword, Role, AccountStatus, DateCreated) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, 'Active', NOW())");
                $stmt->execute([$branch_id, $userCode, $first_name, $last_name, $email, $hashed_password, $role]);
                
                $user_id = $pdo->lastInsertId();
                
                // Insert into Details table
                $stmtDetails = $pdo->prepare("INSERT INTO Details (UserID, PersonalPhoneNumber, HireDate, Position) 
                                              VALUES (?, ?, CURDATE(), ?)");
                $stmtDetails->execute([$user_id, $phone, $position]);
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Staff account created successfully',
                    'user_code' => $userCode,
                    'temp_password' => $temp_password
                ]);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
            break;
            
        case 'update':
            // Update staff member
            $user_id = intval($_POST['user_id'] ?? 0);
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $position = trim($_POST['position'] ?? '');
            $branch_id = intval($_POST['branch_id'] ?? 1);
            $status = trim($_POST['status'] ?? 'Active');
            
            // Validate required fields
            if (empty($user_id) || empty($first_name) || empty($last_name) || empty($email)) {
                echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
                exit;
            }
            
            // Check if email already exists for another user
            $checkEmail = $pdo->prepare("SELECT UserID FROM Accounts WHERE Email = ? AND UserID != ?");
            $checkEmail->execute([$email, $user_id]);
            if ($checkEmail->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Email already exists for another user']);
                exit;
            }
            
            // Start transaction
            $pdo->beginTransaction();
            
            try {
                // Update Accounts table
                $stmt = $pdo->prepare("UPDATE Accounts 
                                       SET FirstName = ?, LastName = ?, Email = ?, BranchID = ?, AccountStatus = ?
                                       WHERE UserID = ?");
                $stmt->execute([$first_name, $last_name, $email, $branch_id, $status, $user_id]);
                
                // Update or insert Details
                $checkDetails = $pdo->prepare("SELECT UserID FROM Details WHERE UserID = ?");
                $checkDetails->execute([$user_id]);
                
                if ($checkDetails->fetch()) {
                    // Update existing details
                    $stmtDetails = $pdo->prepare("UPDATE Details 
                                                  SET PersonalPhoneNumber = ?, Position = ?
                                                  WHERE UserID = ?");
                    $stmtDetails->execute([$phone, $position, $user_id]);
                } else {
                    // Insert new details
                    $stmtDetails = $pdo->prepare("INSERT INTO Details (UserID, PersonalPhoneNumber, HireDate, Position) 
                                                  VALUES (?, ?, CURDATE(), ?)");
                    $stmtDetails->execute([$user_id, $phone, $position]);
                }
                
                $pdo->commit();
                
                echo json_encode(['success' => true, 'message' => 'Staff account updated successfully']);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
            break;
            
        case 'delete':
            // Delete staff member
            $user_id = intval($_POST['user_id'] ?? 0);
            
            if (empty($user_id)) {
                echo json_encode(['success' => false, 'message' => 'User ID is required']);
                exit;
            }
            
            // Prevent deleting self
            if ($user_id == $_SESSION['user_id']) {
                echo json_encode(['success' => false, 'message' => 'Cannot delete your own account']);
                exit;
            }
            
            // Start transaction
            $pdo->beginTransaction();
            
            try {
                // Delete from Details table first (due to foreign key)
                $stmtDetails = $pdo->prepare("DELETE FROM Details WHERE UserID = ?");
                $stmtDetails->execute([$user_id]);
                
                // Delete from Accounts table
                $stmt = $pdo->prepare("DELETE FROM Accounts WHERE UserID = ?");
                $stmt->execute([$user_id]);
                
                $pdo->commit();
                
                echo json_encode(['success' => true, 'message' => 'Staff account deleted successfully']);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

// Helper function to generate unique user code
function generateUserCode($pdo, $branchPrefix, $role) {
    $rolePrefix = ($role === 'Admin') ? 'ADMIN' : 'STAFF';
    
    // Get the highest existing number for this branch and role
    $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(UserCode, LENGTH(?)+1) AS UNSIGNED)) as max_num 
                           FROM Accounts 
                           WHERE UserCode LIKE ?");
    $likePattern = $branchPrefix . $rolePrefix . '%';
    $stmt->execute([$branchPrefix . $rolePrefix, $likePattern]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $nextNum = ($result['max_num'] ?? 0) + 1;
    
    return $branchPrefix . $rolePrefix . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
}

// Helper function to generate temporary password
function generateTemporaryPassword($length = 8) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    
    return $randomString;
}
?>