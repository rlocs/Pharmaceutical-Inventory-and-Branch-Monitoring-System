<?php
// ensure session cookie is available site-wide
session_set_cookie_params(0, '/');
session_start();
// The database connection file MUST use PDO and define a Database class with a getConnection() method.
require_once 'dbconnection.php'; 

// --- Configuration ---
try {
    // Assuming dbconnection.php defines a Database class and returns a PDO connection
    $db = new Database();
    $conn = $db->getConnection();
} catch (Exception $e) {
    // Log the error and redirect with a generic message
    error_log("Database connection error: " . $e->getMessage());
    $_SESSION['login_alert'] = 'System error: Could not connect to the database.';
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --------------------------------------------------------------------------
    // A. FORGOT PASSWORD LOGIC (Reset Password with UserCode and DateOfBirth)
    // --------------------------------------------------------------------------
    if (isset($_POST['confirm_password']) && isset($_POST['dob']) && isset($_POST['username'])) {
        
        $userCode = trim($_POST['username']); 
        $dob = trim($_POST['dob']);
        $password = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);

        // Input validation
        if (empty($userCode) || empty($dob) || empty($password) || empty($confirm_password)) {
            $_SESSION['login_alert'] = 'Please fill in all fields.';
            header("Location: forgot.php");
            exit;
        }

        if ($password !== $confirm_password) {
            $_SESSION['login_alert'] = 'Passwords do not match.';
            header("Location: forgot.php");
            exit;
        }

        try {
            // Hash the new password before sending it to the stored procedure
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Use the SP_ResetPassword stored procedure
            $stmt = $conn->prepare("CALL SP_ResetPassword(:userCode, :dob, :newHashedPassword)");
            $stmt->bindParam(':userCode', $userCode, PDO::PARAM_STR);
            $stmt->bindParam(':dob', $dob, PDO::PARAM_STR);
            $stmt->bindParam(':newHashedPassword', $hashed_password, PDO::PARAM_STR);
            $stmt->execute();

            // Fetch the result from the SELECT in the stored procedure
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $isSuccess = ($result && $result['Success'] == 1);
            
            if ($isSuccess) {
                // Success! Redirect to login with a success message
                $_SESSION['login_alert'] = '✅ Password has been reset successfully. Please login with your new password.';
                header("Location: login.php");
                exit;
            } else {
                $_SESSION['login_alert'] = 'Username (Code) and Date of Birth do not match our records.';
                header("Location: forgot.php");
                exit;
            }

        } catch (PDOException $e) {
             error_log("Forgot Password Error: " . $e->getMessage());
            $_SESSION['login_alert'] = 'A system error occurred during password verification.';
            header("Location: forgot.php");
            exit;
        }

    } else {
        // --------------------------------------------------------------------------
        // B. STANDARD LOGIN LOGIC (Verify UserCode and HashedPassword)
        // --------------------------------------------------------------------------
        $userCode = trim($_POST['username']); 
        $password = trim($_POST['password']);

        // Input validation
        if (empty($userCode) || empty($password)) {
            $_SESSION['login_alert'] = 'Please fill in both fields.';
            header("Location: login.php");
            exit;
        }

        try {
            // Use the SP_AuthenticateUser stored procedure
            $stmt = $conn->prepare("CALL SP_AuthenticateUser(:userCode)");
            $stmt->bindParam(':userCode', $userCode, PDO::PARAM_STR);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Verify password against the stored hash
                if (password_verify($password, $user['HashedPassword'])) {
                    
                    // Password is correct: Set session variables
                    $_SESSION['loggedin'] = true;
                    $_SESSION['user_id'] = $user['UserID'];
                    $_SESSION['user_code'] = $user['UserCode'];
                    $_SESSION['user_role'] = $user['Role']; // This comes from the SP
                    $_SESSION['branch_id'] = $user['BranchID'];
                    $_SESSION['first_name'] = $user['FirstName']; // This comes from the SP
                    $_SESSION['last_name'] = $user['LastName']; // This comes from the SP

                    // Send push notifications for any pending alerts and chat messages
                    sendLoginNotifications($user['UserID'], $user['BranchID']);

                    // ROUTING LOGIC based on ROLE and BRANCHID
                    switch ($user['Role']) {
                        case 'Admin':
                            header("Location: admin1/admin1b1.php");
                            exit;

                        case 'Staff':
                            $branchId = $_SESSION['branch_id'];
                            header("Location: branch{$branchId}/staff1b{$branchId}.php");
                            exit;

                        default:
                            $_SESSION['login_alert'] = 'Unknown role or account type.';
                            header("Location: login.php");
                            exit;
                    }
                } else {
                    $_SESSION['login_alert'] = 'Incorrect password.';
                    header("Location: login.php");
                    exit;
                }
            } else {
                $_SESSION['login_alert'] = 'Username (Code) not found.';
                header("Location: login.php");
                exit;
            }

        } catch (PDOException $e) {
             error_log("Login Error: " . $e->getMessage());
            $_SESSION['login_alert'] = 'A database error occurred during login.';
            header("Location: login.php");
            exit;
        }
    }
} else {
    // If user accessed b-login.php directly without POST data
    header("Location: login.php");
    exit;
}

/**
 * Send push notifications for pending alerts and chat messages on login
 */
function sendLoginNotifications($userId, $branchId) {
    global $conn;

    try {
        // Get alerts count
        $alertsCount = getAlertsCount($conn, $branchId);

        // Get unread chat count
        $chatCount = getUnreadChatCount($conn, $userId);

        // Send notifications if there are any
        if ($alertsCount > 0 || $chatCount > 0) {
            // Send alert notification
            if ($alertsCount > 0) {
                sendNotification($conn, $userId, 'alert', 'Inventory Alerts', "You have {$alertsCount} inventory alerts requiring attention", 'med_inventory.php');
            }

            // Send chat notification
            if ($chatCount > 0) {
                sendNotification($conn, $userId, 'chat', 'New Messages', "You have {$chatCount} unread chat messages", '#chat');
            }
        }
    } catch (Exception $e) {
        error_log("Error sending login notifications: " . $e->getMessage());
        // Don't fail login if notifications fail
    }
}

/**
 * Get alerts count for a branch
 */
function getAlertsCount($conn, $branchId) {
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
    $stmt->execute([$branchId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int)($result['count'] ?? 0);
}





?>
