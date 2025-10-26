# 🎨 Employee Dark Theme - Ready to Apply!

## ✅ Components Created

I've created the reusable dark theme components for employee pages:

- **`includes/user_header.php`** - Dark navbar + sidebar (same design as admin)
- **`includes/user_footer.php`** - Closing tags

---

## 📋 Employee Pages List

### Location: `app/modules/user/views/`

1. ✅ `dashboard.php` - Employee dashboard
2. ✅ `submit_leave.php` - Submit leave request  
3. ✅ `leave_history.php` - View leave history
4. ✅ `leave_credits.php` - View leave credits
5. ✅ `late_leave_application.php` - Late leave submission
6. ✅ `dtr.php` - Daily Time Record
7. ✅ `dtr_status.php` - DTR status
8. ✅ `view_chart.php` - Leave calendar
9. ✅ `profile.php` - Employee profile

---

## 🔧 How to Apply (Quick Steps)

### For Each Employee Page:

**Step 1: Add Page Title** (before the closing `?>` of PHP section)
```php
// Set page title
$page_title = "Dashboard"; // Change based on page
```

**Step 2: Replace Header Section**

Find and REPLACE this:
```php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    ...
</head>
<body>
    <?php include '../../../../includes/unified_navbar.php'; ?>
    ... sidebar code ...
```

With this:
```php
// Include user header
include '../../../../includes/user_header.php';
?>
```

**Step 3: Remove Old Sidebar**

Delete any `<aside>` sidebar HTML code that's manually written in the page.

**Step 4: Update Page Header** (if exists)

Replace page title with:
```php
<h1 class="elms-h1" style="margin-bottom: 0.5rem; display: flex; align-items: center;">
    <i class="fas fa-home" style="color: #0891b2; margin-right: 0.75rem;"></i>Dashboard
</h1>
<p class="elms-text-muted" style="margin-bottom: 2rem;">Welcome back! Here's what's happening today.</p>
```

**Step 5: Add Footer** (at the very end before `</html>`)

Replace this:
```php
</body>
</html>
```

With this:
```php
<?php include '../../../../includes/user_footer.php'; ?>
```

---

## 🎨 Design Features

The employee pages will now have:

- ✅ **Dark theme** matching admin pages
- ✅ **Fixed navbar** at top with user dropdown
- ✅ **Fixed sidebar** with navigation links
- ✅ **Green accent color** for employee (vs cyan for admin)
- ✅ **Active page highlighting** in sidebar
- ✅ **Sleek cyan scrollbar**
- ✅ **Consistent typography** and spacing
- ✅ **Responsive design**

---

## 🚀 Quick Start - Dashboard Example

Here's how to update `dashboard.php`:

### Before Section (Keep this part):
```php
<?php
session_start();
// ... all the PHP logic stays the same ...
$page_title = "Dashboard"; // ADD THIS LINE

// Include user header
include '../../../../includes/user_header.php';
?>
```

### Content Section (Update styling):
- Keep all your modals, forms, and content
- Update headers to use `elms-h1` class
- Ensure content uses dark theme colors

### End Section:
```php
<?php include '../../../../includes/user_footer.php'; ?>
```

---

## 📊 Sidebar Navigation

The new sidebar includes:

### MENU
- 🏠 Dashboard

### LEAVE MANAGEMENT  
- ✈️ Apply for Leave
- 📋 Leave History
- 💰 Leave Credits
- ⏰ Late Application

### REPORTS
- 📅 DTR
- 📊 Leave Chart

### ACCOUNT
- 👤 Profile

---

## 🎯 Color Scheme

**Employee theme uses:**
- Primary: `#10b981` (Green) - for employee branding
- Background: `#0f172a` (Dark slate)
- Cards: `#1e293b` (Slate-800)
- Text: `#f8fafc` (White/Slate-50)
- Muted: `#64748b` (Slate-500)

**Admin theme uses:**
- Primary: `#06b6d4` (Cyan) - for admin branding

---

## ✨ Benefits

1. **Consistency** - Same look & feel as admin pages
2. **Modern** - Dark theme with smooth animations
3. **Professional** - Clean, organized interface
4. **Maintainable** - Single header/footer to update
5. **Responsive** - Works on all screen sizes

---

## 🔄 Need Help?

If you want me to apply the theme to specific pages, just let me know which ones!

Example: "Apply dark theme to dashboard.php and submit_leave.php"

---

**Status:** ✅ Components ready - waiting for your go-ahead to apply!
