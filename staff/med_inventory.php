<?php
// admin/modules/inventory.php

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

require_once '../dbConnection.php';

$db = new Database();
$conn = $db->getConnection();

// --- 1. Fetch ALL medicines and group them by category ---
$stmt = $conn->prepare("SELECT * FROM medicines ORDER BY category, name");
$stmt->execute();
$all_medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);

$medicines_by_category = [];
foreach ($all_medicines as $med) {
    $category = $med['category'];
    if (!isset($medicines_by_category[$category])) {
        $medicines_by_category[$category] = [];
    }
    $medicines_by_category[$category][] = $med;
}

// --- 2. Get unique categories for the filter dropdown ---
$stmt_cat = $conn->prepare("SELECT DISTINCT category FROM medicines ORDER BY category");
$stmt_cat->execute();
$categories = $stmt_cat->fetchAll(PDO::FETCH_COLUMN);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css">
    <script src="js/med_search&filter.js"></script>
</head>
<body class="bg-gray-100 font-sans">
    <div class="p-8">
        <h2 class="text-3xl font-bold text-gray-800 mb-6">💊 Inventory Management</h2>

        <div class="flex flex-wrap gap-4 mb-8">
            <input type="text" id="inventory-search" placeholder="Search medicines by name..."
                   class="flex-1 min-w-[200px] p-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">

            <select id="category-filter" class="p-2 border border-gray-300 rounded-lg">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Add Medicine button -->
            <button id="add-medicine-btn" class="ml-2 bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg">
                + Add Medicine
            </button>
        </div>

        <div id="inventory-display-area">
            <?php foreach ($medicines_by_category as $category => $meds): ?>
                <section class="mb-8 category-section" data-category="<?= htmlspecialchars($category) ?>">
                    <h3 class="text-2xl font-semibold text-primary-accent mb-4 border-b pb-2"><?= htmlspecialchars($category) ?></h3>

                    <div class="flex overflow-x-auto space-x-4 pb-4">
                        <?php foreach ($meds as $med):
                            // Determine status for visual cues
                            $status_color = 'border-gray-300';
                            $status_badge = '';
                            // Low stock and out of stock badges (stock-based)
                            // Normalize values safely. If min_stock_threshold is missing/null/empty, do not show LOW STOCK badge (only OUT OF STOCK when qty == 0).
                            $qty = isset($med['stock_quantity']) ? (int)$med['stock_quantity'] : 0;
                            $min_threshold = (isset($med['min_stock_threshold']) && $med['min_stock_threshold'] !== null && $med['min_stock_threshold'] !== '') ? (int)$med['min_stock_threshold'] : null;

                            if ($qty === 0) {
                                $status_color = 'border-red-500 ring-2 ring-red-300';
                                $status_badge = '<span class="absolute top-0 right-0 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-bl-lg">OUT OF STOCK</span>';
                            } elseif ($min_threshold !== null && $qty <= $min_threshold) {
                                $status_color = 'border-yellow-500 ring-2 ring-yellow-300';
                                $status_badge = '<span class="absolute top-0 right-0 bg-yellow-500 text-white text-xs font-bold px-2 py-1 rounded-bl-lg">LOW STOCK</span>';
                            }

                            // Check for expiry date and show EXPIRED / EXPIRING badges
                            $expiry_alert = '';
                            $today_ts = strtotime(date('Y-m-d'));
                            $threshold_days = 90; // match API threshold
                            if (!empty($med['expiry_date'])) {
                                $expiry_ts = strtotime($med['expiry_date']);
                                if ($expiry_ts < $today_ts) {
                                    // Expired
                                    $expiry_alert = '<span class="absolute bottom-0 left-0 bg-red-600 text-white text-xs font-bold px-2 py-1 rounded-tr-lg">EXPIRED</span>';
                                    // make card visually urgent
                                    $status_color = 'border-red-600 ring-2 ring-red-300';
                                } else {
                                    $days_remaining = (int) floor(($expiry_ts - $today_ts) / (60 * 60 * 24));
                                    if ($days_remaining <= $threshold_days && $med['stock_quantity'] > 0) {
                                        // Expiring soon
                                        $badge_text = ($days_remaining <= 30) ? 'EXPIRING SOON' : 'EXPIRING';
                                        $expiry_alert = '<span class="absolute bottom-0 left-0 bg-orange-600 text-white text-xs font-bold px-2 py-1 rounded-tr-lg">' . $badge_text . '</span>';
                                    }
                                }
                            }
                        ?>
                       <div class="medicine-card flex-shrink-0 w-60 h-72 bg-white shadow-lg rounded-lg overflow-hidden border-2 <?= $status_color ?> relative"
                           data-name="<?= htmlspecialchars(strtolower($med['name'])) ?>"
                           data-category="<?= htmlspecialchars($med['category']) ?>"
                           data-description="<?= htmlspecialchars($med['description'] ?? '') ?>"
                           data-price="<?= htmlspecialchars(number_format($med['price'], 2)) ?>">

                                <?= $status_badge ?>
                                <?= $expiry_alert ?>

                                <?php
                                    // normalize image URL: if empty use an absolute placeholder; if relative (no protocol) prefix https://
                                    $img = $med['image_url'] ?? '';
                                    if (empty($img)) {
                                        $imgSrc = 'https://placehold.co/100x100?text=No+Image';
                                    } else {
                                        // If image is an absolute URL (http/https or protocol-relative), use as-is.
                                        if (preg_match('#^https?://#i', $img) || preg_match('#^//#', $img)) {
                                            $imgSrc = $img;
                                        }
                                        // If image points to local uploads folder, use relative path so browser loads from server.
                                        elseif (preg_match('#^(?:\.?/?)(uploads/)#i', $img) || preg_match('#^/uploads/#i', $img)) {
                                            $imgSrc = $img; // keep relative or absolute local path
                                        }
                                        // Otherwise assume it's an external host without protocol, prefix https://
                                        else {
                                            $imgSrc = 'https://' . ltrim($img, '/');
                                        }
                                    }
                                ?>
                                <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($med['name']) ?>" class="w-full h-24 object-cover bg-gray-200">

                                <div class="p-4 flex flex-col justify-between h-48">
                                    <div>
                                        <h4 class="text-lg font-semibold text-gray-900 mb-1"><?= htmlspecialchars($med['name']) ?></h4>
                                        <p class="text-sm text-gray-500">Category: <?= htmlspecialchars($med['category']) ?></p>
                                    </div>
                                    <div class="mt-2">
                                        <p class="text-xl font-bold text-green-600">₱<?= number_format($med['price'], 2) ?></p>
                                        <p class="text-sm text-gray-700">Stock: <strong><?= $med['stock_quantity'] ?></strong></p>
                                        <button data-id="<?= $med['medicine_id'] ?>" class="view-details mt-2 w-full bg-blue-500 text-white text-sm py-1 rounded hover:bg-blue-600">
                                            View Details
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        </div>

    <script src="js/pos.js"></script>
    <script src="js/inventory_admin.js"></script>
</body>
</html>
