# 🚀 QUICK SETUP GUIDE - Get Email Verification Working in 5 Minutes

## ⚠️ IMPORTANT: You MUST follow these steps to receive emails!

### Step 1: Get Gmail App Password

1. **Go to your Google Account**: https://myaccount.google.com/
2. **Click "Security"** in the left menu
3. **Enable "2-Step Verification"** if not already enabled
4. **Go to "App passwords"** (under 2-Step Verification)
5. **Select "Mail"** and click "Generate"
6. **Copy the 16-character password** (like: `abcd efgh ijkl mnop`)

### Step 2: Update Email Configuration

**Edit this file**: `config/email_config.php`

Replace these lines:
```php
'smtp_username' => 'your-email@gmail.com', // Replace with your Gmail
'smtp_password' => 'your-app-password', // Replace with your Gmail App Password
'from_email' => 'your-email@gmail.com', // Replace with your Gmail
```

With your actual Gmail:
```php
'smtp_username' => 'yourname@gmail.com', // Your real Gmail address
'smtp_password' => 'abcd efgh ijkl mnop', // Your 16-character App Password
'from_email' => 'yourname@gmail.com', // Your real Gmail address
```

### Step 3: Test Email Configuration

Run this command in your terminal:
```bash
php test_email.php
```

You should see: `✅ Email configuration is working correctly!`

### Step 4: Import Database

**Option A: Fresh Installation (New Database)**
```bash
mysql -u root -p < elms_db.sql
```

**Option B: Keep Existing Data**
```bash
mysql -u root -p elms_db < migrate_existing_db.sql
```

### Step 5: Test the System

1. **Login as admin** (admin@example.com / password123)
2. **Go to "Manage Users"**
3. **Click "Add New User"**
4. **Enter a real email address** (like your own email)
5. **Click "Add User"**
6. **Check the email inbox** - you should receive a verification email!

## 🔧 Troubleshooting

### If emails are not sending:

1. **Check Gmail App Password**: Make sure it's exactly 16 characters
2. **Check Gmail address**: Make sure it's your real Gmail
3. **Check 2-Step Verification**: Must be enabled
4. **Check server**: Make sure your server allows SMTP (port 587)

### Common Errors:

- **"Authentication failed"**: Wrong App Password
- **"Connection refused"**: Server firewall blocking SMTP
- **"Invalid email"**: Check Gmail address format

## 📧 What You'll Receive:

1. **Verification Email**: When admin adds a new user
2. **Welcome Email**: When user verifies their email (with temporary password)

## 🎯 Quick Test:

1. Update `config/email_config.php` with your Gmail
2. Run `php test_email.php`
3. Add a new user through admin panel
4. Check your email inbox

**That's it! You should now receive emails when adding new users.** 🎉
