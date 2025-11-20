<?php
session_start();
require_once 'dbconnection.php';
require_once 'config.php';

$error = '';
$success = '';

if (isset($_POST['send_otp'])) {
    $user_code = trim($_POST['user_code'] ?? '');
    $phone_input = trim($_POST['phone'] ?? '');

    // Validate inputs
    if (empty($user_code) || empty($phone_input)) {
        $error = "Please enter both User Code and Phone number.";
    } else {
        try {
            $db = new Database();
            $conn = $db->getConnection();

            // Find user by UserCode
            $stmt = $conn->prepare("SELECT UserID, FirstName, LastName FROM Accounts WHERE UserCode = ? AND AccountStatus = 'Active'");
            $stmt->execute([$user_code]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                $user_id = $result['UserID'];
                $user_name = $result['FirstName'] . ' ' . $result['LastName'];

                // Get user's phone from Details table
                $phone_stmt = $conn->prepare("SELECT PersonalPhoneNumber FROM Details WHERE UserID = ?");
                $phone_stmt->execute([$user_id]);
                $phone_result = $phone_stmt->fetch(PDO::FETCH_ASSOC);

                if (!$phone_result || empty($phone_result['PersonalPhoneNumber'])) {
                    $error = "Phone number not found in system. Please contact administrator.";
                } else {
                    $stored_phone = $phone_result['PersonalPhoneNumber'];

                    // Normalize phone numbers (remove non-digits) before comparing
                    $provided_norm = preg_replace('/\D+/', '', $phone_input);
                    $stored_norm = preg_replace('/\D+/', '', $stored_phone);

                    if ($provided_norm !== $stored_norm) {
                        $error = "User Code and Phone number do not match our records.";
                    } else {
                        // Generate OTP
                        $otp_length = intval(Config::get('OTP_LENGTH', 6));
                        $otp = str_pad(rand(0, pow(10, $otp_length) - 1), $otp_length, '0', STR_PAD_LEFT);

                        // Delete old OTPs for this user
                        $delete_stmt = $conn->prepare("DELETE FROM OTPVerification WHERE UserID = ? AND IsUsed = 0");
                        $delete_stmt->execute([$user_id]);

                        // Store OTP in database using DB server clock
                        $otp_expiry_minutes = intval(Config::get('OTP_EXPIRY_MINUTES', 10));
                        $insert_stmt = $conn->prepare("INSERT INTO OTPVerification (UserID, OTPCode, ExpiresAt) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))");
                        $insert_stmt->execute([$user_id, $otp, $otp_expiry_minutes]);

                        // Send OTP via SMS API
                        $api_token = Config::get('SMS_API_TOKEN');
                        $api_endpoint = Config::get('SMS_API_ENDPOINT');
                        $app_name = Config::get('APP_NAME', 'Pharmaceutical System');
                        $message = "Your $app_name OTP is: $otp. Valid for $otp_expiry_minutes minutes.";

                        $data = [
                            'api_token' => $api_token,
                            'phone_number' => $stored_phone,
                            'message' => $message
                        ];

                        $timeout = intval(Config::get('SMS_API_TIMEOUT', 30));
                        $ch = curl_init($api_endpoint);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

                        $response = curl_exec($ch);
                        $curl_error = curl_error($ch);
                        curl_close($ch);

                        // Log API response for debugging
                        @file_put_contents(__DIR__ . '/logs/otp_api.log', date('c') . " | send_otp | user_id={$user_id} | phone={$stored_phone} | curl_error=" . ($curl_error ?: 'none') . " | response=" . substr($response ?? '', 0, 2000) . "\n", FILE_APPEND);

                        $result_api = json_decode($response, true);
                        if ($curl_error) {
                            // curl-level error
                            $app_env = strtolower(Config::get('APP_ENV', 'production'));
                            $debug = strtolower(Config::get('DEBUG', 'false')) === 'true';
                            if ($debug || $app_env !== 'production') {
                                // write OTP to debug log for local testing
                                @file_put_contents(__DIR__ . '/logs/otp_debug.log', date('c') . " | send_otp_debug | user_id={$user_id} | phone={$stored_phone} | otp={$otp} | curl_error={$curl_error}\n", FILE_APPEND);
                                $_SESSION['otp_user_id'] = $user_id;
                                $_SESSION['otp_phone'] = $phone_input;
                                $_SESSION['otp_user_name'] = $user_name;
                                header("Location: verify_otp.php?debug_logged=1");
                                exit();
                            }
                            $error = "Failed to send OTP. Please try again.";
                        } else {
                            if (isset($result_api['status']) && $result_api['status'] === 'success') {
                                $_SESSION['otp_user_id'] = $user_id;
                                $_SESSION['otp_phone'] = $phone_input;
                                $_SESSION['otp_user_name'] = $user_name;
                                header("Location: verify_otp.php");
                                exit();
                            } else {
                                // API returned failure — log and optionally fallback in debug
                                $app_env = strtolower(Config::get('APP_ENV', 'production'));
                                $debug = strtolower(Config::get('DEBUG', 'false')) === 'true';
                                if ($debug || $app_env !== 'production') {
                                    @file_put_contents(__DIR__ . '/logs/otp_debug.log', date('c') . " | send_otp_debug | user_id={$user_id} | phone={$stored_phone} | otp={$otp} | api_response=" . substr(json_encode($result_api), 0, 2000) . "\n", FILE_APPEND);
                                    $_SESSION['otp_user_id'] = $user_id;
                                    $_SESSION['otp_phone'] = $phone_input;
                                    $_SESSION['otp_user_name'] = $user_name;
                                    header("Location: verify_otp.php?debug_logged=1");
                                    exit();
                                }
                                $error = "Failed to send OTP. Please try again.";
                            }
                        }
                    }
                }
            } else {
                $error = "User Code or Phone not found in system.";
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Pharmaceutical System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }
        body {
            background: linear-gradient(135deg, #0f438bff 0%, #cfcfcfff 100%);
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
    </style>
</head>
<body>
    <div class="w-full max-w-md mx-auto px-4">
        <div class="glass-effect rounded-2xl shadow-2xl p-8">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-indigo-600 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                </div>
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Forgot Password?</h1>
                <p class="text-gray-600">Enter your details to reset your password</p>
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

            <!-- Form -->
            <form method="POST" action="">
                <div class="mb-5">
                    <label for="user_code" class="block text-gray-700 font-semibold mb-2">User Code</label>
                    <input type="text" id="user_code" name="user_code" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:border-transparent transition"
                           placeholder="Enter your user code" required>
                </div>

                <div class="mb-6">
                    <label for="phone" class="block text-gray-700 font-semibold mb-2">Phone Number</label>
                    <input type="tel" id="phone" name="phone" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:border-transparent transition"
                           placeholder="Enter your phone number (e.g. +639XXXXXXXXX)" required>
                </div>

                <button type="submit" name="send_otp" 
                        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 rounded-lg transition duration-200 mb-4">
                    Send OTP
                </button>
            </form>

            <!-- Back to Login Link -->
            <div class="text-center">
                <p class="text-gray-600 text-sm">
                    Remember your password? 
                    <a href="b-login.php" class="text-indigo-600 hover:text-indigo-700 font-semibold">Back to Login</a>
                </p>
            </div>
        </div>

        <!-- Footer -->
        <p class="text-center text-white text-xs mt-8 opacity-80">
            © 2025 Pharmaceutical Cross-Branch System. All rights reserved.
        </p>
    </div>
</body>
</html>