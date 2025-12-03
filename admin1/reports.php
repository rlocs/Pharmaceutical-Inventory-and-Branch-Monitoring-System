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
// 3. Check Branch - Allow access to multiple branches
$allowed_branches = [1, 2, 3]; // All branches that can be accessed
if ($_SESSION["user_role"] === 'Staff' && !in_array($_SESSION["branch_id"], $allowed_branches)) {
    // Redirect staff who don't belong to allowed branches
    header("Location: ../login.php?error=branch_mismatch"); 
    exit;
}

// Set the required branch ID based on user's actual branch (for Staff)
$user_full_name = htmlspecialchars($_SESSION['first_name'] ?? 'Admin') . ' ' . htmlspecialchars($_SESSION['last_name'] ?? 'User');
$user_role = htmlspecialchars($_SESSION['user_role'] ?? 'Admin');
$current_branch_id = $_SESSION['branch_id'];
$user_id = $_SESSION['user_id'];

$branch_names = [
    1 => 'Lipa, Batangas',
    2 => 'Sto Tomas, Batangas',
    3 => 'Malvar, Batangas'
];
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

// Initialize sales summary variables
$totalTransactions = 0;
$totalSales = 0.00;
$totalTax = 0.00;
$totalDiscounts = 0.00;
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
    <style>
        .bg-custom-dark-header { background-color: #1E293B; }
        .bg-custom-bg-white { background-color: #FFFFFF; }
        .backdrop {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background-color: rgba(0, 0, 0, 0.5); z-index: 40;
            display: none;
        }
        .slide-in {
            animation: slideIn 0.3s ease-out forwards;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
    <script>
        // Make the current user's ID available to the JavaScript files
        window.currentUserId = <?php echo json_encode($_SESSION['user_id'] ?? 0); ?>;
        const BRANCH_ID = <?php echo $required_branch_id; ?>;
        const DEFAULT_CASHIER = "<?php echo $user_full_name; ?>";
        
        // User context for JavaScript
        const userContext = {
            fullName: "<?php echo $user_full_name; ?>",
            role: "<?php echo $user_role; ?>",
            branchId: <?php echo $current_branch_id; ?>,
            branchName: "<?php echo $branch_name; ?>"
        };
        
        // Sales data structure - Initialize with empty data for all branches
let salesData = [
    {
        id: 1,
        name: '<?php echo $branch_name; ?>',
        totalSales: 0,
        totalTransactions: 0,
        totalTax: 0,
        totalDiscounts: 0,
        averageTransactionValue: 0,
        returnsCount: 0,
    },
    {
        id: 2,
        name: 'Sto Tomas Branch',
        totalSales: 0,
        totalTransactions: 0,
        totalTax: 0,
        totalDiscounts: 0,
        averageTransactionValue: 0,
        returnsCount: 0,
    },
    {
        id: 3,
        name: 'Malvar Branch',
        totalSales: 0,
        totalTransactions: 0,
        totalTax: 0,
        totalDiscounts: 0,
        averageTransactionValue: 0,
        returnsCount: 0,
    },
];

/**
 * Loads sales data for all branches asynchronously from API
 */
function loadSalesData() {
    const branches = [1, 2, 3];
    let loadedCount = 0;

    branches.forEach(branchId => {
        fetch(`api/sales_history.php?include_summary=1&branch_id=${branchId}`, {
            method: 'GET',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin'
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.summary) {
                // Update branch data in salesData array
                const branchIndex = salesData.findIndex(b => b.id === branchId);
                if (branchIndex !== -1) {
                    salesData[branchIndex] = {
                        ...salesData[branchIndex],
                        totalSales: data.summary.total_sales,
                        totalTransactions: data.summary.total_transactions,
                        totalTax: data.summary.total_tax,
                        totalDiscounts: data.summary.total_discount,
                        averageTransactionValue: data.summary.total_transactions > 0 ? 
                            data.summary.total_sales / data.summary.total_transactions : 0
                    };
                    
                    loadedCount++;
                    // If all branches are loaded, re-render dashboard
                    if (loadedCount === branches.length) {
                        renderDashboard();
                    }
                }
            } else {
                console.error(`Failed to load sales data for branch ${branchId}:`, data.error || 'Unknown error');
                loadedCount++;
            }
        })
        .catch(err => {
            console.error(`Error loading sales data for branch ${branchId}:`, err);
            loadedCount++;
        });
    });
}
    </script>
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
                <img src="https://placehold.co/40x40/fff/1E3A8A?text=Admin" alt="Mercury Logo" class="rounded-full border-2 border-white">
                <span class="text-2xl font-bold text-gray-800 tracking-wider">
                    <span class="text-white">MERCURY</span>
                </span>
            </div>

            <!-- Icons Section -->
            <div id="icons-section" class="flex items-center space-x-4">
                <?php include __DIR__ . '/includes/notification_bell.php'; ?>
                <!-- Hamburger Menu Icon (OPEN BUTTON) -->
                <button id="open-sidebar-btn" aria-label="Menu" onclick="toggleSidebar()" class="p-2 bg-slate-700 hover:bg-slate-600 rounded-lg transition duration-150">
                    <svg class="lucide" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/></svg>
                </button>
            </div>
        </header>

        <!-- 2. SECONDARY NAVIGATION BAR (Light Cream/White) -->
        <nav class="bg-[#F4F6FA] border-b border-gray-200 flex justify-between items-center px-6 py-3 shadow-sm sticky top-16 z-20">

            <!-- Navigation Links - INCREASED TEXT SIZE to text-base -->
            <div class="flex space-x-8 text-base font-medium">
                <a href="admin1b<?php echo $current_branch_id; ?>.php"class="py-2 px-3 rounded-md text-black hover:bg-gray-100 hover:text-black font-medium transition-all duration-300">Dashboard</a>
                <a href="reports.php" class="py-2 px-3 rounded-md bg-blue-100 text-blue-800 hover:text-black font-medium transition-all duration-300">Reports</a>
                <a href="account.php" class="py-2 px-3 rounded-md text-black hover:bg-gray-100 hover:text-black font-medium transition-all duration-300">Account</a>
            </div>

            <!-- Logout Button -->
            <a href="b-crud/logout.php" class="bg-red-500 hover:bg-red-600 text-white font-medium py-2 px-4 rounded-lg transition duration-150 shadow-md text-sm inline-block">
                Logout
            </a>
        </nav>

        <!-- 3. MAIN CONTENT AREA (Cream Background) -->
        <main class="bg-custom-bg-white p-6 flex-grow h-full relative z-10 min-h-[80vh]">
            
            <header class="w-full max-w-6xl mx-auto mb-8">
                <h1 class="text-3xl sm:text-4xl font-extrabold text-gray-800 border-b-4 border-blue-500 pb-2">
                    Monthly Sales 
                </h1>
            </header>

            <div id="report-dashboard" class="w-full max-w-6xl mx-auto">
                <!-- Content will be injected by JavaScript -->
            </div>
            
            <div id="detailed-view" class="w-full max-w-6xl mx-auto mt-10">
                <!-- Detailed view will be injected here -->
            </div>

        </main>
    </div>

    <!-- Include Invoice Modal for consistent receipt display -->
    <?php include __DIR__ . '/includes/invoice_modal.php'; ?>
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        lucide.createIcons();
        
        // --- JAVASCRIPT RENDERING LOGIC ---
        
        let selectedBranchId = null;

        /**
         * ACCESS CONTROL CHECK (Client-side simulation of PHP logic)
         */
/**
 * ACCESS CONTROL CHECK (Client-side simulation of PHP logic)
 */
const isViewAllowed = (userContext) => {
    const role = userContext.role;
    const branchId = userContext.branchId;
    const ALLOWED_BRANCHES = [1, 2, 3];

    if (role === 'Admin') {
        return true;
    }
    // Staff must be from one of the allowed branches
    if (role === 'Staff' && ALLOWED_BRANCHES.includes(branchId)) {
        return true;
    }
    return false;
};

        /**
         * Formats a number as Philippine Peso currency.
         */
        function formatCurrency(value, fractionDigits = 2) {
            return new Intl.NumberFormat('en-PH', { 
                style: 'currency',
                currency: 'PHP',
                minimumFractionDigits: fractionDigits,
                maximumFractionDigits: fractionDigits,
            }).format(value);
        }

        /**
         * Gets the border color class for a branch.
         */
        function getBranchColor(id) {
            switch (id) {
                case 1: return 'border-t-yellow-500'; 
                case 2: return 'border-t-red-500';
                case 3: return 'border-t-green-500';
                default: return 'border-t-gray-400';
            }
        }

        /**
         * Renders the detailed view section for a selected branch.
         */
        function renderDetailedView(branch) {
            const container = document.getElementById('detailed-view');

            if (!branch) {
                container.innerHTML = '';
                return;
            }

            // Show loading state first
            container.innerHTML = `
                <div class="bg-white p-8 rounded-xl shadow-2xl border-l-8 border-indigo-500 slide-in">
                    <div class="flex justify-between items-center mb-6 border-b pb-3">
                        <h3 class="text-2xl font-bold text-gray-800">
                            Transaction Details for ${branch.name}
                        </h3>
                        <button onclick="viewDetails(null)" class="text-gray-500 hover:text-red-500 transition duration-150 p-1 rounded-full hover:bg-gray-100">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                    <div class="text-center py-8">
                        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                        <p class="mt-2 text-gray-600">Loading transaction data...</p>
                    </div>
                </div>
            `;

            // Fetch transaction data from API
            fetch(`api/sales_history.php?page=1&limit=50&branch_id=${branch.id}`, {
                method: 'GET',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin'
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.data) {
                    renderTransactionTable(container, branch, data.data);
                } else {
                    container.innerHTML = `
                        <div class="bg-white p-8 rounded-xl shadow-2xl border-l-8 border-red-500 slide-in">
                            <div class="flex justify-between items-center mb-6 border-b pb-3">
                                <h3 class="text-2xl font-bold text-gray-800">
                                    Transaction Details for ${branch.name}
                                </h3>
                                <button onclick="viewDetails(null)" class="text-gray-500 hover:text-red-500 transition duration-150 p-1 rounded-full hover:bg-gray-100">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            </div>
                            <div class="text-center py-8 text-red-600">
                                <p>Failed to load transaction data</p>
                                <p class="text-sm mt-2">${data.error || 'Unknown error'}</p>
                            </div>
                        </div>
                    `;
                }
            })
            .catch(err => {
                console.error('Error loading transaction data:', err);
                container.innerHTML = `
                    <div class="bg-white p-8 rounded-xl shadow-2xl border-l-8 border-red-500 slide-in">
                        <div class="flex justify-between items-center mb-6 border-b pb-3">
                            <h3 class="text-2xl font-bold text-gray-800">
                                Transaction Details for ${branch.name}
                            </h3>
                            <button onclick="viewDetails(null)" class="text-gray-500 hover:text-red-500 transition duration-150 p-1 rounded-full hover:bg-gray-100">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                        <div class="text-center py-8 text-red-600">
                            <p>Connection error while loading transaction data</p>
                        </div>
                    </div>
                `;
            });
        }

        /**
         * Renders the transaction table with the fetched data
         */
        function renderTransactionTable(container, branch, transactions) {
            let tableHtml = `
                <div class="bg-white p-8 rounded-xl shadow-2xl border-l-8 border-indigo-500 slide-in">
                    <div class="flex justify-between items-center mb-6 border-b pb-3">
                        <h3 class="text-2xl font-bold text-gray-800">
                            Transaction Details for ${branch.name}
                        </h3>
                        <button onclick="viewDetails(null)" class="text-gray-500 hover:text-red-500 transition duration-150 p-1 rounded-full hover:bg-gray-100">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trans ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items Count</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Receipt Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
            `;

            if (transactions.length === 0) {
                tableHtml += `
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                            No transactions found for this branch
                        </td>
                    </tr>
                `;
            } else {
                transactions.forEach(transaction => {
                    const itemCount = transaction.items ? transaction.items.reduce((sum, item) => sum + parseInt(item.Quantity || item.qty || 0), 0) : 0;
                    const paymentMethod = transaction.PaymentMethod || 'Cash';

                    // Style payment method badges
                    let badgeClass = 'bg-green-100 text-green-800';
                    if (paymentMethod.toLowerCase().includes('card')) badgeClass = 'bg-blue-100 text-blue-800';
                    if (paymentMethod.toLowerCase().includes('gcash')) badgeClass = 'bg-purple-100 text-purple-800';

                    tableHtml += `
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-mono font-bold text-gray-900">
                                #${transaction.TransactionID}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                ${transaction.TransactionDateTime}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                ${itemCount} items
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${badgeClass}">
                                    ${paymentMethod}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                                ₱${parseFloat(transaction.TotalAmount).toFixed(2)}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm">
                                <button onclick="viewReceipt(${JSON.stringify(transaction).replace(/"/g, '&quot;')})"
                                        class="text-blue-600 hover:text-blue-900 font-medium">
                                    View Receipt
                                </button>
                            </td>
                        </tr>
                    `;
                });
            }

            tableHtml += `
                            </tbody>
                        </table>
                    </div>

                    ${transactions.length > 0 ? `<p class="mt-4 text-sm text-gray-600">Showing ${transactions.length} recent transactions</p>` : ''}
                </div>
            `;

            container.innerHTML = tableHtml;
        }

        /**
         * Handles clicking the View Details button.
         */
        function viewDetails(branchId) {
            const dashboard = document.getElementById('report-dashboard');

            // Find the button and reset its state
            if (selectedBranchId !== null) {
                const oldButton = dashboard.querySelector(`[data-branch-id="${selectedBranchId}"]`);
                if (oldButton) {
                    oldButton.classList.remove('bg-gray-300', 'text-gray-700', 'hover:bg-gray-400');
                    oldButton.classList.add('bg-blue-600', 'text-white', 'hover:bg-blue-700');
                    oldButton.textContent = 'View Details';
                }
            }
            
            if (branchId === selectedBranchId) {
                // If clicking the currently open branch, close it.
                selectedBranchId = null;
                renderDetailedView(null);
            } else {
                // Open the new branch details.
                selectedBranchId = branchId;
                const branch = salesData.find(b => b.id === branchId);
                renderDetailedView(branch);

                // Update the new button state
                const newButton = dashboard.querySelector(`[data-branch-id="${selectedBranchId}"]`);
                if (newButton) {
                    newButton.classList.remove('bg-blue-600', 'text-white', 'hover:bg-blue-700');
                    newButton.classList.add('bg-gray-300', 'text-gray-700', 'hover:bg-gray-400');
                    newButton.textContent = 'Hide Details';
                }
            }
        }

        /**
         * Renders the main dashboard content (total sales and branch summaries).
         */
        function renderDashboard() {
            const container = document.getElementById('report-dashboard');

            if (!isViewAllowed(userContext)) {
                // ACCESS DENIED VIEW
                container.innerHTML = `
                    <div class="mt-10 p-10 bg-red-100 border-l-4 border-red-500 rounded-lg text-red-800 shadow-md">
                        <p class="text-xl font-bold mb-2">Access Denied</p>
                        <p>You (${userContext.fullName}, ${userContext.role}) do not have the required permissions to view this sales report for Branch <?php echo $required_branch_id; ?>.</p>
                        <p class="mt-2 text-sm">This is based on the security logic in your original PHP file.</p>
                    </div>
                `;
                return;
            }

            // --- ACCESS GRANTED VIEW ---

            const totalSales = salesData.reduce((sum, branch) => sum + branch.totalSales, 0);

            // 1. Global Total Sales Box
            let html = `
                <div class="w-full mb-10">
                    <div class="bg-gradient-to-r from-blue-600 to-indigo-700 p-6 sm:p-8 rounded-2xl shadow-2xl text-white">
                        <div class="flex items-center justify-between">
                            <h2 class="text-2xl font-bold">Total Consolidated Sales</h2>
                            <!-- Coin Icon -->
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 opacity-70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10s-2.9 3.4-7 4c-.5 0-1-.5-1-1v-2c0-.5.5-1 1-1 4.1-.6 7-4 7-4z"/><path d="M22 21V3"/><path d="M2 10s2.9 3.4 7 4c.5 0 1-.5 1-1v-2c0-.5-.5-1-1-1-4.1-.6-7-4-7-4z"/><path d="M2 21V3"/></svg>
                        </div>
                        <p class="text-5xl sm:text-6xl font-black mt-4 tracking-tighter">
                            ${formatCurrency(totalSales, 0)}
                        </p>
                        <p class="mt-2 text-sm opacity-90">Sum of all active branch sales for the current period.</p>
                    </div>
                </div>
                <h3 class="text-2xl font-semibold text-gray-700 mb-5 border-b pb-2">Individual Branch Performance Summaries</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            `;

            // 2. Individual Branch Summary Boxes
            salesData.forEach(branch => {
                html += `
                    <div class="bg-white p-6 rounded-xl shadow-lg border-t-4 ${getBranchColor(branch.id)}">
                        <h4 class="text-xl font-bold mb-3 text-gray-800 border-b pb-2">
                            ${branch.name}
                        </h4>
                        
                        <div class="grid grid-cols-2 gap-3">
                            <!-- 1. Total Transactions -->
                            <div class="bg-gray-50 p-3 rounded-lg shadow-inner border border-gray-200">
                                <p class="text-xs font-medium text-gray-500">Total Transactions</p>
                                <div class="flex items-center justify-between mt-1">
                                    <p class="text-xl font-black text-gray-900">${branch.totalTransactions.toLocaleString()}</p>
                                </div>
                            </div>

                            <!-- 2. Total Sales -->
                            <div class="bg-gray-50 p-3 rounded-lg shadow-inner border border-gray-200">
                                <p class="text-xs font-medium text-gray-500">Total Sales (PHP)</p>
                                <div class="flex items-center justify-between mt-1">
                                    <p class="text-xl font-black text-green-600">${formatCurrency(branch.totalSales, 2)}</p>
                                </div>
                            </div>

                            <!-- 3. Total Tax -->
                            <div class="bg-gray-50 p-3 rounded-lg shadow-inner border border-gray-200">
                                <p class="text-xs font-medium text-gray-500">Total Tax (PHP)</p>
                                <div class="flex items-center justify-between mt-1">
                                    <p class="text-xl font-black text-yellow-600">${formatCurrency(branch.totalTax, 2)}</p>
                                </div>
                            </div>

                            <!-- 4. Total Discounts -->
                            <div class="bg-gray-50 p-3 rounded-lg shadow-inner border border-gray-200">
                                <p class="text-xs font-medium text-gray-500">Total Discounts (PHP)</p>
                                <div class="flex items-center justify-between mt-1">
                                    <p class="text-xl font-black text-red-600">${formatCurrency(branch.totalDiscounts, 2)}</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- View Details Button -->
                        <button onclick="viewDetails(${branch.id})" data-branch-id="${branch.id}"
                                class="mt-4 w-full py-2 px-4 rounded-lg font-semibold transition duration-150 bg-blue-600 text-white hover:bg-blue-700">
                            View Details
                        </button>
                    </div>
                `;
            });

            html += `</div>`;
            container.innerHTML = html;
        }

/**
 * Loads sales data asynchronously from all branches API
 */
function loadSalesData() {
    const branches = [1, 2, 3];
    let loadedCount = 0;

    branches.forEach(branchId => {
        fetch(`api/sales_history.php?include_summary=1&branch_id=${branchId}`, {
            method: 'GET',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin'
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.summary) {
                // Update branch data in salesData array
                const branchIndex = salesData.findIndex(b => b.id === branchId);
                if (branchIndex !== -1) {
                    salesData[branchIndex] = {
                        ...salesData[branchIndex],
                        totalSales: data.summary.total_sales,
                        totalTransactions: data.summary.total_transactions,
                        totalTax: data.summary.total_tax,
                        totalDiscounts: data.summary.total_discount,
                        averageTransactionValue: data.summary.total_transactions > 0 ?
                            data.summary.total_sales / data.summary.total_transactions : 0
                    };

                    loadedCount++;
                    // If all branches are loaded, re-render dashboard
                    if (loadedCount === branches.length) {
                        renderDashboard();
                    }
                }
            } else {
                console.error(`Failed to load sales data for branch ${branchId}:`, data.error || 'Unknown error');
                loadedCount++;
            }
        })
        .catch(err => {
            console.error(`Error loading sales data for branch ${branchId}:`, err);
            loadedCount++;
        });
    });
}

