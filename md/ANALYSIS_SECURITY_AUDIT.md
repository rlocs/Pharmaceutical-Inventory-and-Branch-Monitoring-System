# Security Audit Report - Pharmaceutical Cross-Branch System

## Executive Summary
This security audit identifies vulnerabilities, assesses risks, and provides recommendations for securing the pharmaceutical management system.

**Overall Security Score: 5.5/10** ⚠️ Needs Improvement

---

## 1. Authentication & Session Management

### 🔴 CRITICAL: Weak Session Configuration
**Location:** `b-login.php:3`
```php
session_set_cookie_params(0, '/');
```
**Vulnerabilities:**
- ❌ No `secure` flag (cookies sent over HTTP)
- ❌ No `httponly` flag (accessible via JavaScript)
- ❌ No `samesite` attribute (CSRF risk)
- ❌ Session lifetime is 0 (expires on browser close)

**Risk:** High - Session hijacking, XSS attacks, CSRF attacks

**Recommendation:**
```php
session_set_cookie_params([
    'lifetime' => 3600, // 1 hour
    'path' => '/',
    'domain' => '',
    'secure' => true, // HTTPS only
    'httponly' => true, // Prevent JavaScript access
    'samesite' => 'Strict' // CSRF protection
]);
session_start();
```

### 🟡 MEDIUM: No Session Regeneration
**Location:** `b-login.php:105`
**Issue:** Session ID not regenerated after login
**Risk:** Medium - Session fixation attacks
**Recommendation:**
```php
session_regenerate_id(true); // Regenerate and delete old session
```

### 🟡 MEDIUM: No Session Timeout
**Issue:** No automatic session expiration
**Risk:** Medium - Abandoned sessions remain valid
**Recommendation:**
```php
// Check session timeout
if (isset($_SESSION['last_activity']) && 
    (time() - $_SESSION['last_activity'] > 3600)) {
    session_destroy();
    header("Location: login.php");
    exit;
}
$_SESSION['last_activity'] = time();
```

### ✅ GOOD: Password Hashing
**Location:** `b-login.php:48, 102`
- ✅ Using `password_hash()` with PASSWORD_DEFAULT
- ✅ Using `password_verify()` for comparison
- ✅ Passwords never stored in plain text

---

## 2. Authorization & Access Control

### ✅ GOOD: Role-Based Access Control
**Location:** All branch pages
- ✅ Checks for logged-in status
- ✅ Validates user role (Staff/Admin)
- ✅ Enforces branch restrictions for Staff

### 🟡 MEDIUM: Missing Authorization in APIs
**Location:** `branch1/api/alerts_api.php`
**Issue:** No authentication check before database access
**Risk:** Medium - Unauthorized API access
**Recommendation:**
```php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
```

### 🟡 MEDIUM: Branch ID Hardcoded
**Location:** `branch1/api/alerts_api.php:14`
```php
$branchID = 1; // Hardcoded
```
**Issue:** Should use session branch_id
**Risk:** Medium - Data leakage between branches
**Recommendation:** Use `$_SESSION['branch_id']`

### 🟡 MEDIUM: Admin Bypass Logic
**Location:** `branch1/account.php:16, 23`
```php
if ($_SESSION["user_role"] !== 'Staff' && $_SESSION["user_role"] !== 'Admin') {
    die("ERROR: You do not have permission to view this page.");
}
// Admins are not restricted by BranchID
```
**Issue:** Admin can access any branch data
**Risk:** Low-Medium - May be intentional, but should be documented
**Recommendation:** Document this behavior or restrict admin access

---

## 3. Input Validation & Sanitization

### ✅ GOOD: XSS Protection
**Location:** Most PHP files
- ✅ Using `htmlspecialchars()` for output
- ✅ JavaScript has `escapeHtml()` function
- ✅ Most user input is escaped

### 🟡 MEDIUM: Missing Input Validation
**Location:** `branch1/api/medicine_api.php:144`
```php
$data = json_decode(file_get_contents('php://input'), true);
// No validation
```
**Issue:** No validation of:
- Required fields
- Data types
- Value ranges
- String lengths

