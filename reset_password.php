<?php
session_start();
require_once 'dbconnection.php';
require_once 'config.php';

$error = '';
$success = '';

// Redirect if OTP not verified
if (!isset($_SESSION['otp_verified']) || $_SESSION['otp_verified'] !== true || !isset($_SESSION['otp_user_id'])) {
    header('Location: forgot.php');
    exit();
}

if (isset($_POST['reset_password'])) {
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    // Basic validation
    if ($new_password === '' || $confirm_password === '') {
        $error = 'Please fill in both password fields.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        // Validate password requirements from config
        $min_length = intval(Config::get('PASSWORD_MIN_LENGTH', 8));
        $require_upper = Config::get('PASSWORD_REQUIRE_UPPERCASE', 'true') === 'true';
        $require_lower = Config::get('PASSWORD_REQUIRE_LOWERCASE', 'true') === 'true';
        $require_number = Config::get('PASSWORD_REQUIRE_NUMBERS', 'true') === 'true';
        $require_special = Config::get('PASSWORD_REQUIRE_SPECIAL', 'true') === 'true';

        $validation_errors = [];
        if (strlen($new_password) < $min_length) {
            $validation_errors[] = "at least $min_length characters";
        }
        if ($require_upper && !preg_match('/[A-Z]/', $new_password)) {
            $validation_errors[] = 'uppercase letter';
        }
        if ($require_lower && !preg_match('/[a-z]/', $new_password)) {
            $validation_errors[] = 'lowercase letter';
        }
        if ($require_number && !preg_match('/\\d/', $new_password)) {
            $validation_errors[] = 'number';
        }
        if ($require_special && !preg_match('/[@$!%*?&]/', $new_password)) {
            $validation_errors[] = 'special character';
        }

        if (!empty($validation_errors)) {
            $error = 'Password must contain: ' . implode(', ', $validation_errors) . '.';
        } else {
            try {
                $db = new Database();
                $conn = $db->getConnection();

                // Hash the new password and update DB
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                $stmt = $conn->prepare('UPDATE Accounts SET HashedPassword = ? WHERE UserID = ?');
                $stmt->execute([$hashed_password, $_SESSION['otp_user_id']]);

                // Mark reset success and clear OTP session keys
                $_SESSION['reset_success'] = true;
                unset($_SESSION['otp_verified'], $_SESSION['otp_user_id'], $_SESSION['otp_email'], $_SESSION['otp_user_name']);

                // Redirect to login with success
                header('Location: b-login.php?reset=success');
                exit();
            } catch (Exception $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Pharmaceutical System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .glass-effect {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .password-strength {
            height: 4px;
            border-radius: 2px;
            margin-top: 8px;
            transition: all 0.3s ease;
        }
        .strength-weak { background-color: #ef4444; }
        .strength-fair { background-color: #f59e0b; }
        .strength-good { background-color: #3b82f6; }
        .strength-strong { background-color: #10b981; }
    </style>
</head>
<body>
    <div class="w-full max-w-md mx-auto px-4">
        <div class="glass-effect rounded-2xl shadow-2xl p-8">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                    </svg>
                </div>
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Reset Password</h1>
                <p class="text-gray-600">Create a strong new password</p>
            </div>

            <!-- Error Message -->
            <?php if (!empty($error)) { ?>
                <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 rounded">
                    <p class="text-red-700 text-sm font-medium">
                        <svg class="w-5 h-5 inline mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                        </svg>
                        <?php echo htmlspecialchars($error); ?>
                    </p>
                </div>
            <?php } ?>

            <!-- Password Requirements -->
            <div class="mb-6 p-4 bg-blue-50 border-l-4 border-blue-500 rounded text-sm">
                <p class="font-semibold text-gray-700 mb-2">Password Requirements:</p>
                <ul class="text-gray-600 space-y-1 text-xs">
                    <li>✓ At least 8 characters</li>
                    <li>✓ One uppercase letter (A-Z)</li>
                    <li>✓ One lowercase letter (a-z)</li>
                    <li>✓ One number (0-9)</li>
                    <li>✓ One special character (@$!%*?&)</li>
                </ul>
            </div>

            <!-- Form -->
            <form method="POST" action="">
                <div class="mb-5">
                    <label for="new_password" class="block text-gray-700 font-semibold mb-2">New Password</label>
                    <div class="relative">
                        <input type="password" id="new_password" name="new_password" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:border-transparent transition"
                               placeholder="Enter new password" required>
                        <button type="button" onclick="togglePassword('new_password')" class="absolute right-3 top-2.5 text-gray-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                        </button>
                    </div>
                    <div class="password-strength" id="strength-meter"></div>
                </div>

                <div class="mb-6">
                    <label for="confirm_password" class="block text-gray-700 font-semibold mb-2">Confirm Password</label>
                    <div class="relative">
                        <input type="password" id="confirm_password" name="confirm_password" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:border-transparent transition"
                               placeholder="Confirm new password" required>
                        <button type="button" onclick="togglePassword('confirm_password')" class="absolute right-3 top-2.5 text-gray-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" name="reset_password" 
                        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 rounded-lg transition duration-200">
                    Reset Password
                </button>
            </form>

            <!-- Back to Login Link -->
            <div class="text-center mt-6">
                <p class="text-gray-600 text-sm">
                    <a href="b-login.php" class="text-indigo-600 hover:text-indigo-700 font-semibold">Back to Login</a>
                </p>
            </div>
        </div>

        <!-- Footer -->
        <p class="text-center text-white text-xs mt-8 opacity-80">
            © 2025 Pharmaceutical Cross-Branch System. All rights reserved.
        </p>
    </div>

    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            field.type = field.type === 'password' ? 'text' : 'password';
        }

        // Password strength meter
        document.getElementById('new_password')?.addEventListener('input', function() {
            const password = this.value;
            const meter = document.getElementById('strength-meter');
            let strength = 0;

            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;
            if (/[@$!%*?&]/.test(password)) strength++;

            meter.classList.remove('strength-weak', 'strength-fair', 'strength-good', 'strength-strong');
            if (strength < 2) meter.classList.add('strength-weak');
            else if (strength < 3) meter.classList.add('strength-fair');
            else if (strength < 5) meter.classList.add('strength-good');
            else meter.classList.add('strength-strong');
        });
    </script>
</body>
</html>
