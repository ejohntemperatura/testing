# ELMS Admin Features Documentation

## Overview
The ELMS (E-Learning Management System) admin panel provides comprehensive functionality for managing users, leave requests, generating reports, and tracking system activities.

## Admin Dashboard Features

### 1. Dashboard Overview (`admin_dashboard.php`)
- **Statistics Cards**: Real-time display of total employees, pending requests, approved requests, and rejected requests
- **Recent Leave Requests**: Table showing the latest leave requests with action buttons
- **Quick Actions**: Approve/Reject buttons for pending requests
- **View Details**: Modal popup showing detailed leave request information

#### Key Functions:
- `updateRequestStatus()`: Updates leave request status via AJAX
- `viewRequestDetails()`: Shows detailed leave request information in modal
- `showNotification()`: Displays success/error notifications

### 2. User Management (`manage_user.php`)
- **Add New Users**: Modal form for creating new employees
- **Edit Users**: Inline editing with modal forms
- **Delete Users**: Confirmation-based user deletion
- **User Search**: Real-time search functionality
- **Bulk Operations**: Select multiple users for bulk actions

#### Key Functions:
- `addUser()`: Creates new user accounts
- `editUser()`: Updates existing user information
- `deleteUser()`: Removes users with related data cleanup
- `showNotification()`: User feedback for actions

### 3. Leave Management (`leave_management.php`)
- **Advanced Filtering**: Filter by status, employee, leave type, and date range
- **Bulk Actions**: Approve/Reject multiple requests at once
- **Detailed View**: Comprehensive leave request information
- **Export Functionality**: Export filtered data to CSV
- **Email Notifications**: Automatic email alerts for status changes

#### Key Functions:
- `bulkAction()`: Process multiple leave requests
- `updateRequestStatus()`: Individual request status updates
- `exportLeaveRequests()`: CSV export functionality
- `sendLeaveStatusEmail()`: Email notification system

### 4. Reports & Analytics (`reports.php`)
- **Date Range Filtering**: Customizable date ranges for reports
- **Statistical Charts**: Visual representation of data using Chart.js
- **Department Analysis**: Leave requests by department
- **Leave Type Analysis**: Breakdown by leave type
- **Monthly Trends**: Time-series analysis of leave patterns
- **Export Options**: Multiple report types for download

#### Key Features:
- Interactive charts (pie, bar, line)
- Real-time data filtering
- CSV export functionality
- Responsive design

### 5. Audit Logs (`audit_logs.php`)
- **Activity Tracking**: Complete system activity log
- **Advanced Filtering**: Filter by action, user, and date range
- **Pagination**: Efficient handling of large datasets
- **Export Functionality**: Download audit logs
- **Clear Logs**: Admin option to clear audit history

#### Key Functions:
- `logAuditEvent()`: Records system activities
- `clearLogs()`: Removes audit log entries
- `exportLogs()`: Download audit data

## API Endpoints

### 1. Get Request Details (`get_request_details.php`)
- **Purpose**: Provides detailed leave request information for modals
- **Method**: GET
- **Parameters**: `id` (leave request ID)
- **Response**: JSON with employee and leave details

### 2. Admin Functions (`admin_functions.php`)
- **Utility Functions**: Common admin operations
- **Email System**: Leave status notifications
- **Audit Logging**: Activity tracking
- **Data Export**: CSV generation
- **Statistics**: Dashboard data aggregation

## Database Schema

### Audit Logs Table
```sql
CREATE TABLE audit_logs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT,
  action VARCHAR(100) NOT NULL,
  details TEXT,
  ip_address VARCHAR(45),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES employees(id)
);
```

## Security Features

### 1. Access Control
- Session-based authentication
- Role-based authorization (admin only)
- IP address logging
- Activity tracking

### 2. Data Validation
- Input sanitization
- SQL injection prevention
- XSS protection
- CSRF protection

### 3. Error Handling
- Comprehensive error logging
- User-friendly error messages
- Graceful failure handling

## User Interface Features

### 1. Responsive Design
- Bootstrap 5 framework
- Mobile-friendly layout
- Touch-optimized controls

### 2. Interactive Elements
- Hover effects on cards
- Smooth animations
- Loading indicators
- Toast notifications

### 3. Search & Filter
- Real-time search
- Advanced filtering options
- Sortable tables
- Pagination

## Email System

### Leave Status Notifications
- HTML email templates
- Professional styling
- Automatic sending
- Error handling

### Email Template Features
- Responsive design
- Company branding
- Clear information hierarchy
- Call-to-action buttons

## Installation & Setup

### 1. Database Setup
```sql
-- Run the audit logs table creation script
SOURCE create_audit_logs_table.sql;
```

### 2. File Permissions
- Ensure write permissions for log files
- Configure email settings in PHP
- Set up proper file paths

### 3. Configuration
- Update database connection settings
- Configure email server settings
- Set up audit logging preferences

## Usage Instructions

### 1. Accessing Admin Panel
1. Login with admin credentials
2. Navigate to admin dashboard
3. Use sidebar for navigation

### 2. Managing Users
1. Click "Manage User" in sidebar
2. Use "Add User" button for new users
3. Click edit/delete icons for existing users
4. Use search to find specific users

### 3. Managing Leave Requests
1. Navigate to "Leave Management"
2. Use filters to find specific requests
3. Use bulk actions for multiple requests
4. Export data as needed

### 4. Generating Reports
1. Go to "Reports" section
2. Set date range filters
3. View interactive charts
4. Export reports as CSV

### 5. Viewing Audit Logs
1. Access "Audit Logs" page
2. Use filters to find specific activities
3. Export logs for analysis
4. Clear logs if needed

## Troubleshooting

### Common Issues
1. **Email not sending**: Check PHP mail configuration
2. **Charts not loading**: Verify Chart.js CDN connection
3. **Database errors**: Check connection settings
4. **Permission denied**: Verify file permissions

### Debug Mode
- Enable error reporting in development
- Check browser console for JavaScript errors
- Review server error logs
- Use database query logging

## Future Enhancements

### Planned Features
1. **Real-time Notifications**: WebSocket integration
2. **Advanced Analytics**: Machine learning insights
3. **Mobile App**: Native mobile application
4. **API Integration**: RESTful API endpoints
5. **Multi-language Support**: Internationalization

### Performance Optimizations
1. **Caching**: Redis/Memcached integration
2. **Database Optimization**: Query optimization
3. **CDN Integration**: Static asset delivery
4. **Image Optimization**: Compressed assets

## Support & Maintenance

### Regular Tasks
1. **Database Backup**: Daily automated backups
2. **Log Rotation**: Weekly log cleanup
3. **Security Updates**: Monthly security patches
4. **Performance Monitoring**: Continuous monitoring

### Contact Information
- **Technical Support**: admin@elms.com
- **Documentation**: docs.elms.com
- **Issue Tracking**: github.com/elms/issues

---

*This documentation is maintained by the ELMS development team. For updates and questions, please contact the system administrator.* 