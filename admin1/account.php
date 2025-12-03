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

// 2. Check Role: Only Admin can view this page.
if ($_SESSION["user_role"] !== 'Admin') {
    die("ERROR: You do not have permission to view this page.");
}

// 3. Check Branch (Crucial for Staff access). This file is for Branch 1.
// Admins are not restricted by BranchID, but Staff MUST be from Branch 1.
$required_branch_id = 1;

if ($required_branch_id > 0 && $_SESSION["user_role"] === 'Admin' && $_SESSION["branch_id"] != $required_branch_id) {
    // Redirect staff who ended up on the wrong branch page
    // Optional: Log this security violation attempt
    header("Location: ../login.php?error=branch_mismatch"); 
    exit;
}

// ------------------------------------------------------------------
// DYNAMIC DATA PREPARATION
// ------------------------------------------------------------------

// Set dynamic variables from session data
$user_full_name = htmlspecialchars($_SESSION['first_name'] ?? 'Admin') . ' ' . htmlspecialchars($_SESSION['last_name'] ?? 'User');
$user_role = htmlspecialchars($_SESSION['user_role'] ?? 'Admin');
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


$flash_success = [];
$flash_error = [];

// Fetch staff accounts data for the staff management tab
$staffAccounts = [];
$branchMap = [
    1 => 'Lipa',
    2 => 'Malvar', 
    3 => 'Sto Tomas'
];
$roleMap = [
    'Admin' => 'Administrator',
    'Staff' => 'Staff',
];

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

    // Fetch staff accounts for staff management tab (only for Admins)
    if ($_SESSION["user_role"] === 'Admin') {
        $sql = "SELECT a.UserID, a.UserCode, a.FirstName, a.LastName, a.Email, a.Role, a.AccountStatus, a.BranchID, d.PersonalPhoneNumber, d.Position, d.HireDate
                FROM Accounts a
                LEFT JOIN Details d ON a.UserID = d.UserID
                WHERE a.Role != 'Admin'
                ORDER BY a.UserID";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $staffAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

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
                <img src="https://placehold.co/40x40/fff/1E3A8A?text=Admin" alt="Mercury Logo" class="rounded-full border-2 border-white">
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

                <a href="admin1b<?php echo $current_branch_id; ?>.php"class="py-2 px-3 rounded-md text-black hover:bg-gray-100 hover:text-black font-medium transition-all duration-300">Dashboard</a>
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
            
            <!-- Staff Management Section - Only show for Admin users -->
            <?php if ($_SESSION["user_role"] === 'Admin'): ?>
            <div id="staff-management-root">
                <div class="min-h-screen bg-gray-50 p-4 sm:p-6 lg:p-8">
                    <header class="mb-8">
                        <div class="flex items-center justify-between flex-wrap gap-4">
                            <h1 class="text-3xl font-extrabold text-gray-900 flex items-center">
                                <!-- Users Icon Placeholder -->
                                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-7 h-7 mr-3 text-indigo-600">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                                Staff Accounts Management
                            </h1>
                            <button onclick="openAddStaffModal()" class="flex items-center px-4 py-2 bg-indigo-600 text-white font-semibold rounded-xl shadow-lg hover:bg-indigo-700 transition-colors duration-200 transform hover:scale-[1.02] active:scale-100">
                                <!-- UserPlus Icon Placeholder -->
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-2">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <line x1="19" x2="19" y1="8" y2="14"></line>
                                    <line x1="16" x2="22" y1="11" y2="11"></line>
                                </svg>
                                Add New Staff
                            </button>
                        </div>
                        <p class="text-gray-500 mt-2">Manage user roles, permissions, and staff access to the system.</p>
                    </header>

                    <!-- Staff List Table -->
                    <div class="overflow-x-auto bg-white rounded-2xl shadow-xl p-4">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr class="bg-indigo-50">
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-indigo-600 uppercase tracking-wider rounded-tl-xl">Staff ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-indigo-600 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-indigo-600 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-indigo-600 uppercase tracking-wider">Role</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-indigo-600 uppercase tracking-wider">Branch</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-indigo-600 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-indigo-600 uppercase tracking-wider">Phone</th>
                                    <th class="px-6 py-3 text-right text-xs font-semibold text-indigo-600 uppercase tracking-wider rounded-tr-xl">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <?php if (empty($staffAccounts)): ?>
                                <tr>
                                    <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500">
                                        No staff accounts found.
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php $counter = 1; ?>
                                    <?php foreach ($staffAccounts as $staff): ?>
                                    <tr class="hover:bg-gray-50 transition-colors duration-150" data-user-id="<?php echo htmlspecialchars($staff['UserID']); ?>" data-phone="<?php echo htmlspecialchars($staff['PersonalPhoneNumber'] ?? ''); ?>">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($staff['UserCode']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($staff['FirstName'] . ' ' . $staff['LastName']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($staff['Email']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-indigo-600">
                                            <span class="flex items-center">
                                                <!-- Key Icon Placeholder -->
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 mr-1 text-indigo-400">
                                                    <path d="M10 2l3 3h3l2 2v3l-3 3-3-3l-2-2-3 3-3-3zM7.5 8.5c-1.4 1.4-2.4 2.4-3.5 3.5m10 5.5-3 3-2-2 3-3"></path>
                                                </svg>
                                                <?php echo htmlspecialchars($roleMap[$staff['Role']] ?? $staff['Role']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-purple-600">
                                            <span class="flex items-center bg-purple-50 px-2 py-1 rounded-full text-xs font-semibold">
                                                <!-- MapPin Icon Placeholder -->
                                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3 mr-1">
                                                    <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path>
                                                    <circle cx="12" cy="10" r="3"></circle>
                                                </svg>
                                                <?php echo htmlspecialchars($branchMap[$staff['BranchID']] ?? 'Unknown'); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $staff['AccountStatus'] === 'Active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                <?php echo htmlspecialchars($staff['AccountStatus']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                            <?php echo htmlspecialchars($staff['PersonalPhoneNumber'] ?? 'N/A'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                            <button onclick="editStaff(this)" class="text-blue-600 hover:text-blue-900 p-1 rounded-lg hover:bg-blue-50 transition-colors" title="Edit User">
                                                <!-- Edit Icon Placeholder -->
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5">
                                                    <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"></path>
                                                </svg>
                                            </button>
                                            <button onclick="deleteStaff(this)" class="text-red-600 hover:text-red-900 p-1 rounded-lg hover:bg-red-50 transition-colors" title="Delete User">
                                                <!-- Trash2 Icon Placeholder -->
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5">
                                                    <path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                                                    <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                                                    <line x1="10" x2="10" y1="11" y2="17"></line>
                                                    <line x1="14" x2="14" y1="11" y2="17"></line>
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Footer/Summary -->
                    <div class="mt-6 text-center text-sm text-gray-600">
                        Showing <?php echo count($staffAccounts); ?> of <?php echo count($staffAccounts); ?> staff accounts.
                    </div>
                </div>
            </div>
            <?php endif; ?>

            
        </main>
    </div>

    <!-- Staff Management Modals -->
    <!-- Add Staff Modal -->
    <div id="addStaffModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 hidden">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md relative">
            <!-- Close button (X) -->
            <button type="button" onclick="closeAddModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 transition" title="Close">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-6 h-6">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            
            <div class="p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Add New Staff Account</h3>
                <form id="addStaffForm">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">First Name <span class="text-red-500">*</span></label>
                        <input type="text" id="addFirstName" name="first_name" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Last Name <span class="text-red-500">*</span></label>
                        <input type="text" id="addLastName" name="last_name" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Email <span class="text-red-500">*</span></label>
                        <input type="email" id="addEmail" name="email" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Phone Number</label>
                        <input type="text" id="addPhone" name="phone" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" placeholder="+63 912 345 6789">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Role <span class="text-red-500">*</span></label>
                        <select id="addRole" name="role" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" required>
                            <option value="Staff">Staff</option>
                            <option value="Admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Position</label>
                        <input type="text" id="addPosition" name="position" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" placeholder="e.g., Pharmacist, Cashier">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Branch <span class="text-red-500">*</span></label>
                        <select id="addBranchId" name="branch_id" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" required>
                            <option value="1">Lipa</option>
                            <option value="2">Malvar</option>
                            <option value="3">Sto Tomas</option>
                        </select>
                    </div>
                    <div class="flex justify-end space-x-2">
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition">Add Staff</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Staff Modal -->
    <div id="editStaffModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 hidden">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md relative">
            <!-- Close button (X) -->
            <button type="button" onclick="closeEditModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 transition" title="Close">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-6 h-6">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            
            <div class="p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Edit Staff Account</h3>
                <form id="editStaffForm">
                    <input type="hidden" id="editUserId" name="user_id">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">First Name <span class="text-red-500">*</span></label>
                        <input type="text" id="editFirstName" name="first_name" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Last Name <span class="text-red-500">*</span></label>
                        <input type="text" id="editLastName" name="last_name" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Email <span class="text-red-500">*</span></label>
                        <input type="email" id="editEmail" name="email" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Phone Number</label>
                        <input type="text" id="editPhone" name="phone" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" placeholder="+63 912 345 6789">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Position</label>
                        <input type="text" id="editPosition" name="position" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" placeholder="e.g., Pharmacist, Cashier">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Branch <span class="text-red-500">*</span></label>
                        <select id="editBranchId" name="branch_id" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" required>
                            <option value="1">Lipa</option>
                            <option value="2">Malvar</option>
                            <option value="3">Sto Tomas</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Status <span class="text-red-500">*</span></label>
                        <select id="editStatus" name="status" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" required>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                            <option value="Suspended">Suspended</option>
                        </select>
                    </div>
                    <div class="flex justify-end space-x-2">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Staff Management JavaScript -->
    <script>
    // Global staff management functions
    function openAddStaffModal() {
        console.log('Opening add staff modal');
        document.getElementById('addStaffModal').classList.remove('hidden');
        document.getElementById('addStaffForm').reset();
        // Prevent body scroll when modal is open
        document.body.style.overflow = 'hidden';
    }

    function closeAddModal() {
        console.log('Closing add staff modal');
        document.getElementById('addStaffModal').classList.add('hidden');
        // Restore body scroll
        document.body.style.overflow = 'auto';
    }

    function editStaff(button) {
        console.log('Edit staff clicked');
        const row = button.closest('tr');
        const userId = row.getAttribute('data-user-id');
        const cells = row.querySelectorAll('td');
        const userCode = cells[0].textContent.trim();
        const name = cells[1].textContent.trim().split(' ');
        const firstName = name[0] || '';
        const lastName = name.slice(1).join(' ') || '';
        const email = cells[2].textContent.trim();
        const role = cells[3].textContent.trim().includes('Administrator') ? 'Admin' : 'Staff';
        const branchText = cells[4].textContent.trim();
        const status = cells[5].textContent.trim();
        const phone = row.getAttribute('data-phone') || '';

        // Map branch text to ID
        const branchMap = { 'Lipa': '1', 'Malvar': '2', 'Sto Tomas': '3' };
        const branchId = branchMap[branchText] || '1';

        document.getElementById('editUserId').value = userId;
        document.getElementById('editFirstName').value = firstName;
        document.getElementById('editLastName').value = lastName;
        document.getElementById('editEmail').value = email;
        document.getElementById('editPhone').value = phone;
        document.getElementById('editBranchId').value = branchId;
        document.getElementById('editStatus').value = status;

        document.getElementById('editStaffModal').classList.remove('hidden');
        // Prevent body scroll when modal is open
        document.body.style.overflow = 'hidden';
    }

    function closeEditModal() {
        console.log('Closing edit staff modal');
        document.getElementById('editStaffModal').classList.add('hidden');
        // Restore body scroll
        document.body.style.overflow = 'auto';
    }

    function deleteStaff(button) {
        console.log('Delete staff clicked');
        const row = button.closest('tr');
        const userId = row.getAttribute('data-user-id');
        const userName = row.querySelector('td:nth-child(2)').textContent.trim();

        if (confirm(`Are you sure you want to delete staff account: ${userName}?\nThis action cannot be undone.`)) {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('user_id', userId);

            // Show loading
            button.disabled = true;
            const originalHTML = button.innerHTML;
            button.innerHTML = '<svg class="animate-spin h-5 w-5 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';

            fetch('process_staff.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Staff account deleted successfully');
                    row.remove();
                    const remainingRows = document.querySelectorAll('tbody tr').length;
                    const countDisplay = document.querySelector('.mt-6.text-center.text-sm.text-gray-600');
                    if (countDisplay) {
                        countDisplay.textContent = `Showing ${remainingRows} of ${remainingRows} staff accounts.`;
                    }
                } else {
                    alert('Error: ' + (data.message || 'Unknown error occurred'));
                    button.disabled = false;
                    button.innerHTML = originalHTML;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the staff account');
                button.disabled = false;
                button.innerHTML = originalHTML;
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Lucide icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

        // --- STAFF MANAGEMENT FORM HANDLERS ---
        const addStaffForm = document.getElementById('addStaffForm');
        const editStaffForm = document.getElementById('editStaffForm');

        if (addStaffForm) {
            addStaffForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                console.log('Add staff form submitted');

                // Validate form
                const firstName = document.getElementById('addFirstName').value.trim();
                const lastName = document.getElementById('addLastName').value.trim();
                const email = document.getElementById('addEmail').value.trim();
                
                if (!firstName || !lastName || !email) {
                    alert('Please fill in all required fields');
                    return;
                }

                // Show loading state
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.textContent;
                submitBtn.textContent = 'Adding...';
                submitBtn.disabled = true;

                try {
                    const formData = new FormData(this);
                    formData.append('action', 'add');
                    
                    console.log('Sending form data...');
                    
                    const response = await fetch('process_staff.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    console.log('Response received:', response);
                    
                    // Check if response is OK
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    
                    // Try to parse JSON
                    const text = await response.text();
                    console.log('Raw response:', text);
                    
                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (jsonError) {
                        console.error('JSON parse error:', jsonError);
                        console.error('Response text that failed to parse:', text);
                        throw new Error('Invalid JSON response from server');
                    }
                    
                    console.log('Parsed data:', data);
                    
                    if (data.success) {
                        alert('Staff account added successfully!\nUser Code: ' + data.user_code + '\nTemporary Password: ' + data.temp_password);
                        closeAddModal();
                        // Wait a moment before reloading to ensure modal is closed
                        setTimeout(() => {
                            location.reload();
                        }, 100);
                    } else {
                        alert('Error: ' + (data.message || 'Unknown error occurred'));
                        submitBtn.textContent = originalText;
                        submitBtn.disabled = false;
                    }
                    
                } catch (error) {
                    console.error('Fetch error:', error);
                    console.error('Error details:', error.message);
                    alert('An error occurred while adding the staff account. Please check console for details.');
                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;
                }
            });
        }

        if (editStaffForm) {
            editStaffForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                console.log('Edit staff form submitted');

                // Show loading state
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.textContent;
                submitBtn.textContent = 'Updating...';
                submitBtn.disabled = true;

                try {
                    const formData = new FormData(this);
                    formData.append('action', 'update');
                    
                    const response = await fetch('process_staff.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    
                    const text = await response.text();
                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (jsonError) {
                        console.error('JSON parse error:', jsonError);
                        throw new Error('Invalid JSON response from server');
                    }
                    
                    if (data.success) {
                        alert('Staff account updated successfully');
                        closeEditModal();
                        setTimeout(() => {
                            location.reload();
                        }, 100);
                    } else {
                        alert('Error: ' + (data.message || 'Unknown error occurred'));
                        submitBtn.textContent = originalText;
                        submitBtn.disabled = false;
                    }
                    
                } catch (error) {
                    console.error('Error:', error);
                    alert('An error occurred while updating the staff account');
                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;
                }
            });
        }

        // Close modals when clicking outside
        document.addEventListener('click', function(event) {
            const addModal = document.getElementById('addStaffModal');
            const editModal = document.getElementById('editStaffModal');
            
            if (addModal && !addModal.classList.contains('hidden') && event.target === addModal) {
                closeAddModal();
            }
            
            if (editModal && !editModal.classList.contains('hidden') && event.target === editModal) {
                closeEditModal();
            }
        });

        // Close modals with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeAddModal();
                closeEditModal();
            }
        });
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