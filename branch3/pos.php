<?php
// Start the session on every page
session_start();
require_once '../dbconnection.php';

// ------------------------------------------------------------------
// ACCESS CONTROL CHECK
// ------------------------------------------------------------------

// 1. Check if the user is not logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: ../login.php");
    exit;
}

// 2. Check Role: Only Staff or Admin can view this page.
if ($_SESSION["user_role"] !== 'Staff' && $_SESSION["user_role"] !== 'Admin') {
    die("ERROR: You do not have permission to view this page.");
}

// 3. Check Branch (Crucial for Staff access). This file is for Branch 1.
// Admins are not restricted by BranchID, but Staff MUST be from Branch 1.
$required_branch_id = 3;
if ($required_branch_id > 0 && $_SESSION["user_role"] === 'Staff' && $_SESSION["branch_id"] != $required_branch_id) {
    // Redirect staff who ended up on the wrong branch page
    header("Location: ../login.php?error=branch_mismatch"); 
    exit;
}

// ------------------------------------------------------------------
// DYNAMIC DATA PREPARATION
// ------------------------------------------------------------------

// Set dynamic variables from session data
$user_full_name = htmlspecialchars($_SESSION['first_name'] ?? 'Staff') . ' ' . htmlspecialchars($_SESSION['last_name'] ?? 'User');
$user_role = htmlspecialchars($_SESSION['user_role'] ?? 'Staff');
$current_branch_id = $_SESSION['branch_id'];

// Mock Branch Name lookup
$branch_names = [
    1 => 'Lipa, Batangas',
    2 => 'Sto Tomas, Batangas',
    3 => 'Malvar, Batangas'
];
$branch_name = $branch_names[$current_branch_id] ?? "Branch {$current_branch_id}";

// ------------------------------------------------------------------
// FETCH MEDICINES FOR THIS BRANCH
// ------------------------------------------------------------------
try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // We select BranchInventoryID as 'id' because that is what connects the stock to the sale
    $sql = "SELECT 
                bi.BranchInventoryID as id, 
                m.MedicineName as name, 
                bi.Price as price,
                bi.Stocks
            FROM BranchInventory bi 
            JOIN medicines m ON bi.MedicineID = m.MedicineID 
            WHERE bi.BranchID = :branch_id 
            AND bi.Stocks > 0 
            AND bi.Status = 'Active'
            ORDER BY m.MedicineName";
            
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':branch_id', $current_branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $meds = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $meds = []; // Fail gracefully if DB error
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mercury POS</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="js/theme.js"></script>
    
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Load SweetAlert2 library from CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/chat_window.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/pos.css">

    <script>
        // Make the current user's ID available to the JavaScript files
        window.currentUserId = <?php echo json_encode($_SESSION['user_id'] ?? 0); ?>;

    </script>
    <script src="js/medicine.js" defer></script>
    <script src="js/alerts.js" defer></script>
    <script src="js/pos.js" defer></script>
    </head>
