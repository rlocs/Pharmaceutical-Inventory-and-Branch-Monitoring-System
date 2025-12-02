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

// Mock Branch Name lookup (Replace with actual database query if needed)
$branch_names = [
    1 => 'Lipa, Batangas',
    2 => 'Sto Tomas, Batangas',
    3 => 'Malvar, Batangas'
];
$branch_name = $branch_names[$current_branch_id] ?? "Branch {$current_branch_id}";

// ------------------------------------------------------------------
// DATABASE CONNECTION AND DATA FETCHING
// ------------------------------------------------------------------



try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Fetch categories from Categories table
    $categories_sql = "SELECT CategoryID, CategoryName FROM Categories ORDER BY CategoryName";
    $categories_stmt = $pdo->prepare($categories_sql);
    $categories_stmt->execute();
    $categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch medicines for the current branch with pagination
    $page = 1; // Default to first page
    $limit = 10; // Items per page
    $offset = ($page - 1) * $limit;

    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM BranchInventory bi
                  JOIN medicines m ON bi.MedicineID = m.MedicineID
                  WHERE bi.BranchID = ?";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute([$current_branch_id]);
    $total = $stmt->fetch()['total'];

    // Get medicines with category name
    $sql = "SELECT bi.BranchInventoryID, m.MedicineName, c.CategoryName AS Category, m.Form, m.Unit,
                   bi.Stocks, bi.Price, bi.ExpiryDate
            FROM BranchInventory bi
            JOIN medicines m ON bi.MedicineID = m.MedicineID
            LEFT JOIN Categories c ON m.CategoryID = c.CategoryID
            WHERE bi.BranchID = ?
            ORDER BY m.MedicineName
            LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$current_branch_id, $limit, $offset]);
    $medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // Handle database errors gracefully
    $medicines = [];
    $total = 0;
    error_log("Database error in med_inventory.php: " . $e->getMessage());
}

// Helper functions for status and classes
function getStatus($stocks, $expiryDate) {
    $today = new DateTime();
    $expiry = new DateTime($expiryDate);

    if ($expiry < $today) {
        return 'Expired';
    } elseif ($stocks == 0) {
        return 'Out of Stock';
    } elseif ($stocks > 0 && $stocks <= 10) {
        return 'Low Stock';
    } elseif ($expiry <= $today->modify('+90 days')) {
        return 'Expiring Soon';
    } else {
        return 'Active';
    }
}

