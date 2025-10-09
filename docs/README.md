# 🏢 ELMS - Employee Leave Management System

## 📋 **Professional File Organization**

This system is organized in a **clean, professional structure** that's easy to navigate and present.

---

## 📁 **Directory Structure**

```
ELMS/
├── 📁 core/                       # 🎯 MAIN SYSTEM FILES
│   ├── dashboard.php              # Main dashboard (role-based)
│   ├── calendar.php               # Calendar view (role-based)
│   ├── leave_management.php       # Leave management (role-based)
│   ├── user_management.php        # User management (admin/director)
│   └── reports.php                # Reports (role-based)
│
├── 📁 auth/                       # 🔐 AUTHENTICATION
│   ├── login.php                  # Login page
│   ├── register.php               # Registration
│   ├── verify_email.php           # Email verification
│   └── logout.php                 # Logout
│
├── 📁 actions/                    # ⚡ ACTION HANDLERS
│   ├── approve_leave.php          # Approve leave
│   ├── reject_leave.php           # Reject leave
│   ├── submit_leave.php           # Submit leave
│   └── update_profile.php         # Update profile
│
├── 📁 api/                        # 🔌 API ENDPOINTS
│   ├── get_leave_details.php      # Get leave details
│   ├── get_pending_count.php      # Get pending count
│   └── send_notification.php      # Send notifications
│
├── 📁 includes/                   # 🧩 SHARED COMPONENTS
│   ├── header.php                 # Page header
│   ├── sidebar.php                # Navigation sidebar
│   ├── footer.php                 # Page footer
│   ├── EmailService.php           # Email service
│   └── database.php               # Database connection
│
├── 📁 assets/                     # 🎨 STATIC ASSETS
│   ├── css/                       # Stylesheets
│   ├── js/                        # JavaScript
│   ├── images/                    # Images
│   └── libs/                      # External libraries
│
├── 📁 config/                     # ⚙️ CONFIGURATION
│   ├── database.php               # Database config
│   ├── email.php                  # Email config
│   └── app.php                    # App config
│
└── 📁 docs/                       # 📚 DOCUMENTATION
    ├── README.md                  # This file
    ├── SETUP.md                   # Setup guide
    └── API.md                     # API documentation
```

---

## 🚀 **Quick Start Guide**

### **For Administrators:**
- **Main Entry:** `core/dashboard.php`
- **User Management:** `core/user_management.php`
- **Leave Management:** `core/leave_management.php`
- **Calendar View:** `core/calendar.php`

### **For Directors:**
- **Main Entry:** `core/dashboard.php`
- **User Management:** `core/user_management.php`
- **Leave Management:** `core/leave_management.php`
- **Calendar View:** `core/calendar.php`

### **For Department Heads:**
- **Main Entry:** `core/dashboard.php`
- **Leave Management:** `core/leave_management.php`
- **Calendar View:** `core/calendar.php`

### **For Employees:**
- **Main Entry:** `core/dashboard.php`
- **Submit Leave:** `actions/submit_leave.php`
- **Calendar View:** `core/calendar.php`

---

## 🎯 **Key Features**

### ✅ **Core Functionality:**
- **Role-based Access Control** - Admin, Director, Manager, Employee
- **Leave Management** - Submit, approve, reject leave requests
- **Calendar View** - Interactive calendar with filters
- **User Management** - Add, edit, delete users
- **Reports** - Comprehensive reporting system

### ✅ **Gmail Integration:**
- **Email Verification** - Complete verification workflow
- **Automated Notifications** - Leave status updates
- **Offline Email Queue** - Queue emails when offline
- **SMTP Configuration** - Gmail SMTP integration

### ✅ **Professional Design:**
- **Dark Theme** - Modern, professional appearance
- **Responsive Design** - Works on all devices
- **Consistent UI/UX** - Unified design language
- **Real-time Updates** - Live notifications and alerts

---

## 🔧 **Technical Details**

### **File Organization Benefits:**
1. **Clear Separation** - Each directory has a specific purpose
2. **Easy Navigation** - Logical file placement
3. **Professional Structure** - Industry-standard organization
4. **Maintainable Code** - Easy to find and modify files
5. **Scalable Architecture** - Easy to add new features

### **Role-Based Access:**
- **Admin:** Full system access
- **Director:** User and leave management
- **Manager:** Department leave management
- **Employee:** Personal leave management

### **Database Integration:**
- **MySQL/MariaDB** - Robust database support
- **PDO** - Secure database operations
- **Prepared Statements** - SQL injection protection

---

## 📱 **System Requirements**

- **PHP 7.4+** - Server-side scripting
- **MySQL 5.7+** - Database management
- **Apache/Nginx** - Web server
- **Gmail SMTP** - Email functionality

---

## 🎨 **Design Features**

- **Modern UI** - Clean, professional interface
- **Dark Theme** - Easy on the eyes
- **Responsive** - Mobile-friendly design
- **Interactive** - Real-time updates
- **Accessible** - User-friendly navigation

---

## 📞 **Support**

For technical support or questions about the system organization, please refer to the documentation in the `docs/` directory.

---

**ELMS - Professional Employee Leave Management System**  
*Organized, Clean, and Easy to Navigate*