<body class="overflow-x-hidden">

    <div id="backdrop" class="backdrop" onclick="toggleSidebar()"></div>

    <div class="app-container bg-white">
        
        <header id="main-header" class="bg-custom-dark-header text-white p-4 flex justify-between items-center sticky top-0 z-30 shadow-lg">
            
            <div class="flex items-center space-x-3">
                <img src="https://placehold.co/40x40/fff/1E3A8A?text=B<?php echo $current_branch_id; ?>" alt="Mercury Logo" class="rounded-full border-2 border-white">
                <span class="text-2xl font-bold text-gray-800 tracking-wider">
                    <span class="text-white">MERCURY</span>
                </span>
            </div>

            <div id="icons-section" class="flex items-center space-x-4">
                <?php include __DIR__ . '/includes/notification_bell.php'; ?>
                
                <button id="open-sidebar-btn" aria-label="Menu" onclick="toggleSidebar()" class="p-2 bg-slate-700 hover:bg-slate-600 rounded-lg transition duration-150">
                    <svg class="lucide" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/></svg>
                </button>
            </div>
        </header>

        <nav class="bg-[#F4F6FA] border-b border-gray-200 flex justify-between items-center px-6 py-3 shadow-sm sticky top-16 z-20">
            
            <div class="flex space-x-8 text-base font-medium">
                <a href="staff1b<?php echo $current_branch_id; ?>.php" class="py-2 px-3 rounded-md text-black hover:bg-gray-100 hover:text-black font-medium transition-all duration-300">Dashboard</a>
                <a href="med_inventory.php" class="py-2 px-3 rounded-md text-black hover:bg-gray-100 hover:text-black font-medium transition-all duration-300">Med Inventory</a>
                <a href="pos.php" class="py-2 px-3 rounded-md bg-blue-100 text-blue-800 hover:text-black font-medium transition-all duration-300">POS</a>
                <a href="reports.php" class="py-2 px-3 rounded-md text-black hover:bg-gray-100 hover:text-black font-medium transition-all duration-300">Reports</a>
                <a href="account.php" class="py-2 px-3 rounded-md text-black hover:bg-gray-100 hover:text-black font-medium transition-all duration-300">Account</a>
            </div>

            <a href="b-crud/logout.php" class="bg-red-500 hover:bg-red-600 text-white font-medium py-2 px-4 rounded-lg transition duration-150 shadow-md text-sm inline-block">
                Logout
            </a>
        </nav>

        <main class="bg-custom-bg-white p-6 flex-grow h-full relative z-10">
            <div class="main-content flex-1 p-6 lg:p-10 overflow-y-auto bg-main-bg-color">
                
                <?php if(isset($_GET['success'])): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4">
                    Transaction Completed Successfully!
                </div>
                <?php endif; ?>

                <h2 id="page-title" class="text-4xl font-extrabold text-gray-900 mb-4">
                    Point of Sale
                </h2>

                <div class="container">

    <div class="pos-panel">
        <div class="p-4 border-b border-gray-100 bg-gray-50">
            <div class="relative">
                
                <input id="search" placeholder="Search medicine..." 
                       class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 transition shadow-sm text-sm"
                       onkeyup="filterMeds()" />
            </div>
        </div>

        <div id="med-list" class="bg-gray-50/50 grid grid-cols-4 gap-4">
            <?php if (!empty($meds)): ?>
                <?php foreach($meds as $m): ?>
                <div class="med group bg-[#F4F6FA] p-3 rounded-xl border border-gray-200 hover:border-blue-400 hover:shadow-md cursor-pointer transition-all duration-200 flex flex-col justify-between h-28 relative overflow-hidden"
                     data-id="<?= $m['id'] ?>"
                     data-name="<?= htmlspecialchars($m['name']) ?>"
                     data-price="<?= $m['price'] ?>"
                     onclick="selectMedicine(this)">
                    
                    <div class="absolute top-0 right-0 bg-gray-100 px-2 py-1 rounded-bl-lg text-[10px] font-bold text-gray-500 group-hover:bg-blue-100 group-hover:text-blue-600 transition-colors">
                        <?= $m['Stocks'] ?> Left
                    </div>

                    <div class="font-semibold text-gray-700 text-sm leading-snug pr-4 line-clamp-2">
                        <?= htmlspecialchars($m['name']) ?>
                    </div>
                    
                    <div class="mt-auto flex justify-between items-end">
                        <div class="text-blue-600 font-bold text-lg">
                            ₱<?= number_format($m['price'],2) ?>
                        </div>
                            <button type="button" class="h-6 w-6 rounded-full bg-gray-50 flex items-center justify-center group-hover:bg-blue-500 group-hover:text-white transition-colors" onclick="incrementQty(this)">
                              <i data-lucide="plus" class="w-4 h-4"></i>
                            </button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-span-3 flex flex-col items-center justify-center text-gray-400 h-64">
                    <i data-lucide="box" class="w-10 h-10 mb-2 opacity-50"></i>
                    <span class="text-sm">No medicines found</span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="pos-panel relative">
        <div class="p-4 border-b border-gray-100 flex justify-between items-center bg-[#F4F6FA] z-10">
            <h2 class="font-bold text-gray-800 flex items-center gap-2">
                <i data-lucide="shopping-cart" class="w-5 h-5 text-blue-600"></i> Current Order
            </h2>
            <button id="clear-order" class="text-xs text-red-500 hover:text-red-700 hover:bg-red-50 px-3 py-1.5 rounded-lg transition font-medium border border-transparent hover:border-red-100">
                Clear All
            </button>
        </div>

        <div class="order-scroll-area">
            <table id="order-table" class="w-full text-left border-collapse">
                <thead class="bg-[#F4F6FA] sticky top-0 z-10 shadow-sm">
                    <tr>
                        <th class="py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider w-16 text-center">Qty</th>
                        <th class="py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Item</th>
                        <th class="py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-right">Amt</th>
                        <th class="py-3 px-2 w-10"></th>
                    </tr>
                </thead>
                <tbody id="order-body" class="text-sm divide-y divide-gray-100">
                    </tbody>
            </table>
        </div>

        <div class="mt-auto bg-[#F4F6FA] border-t border-gray-200 p-4 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]">
            <div class="flex justify-between items-end mb-4">
                <span class="text-gray-500 text-sm font-medium">Total Amount</span>
                <span id="total" class="text-3xl font-bold text-slate-800 tracking-tight">₱0.00</span>
            </div>

