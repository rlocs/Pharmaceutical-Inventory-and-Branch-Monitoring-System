<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Reset your password for the Health Center System. Enter your username, date of birth, and new password.">
    <title>Forgot Password - Health Center System</title>

    <!-- Boxicons for icons -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/css/bootstrap.min.css" crossorigin="anonymous">
    <script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.12.9/dist/umd/popper.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/js/bootstrap.min.js" crossorigin="anonymous"></script>
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
            width: 400px;
            height: 500px; /* Taller for more fields */
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
            width: 116%;
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
            transform: scale(1.02);
        }

        .reset-title {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #25344f;
        }

        .back-link {
            text-align: center;
            width: 100%;
            margin-top: 10px;
        }

        .back-link a {
            font-size: 14px;
            color: #0040aa;
            text-decoration: none;
        }

        .back-link a:hover {
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
            <div class="reset-title">Reset Password</div>
            <form action="b-login.php" method="POST">
                <div class="form-group">
                    <i class='bx bxs-user'></i>
                    <input type="text" class="form-control" name="username" placeholder="Username" required>
                </div>
                <div class="form-group">
                    <i class='bx bxs-calendar'></i>
                    <input type="date" class="form-control" name="dob" placeholder="Date of Birth" required>
                </div>
                <div class="form-group">
                    <i class='bx bxs-lock-alt'></i>
                    <input type="password" class="form-control" name="password" placeholder="New Password" required>
                </div>
                <div class="form-group">
                    <i class='bx bxs-lock'></i>
                    <input type="password" class="form-control" name="confirm_password" placeholder="Confirm New Password" required>
                </div>
                <button type="submit" class="btn-reset">Reset Password</button>
            </form>
            <div class="back-link">
                <a href="login.php">Back to Login</a>
            </div>
        </div>
    </div>
</body>

</html>
