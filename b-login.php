<?php
session_start();
require_once 'dbConnection.php'; // Make sure this contains the Database class

$db = new Database();
$conn = $db->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if this is a forgot password form submission
    if (isset($_POST['confirm_password']) && isset($_POST['dob']) && isset($_POST['username'])) {
        // Forgot password logic with username and dob verification
        $username = trim($_POST['username']);
        $dob = trim($_POST['dob']);
        $password = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);

        if (!empty($username) && !empty($dob) && !empty($password) && !empty($confirm_password)) {
            if ($password !== $confirm_password) {
                echo "<script>alert('Passwords do not match.'); window.location='forgot.php';</script>";
                exit;
            }

            // Check if user exists with matching username and dob
            $query = "SELECT * FROM users WHERE username = :username AND dob = :dob";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->bindParam(':dob', $dob, PDO::PARAM_STR);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Inline change password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $update_query = "UPDATE users SET password = :password WHERE username = :username";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bindParam(':password', $hashed_password, PDO::PARAM_STR);
                $update_stmt->bindParam(':username', $username, PDO::PARAM_STR);

                if ($update_stmt->execute()) {
                    echo "<script>alert('Password has been reset successfully. Please login with your new password.'); window.location='login.php';</script>";
                    exit;
                } else {
                    echo "<script>alert('Failed to update password. Please try again.'); window.location='forgot.php';</script>";
                    exit;
                }
            } else {
                echo "<script>alert('Username and Date of Birth do not match our records.'); window.location='forgot.php';</script>";
                exit;
            }
        } else {
            echo "<script>alert('Please fill in all fields.'); window.location='forgot.php';</script>";
            exit;
        }
    } else {
        // Existing login logic
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);

        if (!empty($username) && !empty($password)) {
            $query = "SELECT * FROM users WHERE username = :username";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];

                    // Redirect based on role
                    switch ($user['role']) {
                        case 'Admin':
                            header("Location: admin/index.php");
                            exit;
                        case 'Staff':
                            header("Location: staff/index.php");
                            exit;
                        default:
                            echo "Unknown role.";
                            exit;
                    }
                } else {
                    echo "<script>alert('Incorrect password.'); window.location='login.php';</script>";
                }
            } else {
                echo "<script>alert('Username not found.'); window.location='login.php';</script>";
            }
        } else {
            echo "<script>alert('Please fill in both fields.'); window.location='login.php';</script>";
        }
    }
} else {
    header("Location: login.php");
    exit;
}
?>