/**
 * Displays the receipt modal for a transaction
 */
function viewReceipt(transaction) {
    // Transform transaction data to match invoice modal format
    const invoiceData = {
        transaction_id: transaction.TransactionID,
        branch: transaction.BranchName || 'Branch',
        cashier: transaction.CashierName || 'Staff',
        items: transaction.items ? transaction.items.map(item => ({
            name: item.name || item.MedicineNameSnapshot,
            qty: item.qty || item.Quantity,
            price: item.price || item.PricePerUnit
        })) : [],
        total_amount: transaction.TotalAmount,
        raw_total: transaction.TotalAmount + (transaction.TotalDiscountAmount || 0),
        discount_amount: transaction.TotalDiscountAmount || 0,
        vat_amount: transaction.TotalTaxAmount || 0,
        discount_type: 'regular',
        payment_amount: transaction.TotalAmount, // Assuming full payment
        change_amount: 0,
        payment_method: transaction.PaymentMethod || 'Cash'
    };

    // Call the global showInvoiceModal function from invoice_modal.php
    if (window.showInvoiceModal) {
        window.showInvoiceModal(invoiceData);
    } else {
        console.error('Invoice modal function not available');
        alert('Receipt modal is not available. Please refresh the page.');
    }
}

// Run the dashboard rendering when the page loads, then load real data
document.addEventListener('DOMContentLoaded', function() {
    renderDashboard();
    loadSalesData();
});
    </script>
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <script src="js/report_table.js"></script>
    <!-- Combined JavaScript Logic (from script.js and inline functions) -->

    <script src="js/chat.js?v=<?php echo time(); ?>"></script>
        <script src="js/notifications_bell.js" defer></script>
        <script src="js/script.js"></script>
</body>
</html>