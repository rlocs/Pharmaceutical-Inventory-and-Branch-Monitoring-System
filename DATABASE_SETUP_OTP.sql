-- ============================================================================
-- PHARMACEUTICAL SYSTEM - OTP PASSWORD RESET DATABASE SETUP
-- ============================================================================
-- Run these queries to set up the OTP verification system
-- Date: November 15, 2025
-- ============================================================================

-- 1. CREATE OTP VERIFICATION TABLE
-- ============================================================================
-- This table stores all OTP codes for password reset operations
-- Each OTP is linked to a specific user and has a 10-minute expiration

CREATE TABLE IF NOT EXISTS OTPVerification (
    OTPID INT PRIMARY KEY AUTO_INCREMENT,
    UserID INT NOT NULL,
    OTPCode VARCHAR(6) NOT NULL,
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    ExpiresAt DATETIME NOT NULL,
    IsUsed TINYINT(1) DEFAULT 0,
    AttemptCount INT DEFAULT 0,
    FOREIGN KEY (UserID) REFERENCES Accounts(UserID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. CREATE INDEXES FOR PERFORMANCE
-- ============================================================================
-- These indexes speed up common queries

-- Index for user-based OTP lookups
CREATE INDEX IF NOT EXISTS idx_user_otp ON OTPVerification(UserID, IsUsed);

-- Index for expiration checks
CREATE INDEX IF NOT EXISTS idx_otp_expiry ON OTPVerification(ExpiresAt);

-- Index for OTP code lookups
CREATE INDEX IF NOT EXISTS idx_otp_code ON OTPVerification(OTPCode, UserID);

-- ============================================================================
-- 3. VERIFY REQUIRED COLUMNS IN EXISTING TABLES
-- ============================================================================
-- These columns must exist in your existing tables for the system to work

-- Accounts table requirements:
-- - UserID (INT PRIMARY KEY AUTO_INCREMENT)
-- - UserCode (VARCHAR UNIQUE)
-- - Email (VARCHAR UNIQUE)
-- - HashedPassword (VARCHAR 255)
-- - AccountStatus (ENUM 'Active', 'Inactive', 'Suspended')
-- - FirstName (VARCHAR 50)
-- - LastName (VARCHAR 50)
-- - BranchID (INT FOREIGN KEY)

-- Details table requirements:
-- - UserID (INT PRIMARY KEY FOREIGN KEY to Accounts)
-- - PersonalPhoneNumber (VARCHAR 20)

-- ============================================================================
-- 4. VERIFY YOUR DATABASE HAS THESE COLUMNS
-- ============================================================================
-- Run this to check if columns exist:

-- Check Accounts table
SELECT COLUMN_NAME 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME='Accounts' 
AND TABLE_SCHEMA=DATABASE()
AND COLUMN_NAME IN ('UserID', 'UserCode', 'Email', 'HashedPassword', 'AccountStatus', 'FirstName', 'LastName', 'BranchID');

-- Check Details table
SELECT COLUMN_NAME 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME='Details' 
AND TABLE_SCHEMA=DATABASE()
AND COLUMN_NAME IN ('UserID', 'PersonalPhoneNumber');

-- ============================================================================
-- 5. TEST DATA (Optional - for development/testing only)
-- ============================================================================
-- Insert sample OTP for testing
-- NOTE: Replace 1 with an actual UserID from your Accounts table

-- DEVELOPMENT ONLY - DO NOT USE IN PRODUCTION
-- INSERT INTO OTPVerification (UserID, OTPCode, ExpiresAt, IsUsed) 
-- VALUES (1, '123456', DATE_ADD(NOW(), INTERVAL 10 MINUTE), 0);

-- ============================================================================
-- 6. MAINTENANCE QUERIES
-- ============================================================================

-- Clean up expired OTPs (run regularly via cron job)
-- DELETE FROM OTPVerification WHERE ExpiresAt < NOW();

-- Get all unused OTPs for a user
-- SELECT * FROM OTPVerification WHERE UserID = ? AND IsUsed = 0 AND ExpiresAt > NOW();

-- Mark OTP as used
-- UPDATE OTPVerification SET IsUsed = 1 WHERE OTPID = ?;

-- ============================================================================
-- 7. BACKUP RECOMMENDATION
-- ============================================================================
-- Back up your database before running these queries:
-- mysqldump -u username -p database_name > backup_before_otp.sql

-- ============================================================================
-- 8. VERIFICATION QUERIES
-- ============================================================================

-- Verify OTP table was created:
SHOW TABLES LIKE 'OTPVerification';

-- Check table structure:
DESCRIBE OTPVerification;

-- Check indexes:
SHOW INDEX FROM OTPVerification;

-- ============================================================================
-- 9. ADDITIONAL SETUP (Optional - for enhanced security)
-- ============================================================================

-- Create an audit table for password reset attempts (optional)
/*
CREATE TABLE IF NOT EXISTS PasswordResetAudit (
    AuditID INT PRIMARY KEY AUTO_INCREMENT,
    UserID INT NOT NULL,
    AttemptType ENUM('request', 'verify', 'reset') NOT NULL,
    Status ENUM('success', 'failed') NOT NULL,
    IPAddress VARCHAR(45),
    UserAgent TEXT,
    ErrorMessage VARCHAR(255),
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (UserID) REFERENCES Accounts(UserID) ON DELETE CASCADE,
    INDEX idx_user_attempts (UserID, CreatedAt),
    INDEX idx_status (Status, CreatedAt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
*/

-- ============================================================================
-- 10. COMPLETION CHECKLIST
-- ============================================================================

/*
After running these queries, verify:

☐ OTPVerification table created successfully
☐ All indexes created
☐ DESCRIBE OTPVerification shows correct columns
☐ Accounts table has all required columns
☐ Details table has PersonalPhoneNumber column
☐ Foreign keys are set up correctly
☐ Database backup completed
☐ Test OTP insertion works
☐ Test OTP lookup works
☐ All three new PHP files deployed (forgot.php, verify_otp.php, reset_password.php)
☐ login.php updated
☐ SMS API token configured
☐ Database connection tested
☐ Site accessible and no PHP errors
☐ Users can request password reset
☐ OTP SMS received on test phone
☐ OTP verification works
☐ Password reset updates database
☐ User can login with new password

Once all items are checked, the system is ready for production use.
*/

-- ============================================================================
-- END OF DATABASE SETUP SCRIPT
-- ============================================================================