**Risk:** Medium - Invalid data, potential errors
**Recommendation:**
```php
function validateMedicineData($data) {
    $errors = [];
    if (empty($data['medicineName']) || strlen($data['medicineName']) > 100) {
        $errors[] = 'Invalid medicine name';
    }
    if (!is_numeric($data['stocks']) || $data['stocks'] < 0) {
        $errors[] = 'Invalid stock value';
    }
    // ... more validation
    return $errors;
}
```

### 🟡 MEDIUM: Weak Password Requirements
**Location:** `b-login.php:48`
**Issue:** No password strength validation
**Risk:** Medium - Weak passwords
**Recommendation:**
```php
function validatePasswordStrength($password) {
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return 'Password must contain uppercase letter';
    }
    if (!preg_match('/[a-z]/', $password)) {
        return 'Password must contain lowercase letter';
    }
    if (!preg_match('/[0-9]/', $password)) {
        return 'Password must contain a number';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return 'Password must contain a special character';
    }
    return true;
}
```

### 🟡 MEDIUM: SQL Injection Risk (Low)
**Location:** `branch1/api/medicine_api.php:94-96`
```php
$count_sql = "SELECT COUNT(*) as total FROM BranchInventory bi
              JOIN medicines m ON bi.MedicineID = m.MedicineID
              WHERE $where";
```
**Status:** Currently safe (uses parameters), but fragile
**Risk:** Low - But could become vulnerable if modified
**Recommendation:** Use prepared statements for all query parts

---

## 4. CSRF Protection

### 🔴 CRITICAL: No CSRF Protection
**Location:** All forms and POST endpoints
**Issue:** No CSRF tokens implemented
**Risk:** High - Cross-Site Request Forgery attacks
**Attack Scenario:** 
- Attacker tricks user into submitting form
- User's session is used to perform unauthorized actions

**Recommendation:**
```php
// Generate token
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validate token
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && 
           hash_equals($_SESSION['csrf_token'], $token);
}

// In forms:
<input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

// In handlers:
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    die('Invalid CSRF token');
}
```

---

## 5. Sensitive Data Exposure

### 🔴 CRITICAL: Hardcoded Database Credentials
**Location:** `dbconnection.php:4, 7-8`
```php
private $host = '192.168.18.83';
private $username = 'pharma_user';
private $password = '2003';
```
**Risk:** Critical - Credentials exposed in source code
**Impact:**
- Exposed in version control
- Accessible to anyone with file access
- Cannot be changed without code modification

**Recommendation:**
```php
// Use environment variables
private $host = getenv('DB_HOST') ?: 'localhost';
private $username = getenv('DB_USER') ?: 'root';
private $password = getenv('DB_PASS') ?: '';

// Or use config file outside web root
require_once '/path/to/config/db_config.php';
```

### 🟡 MEDIUM: Sensitive Data in Database
**Location:** `Details` table
**Issue:** Salary, NationalID stored in plain text
**Risk:** Medium - Data breach exposure
**Recommendation:**
- Encrypt sensitive fields
- Implement field-level access control
- Log access to sensitive data

### 🟡 MEDIUM: Error Messages
**Location:** Some error handlers
**Issue:** Some errors may expose system information
**Status:** Most errors are handled well, but review all error messages

---

## 6. API Security

### 🟡 MEDIUM: Missing Authentication
**Location:** `branch1/api/alerts_api.php`
**Issue:** No session check
**Risk:** Medium - Unauthorized access
**Recommendation:** Add authentication to all API endpoints

### 🟡 MEDIUM: CORS Configuration
**Location:** `branch1/api/alerts_api.php:3`
```php
header('Access-Control-Allow-Origin: *');
```
**Issue:** Allows requests from any origin
**Risk:** Medium - CSRF, unauthorized API access
**Recommendation:**
```php
header('Access-Control-Allow-Origin: https://yourdomain.com');
header('Access-Control-Allow-Credentials: true');
```

### 🟡 MEDIUM: No Rate Limiting
**Issue:** API endpoints have no rate limiting
**Risk:** Medium - Brute force, DoS attacks
**Recommendation:**
```php
function checkRateLimit($key, $maxRequests = 100, $window = 3600) {
    // Implement rate limiting logic
    // Use Redis or file-based storage
}
```