function getStatusClass($status) {
    switch ($status) {
        case 'Active': return 'bg-green-100 text-green-800';
        case 'Low Stock': return 'bg-yellow-100 text-yellow-800';
        case 'Out of Stock': return 'bg-red-100 text-red-800';
        case 'Expiring Soon': return 'bg-orange-100 text-orange-800';
        case 'Expired': return 'bg-gray-100 text-gray-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getStockClass($stocks) {
    $numStocks = (int)$stocks;
    if ($numStocks === 0) return 'bg-red-100 text-red-800';
    if ($numStocks <= 10) return 'bg-yellow-100 text-yellow-800';
    return 'bg-green-100 text-green-800';
}

function formatDate($dateStr) {
    if (!$dateStr) return '';
    $date = new DateTime($dateStr);
    return $date->format('m/d/Y');
}

function escapeHtml($text) {
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Med Inventory - Branch <?php echo $current_branch_id; ?> | MERCURY System</title>
    
    <!-- Load Tailwind CSS CDN and Configuration -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="js/theme.js"></script>
    
    <!-- Load Lucide icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Load SweetAlert2 library from CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/chat_window.css?v=<?php echo time(); ?>">
    <script>
        // Make the current user's ID available to the JavaScript files
        window.currentUserId = <?php echo json_encode($_SESSION['user_id'] ?? 0); ?>;
    </script>
    <script src="js/alerts.js?v=<?php echo time(); ?>" defer></script>
    <script src="js/medicine.js?v=<?php echo time(); ?>" defer></script>
    

    <!-- Custom CSS Styles are in css/style.css -->
</head>
<body class="overflow-x-hidden">

    <!-- Backdrop for click outside close -->
    <div id="backdrop" class="backdrop" onclick="toggleSidebar()"></div>

    <!-- Outer container takes full screen space -->
    <div class="app-container bg-white">
        
        <!-- 1. TOP HEADER BAR (Minimalist Dark) -->
        <header id="main-header" class="bg-custom-dark-header text-white p-4 flex justify-between items-center sticky top-0 z-30 shadow-lg">
            
            <!-- Logo Section -->
            <div class="flex items-center space-x-3">
                <img src="https://placehold.co/40x40/fff/1E3A8A?text=B<?php echo $current_branch_id; ?>" alt="Mercury Logo" class="rounded-full border-2 border-white">
                <span class="text-2xl font-bold text-white tracking-wider">
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
                <a href="med_inventory.php" class="py-2 px-3 rounded-md bg-blue-100 text-blue-800 hover:text-black font-medium transition-all duration-300">Med Inventory</a>
                <a href="pos.php" class="py-2 px-3 rounded-md text-black hover:bg-gray-100 hover:text-black font-medium transition-all duration-300">POS</a>
                <a href="reports.php" class="py-2 px-3 rounded-md text-black hover:bg-gray-100 hover:text-black font-medium transition-all duration-300">Reports</a>
                <a href="account.php" class="py-2 px-3 rounded-md text-black hover:bg-gray-100 hover:text-black font-medium transition-all duration-300">Account</a>
            </div>

            <!-- Logout Button -->
            <a href="b-crud/logout.php" class="bg-red-500 hover:bg-red-600 text-white font-medium py-2 px-4 rounded-lg transition duration-150 shadow-md text-sm inline-block">
                Logout
            </a>
        </nav>

        <!-- 3. MAIN CONTENT AREA (Cream Background) -->
        <main class="bg-custom-bg-white p-6 flex-grow h-full relative z-10">
            <div id="dynamic-content" class="main-content flex-1 p-6 lg:p-10 overflow-y-auto bg-main-bg-color">
                <h2 id="page-title" class="text-4xl font-extrabold text-gray-900 mb-4">
                    Medicine Inventory
                </h2>
                <p class="text-gray-600 text-lg mb-8"></p>

                <!-- Alert Banners -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <!-- Near Expiry Alert -->
                    <div id="expirySoonAlert" class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded-lg shadow-md">
                        <div class="flex">
                            <div class="py-1">
                                <svg class="fill-current h-6 w-6 text-yellow-500 mr-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path d="M2.93 17.07A10 10 0 1 1 17.07 2.93 10 10 0 0 1 2.93 17.07zM9 5v6h2V5H9zm0 8h2v2H9v-2z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="font-bold"><span id="expiringSoonCount">...</span> Medications Expiring Soon</p>
                                <p class="text-sm">Will expire in less than 90 days.</p>
                                <button onclick="toggleAlertDetails('expiringSoonDetails')" class="text-yellow-800 hover:text-yellow-900 text-sm mt-1 underline">
                                    View Details
                                </button>
                                <div id="expiringSoonDetails" class="hidden mt-2 text-sm max-h-32 overflow-y-auto">
                                    <ul id="expiringSoonList" class="list-disc pl-4">
                                        <li class="text-gray-500">Loading...</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Low Stock Alert -->
                    <div id="lowStockAlert" class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg shadow-md">
                        <div class="flex">
                            <div class="py-1">
                                <svg class="fill-current h-6 w-6 text-red-500 mr-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path d="M2.93 17.07A10 10 0 1 1 17.07 2.93 10 10 0 0 1 2.93 17.07zM9 5v6h2V5H9zm0 8h2v2H9v-2z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="font-bold"><span id="lowStockCount">...</span> Items Need Attention</p>
                                <p class="text-sm">Stock is running low or out of stock.</p>
                                <button onclick="toggleAlertDetails('lowStockDetails')" class="text-red-800 hover:text-red-900 text-sm mt-1 underline">
                                    View Details
                                </button>
                                <div id="lowStockDetails" class="hidden mt-2 text-sm max-h-32 overflow-y-auto">
                                    <ul id="lowStockList" class="list-disc pl-4">
                                        <li class="text-gray-500">Loading...</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Expired Alert -->
                    <div id="expiredAlert" class="bg-gray-100 border-l-4 border-gray-500 text-gray-700 p-4 rounded-lg shadow-md">
                        <div class="flex">
                            <div class="py-1">
                                <svg class="fill-current h-6 w-6 text-gray-500 mr-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path d="M2.93 17.07A10 10 0 1 1 17.07 2.93 10 10 0 0 1 2.93 17.07zM9 5v6h2V5H9zm0 8h2v2H9v-2z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="font-bold"><span id="expiredCount">...</span> Expired Items</p>
                                <p class="text-sm">Items that have passed their expiry date.</p>
                                <button onclick="toggleAlertDetails('expiredDetails')" class="text-gray-800 hover:text-gray-900 text-sm mt-1 underline">
                                    View Details
                                </button>
                                <div id="expiredDetails" class="hidden mt-2 text-sm max-h-32 overflow-y-auto">
                                    <ul id="expiredList" class="list-disc pl-4">
                                        <li class="text-gray-500">Loading...</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-[#F4F6FA] p-6 rounded-3xl shadow-lg border border-gray-100">
                    <!-- Toolbar: Search, Category Filter and Add Button -->
                    <div class="flex flex-col md:flex-row justify-between items-center mb-6 space-y-4 md:space-y-0">
                        <div class="flex flex-col md:flex-row w-full md:w-2/3 gap-4">
                            <!-- Search Bar -->

                            <!-- Category Filter -->
                            <div class="relative w-full md:w-1/3">
                                <select id="categoryFilter" class="w-full py-2 pl-4 pr-8 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-accent appearance-none bg-[#F4F6FA]">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo escapeHtml($category['CategoryName']); ?>"><?php echo escapeHtml($category['CategoryName']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                    <i data-lucide="chevron-down" class="h-5 w-5 text-gray-400"></i>
                                </div>
                            </div>
                        </div>
                        <!-- Add New Medicine Button -->
                        <button onclick="showAddMedicineModal()" class="bg-primary-accent hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-150 w-full md:w-auto flex items-center gap-2">
                            <i data-lucide="plus" class="h-5 w-5"></i>
                            <span>Add New Medicine</span>
                        </button>
                    </div>

                    <!-- Inventory Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-[#F4F6FA] border-separate" style="border-spacing: 0 0.5rem;">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="text-left py-3 px-4 font-semibold text-sm text-gray-600 uppercase tracking-wider rounded-l-lg">Medicine</th>
                                    <th class="text-left py-3 px-4 font-semibold text-sm text-gray-600 uppercase tracking-wider">Form</th>
                                    <th class="text-left py-3 px-4 font-semibold text-sm text-gray-600 uppercase tracking-wider">Unit</th>
                                    
                                    <th class="text-left py-3 px-4 font-semibold text-sm text-gray-600 uppercase tracking-wider">Price</th>
                                    <th class="text-left py-3 px-4 font-semibold text-sm text-gray-600 uppercase tracking-wider">Category</th>
                                    <th class="text-center py-3 px-4 font-semibold text-sm text-gray-600 uppercase tracking-wider">Stocks</th>
                                    <th class="text-left py-3 px-4 font-semibold text-sm text-gray-600 uppercase tracking-wider">Expiry Date</th>
                                    <th class="text-left py-3 px-4 font-semibold text-sm text-gray-600 uppercase tracking-wider">Status</th>
                                    <th class="text-center py-3 px-4 font-semibold text-sm text-gray-600 uppercase tracking-wider rounded-r-lg">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-700">
                                <?php if (empty($medicines)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-4">No medicines found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($medicines as $med): ?>
                                        <?php
                                        $status = getStatus($med['Stocks'], $med['ExpiryDate']);
                                        $statusClass = getStatusClass($status);
                                        $stockClass = getStockClass($med['Stocks']);
                                        ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="py-3 px-4 border-b border-gray-200"><?php echo escapeHtml($med['MedicineName']); ?></td>
                                            <td class="py-3 px-4 border-b border-gray-200"><?php echo escapeHtml($med['Form']); ?></td>
                                            <td class="py-3 px-4 border-b border-gray-200"><?php echo escapeHtml($med['Unit']); ?></td>
                                            <td class="py-3 px-4 border-b border-gray-200">₱<?php echo number_format($med['Price'], 2); ?></td>
                                            <td class="py-3 px-4 border-b border-gray-200"><?php echo escapeHtml($med['Category']); ?></td>
                                            <td class="py-3 px-4 border-b border-gray-200 text-center">
                                                <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $stockClass; ?>">
                                                    <?php echo $med['Stocks']; ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-4 border-b border-gray-200"><?php echo formatDate($med['ExpiryDate']); ?></td>
                                            <td class="py-3 px-4 border-b border-gray-200">
                                                <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $statusClass; ?>">
                                                    <?php echo $status; ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-4 border-b border-gray-200 text-center">
                                                <button onclick="editMedicine(<?php echo $med['BranchInventoryID']; ?>)" class="text-blue-600 hover:text-blue-800 mr-2">
                                                    <i data-lucide="edit" class="h-4 w-4"></i>
                                                </button>
                                                <button onclick="deleteMedicine(<?php echo $med['BranchInventoryID']; ?>)" class="text-red-600 hover:text-red-800">
                                                    <i data-lucide="trash-2" class="h-4 w-4"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="flex justify-between items-center mt-8">
                        <span class="text-sm text-gray-600 showing-entries">
                            <?php
                            $start = ($page - 1) * $limit + 1;
                            $end = min($page * $limit, $total);
                            echo "Showing $start to $end of $total medicines";
                            ?>
                        </span>
                        <div class="inline-flex">
                            <button data-action="prev" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-l-lg <?php echo ($page <= 1) ? 'disabled:opacity-50' : ''; ?>">
                                Prev
                            </button>
                            <button data-action="next" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-r-lg <?php echo ($page >= ceil($total / $limit)) ? 'disabled:opacity-50' : ''; ?>">
                                Next
                            </button>
                        </div>
                    </div>

</main>

<!-- Modals -->
    <!-- Add Medicine Modal -->
    <div id="addMedicineModal" class="modal-overlay fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50" style="display: none;">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
            <div class="p-6">
                <h3 class="text-lg font-bold mb-4">Add New Medicine</h3>
                <form id="addMedicineForm" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Medicine Name</label>
                        <input type="text" name="medicineName" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Category</label>
                        <select name="category" id="addCategorySelect" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo escapeHtml($category['CategoryName']); ?>"><?php echo escapeHtml($category['CategoryName']); ?></option>
                            <?php endforeach; ?>
                            <option value="Others">Others (Specify)</option>
                        </select>
                        <input type="text" id="addOtherCategory" name="otherCategory" class="hidden mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Enter category name">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Form</label>
                        <select name="form" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Select Form</option>
                            <option value="Pill/Tablet">Pill/Tablet</option>
                            <option value="Liquid">Liquid</option>
                            <option value="Cream/Gel/Ointment">Cream/Gel/Ointment</option>
                            <option value="Inhaler">Inhaler</option>
                            <option value="Injection">Injection</option>
                            <option value="Patch">Patch</option>
                            <option value="Drops">Drops</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Unit</label>
                        <input type="text" name="unit" required placeholder="mg, mL, tablets, etc." class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Initial Stock</label>
                        <input type="number" name="stocks" required min="0" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Price</label>
                        <input type="number" name="price" required min="0" step="0.01" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Expiry Date</label>
                        <input type="date" name="expiryDate" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" onclick="closeAddModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md">
                            Add Medicine
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Medicine Modal -->
    <div id="editMedicineModal" class="modal-overlay fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50" style="display: none;">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md" onclick="event.stopPropagation()">
            <div class="p-6">
                <h3 class="text-lg font-bold mb-4">Edit Medicine</h3>
                <form id="editMedicineForm" class="space-y-4">
                    <input type="hidden" name="medicineId">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Medicine Name</label>
                        <input type="text" name="medicineName" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Category</label>
                        <select name="category" id="editCategorySelect" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo escapeHtml($category['CategoryName']); ?>"><?php echo escapeHtml($category['CategoryName']); ?></option>
                            <?php endforeach; ?>
                            <option value="Others">Others (Specify)</option>
                        </select>
                        <input type="text" id="editOtherCategory" name="otherCategory" placeholder="Enter category name" class="hidden mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Form</label>
                        <select name="form" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Select Form</option>
                            <option value="Pill/Tablet">Pill/Tablet</option>
                            <option value="Liquid">Liquid</option>
                            <option value="Cream/Gel/Ointment">Cream/Gel/Ointment</option>
                            <option value="Inhaler">Inhaler</option>
                            <option value="Injection">Injection</option>
                            <option value="Patch">Patch</option>
                            <option value="Drops">Drops</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Unit</label>
                        <input type="text" name="unit" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Stocks</label>
                        <input type="number" name="stocks" required min="0" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Price</label>
                        <input type="number" name="price" required min="0" step="0.01" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Expiry Date</label>
                        <input type="date" name="expiryDate" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" onclick="closeEditModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md">
                            Update Medicine
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
                  
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Combined JavaScript Logic -->
    <script src="js/chat.js?v=<?php echo time(); ?>"></script>
    <script src="js/notifications_bell.js" defer></script>
    <script src="js/script.js"></script>


</body>
</html>
