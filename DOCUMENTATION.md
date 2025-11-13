# Pharmaceutical Cross-Branch System - Documentation

## Table of Contents
1. [System Overview](#system-overview)
2. [Architecture](#architecture)
3. [Installation](#installation)
4. [Configuration](#configuration)
5. [Database Schema](#database-schema)
6. [API Documentation](#api-documentation)
7. [User Guide](#user-guide)
8. [Developer Guide](#developer-guide)
9. [Troubleshooting](#troubleshooting)

---

## 1. System Overview

### 1.1 Purpose
The Pharmaceutical Cross-Branch System is a web-based application for managing pharmaceutical inventory, sales transactions, and cross-branch communication across multiple pharmacy branches.

### 1.2 Features
- **Multi-Branch Management:** Support for multiple pharmacy branches
- **Inventory Management:** Track medicine stock, expiry dates, and alerts
- **Point of Sale (POS):** Process sales transactions
- **Cross-Branch Chat:** Real-time messaging between branches
- **User Management:** Role-based access control (Admin/Staff)
- **Reports:** Sales and inventory reports (coming soon)
- **Account Management:** User profile and settings

### 1.3 Technology Stack
- **Backend:** PHP 7.4+
- **Database:** MySQL 8.0+
- **Frontend:** HTML5, CSS3, JavaScript (ES6+)
- **Styling:** Tailwind CSS
- **Icons:** Lucide Icons
- **Architecture:** MVC-like structure

---

## 2. Architecture

### 2.1 Directory Structure
```
Pharmaceutical_CrossBranchv.2/
├── admin/                    # Admin panel (future)
├── branch1/                  # Branch 1 files
│   ├── api/                  # API endpoints
│   │   ├── alerts_api.php
│   │   ├── chat_api.php
│   │   └── medicine_api.php
│   ├── b-crud/               # CRUD operations
│   │   └── logout.php
│   ├── css/                  # Stylesheets
│   ├── includes/             # Shared components
│   ├── js/                   # JavaScript files
│   ├── account.php
│   ├── med_inventory.php
│   ├── pos.php
│   ├── reports.php
│   └── staff1b1.php
├── branch2/                  # Branch 2 files (similar structure)
├── branch3/                  # Branch 3 files (similar structure)
├── dbconnection.php          # Database connection class
├── b-login.php              # Login handler
├── login.php                # Login page
├── forgot.php               # Password reset page
└── pharmaceutical_db.sql    # Database schema
```

### 2.2 Database Architecture
- **Branches:** Store branch information
- **Accounts:** User accounts with authentication
- **Details:** Extended user information
- **medicines:** Global medicine catalog
- **BranchInventory:** Branch-specific inventory
- **SalesTransactions:** Sales transaction headers
- **TransactionItems:** Sales transaction line items
- **ChatConversations:** Chat conversation metadata
- **ChatParticipants:** Chat participants
- **ChatMessages:** Chat messages

### 2.3 Authentication Flow
1. User submits credentials via `login.php`
2. `b-login.php` validates credentials
3. Session variables set upon successful login
4. User redirected based on role:
   - Admin → `admin/index.php`
   - Staff → `branch{N}/staff1b{N}.php`

---

## 3. Installation

### 3.1 Prerequisites
- PHP 7.4 or higher
- MySQL 8.0 or higher
- Web server (Apache/Nginx)
- Composer (optional, for dependencies)

### 3.2 Database Setup
1. Create database:
```sql
CREATE DATABASE pharmaceutical_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Import schema:
```bash
mysql -u root -p pharmaceutical_db < pharmaceutical_db.sql
```

3. Create database user:
```sql
CREATE USER 'pharma_user'@'%' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON pharmaceutical_db.* TO 'pharma_user'@'%';
FLUSH PRIVILEGES;
```

### 3.3 Application Setup
1. Clone or extract files to web root
2. Configure database connection in `dbconnection.php`
3. Set proper file permissions:
```bash
chmod 644 *.php
chmod 755 branch*/
```

4. Configure web server:
   - Point document root to project directory
   - Enable PHP
   - Configure URL rewriting if needed

### 3.4 Configuration
Update `dbconnection.php`:
```php
private $host = 'your_db_host';
private $db_name = 'pharmaceutical_db';
private $username = 'pharma_user';
private $password = 'your_secure_password';
```

**⚠️ SECURITY:** Move credentials to environment variables in production!

---

## 4. Configuration

### 4.1 Session Configuration
Edit `b-login.php`:
```php
session_set_cookie_params([
    'lifetime' => 3600,
    'path' => '/',
    'domain' => '',
    'secure' => true,    // HTTPS only
    'httponly' => true,  // Prevent JS access
    'samesite' => 'Strict'
]);
```

### 4.2 Branch Configuration
Branch names are defined in each branch's PHP files:
```php
$branch_names = [
    1 => 'Lipa, Batangas',
    2 => 'Sto Tomas, Batangas',
    3 => 'Malvar, Batangas'
];
```

### 4.3 Inventory Thresholds
Low stock threshold: 10 units (hardcoded in multiple places)
Expiry warning: 30 days (hardcoded in SQL queries)

---

## 5. Database Schema

### 5.1 Core Tables

#### Branches
- `BranchID` (PK)
- `BranchName`
- `BranchAddress`
- `BranchCode` (UNIQUE)

#### Accounts
- `UserID` (PK)
- `BranchID` (FK)
- `UserCode` (UNIQUE)
- `FirstName`, `LastName`
- `Email` (UNIQUE)
- `HashedPassword`
- `Role` (ENUM: Admin, Staff)
- `AccountStatus` (ENUM: Active, Inactive, Suspended)
- `DateCreated`, `LastLogin`

#### medicines
- `MedicineID` (PK)
- `MedicineName`
- `Category` (ENUM)
- `Form` (ENUM)
- `Unit`

#### BranchInventory
- `BranchInventoryID` (PK)
- `BranchID` (FK)
- `MedicineID` (FK)
- `Stocks`
- `Price`
- `ExpiryDate`
- `Status` (ENUM)

### 5.2 Relationships
- Accounts → Branches (Many-to-One)
- Details → Accounts (One-to-One)
- BranchInventory → Branches (Many-to-One)
- BranchInventory → medicines (Many-to-One)
- SalesTransactions → Branches (Many-to-One)
- SalesTransactions → Accounts (Many-to-One)
- TransactionItems → SalesTransactions (Many-to-One)
- TransactionItems → BranchInventory (Many-to-One)

---

## 6. API Documentation

### 6.1 Medicine API (`api/medicine_api.php`)

#### Get Medicines
```
GET /api/medicine_api.php?action=get_medicines&page=1&category=Analgesic
```
**Response:**
```json
{
    "success": true,
    "medicines": [...],
    "total": 50,
    "page": 1,
    "limit": 10,
    "total_pages": 5
}
```

#### Add Medicine
```
POST /api/medicine_api.php
Content-Type: application/json

{
    "medicineName": "Paracetamol 500mg",
    "category": "Analgesic",
    "form": "Pill/Tablet",
    "unit": "mg",
    "stocks": 100,
    "price": 3.50,
    "expiryDate": "2026-12-31"
}
```

#### Update Medicine
```
POST /api/medicine_api.php
Content-Type: application/json

{
    "medicineId": 1,
    "stocks": 95,
    "price": 3.75,
    "expiryDate": "2026-12-31"
}
```

#### Delete Medicine
```
POST /api/medicine_api.php
Content-Type: application/x-www-form-urlencoded

action=delete_medicine&medicine_id=1
```

#### Get Alerts
```
GET /api/medicine_api.php?action=get_alerts
```
**Response:**
```json
{
    "success": true,
    "alerts": {
        "lowStock": [...],
        "outOfStock": [...],
        "expiringSoon": [...],
        "expired": [...]
    },
    "counts": {
        "lowStock": 5,
        "outOfStock": 2,
        "expiringSoon": 3,
        "expired": 1
    }
}
```

### 6.2 Chat API (`api/chat_api.php`)

#### Get Conversations
```
GET /api/chat_api.php?action=get_conversations
```

#### Get Messages
```
GET /api/chat_api.php?action=get_messages&conversation_id=1
```

#### Send Message
```
POST /api/chat_api.php
Content-Type: application/x-www-form-urlencoded

action=send_message&conversation_id=1&message=Hello
```

#### Create Conversation
```
POST /api/chat_api.php
Content-Type: application/x-www-form-urlencoded

action=create_conversation&recipient_id=2
```

### 6.3 Alerts API (`api/alerts_api.php`)
```
GET /api/alerts_api.php
```
**Response:**
```json
{
    "lowStock": [...],
    "outOfStock": [...],
    "expiringSoon": [...],
    "expired": [...]
}
```

---

## 7. User Guide

### 7.1 Login
1. Navigate to `login.php`
2. Enter User Code and Password
3. Click "Login"
4. System redirects based on role

### 7.2 Password Reset
1. Click "Forgot password?" on login page
2. Enter User Code and Date of Birth
3. Enter new password twice
4. Click "Reset Password"
5. Login with new password

### 7.3 Dashboard
- View branch statistics
- Access quick links to all modules
- View alerts in sidebar

### 7.4 Medicine Inventory
- View all medicines with pagination
- Filter by category
- Add new medicine (Admin only)
- Edit medicine details
- Delete medicine (Admin only)
- View alerts (Low Stock, Expiring Soon, Expired)

### 7.5 Point of Sale
- **Status:** Coming soon
- Will allow processing sales transactions

### 7.6 Reports
- **Status:** Coming soon
- Will provide sales and inventory reports

### 7.7 Account Management
- View profile information
- Update personal details
- Change password (button present, functionality coming)
- View activity log

### 7.8 Cross-Branch Chat
- Access via sidebar
- View conversations
- Send messages to other branches
- Zoom chat window for better view

---

## 8. Developer Guide

### 8.1 Adding a New Branch
1. Create `branch{N}/` directory
2. Copy files from `branch1/`
3. Update branch ID in files:
   - Change `$required_branch_id = 1` to new branch ID
   - Update branch name in `$branch_names` array
4. Update file names: `staff1b1.php` → `staff1b{N}.php`
5. Add branch to database:
```sql
INSERT INTO Branches (BranchName, BranchCode) 
VALUES ('New Branch', 'B00{N}');
```

### 8.2 Adding a New API Endpoint
1. Create or edit file in `api/` directory
2. Add session check:
```php
session_start();
if (!isset($_SESSION['loggedin'])) {
    http_response_code(401);
    exit;
}
```
3. Handle action parameter:
```php
$action = $_REQUEST['action'] ?? '';
switch ($action) {
    case 'your_action':
        yourFunction($pdo);
        break;
}
```
4. Return JSON response:
```php
echo json_encode(['success' => true, 'data' => $data]);
```

### 8.3 Adding a New Page
1. Create PHP file in appropriate branch directory
2. Include access control:
```php
<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: ../login.php");
    exit;
}
// Role and branch checks...
?>
```
3. Include common components:
```php
<?php include __DIR__ . '/includes/sidebar.php'; ?>
```

### 8.4 Database Queries
Always use prepared statements:
```php
$stmt = $pdo->prepare("SELECT * FROM medicines WHERE MedicineID = ?");
$stmt->execute([$medicineId]);
$medicine = $stmt->fetch(PDO::FETCH_ASSOC);
```

### 8.5 Output Escaping
Always escape user input:
```php
echo htmlspecialchars($userInput, ENT_QUOTES, 'UTF-8');
// Or use helper function:
echo escapeHtml($userInput);
```

### 8.6 JavaScript Best Practices
- Use `escapeHtml()` function for all user-generated content
- Validate input on client and server
- Handle errors gracefully
- Use async/await for API calls

---

## 9. Troubleshooting

### 9.1 Database Connection Errors
**Error:** "Database connection failed"
**Solutions:**
- Check database credentials in `dbconnection.php`
- Verify database server is running
- Check network connectivity
- Verify database user permissions

### 9.2 Session Issues
**Error:** Session not persisting
**Solutions:**
- Check `session_set_cookie_params()` configuration
- Verify `session_start()` is called before any output
- Check PHP session configuration in `php.ini`
- Clear browser cookies

### 9.3 Access Denied Errors
**Error:** "You do not have permission to view this page"
**Solutions:**
- Verify user role in database
- Check branch ID matches user's branch
- Ensure session variables are set correctly
- Check access control logic in file

### 9.4 API Errors
**Error:** "Unauthorized" or "Invalid action"
**Solutions:**
- Verify user is logged in
- Check session is active
- Verify action parameter is correct
- Check API endpoint authentication

### 9.5 JavaScript Errors
**Error:** Functions not defined
**Solutions:**
- Check script loading order
- Verify all required scripts are included
- Check browser console for errors
- Ensure DOM is loaded before script execution

---

## 10. Security Considerations

### 10.1 Production Checklist
- [ ] Move database credentials to environment variables
- [ ] Enable HTTPS
- [ ] Configure secure session settings
- [ ] Implement CSRF protection
- [ ] Add input validation to all endpoints
- [ ] Enable security headers
- [ ] Set up error logging
- [ ] Implement rate limiting
- [ ] Regular security audits
- [ ] Keep dependencies updated

### 10.2 Common Vulnerabilities to Avoid
- SQL Injection: Always use prepared statements
- XSS: Always escape output
- CSRF: Implement token validation
- Session Hijacking: Use secure session settings
- Password Storage: Never store plain text passwords

---

## 11. Support & Maintenance

### 11.1 Log Files
- PHP errors: Check web server error logs
- Application errors: Check `error_log()` output
- Database errors: Check MySQL error logs

### 11.2 Backup Procedures
1. Database backup:
```bash
mysqldump -u root -p pharmaceutical_db > backup_$(date +%Y%m%d).sql
```

2. File backup:
```bash
tar -czf backup_files_$(date +%Y%m%d).tar.gz /path/to/project
```

### 11.3 Updates
- Test updates in development environment first
- Backup database before updates
- Review changelog
- Test all functionality after update

---

## 12. Future Enhancements

### Planned Features
- Complete POS implementation
- Reports module
- Email notifications
- Mobile responsive improvements
- Advanced search functionality
- Bulk operations
- Export functionality (CSV, PDF)
- Real-time notifications
- Audit trail
- Multi-language support

---

*Last Updated: 2024*
*Version: 2.0*
*Maintained by: Development Team*

