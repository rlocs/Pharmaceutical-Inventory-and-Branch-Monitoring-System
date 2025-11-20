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
$user_id = $_SESSION['user_id'];

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

require_once '../dbconnection.php';

$flash_success = [];
$flash_error = [];

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Handle form submissions: update details or change password
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'update_details') {
            $personal_phone = trim($_POST['personal_phone'] ?? '');
            $gender_in = trim($_POST['gender'] ?? '');
            $personal_address_in = trim($_POST['personal_address'] ?? '');
            $emergency_name_in = trim($_POST['emergency_name'] ?? '');
            $emergency_phone_in = trim($_POST['emergency_phone'] ?? '');

            // Basic validation
            if (strlen($personal_phone) > 50) {
                $flash_error[] = 'Personal phone is too long.';
            }
            if (strlen($personal_address_in) > 255) {
                $flash_error[] = 'Address is too long.';
            }

            if (empty($flash_error)) {
                $update = $pdo->prepare(
                    "UPDATE Details SET PersonalPhoneNumber = ?, Gender = ?, PersonalAddress = ?, EmergencyContactName = ?, EmergencyContactPhone = ? WHERE UserID = ?"
                );
                $update->execute([$personal_phone, $gender_in, $personal_address_in, $emergency_name_in, $emergency_phone_in, $user_id]);
                $flash_success[] = 'Personal details updated successfully.';
            }
        }

        if ($action === 'change_password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $flash_error[] = 'Please fill out all password fields.';
            } elseif ($new_password !== $confirm_password) {
                $flash_error[] = 'New password and confirmation do not match.';
            } elseif (strlen($new_password) < 8) {
                $flash_error[] = 'New password must be at least 8 characters.';
            } else {
                // Fetch current hashed password
                $stmtPwd = $pdo->prepare("SELECT HashedPassword FROM Accounts WHERE UserID = ?");
                $stmtPwd->execute([$user_id]);
                $row = $stmtPwd->fetch(PDO::FETCH_ASSOC);
                $hashed = $row['HashedPassword'] ?? '';

                if (!password_verify($current_password, $hashed)) {
                    $flash_error[] = 'Current password is incorrect.';
                } else {
                    $new_hashed = password_hash($new_password, PASSWORD_BCRYPT);
                    $upd = $pdo->prepare("UPDATE Accounts SET HashedPassword = ? WHERE UserID = ?");
                    $upd->execute([$new_hashed, $user_id]);
                    $flash_success[] = 'Password changed successfully.';
                }
            }
        }
    }

    // Fetch user account details
    $stmt = $pdo->prepare("SELECT Email, DateCreated, LastLogin FROM Accounts WHERE UserID = ?");
    $stmt->execute([$user_id]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch user details
    $stmt = $pdo->prepare("SELECT DateOfBirth, Gender, PersonalPhoneNumber, PersonalAddress, EmergencyContactName, EmergencyContactPhone, HireDate, Position, Salary, NationalIDNumber FROM Details WHERE UserID = ?");
    $stmt->execute([$user_id]);
    $details = $stmt->fetch(PDO::FETCH_ASSOC);

    // Set variables with real data
    $user_email = htmlspecialchars($account['Email'] ?? 'N/A');
    $date_created = $account['DateCreated'] ? date('Y-m-d', strtotime($account['DateCreated'])) : 'N/A';
    $last_login = $account['LastLogin'] ? date('Y-m-d', strtotime($account['LastLogin'])) : 'N/A';

    $date_of_birth = $details['DateOfBirth'] ? date('F j, Y', strtotime($details['DateOfBirth'])) : 'N/A';
    $gender = htmlspecialchars($details['Gender'] ?? 'N/A');
    $personal_phone = htmlspecialchars($details['PersonalPhoneNumber'] ?? 'N/A');
    $personal_address = htmlspecialchars($details['PersonalAddress'] ?? 'N/A');
    $emergency_name = htmlspecialchars($details['EmergencyContactName'] ?? 'N/A');
    $emergency_phone = htmlspecialchars($details['EmergencyContactPhone'] ?? 'N/A');
    $hire_date = $details['HireDate'] ? date('F j, Y', strtotime($details['HireDate'])) : 'N/A';
    $position = htmlspecialchars($details['Position'] ?? 'N/A');
    $salary = $details['Salary'] ? '₱ ' . number_format($details['Salary'], 2) : 'N/A';
    $national_id = $details['NationalIDNumber'] ? '****-**-'.substr($details['NationalIDNumber'], -3) : 'N/A';

    // For JS calendar hire date
    $hire_date_js = $details['HireDate'] ? date('Y-m-d', strtotime($details['HireDate'])) : '';

} catch (Exception $e) {
    // Fallback to mock data if DB fails
    $user_email = 'erryca.s@branch1.com';
    $date_created = '2024-05-20';
    $last_login = '2024-11-05';
    $date_of_birth = 'November 20, 1992';
    $gender = 'Female';
    $personal_phone = '+63 917 123 4567';
    $personal_address = '202 Oak St, Branch 1 Lipa City';
    $emergency_name = 'Jane Bautista';
    $emergency_phone = '+63 999 888 7777';
    $hire_date = 'May 15, 2022';
    $position = 'Staff';
    $salary = '₱ 48,000.00';
    $national_id = '****-**-012';
    $hire_date_js = '2022-05-15';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Account Information</title>
    
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

                <a href="staff1b<?php echo $current_branch_id; ?>.php"class="py-2 px-3 rounded-md text-black hover:bg-gray-100 hover:text-black font-medium transition-all duration-300">Dashboard</a>
                <a href="med_inventory.php"  class="py-2 px-3 rounded-md text-black hover:bg-gray-100 hover:text-black font-medium transition-all duration-300">Med Inventory</a>
                <a href="pos.php" class="py-2 px-3 rounded-md text-black hover:bg-gray-100 hover:text-black font-medium transition-all duration-300">POS</a>
                <a href="reports.php" class="py-2 px-3 rounded-md text-black hover:bg-gray-100 hover:text-black font-medium transition-all duration-300">Reports</a>
                <a href="account.php" class="py-2 px-3 rounded-md bg-blue-100 text-blue-800 hover:text-black font-medium transition-all duration-300">Account</a>
            </div>

            <!-- Logout Button -->
            <a href="b-crud/logout.php" class="bg-red-500 hover:bg-red-600 text-white font-medium py-2 px-4 rounded-lg transition duration-150 shadow-md text-sm inline-block">
                Logout
            </a>
        </nav>

        <!-- 3. MAIN CONTENT AREA (Cream Background) -->
        <main class="bg-custom-bg-white p-6 flex-grow h-full relative z-10">
            <div class="main-content flex-1 p-6 lg:p-10 overflow-y-auto bg-main-bg-color">
                <h2 id="page-title" class="text-4xl font-extrabold text-gray-900 mb-4">
                    Account 
                </h2>
                <p class="text-gray-600 text-lg mb-8">
                    Welcome, <?php echo $user_full_name; ?>. Ready to manage your account and view today's essential insights for <?php echo $branch_name; ?>.
                </p>

                <!-- Flash messages -->
                <?php if (!empty($flash_success) || !empty($flash_error)): ?>
                    <div class="mb-6">
                        <?php foreach ($flash_success as $msg): ?>
                            <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-2 text-sm text-green-800"><?php echo htmlspecialchars($msg); ?></div>
                        <?php endforeach; ?>
                        <?php foreach ($flash_error as $msg): ?>
                            <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-2 text-sm text-red-800"><?php echo htmlspecialchars($msg); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Dashboard Content based on requirements -->
                <div class="space-y-8">

                    <!-- TOP SECTION: FULL-WIDTH USER PROFILE -->
                    <div class="bg-white p-6 md:p-8 rounded-xl shadow-lg">
                        <h3 class="text-3xl font-extrabold text-indigo-700 mb-6 border-b pb-2">User Profile</h3>
                        <div class="flex items-center space-x-6">
                            <!-- Avatar/Placeholder -->
                            <div class="relative w-24 h-24 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-600 text-4xl font-bold border-4 border-indigo-200 flex-shrink-0">
                                <?php echo strtoupper(substr($_SESSION['first_name'] ?? 'S', 0, 1) . substr($_SESSION['last_name'] ?? 'U', 0, 1)); ?>
                                <span class="absolute bottom-0 right-0 w-5 h-5 bg-green-500 rounded-full border-2 border-white" title="Active"></span>
                            </div>

                            <div class="min-w-0">
                                <!-- User Full Name -->
                                <h4 class="text-3xl font-extrabold text-gray-900 truncate"><?php echo $user_full_name; ?></h4>

                                <!-- Role & Position -->
                                <div class="flex items-center mt-1 space-x-3 text-lg">
                                    <span class="px-3 py-1 bg-indigo-100 text-indigo-700 font-bold rounded-full text-sm shadow-sm">
                                        <?php echo $user_role; ?>
                                    </span>
                                    <span class="text-gray-600 font-medium">| <?php echo $user_role; ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Key Employment Details -->
                        <div class="mt-6 border-t pt-4 grid grid-cols-1 sm:grid-cols-3 gap-y-4 gap-x-6 text-sm">
                            <div>
                                <p class="text-gray-500">Branch Name & Code</p>
                                <p class="font-semibold text-gray-800 truncate"><?php echo $branch_name; ?> (B00<?php echo $current_branch_id; ?>)</p>
                            </div>
                            <div>
                                <p class="text-gray-500">User Code</p>
                                <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($_SESSION['user_code'] ?? 'N/A'); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-500">Last Login</p>
                                <p class="font-semibold text-gray-800"><?php echo $last_login; ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- MIDDLE SECTION: 2 COLUMNS (Time/Date & Calendar | Personal Notes & Todo) -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

                        <!-- LEFT: TIME/DATE & CALENDAR -->
                        <div class="space-y-4">
                            <!-- Time/Date -->
                            <div class="bg-white p-4 rounded-xl shadow-lg text-right text-lg font-medium">
                                <p id="current-date" class="text-gray-600 text-sm"></p>
                                <p class="text-indigo-600 font-extrabold text-4xl mt-1" id="current-time"></p>
                            </div>

                            <!-- Calendar Widget -->
                            <div class="bg-white p-6 rounded-xl shadow-lg">
                                <h4 class="text-lg font-bold text-gray-800 mb-4">Quick Calendar View</h4>
                                <div class="flex justify-between items-center mb-4">
                                    <button id="prev-month" class="text-gray-600 hover:text-indigo-600 transition p-1 rounded-full hover:bg-gray-100"><i data-lucide="chevron-left" class="w-5 h-5"></i></button>
                                    <h5 id="calendar-month-year" class="font-semibold text-gray-800 text-lg"></h5>
                                    <button id="next-month" class="text-gray-600 hover:text-indigo-600 transition p-1 rounded-full hover:bg-gray-100"><i data-lucide="chevron-right" class="w-5 h-5"></i></button>
                                </div>
                                <div class="grid grid-cols-7 text-center text-sm font-medium text-gray-500 mb-2">
                                    <div>S</div><div>M</div><div>T</div><div>W</div><div>T</div><div>F</div><div>S</div>
                                </div>
                                <div id="calendar-days" class="grid grid-cols-7 gap-1">
                                    <!-- Calendar days rendered by JS -->
                                </div>
                            </div>
                        </div>

                        <!-- RIGHT: PERSONAL NOTES & TODO -->
                        <div class="bg-gray-50 rounded-xl shadow-lg p-6 flex flex-col">
                            <h4 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                                <i data-lucide="clipboard-list" class="w-6 h-6 mr-2 text-indigo-600"></i>
                                Personal Notes & To-Do
                            </h4>

                            <!-- New Note Input -->
                            <div class="mb-4 p-4 bg-white rounded-lg border">
                                <textarea id="note-input" rows="3" class="w-full border-gray-300 rounded-lg p-2 text-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="Write a quick note or to-do item..."></textarea>
                                <button id="save-note-btn" class="mt-2 w-full px-4 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition">
                                    <i data-lucide="plus" class="w-4 h-4 inline-block mr-2"></i> Add Note
                                </button>
                            </div>

                            <!-- Existing Notes List (Firestore driven) -->
                            <div id="notes-list" class="space-y-3 flex-grow overflow-y-auto p-1 border-t pt-4 max-h-48">
                                <!-- Notes will be dynamically loaded here via Firestore listener -->
                                <p class="text-gray-500 text-sm italic p-4">Loading notes...</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- BOTTOM SECTION: ACCOUNT FEATURES TABS -->
                    <div class="bg-white rounded-xl shadow-lg">
                        
                        <!-- Tab Navigation -->
                        <div class="border-b border-gray-200 px-4 sm:px-6">
                            <nav class="flex space-x-4">
                                <button id="general-tab" onclick="setActiveTab('general')" class="tab-button py-4 px-1 text-sm transition duration-150">General Information</button>
                                <button id="security-tab" onclick="setActiveTab('security')" class="tab-button py-4 px-1 text-sm transition duration-150">Security & Settings</button>
                                <button id="details-tab" onclick="setActiveTab('details')" class="tab-button py-4 px-1 text-sm transition duration-150">Personal Details</button>
                                <button id="activity-tab" onclick="setActiveTab('activity')" class="tab-button py-4 px-1 text-sm transition duration-150">Activity Log</button>
                            </nav>
                        </div>

                        <!-- Tab Content -->
                        <div class="p-6 md:p-8 space-y-8 overflow-y-auto max-h-[500px]">

                            <!-- 1. GENERAL INFORMATION CONTENT -->
                            <div id="general-content" class="tab-content">
                                <h4 class="text-xl font-bold text-gray-800 mb-4">Core Account Information</h4>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    
                                    <!-- Account Details Card -->
                                    <div class="bg-gray-50 p-5 rounded-lg border">
                                        <h5 class="font-semibold text-lg mb-3 text-indigo-700">Contact & Login</h5>
                                        <dl class="space-y-3 text-sm">
                                            <div class="flex justify-between border-b pb-1">
                                                <dt class="text-gray-500">Email:</dt>
                                                <dd class="font-medium text-gray-800 break-all"><?php echo $user_email; ?></dd>
                                            </div>
                                            <div class="flex justify-between border-b pb-1">
                                                <dt class="text-gray-500">Personal Phone:</dt>
                                                <dd class="font-medium text-gray-800"><?php echo $personal_phone; ?></dd>
                                            </div>
                                            <div class="flex justify-between border-b pb-1">
                                                <dt class="text-gray-500">Date Created:</dt>
                                                <dd class="font-medium text-gray-800"><?php echo $date_created; ?></dd>
                                            </div>
                                            <div class="flex justify-between">
                                                <dt class="text-gray-500">Hire Date:</dt>
                                                <dd class="font-medium text-gray-800"><?php echo $hire_date; ?></dd>
                                            </div>
                                        </dl>
                                    </div>

                                    <!-- HR Details Card -->
                                    <div class="bg-gray-50 p-5 rounded-lg border">
                                        <h5 class="font-semibold text-lg mb-3 text-indigo-700">HR Snapshot</h5>
                                        <dl class="space-y-3 text-sm">
                                            <div class="flex justify-between border-b pb-1">
                                                <dt class="text-gray-500">Date of Birth:</dt>
                                                <dd class="font-medium text-gray-800"><?php echo $date_of_birth; ?></dd>
                                            </div>
                                            <div class="flex justify-between border-b pb-1">
                                                <dt class="text-gray-500">Gender:</dt>
                                                <dd class="font-medium text-gray-800"><?php echo $gender; ?></dd>
                                            </div>
                                            <div class="flex justify-between border-b pb-1">
                                                <dt class="text-gray-500">Salary (Confidential):</dt>
                                                <dd class="font-medium text-green-700"><?php echo $salary; ?></dd>
                                            </div>
                                            <div class="flex justify-between">
                                                <dt class="text-gray-500">National ID:</dt>
                                                <dd class="font-medium text-gray-800"><?php echo $national_id; ?></dd>
                                            </div>
                                        </dl>
                                    </div>

                                </div>
                            </div>

                            <!-- 2. SECURITY & SETTINGS CONTENT -->
                            <div id="security-content" class="tab-content hidden">
                                <h4 class="text-xl font-bold text-gray-800 mb-6">Account Security Management</h4>
                                
                                <div class="space-y-6">
                                    
                                    <!-- Change Password -->
                                    <div class="bg-indigo-50 p-6 rounded-lg border border-indigo-200">
                                        <h5 class="text-lg font-semibold mb-2">Change Password</h5>
                                        <p class="text-sm text-gray-600 mb-4">Update your account password frequently to ensure security.</p>
                                        <form method="post" class="space-y-3">
                                            <input type="hidden" name="action" value="change_password">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Current Password</label>
                                                <input type="password" name="current_password" class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-2">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">New Password</label>
                                                <input type="password" name="new_password" class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-2">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                                                <input type="password" name="confirm_password" class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-2">
                                            </div>
                                            <div class="text-right">
                                                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition">
                                                    <i data-lucide="lock" class="w-4 h-4 inline-block mr-2"></i> Update Password
                                                </button>
                                            </div>
                                        </form>
                                    </div>

                                    <!-- Two-Factor Authentication -->
                                    <div class="bg-white p-6 rounded-lg border">
                                        <h5 class="text-lg font-semibold mb-2">Two-Factor Authentication (2FA)</h5>
                                        <div class="flex justify-between items-center">
                                            <p class="text-gray-600">Status: <span class="font-semibold text-green-600">Enabled</span></p>
                                            <button class="text-red-600 hover:text-red-700 text-sm font-medium">Disable</button>
                                        </div>
                                    </div>
                                    
                                    <!-- Session Management -->
                                    <div class="bg-white p-6 rounded-lg border">
                                        <h5 class="text-lg font-semibold mb-4">Active Sessions</h5>
                                        <ul class="space-y-3 text-sm text-gray-700">
                                            <li class="flex justify-between items-center border-b pb-2">
                                                <span>Current Device (Chrome on Windows)</span>
                                                <span class="text-xs text-indigo-600 font-semibold">This Session</span>
                                            </li>
                                            <li class="flex justify-between items-center">
                                                <span>Safari on iOS (Last Active: 2 hours ago)</span>
                                                <button class="text-red-500 hover:text-red-600 text-xs font-medium">Log Out</button>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <!-- 3. PERSONAL DETAILS CONTENT -->
                            <div id="details-content" class="tab-content hidden">
                                <h4 class="text-xl font-bold text-gray-800 mb-6">Update & View Personal Details</h4>
                                
                                <div class="space-y-8">
                                    <!-- Editable Details Form -->
                                    <div class="bg-gray-50 p-6 rounded-lg border">
                                        <h5 class="font-semibold text-lg mb-4 text-indigo-700">Personal Contact Information</h5>
                                        <form method="post" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <input type="hidden" name="action" value="update_details">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Personal Phone Number</label>
                                                <input type="text" name="personal_phone" value="<?php echo htmlspecialchars($personal_phone ?? ''); ?>" class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-2">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Gender</label>
                                                <select name="gender" class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-2">
                                                    <option value="Male" <?php echo ($gender === 'Male') ? 'selected' : ''; ?>>Male</option>
                                                    <option value="Female" <?php echo ($gender === 'Female') ? 'selected' : ''; ?>>Female</option>
                                                    <option value="Other" <?php echo ($gender === 'Other') ? 'selected' : ''; ?>>Other</option>
                                                </select>
                                            </div>
                                            <div class="md:col-span-2">
                                                <label class="block text-sm font-medium text-gray-700">Residential Address</label>
                                                <input type="text" name="personal_address" value="<?php echo htmlspecialchars($personal_address ?? ''); ?>" class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-2">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Emergency Contact Name</label>
                                                <input type="text" name="emergency_name" value="<?php echo htmlspecialchars($emergency_name ?? ''); ?>" class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-2">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Emergency Contact Phone</label>
                                                <input type="text" name="emergency_phone" value="<?php echo htmlspecialchars($emergency_phone ?? ''); ?>" class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-2">
                                            </div>
                                            <div class="md:col-span-2 text-right">
                                                <button type="submit" class="px-5 py-2 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 transition">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>

                                    <!-- Emergency Contact -->
                                    <div class="p-6 rounded-lg border bg-white">
                                        <h5 class="text-lg font-semibold mb-4 text-red-600">Emergency Contact</h5>
                                        <dl class="space-y-3 text-sm">
                                            <div class="flex justify-between border-b pb-1">
                                                <dt class="text-gray-500">Name:</dt>
                                                <dd class="font-medium text-gray-800"><?php echo $emergency_name; ?></dd>
                                            </div>
                                            <div class="flex justify-between">
                                                <dt class="text-gray-500">Phone:</dt>
                                                <dd class="font-medium text-gray-800"><?php echo $emergency_phone; ?></dd>
                                            </div>
                                        </dl>
                                    </div>
                                </div>
                            </div>

                            <!-- 4. ACTIVITY LOG CONTENT -->
                            <div id="activity-content" class="tab-content hidden">
                                <h4 class="text-xl font-bold text-gray-800 mb-6">Recent Activity Timeline</h4>

                                <ol class="relative border-l border-gray-200">                  
                                    <!-- Activity Item 1 -->
                                    <li class="mb-8 ml-6">            
                                        <span class="absolute flex items-center justify-center w-3 h-3 bg-blue-100 rounded-full -left-1.5 ring-8 ring-white">
                                            <i data-lucide="receipt" class="w-3 h-3 text-blue-600"></i>
                                        </span>
                                        <h5 class="flex items-center mb-1 text-lg font-semibold text-gray-900">
                                            Processed Sale (ID: 521)
                                        </h5>
                                        <time class="block mb-2 text-sm font-normal leading-none text-gray-400">
                                            November 4, 2024 at 11:45 AM
                                        </time>
                                        <p class="text-base font-normal text-gray-500">
                                            Transaction Type: Cash | Total Amount: ₱ 1,450.00
                                        </p>
                                    </li>
                                    <!-- Activity Item 2 -->
                                    <li class="mb-8 ml-6">
                                        <span class="absolute flex items-center justify-center w-3 h-3 bg-green-100 rounded-full -left-1.5 ring-8 ring-white">
                                            <i data-lucide="check-check" class="w-3 h-3 text-green-600"></i>
                                        </span>
                                        <h5 class="mb-1 text-lg font-semibold text-gray-900">
                                            Profile Update
                                        </h5>
                                        <time class="block mb-2 text-sm font-normal leading-none text-gray-400">
                                            October 28, 2024
                                        </time>
                                        <p class="text-base font-normal text-gray-500">
                                            Updated Emergency Contact Phone Number.
                                        </p>
                                    </li>
                                     <!-- Activity Item 3 -->
                                    <li class="mb-8 ml-6">
                                        <span class="absolute flex items-center justify-center w-3 h-3 bg-purple-100 rounded-full -left-1.5 ring-8 ring-white">
                                            <i data-lucide="log-in" class="w-3 h-3 text-purple-600"></i>
                                        </span>
                                        <h5 class="mb-1 text-lg font-semibold text-gray-900">
                                            Successful Login
                                        </h5>
                                        <time class="block mb-2 text-sm font-normal leading-none text-gray-400">
                                            October 20, 2024 at 8:05 AM
                                        </time>
                                        <p class="text-base font-normal text-gray-500">
                                            Login from Lipa Branch workstation.
                                        </p>
                                    </li>
                                </ol>
                            </div>

                        </div>

                    </div>

                </div>

                <!-- JavaScript for page functionality -->
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        // --- TIME AND DATE LOGIC ---
                        function updateTime() {
                            const now = new Date();
                            const timeOptions = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true };
                            const dateOptions = { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' };

                            const timeStr = now.toLocaleTimeString('en-US', timeOptions);
                            const dateStr = now.toLocaleDateString('en-US', dateOptions);

                            const timeElement = document.getElementById('current-time');
                            if (timeElement) timeElement.textContent = timeStr;
                            
                            const dateElement = document.getElementById('current-date');
                            if (dateElement) dateElement.textContent = dateStr;
                        }
                        updateTime();
                        setInterval(updateTime, 1000);

                        // --- CALENDAR WIDGET LOGIC ---
                        let currentCalendarDate = new Date();

                        function renderCalendar() {
                            const container = document.getElementById('calendar-days');
                            const monthYearDisplay = document.getElementById('calendar-month-year');
                            
                            if (!container || !monthYearDisplay) return;

                            container.innerHTML = '';
                            
                            const month = currentCalendarDate.getMonth();
                            const year = currentCalendarDate.getFullYear();

                            monthYearDisplay.textContent = new Date(year, month).toLocaleString('en-US', { month: 'long', year: 'numeric' });

                            const firstDayOfMonth = new Date(year, month, 1).getDay();
                            const daysInMonth = new Date(year, month + 1, 0).getDate();
                            const today = new Date();
                            const todayDate = today.getDate();
                            const todayMonth = today.getMonth();
                            const todayYear = today.getFullYear();

                            for (let i = 0; i < firstDayOfMonth; i++) {
                                container.appendChild(document.createElement('div'));
                            }

                            for (let day = 1; day <= daysInMonth; day++) {
                                const dayEl = document.createElement('div');
                                dayEl.textContent = day;
                                dayEl.className = 'p-1 text-sm text-center rounded-lg transition duration-150 cursor-pointer';

                                if (day === todayDate && month === todayMonth && year === todayYear) {
                                    dayEl.classList.add('bg-indigo-600', 'text-white', 'font-bold', 'shadow-md');
                                } else {
                                    dayEl.classList.add('hover:bg-indigo-100', 'text-gray-700');
                                }
                                
                                if (month === 4 && day === 15 && year === 2022) { // May 15, 2022
                                    dayEl.classList.remove('bg-indigo-600', 'text-white', 'font-bold');
                                    dayEl.classList.add('bg-green-200', 'text-green-800', 'font-semibold', 'border-2', 'border-green-500');
                                    dayEl.title = 'Hire Date';
                                }
                                container.appendChild(dayEl);
                            }
                        }

                        function changeMonth(delta) {
                            currentCalendarDate.setMonth(currentCalendarDate.getMonth() + delta);
                            renderCalendar();
                        }

                        document.getElementById('prev-month')?.addEventListener('click', () => changeMonth(-1));
                        document.getElementById('next-month')?.addEventListener('click', () => changeMonth(1));
                        renderCalendar();

                        // --- TAB LOGIC ---
                        window.setActiveTab = function(tabId) {
                            document.querySelectorAll('.tab-content').forEach(content => content.classList.add('hidden'));
                            document.querySelectorAll('.tab-button').forEach(button => {
                                button.classList.remove('bg-white', 'text-indigo-600', 'font-semibold', 'border-b-2', 'border-indigo-600');
                                button.classList.add('text-gray-500', 'hover:text-indigo-600', 'hover:bg-gray-50');
                            });

                            document.getElementById(tabId + '-content')?.classList.remove('hidden');
                            const activeTabButton = document.getElementById(tabId + '-tab');
                            if (activeTabButton) {
                                activeTabButton.classList.remove('text-gray-500', 'hover:text-indigo-600', 'hover:bg-gray-50');
                                activeTabButton.classList.add('bg-white', 'text-indigo-600', 'font-semibold', 'border-b-2', 'border-indigo-600');
                            }
                        }
                        setActiveTab('general'); // Set initial tab

                        // --- FIRESTORE NOTES LOGIC (MOCK) ---
                        // This is a placeholder. A real implementation would use Firebase SDK.
                        const notesList = document.getElementById('notes-list');
                        const saveNoteBtn = document.getElementById('save-note-btn');
                        const noteInput = document.getElementById('note-input');

                        let mockNotes = [
                            { id: 'note1', content: 'Follow up on inventory report.' },
                            { id: 'note2', content: 'Prepare for the weekly staff meeting.' }
                        ];

                        function renderNotes() {
                            if (!notesList) return;
                            notesList.innerHTML = '';
                            if (mockNotes.length === 0) {
                                notesList.innerHTML = '<p class="text-gray-500 text-sm italic p-4">No notes yet. Add one above!</p>';
                                return;
                            }
                            mockNotes.forEach(note => {
                                const noteEl = document.createElement('div');
                                noteEl.className = 'p-3 bg-white rounded-lg border border-gray-100 flex justify-between items-start transition duration-100 hover:shadow-md';
                                noteEl.innerHTML = `
                                    <p class="text-sm text-gray-800 break-words flex-grow mr-4">${note.content}</p>
                                    <button onclick="deleteNote('${note.id}')" class="flex-shrink-0 text-red-400 hover:text-red-600 transition" title="Delete Note">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                `;
                                notesList.appendChild(noteEl);
                            });
                            lucide.createIcons();
                        }

                        window.deleteNote = function(noteId) {
                            mockNotes = mockNotes.filter(n => n.id !== noteId);
                            renderNotes();
                        }

                        saveNoteBtn?.addEventListener('click', function() {
                            const content = noteInput.value.trim();
                            if (content) {
                                mockNotes.push({ id: 'note' + Date.now(), content: content });
                                noteInput.value = '';
                                renderNotes();
                            }
                        });

                        renderNotes(); // Initial render

                        // Initialize Lucide icons for the whole page
                        lucide.createIcons();
                    });
                </script>
            </div>
        </main>

    </div>

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    
    <!-- Combined JavaScript Logic (from script.js and inline functions) -->
    <script src="js/chat.js?v=<?php echo time(); ?>"></script>
        <script src="js/notifications_bell.js" defer></script>
        <script src="js/script.js"></script>
</body>
</html>
