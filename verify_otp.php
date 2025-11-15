<?php
session_start();
require_once 'dbconnection.php';
require_once 'config.php';

$error = '';
$success = '';

// Redirect if no OTP session
if (!isset($_SESSION['otp_user_id'])) {
    header("Location: forgot.php");
    exit();
}

if (isset($_POST['verify_otp'])) {
    $user_otp = trim($_POST['otp'] ?? '');

    if (empty($user_otp)) {
        $error = "Please enter the OTP.";
    } else {
        try {
            $db = new Database();
            $conn = $db->getConnection();
            
            // Check if OTP exists, matches, and is not expired
            $stmt = $conn->prepare("SELECT OTPID FROM OTPVerification 
                                   WHERE UserID = ? 
                                   AND OTPCode = ? 
                                   AND IsUsed = 0 
                                   AND ExpiresAt > NOW() 
                                   LIMIT 1");
            $stmt->execute([$_SESSION['otp_user_id'], $user_otp]);
            $otp_record = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($otp_record) {
                // Mark OTP as used
                $update_stmt = $conn->prepare("UPDATE OTPVerification SET IsUsed = 1 WHERE OTPID = ?");
                $update_stmt->execute([$otp_record['OTPID']]);
                
                // Mark session as OTP verified
                $_SESSION['otp_verified'] = true;
                header("Location: reset_password.php");
                exit();
            } else {
                $error = "Invalid or expired OTP. Please try again.";
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Resend OTP if requested
if (isset($_POST['resend_otp'])) {
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        $user_id = $_SESSION['otp_user_id'];
        
        // Get user details
        $user_stmt = $conn->prepare("SELECT Email, FirstName, LastName FROM Accounts WHERE UserID = ?");
        $user_stmt->execute([$user_id]);
        $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get phone number
        $phone_stmt = $conn->prepare("SELECT PersonalPhoneNumber FROM Details WHERE UserID = ?");
        $phone_stmt->execute([$user_id]);
        $phone_result = $phone_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($phone_result && $phone_result['PersonalPhoneNumber']) {
            // Generate new OTP
            $otp_length = intval(Config::get('OTP_LENGTH', 6));
            $otp = str_pad(rand(0, pow(10, $otp_length) - 1), $otp_length, '0', STR_PAD_LEFT);
            
            // Delete old OTPs
            $delete_stmt = $conn->prepare("DELETE FROM OTPVerification WHERE UserID = ? AND IsUsed = 0");
            $delete_stmt->execute([$user_id]);
            
            // Store new OTP using DB server clock (avoid PHP/DB timezone mismatch)
            $otp_expiry_minutes = intval(Config::get('OTP_EXPIRY_MINUTES', 10));
            $insert_stmt = $conn->prepare("INSERT INTO OTPVerification (UserID, OTPCode, ExpiresAt) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))");
            $insert_stmt->execute([$user_id, $otp, $otp_expiry_minutes]);
            
            // Send OTP via SMS
            $api_token = Config::get('SMS_API_TOKEN');
            $api_endpoint = Config::get('SMS_API_ENDPOINT');
            $app_name = Config::get('APP_NAME', 'Pharmaceutical System');
            $message = "Your $app_name OTP is: $otp. Valid for $otp_expiry_minutes minutes.";
            
            $data = [
                'api_token' => $api_token,
                'phone_number' => $phone_result['PersonalPhoneNumber'],
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
            
            if (!$curl_error) {
                $result_api = json_decode($response, true);
                if (isset($result_api['status']) && $result_api['status'] === 'success') {
                    $success = "OTP resent successfully! Check your phone.";
                }
            }
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - Pharmaceutical System</title>
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
        .otp-input {
            text-align: center;
            font-size: 24px;
            font-weight: 600;
            letter-spacing: 8px;
        }
    </style>
</head>
<body>
    <div class="w-full max-w-md mx-auto px-4">
        <div class="glass-effect rounded-2xl shadow-2xl p-8">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-green-600 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Verify OTP</h1>
                <p class="text-gray-600">Enter the 6-digit code sent to your phone</p>
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

            <!-- Success Message -->
            <?php if (!empty($success)) { ?>
                <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-500 rounded">
                    <p class="text-green-700 text-sm font-medium">
                        <svg class="w-5 h-5 inline mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                        <?php echo htmlspecialchars($success); ?>
                    </p>
                </div>
            <?php } ?>

            <!-- Form -->
            <form method="POST" action="">
                <div class="mb-6">
                    <label for="otp" class="block text-gray-700 font-semibold mb-3">Enter OTP Code</label>
                    <input type="text" id="otp" name="otp" maxlength="6" inputmode="numeric"
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:border-transparent transition otp-input"
                           placeholder="000000" required>
                </div>

                <button type="submit" name="verify_otp" 
                        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 rounded-lg transition duration-200 mb-4">
                    Verify OTP
                </button>
            </form>

            <!-- Resend OTP -->
            <div class="text-center pt-4 border-t border-gray-200">
                <p class="text-gray-600 text-sm mb-3">Didn't receive the code?</p>
                <form method="POST" action="">
                    <button type="submit" name="resend_otp" 
                            class="text-indigo-600 hover:text-indigo-700 font-semibold text-sm">
                        Resend OTP
                    </button>
                </form>
            </div>

            <!-- Back to Forgot Password -->
            <div class="text-center mt-4">
                <p class="text-gray-600 text-sm">
                    Wrong email? 
                    <a href="forgot.php" class="text-indigo-600 hover:text-indigo-700 font-semibold">Go Back</a>
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
