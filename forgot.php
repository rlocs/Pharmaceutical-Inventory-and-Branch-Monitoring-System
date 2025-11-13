<?php
session_start();

// The database connection file MUST use PDO and define a Database class with a getConnection() method.
require_once 'dbconnection.php';

// --- Configuration ---
try {
    // Establish database connection
    $db = new Database();
    $conn = $db->getConnection();
} catch (Exception $e) {
    // Log the error and show a generic message if connection fails
    error_log("Database connection error in forgot.php: " . $e->getMessage());
    $_SESSION['login_alert'] = 'System error: Could not connect to the database.';
    // Redirect to login page if DB is down, as this page is unusable.
    header("Location: login.php");
    exit;
}

// Check for and clear any alert messages stored in the session
$login_alert = '';
if (isset($_SESSION['login_alert'])) {
    $login_alert = $_SESSION['login_alert'];
    unset($_SESSION['login_alert']);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Pharmaceutical System</title>

    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/css/bootstrap.min.css" crossorigin="anonymous">
    <style>
        * {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background-color: rgb(255, 255, 255);
        }

        .container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0;
            padding: 0;
        }

        .square {
            width: 450px; /* Increased width for more space */
            height: auto; /* Auto height to fit content */
            min-height: 400px;
            display: flex;
            justify-content: center;
            align-items: center;
            border: 1px solid #ccc;
            background-color: rgb(203, 202, 209);
            box-shadow: 0 6px 8px rgba(22, 22, 22, 0.1);
            border-radius: 15px;
            flex-direction: column;
            padding: 28px;
        }

        .form-group {
            position: relative;
            margin-bottom: 20px;
            width: 100%;
        }

        .form-group i {
            position: absolute;
            top: 50%;
            left: 10px;
            transform: translateY(-50%);
            color: #444;
        }

        .form-control {
            padding-left: 35px;
        }

        .btn-reset {
            width: 100%;
            background-color: rgb(37, 52, 79);
            color: white;
            padding: 12px;
            font-size: 16px;
            border: none;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .btn-reset:hover {
            background-color: rgb(25, 54, 243);
        }

        .reset-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #25344f;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="square">
            <div class="reset-title">Reset Your Password</div>

            <?php
            if (!empty($login_alert)) {
                echo '<div class="alert alert-danger" role="alert" style="width: 100%;">' . htmlspecialchars($login_alert) . '</div>';
            }
            ?>
            
            <form action="b-login.php" method="POST">
                <div class="form-group"><i class='bx bxs-user'></i><input type="text" class="form-control" name="username" placeholder="User Code" required></div>
                <div class="form-group"><i class='bx bxs-calendar'></i><input type="date" class="form-control" name="dob" placeholder="Date of Birth" required></div>
                <div class="form-group"><i class='bx bxs-lock-alt'></i><input type="password" class="form-control" name="password" placeholder="New Password" required></div>
                <div class="form-group"><i class='bx bxs-lock-alt'></i><input type="password" class="form-control" name="confirm_password" placeholder="Confirm New Password" required></div>
                <button type="submit" class="btn-reset">Reset Password</button>
                <div class="text-center mt-3"><a href="login.php">Back to Login</a></div>
            </form>
        </div>
    </div>
</body>

</html>