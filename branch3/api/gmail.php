<?php
// -------------------------------------------------------------------------
// CONFIGURATION
// -------------------------------------------------------------------------
// SET THIS TO 'FALSE' IF YOU WANT TO SEND REAL EMAILS (Requires PHPMailer)
$DEMO_MODE = true; 

// IF SENDING REAL EMAILS, CONFIGURE THIS:
$SMTP_CONFIG = [
    'host' => 'smtp.gmail.com',
    'username' => 'bautistaautopay@gmail.com',
    'password' => 'qkau hafh qhxo gyjj', // Google Account > Security > App Passwords
    'port' => 587
];
// -------------------------------------------------------------------------

// Disable error display to prevent breaking JSON
ini_set('display_errors', 0);
header('Content-Type: application/json');

// Get POST Data
$inputJSON = file_get_contents('php://input');
$data = json_decode($inputJSON, true);

// 1. Validate Input
if (!isset($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}

// 2. Prepare Variables
$to = $data['email'];
$transactionId = $data['transaction_id'] ?? 'N/A';
$subject = "Receipt for Transaction #" . $transactionId;
$storeName = "Mercury Drug"; 
$total = number_format($data['total'] ?? 0, 2);
$date = $data['date'] ?? date('Y-m-d H:i:s');

// 3. Construct HTML Email Body
$itemsHtml = "";
if (!empty($data['items'])) {
    foreach ($data['items'] as $item) {
        $price = number_format($item['price'], 2);
        $subtotal = number_format($item['price'] * $item['qty'], 2);
        $itemsHtml .= "
        <tr>
            <td style='padding:5px; border-bottom:1px solid #ddd;'>{$item['name']}</td>
            <td style='padding:5px; border-bottom:1px solid #ddd; text-align:center;'>{$item['qty']}</td>
            <td style='padding:5px; border-bottom:1px solid #ddd; text-align:right;'>₱{$subtotal}</td>
        </tr>";
    }
}

$message = "
<!DOCTYPE html>
<html>
<body style='font-family: monospace; color: #333; background-color: #f4f4f4; padding: 20px;'>
    <div style='max-width: 350px; margin: auto; background: #fff; padding: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);'>
        <h2 style='text-align: center; margin: 0; color: #e11d48;'>{$storeName}</h2>
        <p style='text-align: center; font-size: 12px; color: #777; margin-bottom: 15px;'>OFFICIAL RECEIPT</p>
        
        <div style='font-size: 12px; border-top: 2px dashed #333; border-bottom: 2px dashed #333; padding: 10px 0; margin-bottom: 15px;'>
            <p style='margin: 2px 0;'><strong>Date:</strong> {$date}</p>
            <p style='margin: 2px 0;'><strong>Trans ID:</strong> {$transactionId}</p>
            <p style='margin: 2px 0;'><strong>Customer:</strong> {$to}</p>
        </div>

        <table style='width: 100%; font-size: 12px; border-collapse: collapse; margin-bottom: 15px;'>
            <thead>
                <tr style='background: #f9f9f9;'>
                    <th style='text-align: left; padding: 5px;'>Item</th>
                    <th style='text-align: center; padding: 5px;'>Qty</th>
                    <th style='text-align: right; padding: 5px;'>Amt</th>
                </tr>
            </thead>
            <tbody>
                {$itemsHtml}
            </tbody>
        </table>

        <h3 style='text-align: right; margin: 10px 0; font-size: 18px;'>Total: ₱{$total}</h3>
        
        <p style='text-align: center; font-size: 10px; color: #999; margin-top: 30px;'>
            Thank you for shopping at Mercury Drug!<br>
            Please keep this receipt for warranty purposes.
        </p>
    </div>
</body>
</html>
";

// -------------------------------------------------------------------------
// LOGIC: DEMO MODE vs REAL MODE
// -------------------------------------------------------------------------

if ($DEMO_MODE = false) {
    // --- OPTION A: SIMULATION (Works immediately on Localhost) ---
    // Instead of sending, we save the email to a folder so you can see it.
    
    $folder = __DIR__ . '../includes/sent_emails';
    if (!file_exists($folder)) {
        mkdir($folder, 0777, true);
    }
    
    $filename = "Receipt_{$transactionId}_" . time() . ".html";
    $filepath = $folder . '/' . $filename;
    
    if (file_put_contents($filepath, $message)) {
        // Return success so the UI shows "Sent Successfully"
        echo json_encode([
            'success' => true, 
            'message' => 'Demo Mode: Receipt saved to handlers/sent_emails/' . $filename
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save demo email file.']);
    }

} else {
    // --- OPTION B: REAL GMAIL SENDING (Requires PHPMailer) ---
    // You must download PHPMailer and place it in a folder named 'src'
    
    require 'src/Exception.php';
    require 'src/PHPMailer.php';
    require 'src/SMTP.php';
    
    // Check if classes exist (Simple check to prevent crash if you forgot to download)
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        echo json_encode(['success' => false, 'message' => 'PHPMailer library missing. Switch back to Demo Mode ($DEMO_MODE = true) or install PHPMailer.']);
        exit;
    }

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $SMTP_CONFIG['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $SMTP_CONFIG['username'];
        $mail->Password   = $SMTP_CONFIG['password'];
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $SMTP_CONFIG['port'];

        // Recipients
        $mail->setFrom($SMTP_CONFIG['username'], $storeName);
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;

        $mail->send();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => "Mailer Error: {$mail->ErrorInfo}"]);
    }
}
?>