<?php
// api/send_invoice_email.php

$DEMO_MODE = false;

$SMTP_CONFIG = [
    'host' => 'smtp.gmail.com',
    'username' => 'bautistaautopay@gmail.com',
    'password' => 'qkau hafh qhxo gyjj',
    'port' => 587
];

ini_set('display_errors', 0);
header('Content-Type: application/json');

require 'src/Exception.php';
require 'src/PHPMailer.php';
require 'src/SMTP.php';

$inputJSON = file_get_contents('php://input');
$data = json_decode($inputJSON, true);

// Validate email
if (!isset($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}


$to = $data['email'];
$payment = htmlspecialchars($data['payment_method'] ?? ($data['payment_method'] ?? 'Cash'));

$totalTaxAmount = number_format($data['vat_amount'] ?? $data['TotalTaxAmount'] ?? 0, 2);
$totalDiscountAmount = number_format($data['discount_amount'] ?? $data['TotalDiscountAmount'] ?? 0, 2);
$rawTotal = number_format($data['raw_total'] ?? 0, 2);
$discountType = htmlspecialchars($data['discount_type'] ?? 'regular');

$transactionId = $data['transaction_id'] ?? ($data['transactionId'] ?? '0000');
$subject = "Receipt for Transaction #" . $transactionId;
$storeName = "Mercury Drug";

$total = number_format($data['total_amount'] ?? ($data['total'] ?? 0), 2);
$date = $data['date'] ?? date('Y-m-d H:i:s');

// Build items HTML
$itemsHtml = "";
if (!empty($data['items']) && is_array($data['items'])) {
    foreach ($data['items'] as $item) {
        $price = number_format($item['price'], 2);
        $subtotal = number_format(($item['price'] ?? 0) * ($item['qty'] ?? 0), 2);
        $itemsHtml .= "
        <tr>
            <td style='padding:5px; border-bottom:1px solid #ddd;'>{$item['name']}</td>
            <td style='padding:5px; border-bottom:1px solid #ddd; text-align:center;'>{$item['qty']}</td>
            <td style='padding:5px; border-bottom:1px solid #ddd; text-align:right;'>₱{$subtotal}</td>
        </tr>";
    }
}

// HTML Email
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
        <p style='margin: 2px 0;'><strong>Discount Type:</strong> {$discountType}</p>
        <p style='margin: 2px 0;'><strong>Subtotal:</strong> ₱{$rawTotal}</p>
        <p style='margin: 2px 0;'><strong>Total Discount:</strong> ₱{$totalDiscountAmount}</p>
        <p style='margin: 2px 0;'><strong>Total VAT:</strong> ₱{$totalTaxAmount}</p>
    </div>

    <table style='width: 100%; font-size: 12px; border-collapse: collapse; margin-bottom: 15px;'>
        <thead>
            <tr style='background: #f9f9f9;'>
                <th style='text-align: left; padding: 5px;'>Item</th>
                <th style='text-align: center; padding: 5px;'>Qty</th>
                <th style='text-align: right; padding: 5px;'>Amt</th>
            </tr>
        </thead>
        <tbody>{$itemsHtml}</tbody>
    </table>

    <h3 style='text-align: right; margin: 10px 0; font-size: 18px;'>Total: ₱{$total}</h3>
</div>
</body>
</html>
";

// Demo or SMTP send
if ($DEMO_MODE) {
    $folder = __DIR__ . '/../sent_emails';
    if (!file_exists($folder)) mkdir($folder, 0777, true);
    $filename = "Receipt_{$transactionId}_" . time() . ".html";
    if (file_put_contents($folder . '/' . $filename, $message)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save demo file.']);
    }
} else {
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        echo json_encode(['success' => false, 'message' => 'PHPMailer library missing']);
        exit;
    }

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $SMTP_CONFIG['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $SMTP_CONFIG['username'];
        $mail->Password = $SMTP_CONFIG['password'];
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $SMTP_CONFIG['port'];

        $mail->setFrom($SMTP_CONFIG['username'], $storeName);
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;

        $mail->send();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => "Mailer Error: {$mail->ErrorInfo}"]);
    }
}
?>
