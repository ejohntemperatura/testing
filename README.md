# ELMS - File Organization Guide

This project is organized by user roles and functionality for easy navigation.

## 📁 Directory Structure

```
ELMS/
├── admin/                  # Admin-only files
│   ├── admin_dashboard.php
│   ├── admin_functions.php
│   ├── manage_user.php
│   ├── manager_user.php
│   ├── leave_management.php
│   └── view_chart.php
│
├── user/                   # User/Employee files
│   ├── dashboard.php
│   ├── profile.php
│   ├── leave_history.php
│   ├── submit_leave.php
│   ├── leave_credits.php
│   ├── dtr.php
│   └── dtr_status.php
│
├── auth/                   # Authentication files
│   ├── index.php          # Login page
│   ├── login_process.php
│   ├── register.php
│   └── logout.php
│
├── reports/                # Report files (empty for now)
│
├── includes/               # Helper and utility files
│   ├── navigation.php
│   ├── get_request_details.php
│   └── manage_leave.php
│
├── assets/                 # CSS, JS, and other assets
│   ├── css/
│   │   ├── style.css
│   │   └── admin_style.css
│   └── js/                 # JavaScript files
│
├── config/                 # Configuration files
│   └── database.php
│
├── index.php               # Main entry point
├── elms_db.sql            # Database schema
├── composer.json          # Dependencies
└── README.md              # This file
```

## 🚀 How to Use

### For Admins:
- Access admin features through files in the `admin/` folder
- Main entry: `admin/admin_dashboard.php`

### For Users/Employees:
- Access user features through files in the `user/` folder
- Main entry: `user/dashboard.php`

### For Authentication:
- Login/Register through files in the `auth/` folder
- Main entry: `auth/index.php`

## 🔗 Navigation

The `includes/navigation.php` file provides consistent navigation across all pages. Include it in your pages like this:

```php
<?php
require_once '../includes/navigation.php';
// For admin pages
renderNavigation('admin');
// For user pages
renderNavigation('user');
?>
```

## 📝 File Locations

### Admin Files:
- **Dashboard**: `admin/admin_dashboard.php`
- **User Management**: `admin/manage_user.php`
- **Leave Management**: `admin/leave_management.php`
- **Calendar View**: `admin/view_chart.php` (shared with users)
- **Reports**: `admin/reports.php`

### User Files:
- **Dashboard**: `user/dashboard.php`
- **Profile**: `user/profile.php`
- **Leave History**: `user/leave_history.php`
- **Submit Leave**: `user/submit_leave.php`
- **DTR**: `user/dtr.php`
- **Calendar View**: `user/view_chart.php` (shared with admins)

### Authentication:
- **Login**: `auth/index.php`
- **Register**: `auth/register.php`
- **Logout**: `auth/logout.php`

## 💡 Benefits of This Organization

1. **Easy to Find**: Admin files are in admin folder, user files in user folder
2. **Clear Separation**: Authentication is separate from main functionality
3. **Simple Navigation**: Each role has its own set of files
4. **Unified Calendar**: Both users and admins share the same calendar view with role-based data access

## 📅 Unified Calendar View

The system now features a unified calendar view accessible from both user and admin areas:

- **Location**: `admin/view_chart.php` and `user/view_chart.php`
- **Features**: Full-screen calendar showing leave requests with color-coded status
- **Role-based Access**: 
  - Admins see all leave requests across the organization
  - Users see their own requests plus approved leaves from colleagues
- **Navigation**: Automatically adapts based on user role
- **Responsive**: Works on both desktop and mobile devices
4. **Easy Maintenance**: Related files are grouped together
5. **No Complex Structure**: Simple folder organization without MVC complexity

## 🔧 Quick Start

1. **Admin Access**: Go to `admin/admin_dashboard.php`
2. **User Access**: Go to `user/dashboard.php`
3. **Login**: Go to `auth/index.php`
4. **Main Entry**: Use `index.php` (auto-redirects based on role)

## 📱 URL Examples

- Admin Dashboard: `admin/admin_dashboard.php`
- User Dashboard: `user/dashboard.php`
- Login Page: `auth/index.php`
- Manage Users: `admin/manage_user.php`
- Submit Leave: `user/submit_leave.php`

This organization makes it super easy to find what you need without getting lost in complex folder structures!
