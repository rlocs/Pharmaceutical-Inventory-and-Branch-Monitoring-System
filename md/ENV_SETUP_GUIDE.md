# 🔐 Environment Configuration Setup Guide

## Overview

The Pharmaceutical System now uses `.env` file for environment-specific configuration instead of hardcoding sensitive values. This is a security best practice.

---

## 📁 Files Involved

### Configuration Files
- **`.env`** - Your actual configuration (DO NOT commit to version control)
- **`.env.example`** - Template for `.env` file (safe to commit)
- **`.gitignore`** - Prevents `.env` from being committed
- **`config.php`** - Configuration loader class

### Updated Application Files
- **`forgot.php`** - Uses `Config::get()` for SMS API settings
- **`verify_otp.php`** - Uses `Config::get()` for SMS API settings
- **`reset_password.php`** - Uses `Config::get()` for password validation rules

---

## 🚀 Quick Setup (5 Steps)

### Step 1: Copy the Template
```bash
cp .env.example .env
```

### Step 2: Update .env with Your Values
Edit `.env` file and update:
```env
SMS_API_TOKEN=your_actual_token_here
SMS_API_ENDPOINT=https://sms.iprogtech.com/api/v1/otp/send_otp
APP_NAME=Pharmaceutical System
```

### Step 3: Verify Configuration Loading
The `config.php` is automatically loaded by:
- `forgot.php`
- `verify_otp.php`
- `reset_password.php`

### Step 4: Test the System
```
1. Navigate to login.php
2. Click "Forgot password?"
3. Enter User Code and Email
4. Should receive OTP via SMS
```

### Step 5: Add to Version Control
```bash
git add .env.example
git add .gitignore
git add config.php
git add forgot.php
git add verify_otp.php
git add reset_password.php

# DO NOT commit .env
```

---

## 📋 Configuration Options

### SMS API Configuration
```env
# SMS provider token for iprogtech.com
SMS_API_TOKEN=your_token_here

# API endpoint for sending OTP
SMS_API_ENDPOINT=https://sms.iprogtech.com/api/v1/otp/send_otp

# cURL timeout in seconds
SMS_API_TIMEOUT=30
```

### OTP Configuration
```env
# Length of generated OTP code
OTP_LENGTH=6

# Minutes until OTP expires
OTP_EXPIRY_MINUTES=10

# Maximum failed attempts before lockout
OTP_MAX_ATTEMPTS=5
```

### Password Configuration
```env
# Minimum password length
PASSWORD_MIN_LENGTH=8

# Require uppercase letters (A-Z)
PASSWORD_REQUIRE_UPPERCASE=true

# Require lowercase letters (a-z)
PASSWORD_REQUIRE_LOWERCASE=true

# Require numbers (0-9)
PASSWORD_REQUIRE_NUMBERS=true

# Require special characters (@$!%*?&)
PASSWORD_REQUIRE_SPECIAL=true
```

### System Configuration
```env
# Application name (used in SMS messages)
APP_NAME=Pharmaceutical System

# Environment (production, staging, development)
APP_ENV=production

# Debug mode (true/false)
DEBUG=false
```

---

## 🔧 How It Works

### Configuration Loader (`config.php`)

The `Config` class provides a simple interface:

```php
// Load configuration (automatic)
Config::load();

// Get a value with default
$token = Config::get('SMS_API_TOKEN', 'default_value');

// Check if key exists
if (Config::has('SMS_API_TOKEN')) {
    // Use it
}

// Get all configuration
$all_config = Config::all();
```

### Usage in Application Files

**Before (.env):**
```php
$api_token = "b762be2b208425771747ea780ac4de0ad101f2e9";
$message = "Your Pharmaceutical System OTP is: $otp. Valid for 10 minutes.";
```

**After (with Config):**
```php
require_once 'config.php';

$api_token = Config::get('SMS_API_TOKEN');
$app_name = Config::get('APP_NAME', 'Pharmaceutical System');
$otp_expiry = Config::get('OTP_EXPIRY_MINUTES', 10);
$message = "Your $app_name OTP is: $otp. Valid for $otp_expiry minutes.";
```

---

## 🛡️ Security Best Practices

### ✅ Do:
- Keep `.env` file locally, never commit it
- Add `.env` to `.gitignore` (already done)
- Use `.env.example` as template
- Rotate API tokens regularly
- Use strong, unique tokens
- Enable HTTPS only
- Review `.env` permissions (should be readable only by app)

