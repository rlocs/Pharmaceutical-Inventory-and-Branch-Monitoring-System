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
$required_branch_id = 1;
if ($required_branch_id > 0 && $_SESSION["user_role"] === 'Staff' && $_SESSION["branch_id"] != $required_branch_id) {
    // Redirect staff who ended up on the wrong branch page
    // Optional: Log this security violation attempt
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

// Initialize variables with fallback values
$branch_name = "Branch {$current_branch_id}";
$branch_address = '123 Main Street, City Center';
$staff_phone = '(043) 123-4567';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Fetch branch details
    $branch_sql = "SELECT BranchName, BranchAddress FROM Branches WHERE BranchID = ?";
    $branch_stmt = $pdo->prepare($branch_sql);
    $branch_stmt->execute([$current_branch_id]);
    $branch = $branch_stmt->fetch(PDO::FETCH_ASSOC);

    $branch_name = $branch['BranchName'] ?? "Branch {$current_branch_id}";
    $branch_address = $branch['BranchAddress'] ?? '123 Main Street, City Center';

    // Fetch current staff's personal phone number
    $phone_sql = "SELECT PersonalPhoneNumber FROM Details WHERE UserID = ?";
    $phone_stmt = $pdo->prepare($phone_sql);
    $phone_stmt->execute([$_SESSION['user_id']]);
    $staff_phone = $phone_stmt->fetchColumn() ?? '(043) 123-4567';

} catch (Exception $e) {
    // Fallback values already set above
    error_log("Error fetching branch/staff details in reports.php: " . $e->getMessage());
}

// NOTE: You'll need to update your b-login.php to also pull FirstName and LastName 
// and store them in the session for this to work perfectly.
// For now, it uses 'Staff' and 'User' as fallback names.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Ledger & Receipts - Branch <?php echo $required_branch_id; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    
    <!-- Load Tailwind CSS CDN and Configuration -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="js/theme.js"></script>
    
    <!-- Load Lucide icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/chat_window.css?v=<?php echo time(); ?>">
    <script>
        // Make the current user's ID available to the JavaScript files
        window.currentUserId = <?php echo json_encode($_SESSION['user_id'] ?? 0); ?>;
        
    </script>
    <script src="js/medicine.js" defer></script>
    <script src="js/alerts.js" defer></script>
    <!-- Custom CSS Styles are in css/style.css -->
    <link rel="stylesheet" href="css/reports.css">