### 🟡 MEDIUM: No API Versioning
**Issue:** No versioning in API endpoints
**Risk:** Low - Breaking changes affect clients
**Recommendation:** Implement `/api/v1/` structure

---

## 7. File Upload Security

### ✅ N/A: No File Uploads
**Status:** No file upload functionality found
**Recommendation:** If adding file uploads, implement:
- File type validation
- File size limits
- Virus scanning
- Secure storage outside web root
- Rename uploaded files

---

## 8. Logging & Monitoring

### 🟡 MEDIUM: Insufficient Logging
**Issue:** Limited security event logging
**Missing:**
- Failed login attempts
- Access violations
- Suspicious activities
- API abuse

**Recommendation:**
```php
function logSecurityEvent($event, $details) {
    $log = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event' => $event,
        'user_id' => $_SESSION['user_id'] ?? 'anonymous',
        'ip' => $_SERVER['REMOTE_ADDR'],
        'details' => $details
    ];
    error_log(json_encode($log));
}

// Usage:
logSecurityEvent('failed_login', ['usercode' => $userCode]);
logSecurityEvent('access_violation', ['page' => $_SERVER['REQUEST_URI']]);
```

---

## 9. HTTPS & Transport Security

### ⚠️ ASSUMED: HTTPS Not Enforced
**Issue:** No HTTPS enforcement in code
**Risk:** High - Data transmitted in plain text
**Recommendation:**
```php
// Force HTTPS
if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
    exit;
}
```

---

## 10. Dependency Security

### ⚠️ UNKNOWN: Third-Party Libraries
**Issue:** No dependency audit performed
**Risk:** Unknown - Vulnerable libraries
**Recommendation:**
- Audit all dependencies
- Keep libraries updated
- Use Composer for PHP dependencies
- Regularly check for security advisories

---

## 11. Security Headers

### 🟡 MEDIUM: Missing Security Headers
**Issue:** No security headers configured
**Recommendation:**
```php
// Add to common header file
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header('Content-Security-Policy: default-src \'self\'');
```

---

## 12. Recommendations Priority

### 🔴 CRITICAL (Fix Immediately)
1. ✅ Move database credentials to environment variables
2. ✅ Fix session security configuration
3. ✅ Implement CSRF protection
4. ✅ Add authentication to all API endpoints
5. ✅ Enforce HTTPS

### 🟡 HIGH (Fix Soon)
1. ✅ Regenerate session ID after login
2. ✅ Implement session timeout
3. ✅ Add input validation to all endpoints
4. ✅ Implement password strength requirements
5. ✅ Add security headers
6. ✅ Fix hardcoded branch ID

### 🟢 MEDIUM (Fix When Possible)
1. ✅ Implement rate limiting
2. ✅ Add comprehensive security logging
3. ✅ Encrypt sensitive database fields
4. ✅ Review CORS configuration
5. ✅ Audit dependencies

---

## 13. Security Checklist

### Authentication
- [x] Password hashing (bcrypt)
- [ ] Session security (secure, httponly, samesite)
- [ ] Session regeneration after login
- [ ] Session timeout
- [ ] Password strength requirements
- [ ] Account lockout after failed attempts

### Authorization
- [x] Role-based access control
- [x] Branch-based access control
- [ ] API authentication
- [ ] Resource-level permissions

### Input Validation
- [x] XSS protection (most places)
- [ ] Input validation (all endpoints)
- [ ] SQL injection prevention (mostly done)
- [ ] File upload validation (N/A)

### Data Protection
- [ ] Encrypted sensitive data
- [ ] Secure credential storage
- [ ] HTTPS enforcement
- [ ] Secure error handling

### Other
- [ ] CSRF protection
- [ ] Security headers
- [ ] Rate limiting
- [ ] Security logging
- [ ] Dependency audit

---

## Summary Statistics

- **Critical Vulnerabilities:** 4
- **High Priority Issues:** 6
- **Medium Priority Issues:** 12
- **Security Score:** 5.5/10
- **Compliance:** Partial

**Overall Assessment:** The application has good foundations (password hashing, prepared statements, access control) but needs significant security hardening before production deployment.

---

*Report Generated: 2024*
*Audit Scope: Authentication, Authorization, Input Validation, Data Protection, API Security*