### ❌ Don't:
- Commit `.env` file to repository
- Share `.env` file via email/chat
- Hardcode sensitive values in code
- Use weak or default tokens
- Log sensitive configuration
- Store backups insecurely

---

## 🔄 Configuration Changes

### Adding New Configuration

1. **Add to `.env.example`:**
```env
NEW_SETTING=example_value
```

2. **Add to `.env`:**
```env
NEW_SETTING=actual_value
```

3. **Use in application:**
```php
$value = Config::get('NEW_SETTING', 'default_value');
```

### Modifying Existing Configuration

1. Edit `.env` file:
```env
SMS_API_TOKEN=new_token_value
OTP_EXPIRY_MINUTES=15
```

2. Application picks up changes on next request
3. No restart needed for PHP applications

---

## 🐛 Troubleshooting

### Issue: `.env file not found`
**Solution:**
```bash
cp .env.example .env
# Edit .env with your values
```

### Issue: Configuration values not loading
**Solution:**
```php
// Check if config is loaded
if (Config::has('SMS_API_TOKEN')) {
    echo "Config loaded successfully";
} else {
    echo "Config not loaded";
    Config::load(); // Force load
}
```

### Issue: SMS not sending with new token
**Solution:**
1. Verify token in `.env` is correct
2. Check `SMS_API_ENDPOINT` is correct
3. Verify internet connection
4. Check SMS API provider status
5. Review error logs

### Issue: Password validation not working
**Solution:**
```php
// Debug configuration
echo Config::get('PASSWORD_MIN_LENGTH');
echo Config::get('PASSWORD_REQUIRE_UPPERCASE');

// Check .env file format
// Should be: KEY=VALUE (no spaces around =)
```

---

## 📊 Example .env File

```env
# SMS API Configuration
SMS_API_TOKEN=b762be2b208425771747ea780ac4de0ad101f2e9
SMS_API_ENDPOINT=https://sms.iprogtech.com/api/v1/otp/send_otp
SMS_API_TIMEOUT=30

# OTP Configuration
OTP_LENGTH=6
OTP_EXPIRY_MINUTES=10
OTP_MAX_ATTEMPTS=5

# Password Configuration
PASSWORD_MIN_LENGTH=8
PASSWORD_REQUIRE_UPPERCASE=true
PASSWORD_REQUIRE_LOWERCASE=true
PASSWORD_REQUIRE_NUMBERS=true
PASSWORD_REQUIRE_SPECIAL=true

# System Configuration
APP_NAME=Pharmaceutical System
APP_ENV=production
DEBUG=false
```

---

## ✅ Deployment Checklist

- [ ] `.env.example` created and committed
- [ ] `.gitignore` includes `.env`
- [ ] `config.php` deployed to production
- [ ] `.env` file created in production (from `.env.example`)
- [ ] Production values set in `.env`
- [ ] `.env` file NOT committed to git
- [ ] `.env` file permissions set correctly (644)
- [ ] Tested password reset flow
- [ ] Tested OTP sending
- [ ] Verified SMS API token works
- [ ] No hardcoded secrets remain in code

---

## 🔐 Token Security

### Current Token
```
b762be2b208425771747ea780ac4de0ad101f2e9
```

### Rotation Schedule
- Rotate every 6 months
- Immediately if exposed
- When staff changes

### Token Storage
- Keep in `.env` only
- Never log it
- Never expose in error messages
- Use HTTPS for all API calls

---

## 📞 Support

For configuration issues:
1. Check `.env` file exists
2. Verify file format (KEY=VALUE)
3. Review `config.php` documentation
4. Check error logs
5. Test configuration with simple script:

```php
<?php
require_once 'config.php';
echo "SMS Token: " . Config::get('SMS_API_TOKEN');
echo "OTP Expiry: " . Config::get('OTP_EXPIRY_MINUTES');
?>
```

---

## 📚 Related Files

- `forgot.php` - OTP request page
- `verify_otp.php` - OTP verification page
- `reset_password.php` - Password reset page
- `config.php` - Configuration loader
- `.env` - Environment configuration
- `.env.example` - Configuration template
- `.gitignore` - Git ignore rules

---

**Last Updated: November 15, 2025**
**Status: Production Ready** ✅