</head>
<body class="overflow-x-hidden">

    <!-- Backdrop for click outside close -->
    <div id="backdrop" class="backdrop" onclick="toggleSidebar()"></div>

    <!-- Outer container takes full screen space -->
    <div class="app-container bg-white">
        
        <!-- 1. TOP HEADER BAR (Dark Slate) -->
        <header id="main-header" class="bg-custom-dark-header text-white p-4 flex justify-between items-center sticky top-0 z-30 shadow-lg">
            
            <!-- Logo Section -->
            <div class="flex items-center space-x-3">
                <img src="https://placehold.co/40x40/fff/1E3A8A?text=B<?php echo $current_branch_id; ?>" alt="Mercury Logo" class="rounded-full border-2 border-white">
                <span class="text-2xl font-bold text-gray-800 tracking-wider">
                    <span class="text-white">MERCURY</span>
                </span>
            </div>

            <!-- Icons Section -->
            <div id="icons-section" class="flex items-center space-x-4">
                <?php include __DIR__ . '/includes/notification_bell.php'; ?>
                                                                <!-- Hamburger Menu Icon (OPEN BUTTON) -->
                <!-- Calls toggleSidebar() -->
                <button id="open-sidebar-btn" aria-label="Menu" onclick="toggleSidebar()" class="p-2 bg-slate-700 hover:bg-slate-600 rounded-lg transition duration-150">
                    <svg class="lucide" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/></svg>
                </button>
            </div>
        </header>

        <!-- 2. SECONDARY NAVIGATION BAR (Light Cream/White) -->
        <nav class="bg-[#F4F6FA] border-b border-gray-200 flex justify-between items-center px-6 py-3 shadow-sm sticky top-16 z-20">
            
            <!-- Navigation Links - INCREASED TEXT SIZE to text-base -->
            <div class="flex space-x-8 text-base font-medium">

                <a href="staff1b<?php echo $current_branch_id; ?>.php" class="py-2 px-3 rounded-md text-black hover:bg-gray-100 hover:text-black font-medium transition-all duration-300">Dashboard</a>
                <a href="med_inventory.php" class="py-2 px-3 rounded-md text-black hover:bg-gray-100 hover:text-black font-medium transition-all duration-300">Med Inventory</a>
                <a href="pos.php" class="py-2 px-3 rounded-md text-black hover:bg-gray-100 hover:text-black font-medium transition-all duration-300">POS</a>
                <a href="reports.php" class="py-2 px-3 rounded-md bg-blue-100 text-blue-800 hover:text-black font-medium transition-all duration-300">Reports</a>
                <a href="account.php" class="py-2 px-3 rounded-md text-black hover:bg-gray-100 hover:text-black font-medium transition-all duration-300">Account</a>
            </div>

            <!-- Logout Button -->
            <a href="b-crud/logout.php" class="bg-red-500 hover:bg-red-600 text-white font-medium py-2 px-4 rounded-lg transition duration-150 shadow-md text-sm inline-block">
                Logout
            </a>
        </nav>

        <!-- 3. MAIN CONTENT AREA (Cream Background) -->
        <main class="bg-custom-bg-white p-6 flex-grow h-full relative z-10">
            <!-- Page Header -->
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-800 mb-2 flex items-center gap-2"><i data-lucide="scroll-text"></i> Sales Ledger & Receipts</h1>
                <p class="text-gray-600">Branch <?php echo $required_branch_id; ?> Repository</p>
            </div>

            <!-- Controls Section -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
                <div class="flex flex-wrap gap-4 items-center justify-between">
                    
                    <!-- Search and Filter Controls -->
                    <div class="flex flex-wrap gap-4 items-center">
                        <div class="relative">
                            <input type="text" id="date-picker" placeholder="Click to select date range" class="pl-10 pr-10 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent w-64 cursor-pointer" readonly>
                            <svg class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <svg class="absolute right-3 top-2.5 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>

                        <div class="relative">
                            <input type="text" id="search-input" placeholder="Search Order ID (e.g., 1024)" class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent w-64">
                            <svg class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>

                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="flex gap-3">
                        <button id="refresh-btn" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition duration-150 flex items-center gap-2">
                            <i data-lucide="refresh-cw" size="16"></i> Refresh
                        </button>
                        
                        <button id="export-btn" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition duration-150 flex items-center gap-2">
                            <i data-lucide="download" size="16"></i> Export CSV
                        </button>
                    </div>
                </div>
            </div>

            <!-- Reports Table -->
            <div class="bg-[#F4F6FA] p-6 rounded-3xl shadow-lg border border-gray-100">
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-[#F4F6FA] border-separate" style="border-spacing: 0 0.5rem;">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="text-left py-3 px-4 font-semibold text-sm text-gray-600 uppercase tracking-wider rounded-l-lg">Trans ID</th>
                                <th class="text-left py-3 px-4 font-semibold text-sm text-gray-600 uppercase tracking-wider">Date & Time</th>
                                <th class="text-left py-3 px-4 font-semibold text-sm text-gray-600 uppercase tracking-wider">Items Count</th>
                                <th class="text-left py-3 px-4 font-semibold text-sm text-gray-600 uppercase tracking-wider">Payment</th>
                                <th class="text-left py-3 px-4 font-semibold text-sm text-gray-600 uppercase tracking-wider" style="text-align: right;">Total Amount</th>
                                <th class="text-center py-3 px-4 font-semibold text-sm text-gray-600 uppercase tracking-wider rounded-r-lg">Receipt Action</th>
                            </tr>
                        </thead>
                        <tbody id="report-table-body" class="text-gray-700">
                            <tr><td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">Loading records...</td></tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Container -->
                <div id="pagination-container" class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6 mt-6">
                    <!-- Pagination will be rendered here by JavaScript -->
                </div>
            </div>

            <!-- Sales Summary -->
            <div class="bg-[#F4F6FA] p-6 rounded-3xl shadow-lg border border-gray-100 mt-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                    <i data-lucide="bar-chart-3"></i> Sales Summary
                </h2>
                <div id="summary-container" class="text-center text-gray-500">
                    Loading summary data...
                </div>
            </div>

    <!-- DIGITAL RECEIPT MODAL -->
    <div id="receipt-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 max-h-[90vh] overflow-hidden">

            <!-- Modal Header -->
            <div class="bg-blue-600 text-white px-6 py-4 flex justify-between items-center">
                <h2 class="text-xl font-bold">Digital Receipt View</h2>
                <button onclick="closeModal()" class="text-white hover:text-gray-200 text-2xl">&times;</button>
            </div>

            <!-- Receipt Content -->
            <div class="p-6 overflow-y-auto max-h-[70vh]" id="printable-receipt-area">
                <div class="receipt-paper text-sm font-mono leading-relaxed">

                    <!-- Header -->
                    <div class="text-center mb-4">
                        <div class="text-lg font-bold uppercase"><?php echo htmlspecialchars($branch_name); ?></div>
                        <div class="text-xs text-gray-600"><?php echo htmlspecialchars($branch_address); ?></div>
                        <div class="text-xs text-gray-600">Tel: <?php echo htmlspecialchars($staff_phone); ?></div>
                    </div>

                    <!-- Transaction Details -->
                    <div class="border-t border-b border-gray-300 py-2 mb-4">
                        <div class="flex justify-between text-xs">
                            <span>OR#: <strong id="rec-id">000</strong></span>
                            <span id="rec-date">00/00/0000</span>
                        </div>
                        <div class="text-xs">Cashier: <span id="rec-cashier">Unknown</span></div>
                    </div>

                    <!-- Items Table -->
                    <table class="w-full text-xs mb-4">
                        <thead>
                            <tr class="border-b border-gray-300">
                                <th class="text-left py-1">Item</th>
                                <th class="text-center py-1 w-12">Qty</th>
                                <th class="text-right py-1 w-16">Price</th>
                                <th class="text-right py-1 w-20">Total</th>
                            </tr>
                        </thead>
                        <tbody id="rec-items-list">
                            <!-- Items will be populated by JavaScript -->
                        </tbody>
                    </table>

                    <!-- Totals -->
                    <div class="border-t border-gray-300 pt-2">
                        <div class="flex justify-between text-sm font-bold mb-1">
                            <span>TOTAL:</span>
                            <span id="rec-total">₱0.00</span>
                        </div>
                        <div class="flex justify-between text-xs mb-1">
                            <span>Payment Method:</span>
                            <span id="rec-payment-method">CASH</span>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="text-center text-xs mt-4 pt-2 border-t border-gray-300">
                        Thank you for your purchase!<br>
                        This serves as your official receipt.<br>
                        Returns valid within 7 days.
                    </div>
                </div>
            </div>

            <!-- Modal Actions -->
            <div class="bg-gray-50 px-6 py-4 flex justify-between">
                <button onclick="closeModal()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition">
                    Close
                </button>
                <button onclick="printReceipt()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                    </svg>
                    Print Receipt
                </button>
            </div>
        </div>
    </div>
        </main>

    </div>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
      const BRANCH_ID = <?php echo $required_branch_id; ?>;
      const DEFAULT_CASHIER = "<?php echo $user_full_name; ?>";
      lucide.createIcons();
    </script>
    <script src="js/reports.js"></script>
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <script src="js/report_table.js"></script>
    <!-- Combined JavaScript Logic (from script.js and inline functions) -->

    <script src="js/chat.js?v=<?php echo time(); ?>"></script>
        <script src="js/notifications_bell.js" defer></script>
        <script src="js/script.js"></script>
</body>
</html>
