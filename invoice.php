<?php
date_default_timezone_set('Asia/Manila');
// invoice.php receives POST items JSON and displays printable invoice
if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    header('Location: index.php');
    exit;
}

// Order Items (still named 'items' for legacy compatibility with original file)
$raw = $_POST['items'] ?? '[]';
$items = json_decode($raw, true);

// New Summary Variables
$total = $_POST['total_amount'] ?? 0.00;
$payment = $_POST['payment_amount'] ?? 0.00;
$change = $_POST['change_amount'] ?? 0.00;
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Invoice</title>
<link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="invoice-container">
  <h1>Invoice</h1>
  <div class="invoice-header">
    <p><strong>Branch:</strong> 001</p>
    <p><strong>Date:</strong> <?= date('Y-m-d') ?></p>
    <p><strong>Time:</strong> <?= date('H:i:s') ?></p>
  </div>
  <table id="invoice-table">
    <thead><tr><th>QTY</th><th>Medicine</th><th>Amount</th></tr></thead>
    <tbody>
    <?php foreach($items as $i): ?>
      <tr>
        <td><?= htmlspecialchars($i['qty']) ?></td>
        <td><?= htmlspecialchars($i['name']) ?></td>
        <td>$<?= number_format(floatval($i['amount']),2) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div class="summary-section">
    <div class="total-row">Total Amount: <strong>$<?= number_format($total,2) ?></strong></div>
    <div class="payment-row">Payment Received: <strong>$<?= number_format($payment,2) ?></strong></div>
    <div class="change-row">Change: <strong>$<?= number_format($change,2) ?></strong></div>
  </div>

  <div class="invoice-buttons">
    <button onclick="window.history.back()">Back</button>
    <button onclick="window.print()">Print</button>
  </div>
</div>
</body>
</html>