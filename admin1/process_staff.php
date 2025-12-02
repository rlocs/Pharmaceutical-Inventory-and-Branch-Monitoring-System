<?php
session_start();
require_once '../dbconnection.php';

// Check authentication
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_role"] !== 'Admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Unknown action'];
    
    switch ($action) {
        case 'add':
            // Validate input
            $required_fields = ['first_name', 'last_name', 'email', 'role', 'branch_id'];
            foreach ($required_fields as $field) {
                if (empty($_POST[$field])) {
                    throw new Exception("Missing required field: $field");
                }
            }
            
            // Generate user code (e.g., STAFF2B1)
            $branch_id = intval($_POST['branch_id']);
            $role = $_POST['role'];
            
            // Get next staff number for this branch
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM Accounts WHERE BranchID = ? AND Role != 'Admin'");
            $stmt->execute([$branch_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $staff_number = $result['count'] + 1;
            
            $user_code = ($role === 'Admin' ? 'ADMIN' : 'STAFF') . $staff_number . 'B' . $branch_id;
            
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT UserID FROM Accounts WHERE Email = ?");
            $stmt->execute([$_POST['email']]);
            if ($stmt->fetch()) {
                throw new Exception("Email already exists");
            }
            
            // Generate temporary password
            $temp_password = bin2hex(random_bytes(8)); // 16 character password
            $hashed_password = password_hash($temp_password, PASSWORD_BCRYPT);
            
            // Start transaction
            $pdo->beginTransaction();
            
            // Insert into Accounts table
            $stmt = $pdo->prepare("
                INSERT INTO Accounts (BranchID, UserCode, FirstName, LastName, Email, HashedPassword, Role, AccountStatus)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Active')
            ");
            $stmt->execute([
                $branch_id,
                $user_code,
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['email'],
                $hashed_password,
                $role
            ]);
            
            $user_id = $pdo->lastInsertId();
            
            // Insert into Details table with default values
            $stmt = $pdo->prepare("
                INSERT INTO Details (UserID, HireDate, Position)
                VALUES (?, CURDATE(), ?)
            ");
            $stmt->execute([$user_id, $role]);
            
            // Create user directory structure
            createUserDirectoryStructure($branch_id, $user_id, $role, $_POST['first_name'] . ' ' . $_POST['last_name']);
            
            // Send email with login credentials
            sendWelcomeEmail($_POST['email'], $_POST['first_name'], $user_code, $temp_password);
            
            $pdo->commit();
            
            $response = [
                'success' => true, 
                'message' => 'Staff account created successfully',
                'user_id' => $user_id,
                'user_code' => $user_code,
                'temp_password' => $temp_password // Only for immediate display, not stored
            ];
            break;
            
        case 'update':
            $user_id = intval($_POST['user_id']);
            
            // Update Accounts table
            $stmt = $pdo->prepare("
                UPDATE Accounts 
                SET FirstName = ?, LastName = ?, Email = ?, BranchID = ?, AccountStatus = ?
                WHERE UserID = ?
            ");
            $stmt->execute([
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['email'],
                $_POST['branch_id'],
                $_POST['status'],
                $user_id
            ]);
            
            $response = ['success' => true, 'message' => 'Staff account updated successfully'];
            break;
            
        case 'delete':
            $user_id = intval($_POST['user_id']);
            
            // Check if user exists and is not an admin
            $stmt = $pdo->prepare("SELECT UserID, Role FROM Accounts WHERE UserID = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                throw new Exception("User not found");
            }
            
            if ($user['Role'] === 'Admin') {
                throw new Exception("Cannot delete admin accounts");
            }
            
            // Soft delete (update status to Inactive)
            $stmt = $pdo->prepare("UPDATE Accounts SET AccountStatus = 'Inactive' WHERE UserID = ?");
            $stmt->execute([$user_id]);
            
            // Or hard delete (uncomment if you want to permanently delete)
            // $stmt = $pdo->prepare("DELETE FROM Details WHERE UserID = ?");
            // $stmt->execute([$user_id]);
            // $stmt = $pdo->prepare("DELETE FROM Accounts WHERE UserID = ?");
            // $stmt->execute([$user_id]);
            
            $response = ['success' => true, 'message' => 'Staff account deleted successfully'];
            break;
            
        case 'reset_password':
            $user_id = intval($_POST['user_id']);
            
            // Generate new temporary password
            $temp_password = bin2hex(random_bytes(8));
            $hashed_password = password_hash($temp_password, PASSWORD_BCRYPT);
            
            $stmt = $pdo->prepare("UPDATE Accounts SET HashedPassword = ? WHERE UserID = ?");
            $stmt->execute([$hashed_password, $user_id]);
            
            // Get user email
            $stmt = $pdo->prepare("SELECT Email, FirstName FROM Accounts WHERE UserID = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                sendPasswordResetEmail($user['Email'], $user['FirstName'], $temp_password);
            }
            
            $response = [
                'success' => true, 
                'message' => 'Password reset successfully',
                'temp_password' => $temp_password
            ];
            break;
    }
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response = ['success' => false, 'message' => $e->getMessage()];
}

header('Content-Type: application/json');
echo json_encode($response);

// Function to create user directory structure
function createUserDirectoryStructure($branch_id, $user_id, $role, $user_name) {
    $base_path = __DIR__ . "/../";
    
    // Determine base template directory based on role
    if ($role === 'Admin') {
        $template_dir = $base_path . "admin1/";
        $target_dir = $base_path . "admin" . $user_id . "/";
    } else {
        // For staff, use their branch template
        $template_dir = $base_path . "branch" . $branch_id . "/";
        $target_dir = $base_path . "staff" . $user_id . "b" . $branch_id . "/";
    }
    
    // Create target directory if it doesn't exist
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    // Copy all files from template directory
    copyDirectory($template_dir, $target_dir);
    
    // Update configuration files for the new user
    updateUserConfigFiles($target_dir, $user_id, $branch_id, $user_name);
    
    // Create a README file
    $readme_content = "User Directory for {$user_name}\n";
    $readme_content .= "Created: " . date('Y-m-d H:i:s') . "\n";
    $readme_content .= "User ID: {$user_id}\n";
    $readme_content .= "Branch ID: {$branch_id}\n";
    $readme_content .= "Role: {$role}\n";
    
    file_put_contents($target_dir . "README.txt", $readme_content);
}

// Function to recursively copy directory
function copyDirectory($source, $dest) {
    if (!file_exists($dest)) {
        mkdir($dest, 0755, true);
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $item) {
        $target = $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
        
        if ($item->isDir()) {
            if (!file_exists($target)) {
                mkdir($target, 0755, true);
            }
        } else {
            copy($item, $target);
        }
    }
}

// Function to update user-specific configuration
function updateUserConfigFiles($dir, $user_id, $branch_id, $user_name) {
    // Update PHP files that need user-specific configuration
    $files_to_update = [
        'config.php' => [
            'pattern' => '/define\(\'USER_ID\', \d+\);/',
            'replacement' => "define('USER_ID', {$user_id});"
        ],
        'index.php' => [
            'pattern' => '/<title>.*<\/title>/',
            'replacement' => "<title>{$user_name}'s Dashboard</title>"
        ]
    ];
    
    foreach ($files_to_update as $file => $config) {
        $file_path = $dir . $file;
        if (file_exists($file_path)) {
            $content = file_get_contents($file_path);
            $content = preg_replace($config['pattern'], $config['replacement'], $content);
            file_put_contents($file_path, $content);
        }
    }
}

// Function to send welcome email
function sendWelcomeEmail($to_email, $first_name, $user_code, $temp_password) {
    $subject = "Welcome to Mercury Pharmacy System";
    $message = "
    <html>
    <head>
        <title>Welcome to Mercury Pharmacy System</title>
    </head>
    <body>
        <h2>Welcome {$first_name}!</h2>
        <p>Your account has been created in the Mercury Pharmacy System.</p>
        <p><strong>Login Credentials:</strong></p>
        <ul>
            <li><strong>User Code:</strong> {$user_code}</li>
            <li><strong>Temporary Password:</strong> {$temp_password}</li>
        </ul>
        <p><strong>Important:</strong> Please change your password after first login.</p>
        <p>Login URL: http://your-domain.com/login.php</p>
        <p>If you have any questions, please contact your system administrator.</p>
        <br>
        <p>Best regards,<br>Mercury Pharmacy System</p>
    </body>
    </html>
    ";
    
    // Use PHPMailer or mail() function
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Mercury Pharmacy <noreply@mercurypharmacy.com>" . "\r\n";
    
    mail($to_email, $subject, $message, $headers);
}

// Function to send password reset email
function sendPasswordResetEmail($to_email, $first_name, $new_password) {
    $subject = "Password Reset - Mercury Pharmacy System";
    $message = "
    <html>
    <head>
        <title>Password Reset</title>
    </head>
    <body>
        <h2>Password Reset Request</h2>
        <p>Hello {$first_name},</p>
        <p>Your password has been reset by the administrator.</p>
        <p><strong>New Temporary Password:</strong> {$new_password}</p>
        <p><strong>Important:</strong> Please change your password after login.</p>
        <p>Login URL: http://your-domain.com/login.php</p>
        <br>
        <p>If you didn't request this reset, please contact your system administrator immediately.</p>
        <br>
        <p>Best regards,<br>Mercury Pharmacy System</p>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Mercury Pharmacy <noreply@mercurypharmacy.com>" . "\r\n";
    
    mail($to_email, $subject, $message, $headers);
}
?>