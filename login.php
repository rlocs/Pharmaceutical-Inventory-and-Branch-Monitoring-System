<?php
// Start the session to check for existing login state or alerts
session_start();

// Check for and clear any login error messages stored in the session by b-login.php
$login_alert = '';
if (isset($_SESSION['login_alert'])) {
    $login_alert = $_SESSION['login_alert'];
    // Clear the alert so it doesn't reappear on refresh
    unset($_SESSION['login_alert']);
}

// Always reset the login state *after* capturing the alert.
// This ensures that visiting the login page is a clean start and prevents redirect loops.
if (isset($_SESSION['loggedin'])) {
    // Unset all of the session variables.
    $_SESSION = array();
    // Finally, destroy the session.
    session_destroy();
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Login to access the Pharmaceutical Inventory and Branch Monitoring System. Secure and easy login process.">
    <title>Login - Pharmaceutical Inventory and Branch Monitoring System</title>

    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/css/bootstrap.min.css" crossorigin="anonymous">
    <script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.12.9/dist/umd/popper.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/js/bootstrap.min.js" crossorigin="anonymous"></script>
    <style>
        /* CSS styles remain exactly the same as provided */
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
            width: 400px;
            height: 400px;
            display: flex;
            justify-content: center;
            align-items: center;
            border: 1px solid #ccc;
            background-color: rgb(203, 202, 209);
            box-shadow: 0 6px 8px rgba(22, 22, 22, 0.1);
            border-radius: 15px;
            flex-direction: column;
            padding: 27.9px;
        }

        .square img {
            width: 90%; /* Adjusted for better responsiveness */
            height: auto; 
            border-radius: 10px;
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

        .btn-login {
            width: 100%;
            background-color: rgb(37, 52, 79);
            color: white;
            padding: 12px;
            font-size: 16px;
            border: none;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .btn-login:hover {
            background-color: rgb(25, 54, 243);
            transform: scale(1.02);
        }

        .login-title {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #25344f;
        }

        .forgot-password {
            text-align: right;
            width: 100%;
            margin-top: -10px;
            margin-bottom: 15px;
        }

        .forgot-password a {
            font-size: 14px;
            color: #0040aa;
            text-decoration: none;
        }

        .forgot-password a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="square">
            <img src="sanpedro.png" alt="Health Center Logo">
        </div>
        <div class="square">
            <div class="login-title">Login</div>

            <?php
            // Display any login alerts with conditional styling for success/error.
            if (!empty($login_alert)) {
                // Check if the alert is a success message
                if (strpos($login_alert, '✅') !== false) {
                    $alert_class = 'alert-success';
                } else {
                    $alert_class = 'alert-danger';
                }
                echo '<div class="alert ' . $alert_class . '" role="alert" style="width: 100%;">
                        ' . htmlspecialchars($login_alert, ENT_QUOTES, 'UTF-8') . '
                      </div>';
            }
            ?>
            
            <form action="b-login.php" method="POST">
                <div class="form-group">
                    <i class='bx bxs-user'></i>
                    <input type="text" class="form-control" name="username" id="username" placeholder="User Code" required> 
                </div>
                <div class="form-group">
                    <i class='bx bxs-lock-alt'></i>
                    <input type="password" class="form-control" name="password" id="password" placeholder="Password" required>
                </div>
                <div class="forgot-password">
                    <a href="forgot.php">Forgot password?</a>
                </div>
                <button type="submit" class="btn-login">Login</button>
            </form>
        </div>
    </div>
</body>

</html>