# Pharmaceutical System - OTP-Based Password Reset Implementation

## Overview
A complete OTP (One-Time Password) based password reset system has been integrated into the Pharmaceutical Cross-Branch System. The system uses SMS API for secure password recovery.

---

## Database Changes

### New OTP Verification Table
```sql
CREATE TABLE OTPVerification (
    OTPID INT PRIMARY KEY AUTO_INCREMENT,
    UserID INT NOT NULL,
    OTPCode VARCHAR(6) NOT NULL,
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    ExpiresAt DATETIME NOT NULL,
    IsUsed TINYINT(1) DEFAULT 0,
    AttemptCount INT DEFAULT 0,
    FOREIGN KEY (UserID) REFERENCES Accounts(UserID) ON DELETE CASCADE
)ENGINE=InnoDB;
```

**Features:**
- Stores OTP codes linked to UserID
- Automatic expiration tracking (10 minutes)
- Tracks OTP usage status
- Attempt counting for security

---

## File Structure

### 1. **forgot.php** - Initiate Password Reset
**Purpose:** Request password reset
**Features:**
- Input: User Code & Email
- Verification against Accounts table
- Retrieves phone number from Details table
- Generates 6-digit OTP
- Sends OTP via SMS API
- Stores OTP in database (10-minute expiry)
- Modern gradient UI with Tailwind CSS

**Flow:**
```
User enters code & email 
  → Verify against database 
  → Generate OTP 
  → Send via SMS 
  → Store in DB 
  → Redirect to verify_otp.php
```

### 2. **verify_otp.php** - Verify OTP
**Purpose:** Verify the OTP received via SMS
**Features:**
- Input: 6-digit OTP
- Validates OTP against database
- Checks expiration
- Prevents reuse (IsUsed flag)
- Resend OTP functionality
- Real-time error messages
- Responsive design with success/error states

**Flow:**
```
User enters OTP 
  → Check database 
  → Verify not expired 
  → Mark as used 
  → Set OTP verified flag 
  → Redirect to reset_password.php
```

### 3. **reset_password.php** - Set New Password
**Purpose:** Create new password after OTP verification
**Features:**
- Strong password validation:
  - Minimum 8 characters
  - Must include uppercase (A-Z)
  - Must include lowercase (a-z)
  - Must include numbers (0-9)
  - Must include special characters (@$!%*?&)
- Password strength meter
- Password visibility toggle
- Hashed password storage (BCRYPT)
- Session destruction after reset
- Redirect to login with success message

**Flow:**
```
User enters new password 
  → Validate strength 
  → Hash password 
  → Update Accounts table 
  → Destroy session 
  → Redirect to login with success message
```

### 4. **login.php** - Login Page (Updated)
**Changes:**
- Added success message display for password reset
- Existing "Forgot password?" link now points to forgot.php
- Displays: "✅ Password reset successfully! Please login with your new password."

---

## API Integration

### SMS Gateway
- **Provider:** iprogtech.com
- **API Token:** `b762be2b208425771747ea780ac4de0ad101f2e9`
- **Endpoint:** `https://sms.iprogtech.com/api/v1/otp/send_otp`
- **Message Format:** "Your Pharmaceutical System OTP is: XXXXXX. Valid for 10 minutes."

---

## User Journey

### Complete Password Reset Flow
```
1. Login Page
   ↓
2. User clicks "Forgot password?"
   ↓
3. forgot.php - Enter User Code & Email
   ↓
4. System validates & sends OTP via SMS
   ↓
5. verify_otp.php - Enter 6-digit OTP
   ↓
6. System verifies OTP & marks session
   ↓
7. reset_password.php - Enter strong new password
   ↓
8. System updates password & clears session
   ↓
9. Redirect to login.php with success message
   ↓
10. User logs in with new password
```

---

## Security Features

### Database Level
- ✅ OTP linked to specific UserID
- ✅ Automatic expiration (10 minutes)
- ✅ One-time use only (IsUsed flag)
- ✅ Cascading delete on user removal
- ✅ Passwords hashed with BCRYPT

### Session Level
- ✅ Session validation at each step
- ✅ Mandatory OTP verification
- ✅ Session destruction after password update
- ✅ Redirect loops prevented

### Password Security
- ✅ Minimum 8 characters
- ✅ Mixed case requirement
- ✅ Number requirement
- ✅ Special character requirement
- ✅ Password strength meter
- ✅ BCRYPT hashing (not MD5/SHA1)

### SMS Security
- ✅ Secure API endpoint (HTTPS)
- ✅ API token authentication
- ✅ OTP expires in 10 minutes
- ✅ No OTP reuse possible

---

## UI/UX Enhancements

### Design System
- **Color Scheme:** Purple gradient (Pharmaceutical theme)
- **Framework:** Tailwind CSS
- **Font:** Poppins (modern, clean)
- **Responsive:** Mobile-friendly design

### Forgot Password Page (forgot.php)
- Lock icon in header
- Purple gradient background
- Error message display with red styling
- Input fields with focus states
- "Back to Login" link
- Footer with copyright

### OTP Verification Page (verify_otp.php)
- Checkmark icon in header
- Large input field with letter-spacing
- Success/Error message alerts
- Resend OTP functionality
- "Go Back" option
- Real-time validation feedback

### Password Reset Page (reset_password.php)
- Password strength meter
- Requirements checklist
- Eye icon for password visibility toggle
- Confirm password field
- Visual strength feedback
- Security guidelines display

---

## Configuration & Maintenance

### SMS API Token
Location: `forgot.php`, `verify_otp.php`
```php
$api_token = "b762be2b208425771747ea780ac4de0ad101f2e9";
```

### OTP Expiration
Default: 10 minutes
Location: Line in forgot.php & verify_otp.php
```php
$expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
```

### Database Connection
Uses existing `dbconnection.php` with PDO Database class

---

## Testing Checklist

- [ ] User can request password reset with valid code & email
- [ ] OTP is sent via SMS successfully
- [ ] User can verify OTP within 10 minutes
- [ ] Expired OTP is rejected (test after 10+ minutes)
- [ ] New OTP cannot be reused (IsUsed flag works)
- [ ] Password strength validation works
- [ ] Weak passwords are rejected
- [ ] Password reset updates database
- [ ] Session is cleared after reset
- [ ] Success message displays on login page
- [ ] User can login with new password
- [ ] Forgot password link works from login page
- [ ] Mobile responsiveness works on all pages

---

## Troubleshooting

### OTP Not Received
1. Check phone number in Details table
2. Verify API token is correct
3. Check internet connectivity
4. Test SMS API endpoint directly

### Session Issues
1. Verify cookie settings
2. Check session directory permissions
3. Ensure session_start() called at top of files

### Password Not Updating
1. Verify UserID is correct in session
2. Check database write permissions
3. Verify OTPVerification table exists
4. Check hashedPassword column exists in Accounts

---

## Security Recommendations

1. **Rate Limiting:** Add request throttling to prevent OTP brute force
2. **Logging:** Log all password reset attempts
3. **Email Notification:** Send email confirmation when password is reset
4. **2FA:** Consider adding 2-factor authentication
5. **API Key Rotation:** Rotate SMS API token periodically
6. **HTTPS Only:** Ensure all pages use HTTPS in production

---

## Version Information
- **Created:** November 15, 2025
- **System:** Pharmaceutical Cross-Branch v2
- **Database:** MySQL/MariaDB with PDO
- **UI Framework:** Tailwind CSS
- **Password Hashing:** BCRYPT (PASSWORD_BCRYPT)

---

## Support & Maintenance
For issues or enhancements, contact system administrator.
