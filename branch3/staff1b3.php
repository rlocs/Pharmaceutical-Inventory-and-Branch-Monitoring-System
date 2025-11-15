<?php
// Start the session on every page
session_start();

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

// NOTE: You'll need to update your b-login.php to also pull FirstName and LastName 
// and store them in the session for this to work perfectly.
// For now, it uses 'Staff' and 'User' as fallback names.

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mercury Dashboard</title>
    
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
        <nav class="bg-white border-b border-gray-200 flex justify-between items-center px-6 py-3 shadow-sm sticky top-16 z-20">

            <!-- Navigation Links - INCREASED TEXT SIZE to text-base -->
            <div class="flex space-x-8 text-base font-medium">

                <a href="staff1b<?php echo $current_branch_id; ?>.php" class="py-2 px-3 rounded-md bg-blue-100 text-blue-800 font-medium transition-all duration-300">Dashboard</a>
                <a href="med_inventory.php" class="py-2 px-3 rounded-md text-black hover:bg-gray-100 hover:text-black font-medium transition-all duration-300">Med Inventory</a>
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
        <main class="bg-custom-bg-cream p-6 flex-grow h-full relative z-10">
            <div id="dynamic-content" class="main-content flex-1 p-6 lg:p-10 overflow-y-auto bg-main-bg-color">
                <h2 id="page-title" class="text-4xl font-extrabold text-gray-900 mb-4">
                    Branch <?php echo $current_branch_id; ?> Dashboard
                </h2>
                <p class="text-gray-600 text-lg mb-8">
                    Welcome back, <?php echo $user_full_name; ?>. Managing operations for <?php echo $branch_name; ?>.
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="bg-white p-6 rounded-3xl shadow-lg border border-gray-100">
                        <p class="text-sm font-semibold text-gray-500">Sales Value (Branch <?php echo $current_branch_id; ?>)</p>
                        <p class="text-3xl font-bold text-gray-800 mt-1">$12,450.00</p>
                        <span class="text-sm text-green-500 flex items-center mt-2">+5.2% Daily</span>
                    </div>

                    <div class="bg-white p-6 rounded-3xl shadow-lg border border-gray-100">
                        <p class="text-sm font-semibold text-gray-500">Inventory Items</p>
                        <p class="text-3xl font-bold text-gray-800 mt-1">452 SKUs</p>
                        <span class="text-sm text-primary-accent flex items-center mt-2">Check for restock needs</span>
                    </div>

                    <div class="bg-white p-6 rounded-3xl shadow-lg border border-gray-100">
                        <p class="text-3xl font-bold text-red-500 mt-1">7</p>
                        <span class="text-sm text-red-500 flex items-center mt-2">Urgent attention required</span>
                    </div>
                </div>
            </div>
        </main>

        </div>

        <!-- Zoomed Chat Host -->
       

        <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Combined JavaScript Logic (from script.js and inline functions) -->

    <script src="js/chat.js?v=<?php echo time(); ?>"></script>
        <script src="js/notifications_bell.js" defer></script>
        <script src="js/script.js"></script>
</body>
</html>