<form id="checkoutForm" onsubmit="return false;">
    <input type="hidden" name="items" id="order_data">
    <input type="hidden" name="total_amount" id="total_amount">
    <input type="hidden" name="payment_amount" id="payment_amount">
    <input type="hidden" name="change_amount" id="change_amount">
    
    <button id="checkout" type="submit" class="w-full bg-[#F4F6FA] from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-bold py-4 rounded-xl shadow-lg shadow-blue-200 transform active:scale-[0.98] transition-all flex justify-center items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed disabled:shadow-none">
        <span>CHECKOUT</span>
        
    </button>
</form>

<script>
    // Pass current user and branch info to JS for invoice display
    window.currentUserFull = <?php echo json_encode($user_full_name); ?>;
    window.currentBranchName = <?php echo json_encode($branch_name); ?>;
</script>
        </div>
    </div>

    <div class="pos-panel p-4 bg-[#F4F6FA]">
        
        <div class="mb-4">
            <label class="block text-xs font-bold text-gray-400 uppercase mb-1 ml-1">Quantity</label>
<input type="number" id="qty_input" value="" placeholder="Enter quantity"
       class="w-full bg-[#F4F6FA] border border-gray-200 text-gray-800 text-xl font-bold rounded-xl px-4 py-3 text-center focus:outline-none transition-all cursor-pointer placeholder-gray-300"
       onclick="activeTarget='qty'; updateInputHighlight();">
        </div>
        <div class="mb-4">
            <label class="block text-xs font-bold text-gray-400 uppercase mb-1 ml-1">Discount & VAT</label>
            <div class="flex gap-2 items-center">
                <!-- Discount type dropdown -->
                <select id="discount_type" class="w-1/2 bg-[#F4F6FA] border border-gray-200 text-gray-800 text-sm font-semibold rounded-xl px-3 py-3 focus:outline-none">
                    <option value="regular" selected>Regular</option>
                    <option value="senior">Senior</option>
                    <option value="pwd">PWD</option>
                </select>

                <!-- Discount amount (auto-calculated) -->
                <input type="number" id="discount_amount" placeholder="0.00"
                    class="w-1/2 bg-[#F4F6FA] border border-gray-200 text-gray-800 text-xl font-bold rounded-xl px-4 py-3 text-center focus:outline-none transition-all cursor-pointer placeholder-gray-300">

                <!-- VAT percentage -->
                <input type="number" id="vat_percent" placeholder="VAT %" value="12"
                    class="w-1/4 bg-[#F4F6FA] border border-gray-200 text-gray-800 text-xl font-bold rounded-xl px-4 py-3 text-center focus:outline-none transition-all cursor-pointer placeholder-gray-300">
            </div>
        </div>


        <div class="mb-4">
            <label class="block text-xs font-bold text-gray-400 uppercase mb-1 ml-1">Payment Amount</label>
            <div class="flex gap-2 items-center">
                <input type="number" id="payment" placeholder="0.00"
                       class="flex-grow bg-white border border-gray-200 text-gray-800 text-2xl font-bold rounded-xl px-4 py-3 text-right focus:outline-none transition-all cursor-pointer placeholder-gray-300"
                       onclick="activeTarget='payment'; updateInputHighlight();">
                <select id="payment_type" class="w-20 border border-gray-200 rounded-xl text-gray-700 text-sm font-semibold px-2 py-2 cursor-pointer focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all">
                    <option value="" selected>Cash (Default)</option>
                    <option value="Card">Card</option>
                    <option value="Credit">Credit</option>
                </select>
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-xl p-3 mb-4 flex justify-between items-center shadow-sm">
            <span class="text-gray-500 font-medium text-sm">Change</span>
            <span id="change" class="text-green-600 font-bold text-xl">₱0.00</span>
        </div>

        <div class="numpad-grid">
            <button onclick="numpadInput('1')" class="bg-white border border-gray-200 rounded-xl text-xl font-semibold text-gray-700 shadow-sm hover:bg-gray-50 active:bg-gray-100 active:scale-95 transition">1</button>
            <button onclick="numpadInput('2')" class="bg-white border border-gray-200 rounded-xl text-xl font-semibold text-gray-700 shadow-sm hover:bg-gray-50 active:bg-gray-100 active:scale-95 transition">2</button>
            <button onclick="numpadInput('3')" class="bg-white border border-gray-200 rounded-xl text-xl font-semibold text-gray-700 shadow-sm hover:bg-gray-50 active:bg-gray-100 active:scale-95 transition">3</button>
            
            <button onclick="numpadInput('4')" class="bg-white border border-gray-200 rounded-xl text-xl font-semibold text-gray-700 shadow-sm hover:bg-gray-50 active:bg-gray-100 active:scale-95 transition">4</button>
            <button onclick="numpadInput('5')" class="bg-white border border-gray-200 rounded-xl text-xl font-semibold text-gray-700 shadow-sm hover:bg-gray-50 active:bg-gray-100 active:scale-95 transition">5</button>
            <button onclick="numpadInput('6')" class="bg-white border border-gray-200 rounded-xl text-xl font-semibold text-gray-700 shadow-sm hover:bg-gray-50 active:bg-gray-100 active:scale-95 transition">6</button>
            
            <button onclick="numpadInput('7')" class="bg-white border border-gray-200 rounded-xl text-xl font-semibold text-gray-700 shadow-sm hover:bg-gray-50 active:bg-gray-100 active:scale-95 transition">7</button>
            <button onclick="numpadInput('8')" class="bg-white border border-gray-200 rounded-xl text-xl font-semibold text-gray-700 shadow-sm hover:bg-gray-50 active:bg-gray-100 active:scale-95 transition">8</button>
            <button onclick="numpadInput('9')" class="bg-white border border-gray-200 rounded-xl text-xl font-semibold text-gray-700 shadow-sm hover:bg-gray-50 active:bg-gray-100 active:scale-95 transition">9</button>
            
<button onclick="numpadInput('X')" aria-label="Delete" title="Delete" class="bg-red-50 border border-red-100 rounded-xl text-xl font-bold text-red-500 shadow-sm hover:bg-red-100 active:scale-95 transition flex items-center justify-center gap-2 font-semibold text-red-500 text-sm">
    <i data-lucide="delete" class="w-6 h-6"></i>
</button>
            <button onclick="numpadInput('0')" class="bg-white border border-gray-200 rounded-xl text-xl font-semibold text-gray-700 shadow-sm hover:bg-gray-50 active:bg-gray-100 active:scale-95 transition">0</button>
            <button onclick="numpadInput('✓')" class="bg-blue-600 border border-blue-600 rounded-xl text-xl font-bold text-white shadow-md hover:bg-blue-700 active:scale-95 transition">
                Enter
            </button>
        </div>
    </div>

</div>
        </main>
    </div>


    <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script src="js/chat.js?v=<?php echo time(); ?>"></script>
    <script src="js/notifications_bell.js" defer></script>
    <script src="js/script.js"></script>
  
    <?php include __DIR__ . '/includes/invoice_modal.php'; ?>
</body>
</html>
