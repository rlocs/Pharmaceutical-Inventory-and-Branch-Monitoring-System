# Quick Setup Guide - OTP Password Reset System

## Installation Steps

### 1. Database Setup
Run the following SQL in your MySQL/MariaDB database:

```sql
-- Create OTP Verification table
CREATE TABLE OTPVerification (
    OTPID INT PRIMARY KEY AUTO_INCREMENT,
    UserID INT NOT NULL,
    OTPCode VARCHAR(6) NOT NULL,
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    ExpiresAt DATETIME NOT NULL,
    IsUsed TINYINT(1) DEFAULT 0,
    AttemptCount INT DEFAULT 0,
    FOREIGN KEY (UserID) REFERENCES Accounts(UserID) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Create index for faster queries
CREATE INDEX idx_user_otp ON OTPVerification(UserID, IsUsed);
CREATE INDEX idx_otp_expiry ON OTPVerification(ExpiresAt);
```

### 2. Verify Database Table
Ensure all required columns in existing tables:
- **Accounts table:** UserID, UserCode, Email, HashedPassword, AccountStatus
- **Details table:** UserID, PersonalPhoneNumber

### 3. File Deployment
All files are ready to use. No additional configuration needed:
- ✅ `forgot.php` - Password reset request
- ✅ `verify_otp.php` - OTP verification
- ✅ `reset_password.php` - New password creation
- ✅ `login.php` - Updated with reset success message
- ✅ `pharmaceutical_db.sql` - Updated schema

### 4. Verify API Settings
Confirm SMS API settings in files:

**Location:** forgot.php (line ~58)
**Location:** verify_otp.php (line ~98)

```php
$api_token = "b762be2b208425771747ea780ac4de0ad101f2e9";
```

✅ Token is already configured correctly

### 5. Test the System
1. Go to login page
2. Click "Forgot password?"
3. Enter your User Code and Email
4. Check your phone for OTP
5. Enter OTP on verification page
6. Set new strong password (8+ chars, uppercase, lowercase, number, special)
7. Login with new password

---

## System Flow Diagram

```
LOGIN PAGE
    ↓
"Forgot password?" link
    ↓
FORGOT.PHP
    • Enter User Code & Email
    • Validate against Accounts table
    • Get phone from Details table
    • Generate 6-digit OTP
    • Send SMS via API
    • Store in OTPVerification table
    ↓
VERIFY_OTP.PHP
    • Enter 6-digit OTP
    • Check database for match
    • Verify not expired
    • Mark IsUsed = 1
    • Set session flag
    ↓
RESET_PASSWORD.PHP
    • Enter new password
    • Validate strength requirements
    • Hash with BCRYPT
    • Update Accounts table
    • Destroy session
    ↓
LOGIN.PHP (with success message)
    • Display: "✅ Password reset successfully!"
    • User logs in with new password
```

---

## Security Checklist

Before going to production:

- [ ] SMS API token is secure (never commit to version control)
- [ ] All pages use HTTPS in production
- [ ] Database backup created
- [ ] OTP expiration set to reasonable time (default: 10 minutes)
- [ ] Rate limiting implemented on forgot.php
- [ ] Error messages don't reveal system details
- [ ] All input is properly escaped
- [ ] Session timeout configured
- [ ] Logging setup for password resets
- [ ] CORS headers configured if needed

---

## API Configuration

### SMS Gateway Details
- **Service:** iprogtech.com
- **API Version:** v1
- **Endpoint:** https://sms.iprogtech.com/api/v1/otp/send_otp
- **Method:** POST
- **Content-Type:** application/json
- **Auth:** API Token in payload

### Message Format
```
"Your Pharmaceutical System OTP is: XXXXXX. Valid for 10 minutes."
```

---

## Troubleshooting

### Issue: OTP not sending
**Solution:**
1. Check internet connectivity
2. Verify API token (copy from email confirmation)
3. Ensure user has phone number in Details table
4. Test API endpoint manually with cURL

### Issue: "User Code or Email not found"
**Solution:**
1. Verify User Code is correct (case-sensitive)
2. Check email matches Accounts table
3. Ensure AccountStatus = 'Active'

### Issue: OTP expired
**Solution:**
1. Resend OTP (button available on verify page)
2. OTP valid for 10 minutes from generation
3. Each new OTP request invalidates previous ones

### Issue: Password reset fails
**Solution:**
1. Check password meets all requirements
2. Ensure passwords match (confirm field)
3. Verify database write permissions
4. Check OTPVerification table exists

---

## Support Information

**System Version:** Pharmaceutical Cross-Branch v2
**Last Updated:** November 15, 2025
**Support Email:** admin@pharmasystem.local

For additional support, refer to:
- OTP_PASSWORD_RESET_DOCUMENTATION.md (full documentation)
- Database schema in pharmaceutical_db.sql
- API documentation at iprogtech.com

---

## Quick Reference

| Page | Purpose | Input | Output |
|------|---------|-------|--------|
| forgot.php | Initiate reset | Code, Email | OTP sent to phone |
| verify_otp.php | Verify OTP | 6-digit code | Session marked as verified |
| reset_password.php | Set password | New password | Password updated |
| login.php | Login | Code, Password | Access granted or success message |

---

## API Response Examples

### Success Response
```json
{
  "status": "success",
  "message": "OTP sent successfully",
  "request_id": "12345"
}
```

### Error Response
```json
{
  "status": "error",
  "message": "Invalid phone number",
  "error_code": "INVALID_PHONE"
}
```

---

Happy coding! 🎉
