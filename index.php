<?php
require 'config.php';
// fetch medicines
$stmt = $pdo->query("SELECT id, name, price FROM medicines ORDER BY id");
$meds = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Medicine POS - Order</title>
<link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="container">

  <div class="left-panel">
    <div class="search-row">
      <input id="search" placeholder="Search medicine..." />
      <button id="clear-search">Clear</button>
    </div>
    <div id="med-list" class="grid">
      <?php foreach($meds as $m): ?>
      <div class="med" data-id="<?= $m['id'] ?>" data-name="<?= htmlspecialchars($m['name']) ?>" data-price="<?= $m['price'] ?>">
        <div class="card-top"></div>
        <div class="card-name"><?= htmlspecialchars($m['name']) ?></div>
        <div class="card-price">$<?= number_format($m['price'],2) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="right-panel">
    <h1>Order</h1>
    <table id="order-table">
      <thead><tr><th>QTY</th><th>Medicine</th><th>Amount</th></tr></thead>
      <tbody></tbody>
    </table>

    <div class="total-row">Total Amount: <span id="total">$0.00</span></div>

    <div class="numpad-area">
      <div class="qty-control">
          <label for="qty_input">Quantity:</label>
          <input type="number" id="qty_input" step="1" min="1" value="" placeholder="1" data-input-target="qty_input" readonly>
          <button id="confirm_qty">OK</button>
      </div>
      
      <div class="payment-area">
          <label for="payment">Payment:</label>
          <input type="number" id="payment" step="0.01" min="0" placeholder="0.00" data-input-target="payment">
      </div>

      <div class="change-area">
          Change: <span id="change">$0.00</span>
      </div>

      <p>Numpad Target: <span id="numpad-target-display">Quantity</span></p>
      <div id="numpad" class="numpad">
        <button data-num="1">1</button><button data-num="2">2</button><button data-num="3">3</button>
        <button data-num="4">4</button><button data-num="5">5</button><button data-num="6">6</button>
        <button data-num="7">7</button><button data-num="8">8</button><button data-num="9">9</button>
        <button data-num="X">X</button><button data-num="0">0</button><button data-num="✓">✓</button>
      </div>
    </div>

    <div class="checkout-row">
      <form action="invoice.php" method="POST" id="checkoutForm">
        <input type="hidden" name="items" id="order_data">
        <input type="hidden" name="total_amount" id="total_amount">
        <input type="hidden" name="payment_amount" id="payment_amount">
        <input type="hidden" name="change_amount" id="change_amount">
        <button id="checkout" type="submit">Checkout</button>
      </form>
    </div>
  </div>
</div>

<script src="script.js"></script>
</body>
</html>