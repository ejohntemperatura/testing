<?php
session_start();
require_once '../config/database.php';

// Allow admin or manager (department head) to access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin','manager'])) {
    header('Location: ../auth/index.php');
    exit();
}

// Get department head info
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$me = $stmt->fetch();

// Get leave requests for department head's department (excluding annual and unpaid leave types)
// Department head can see all requests from their department for management
$stmt = $pdo->prepare("
    SELECT lr.*, e.name as employee_name, e.position, e.department
    FROM leave_requests lr 
    JOIN employees e ON lr.employee_id = e.id 
    WHERE e.department = ? 
    AND (lr.leave_type NOT IN ('annual', 'unpaid') OR lr.leave_type IS NULL OR lr.leave_type = '')
    ORDER BY lr.start_date ASC
");
$stmt->execute([$me['department']]);
$leave_requests = $stmt->fetchAll();

// Debug: Log department and leave requests
error_log("Department Head Department: " . $me['department']);
error_log("Number of leave requests found: " . count($leave_requests));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- OFFLINE Tailwind CSS - No internet required! -->
    <link rel="stylesheet" href="../assets/css/tailwind.css">
        <!-- Font Awesome Local - No internet required! -->
    <link rel="stylesheet" href="../assets/libs/fontawesome/css/all.min.css">
    

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>ELMS - Department Leave Calendar</title>
    <script>
    </script>
    
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dark-theme.css">
    <link href='../assets/libs/fullcalendar/css/main.min.css' rel='stylesheet' />
    
    <style>
        /* FullCalendar Custom Styling */
        .fc {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .fc-header-toolbar {
            margin-bottom: 1.5rem !important;
            padding: 1rem;
            background: #1e293b !important;
            border-radius: 8px;
            border: 1px solid #334155 !important;
        }
        
        .fc-toolbar-title {
            font-size: 1.5rem !important;
            font-weight: 600 !important;
            color: #f8fafc !important;
        }
        
        .fc-button {
            background: #0891b2 !important;
            border: 1px solid #0891b2 !important;
            border-radius: 6px !important;
            font-weight: 500 !important;
            padding: 0.5rem 1rem !important;
            color: white !important;
        }
        
        .fc-button:hover {
            background: #0e7490 !important;
            border-color: #0e7490 !important;
        }
        
        .fc-button:focus {
            box-shadow: 0 0 0 3px rgba(8, 145, 178, 0.3) !important;
        }
        
        .fc-button-primary:not(:disabled):active {
            background: #0e7490 !important;
            border-color: #0e7490 !important;
        }
        
        .fc-button-group {
            background: #1e293b !important;
        }
        
        .fc-button-group .fc-button {
            background: #334155 !important;
            border-color: #475569 !important;
            color: #f8fafc !important;
        }
        
        .fc-button-group .fc-button:hover {
            background: #475569 !important;
            border-color: #64748b !important;
        }
        
        .fc-button-group .fc-button:focus {
            box-shadow: 0 0 0 3px rgba(71, 85, 105, 0.3) !important;
        }
        
        .fc-event {
            border-radius: 4px !important;
            border: none !important;
            padding: 2px 6px !important;
            font-size: 0.85rem !important;
            font-weight: 500 !important;
        }
        
        .fc-event-title {
            font-weight: 600 !important;
        }
        
        /* Status Colors - Removed for cleaner look */
        
        /* Leave Type Colors - Common Color Wheel Colors */
        .leave-vacation { background: #00cc66 !important; color: white !important; }    /* Green */
        .leave-sick { background: #cc6600 !important; color: white !important; }        /* Orange */
        .leave-maternity { background: #cc0066 !important; color: white !important; }   /* Pink/Magenta */
        .leave-paternity { background: #0066cc !important; color: white !important; }   /* Light Blue */
        .leave-bereavement { background: #666666 !important; color: white !important; } /* Gray */
        .leave-study { background: #9900cc !important; color: white !important; }       /* Purple */
        .leave-emergency { background: #cc0000 !important; color: white !important; }   /* Red */
        
        /* Status-based colors removed - only leave type colors are used */
        
        .calendar-container {
            background: #1e293b;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -1px rgba(0, 0, 0, 0.2);
            border: 1px solid #334155;
            overflow: hidden;
        }
        
        .calendar-header {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            padding: 1.5rem;
            border-bottom: 1px solid #334155;
        }
        
        .calendar-header h2 {
            margin: 0;
            color: #f8fafc;
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .calendar-header p {
            margin: 0.5rem 0 0 0;
            color: #94a3b8;
            font-size: 0.95rem;
        }
        
        .legend {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 4px;
        }
        
        .department-filter {
            margin-top: 1rem;
        }
        
        .department-filter select {
            max-width: 300px;
        }
        
        /* FullCalendar Dark Theme */
        .fc {
            background: #0f172a !important;
            color: #f8fafc !important;
        }
        
        .fc-header-toolbar {
            background: #0f172a !important;
            border: 1px solid #334155 !important;
            margin-bottom: 1rem !important;
        }
        
        .fc-toolbar-title {
            color: #f8fafc !important;
            font-size: 1.5rem !important;
            font-weight: 600 !important;
        }
        
        .fc-button {
            background: #0891b2 !important;
            border: 1px solid #0891b2 !important;
            border-radius: 6px !important;
            font-weight: 500 !important;
            padding: 0.5rem 1rem !important;
            color: white !important;
        }
        
        .fc-button:hover {
            background: #0e7490 !important;
            border-color: #0e7490 !important;
        }
        
        .fc-button:focus {
            box-shadow: 0 0 0 3px rgba(8, 145, 178, 0.3) !important;
        }
        
        .fc-button-primary:not(:disabled):active {
            background: #0e7490 !important;
            border-color: #0e7490 !important;
        }
        
        .fc-button-group {
            background: #0f172a !important;
        }
        
        .fc-button-group .fc-button {
            background: #334155 !important;
            border-color: #475569 !important;
            color: #f8fafc !important;
        }
        
        .fc-button-group .fc-button:hover {
            background: #475569 !important;
            border-color: #64748b !important;
        }
        
        .fc-button-group .fc-button:focus {
            box-shadow: 0 0 0 3px rgba(71, 85, 105, 0.3) !important;
        }
        
        .fc-daygrid-day {
            background: #0f172a !important;
            border-color: #334155 !important;
        }
        
        .fc-daygrid-day-number {
            color: #f8fafc !important;
        }
        
        .fc-daygrid-day:hover {
            background: #1e293b !important;
        }
        
        .fc-daygrid-day.fc-day-today {
            background: #1e293b !important;
        }
        
        .fc-daygrid-day.fc-day-today .fc-daygrid-day-number {
            color: #0891b2 !important;
            font-weight: bold !important;
        }
        
        .fc-col-header-cell {
            background: #1e293b !important;
            color: #f8fafc !important;
            border-color: #334155 !important;
        }
        
        .fc-scrollgrid {
            border-color: #334155 !important;
            background: #0f172a !important;
        }
        
        .fc-scrollgrid-sync-table {
            border-color: #334155 !important;
        }
        
        .fc-scrollgrid-section > * {
            border-color: #334155 !important;
        }
        
        .fc-daygrid-body {
            background: #0f172a !important;
        }
        
        .fc-daygrid-day-frame {
            background: #0f172a !important;
        }
        
        .fc-daygrid-day-events {
            background: #0f172a !important;
            margin-top: 2px !important;
        }
        
        .fc-daygrid-event {
            margin: 1px 2px !important;
        }
        
        .fc-event {
            border-radius: 4px !important;
            border: none !important;
            padding: 4px 8px !important;
            font-size: 0.9rem !important;
            font-weight: 600 !important;
            min-height: 20px !important;
            opacity: 1 !important;
            display: block !important;
        }
        
        .fc-event-title {
            font-weight: 600 !important;
        }
        
        /* Ensure all calendar elements are dark */
        .fc-daygrid-day-bg {
            background: #0f172a !important;
        }
        
        .fc-daygrid-day-top {
            background: #0f172a !important;
        }
        
        .fc-daygrid-day-bottom {
            background: #0f172a !important;
        }
        
        .fc-daygrid-day-content {
            background: #0f172a !important;
        }
        
        .fc-daygrid-day-bg .fc-daygrid-day-number {
            color: #f8fafc !important;
        }
        
        /* List view dark theme */
        .fc-list {
            background: #0f172a !important;
            color: #f8fafc !important;
        }
        
        .fc-list-day-cushion {
            background: #1e293b !important;
            color: #f8fafc !important;
        }
        
        .fc-list-event {
            background: #0f172a !important;
            color: #f8fafc !important;
        }
        
        .fc-list-event-title {
            color: #f8fafc !important;
        }
        
        .fc-list {
            background: #1e293b !important;
        }
        
        .fc-list-day-cushion {
            background: #334155 !important;
            color: #f8fafc !important;
        }
        
        .fc-list-event {
            background: #1e293b !important;
            border-color: #334155 !important;
        }
        
        .fc-list-event:hover {
            background: #334155 !important;
        }
        
        .fc-list-event-time {
            color: #94a3b8 !important;
        }
        
        .fc-list-event-title {
            color: #f8fafc !important;
        }
        
        /* Leave type specific colors */
        .leave-general {
            background-color: #3b82f6 !important;
            border-color: #3b82f6 !important;
            color: white !important;
        }
        
        .leave-vacation {
            background-color: #10b981 !important;
            border-color: #10b981 !important;
            color: white !important;
        }
        
        .leave-sick {
            background-color: #f59e0b !important;
            border-color: #f59e0b !important;
            color: white !important;
        }
        
        .leave-maternity {
            background-color: #ec4899 !important;
            border-color: #ec4899 !important;
            color: white !important;
        }
        
        .leave-paternity {
            background-color: #8b5cf6 !important;
            border-color: #8b5cf6 !important;
            color: white !important;
        }
        
        .leave-bereavement {
            background-color: #6b7280 !important;
            border-color: #6b7280 !important;
            color: white !important;
        }
        
        .leave-study {
            background-color: #eab308 !important;
            border-color: #eab308 !important;
            color: white !important;
        }
        
        .leave-emergency {
            background-color: #ef4444 !important;
            border-color: #ef4444 !important;
            color: white !important;
        }
        
        .calendar-container {
            background: #1e293b;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -1px rgba(0, 0, 0, 0.2);
            border: 1px solid #334155;
            overflow: hidden;
        }
        
        .calendar-header {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            padding: 1.5rem;
            border-bottom: 1px solid #334155;
        }
        
        .calendar-header h2 {
            margin: 0;
            color: #f8fafc;
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .calendar-header p {
            margin: 0.5rem 0 0 0;
            color: #94a3b8;
            font-size: 0.95rem;
        }
        
        .legend {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 4px;
        }
        
        .filters-row {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-group select {
            max-width: 100%;
        }
    </style>
</head>
<body class="bg-slate-900 text-white">
    <?php include '../includes/unified_navbar.php'; ?>

    <div class="flex">
        <!-- Left Sidebar -->
        <aside id="sidebar" class="fixed left-0 top-16 h-screen w-64 bg-slate-900 border-r border-slate-800 overflow-y-auto z-40">
            <nav class="p-4 space-y-2">
                <!-- Other Navigation Items -->
                <a href="department_head_dashboard.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                    <i class="fas fa-tachometer-alt w-5"></i>
                    <span>Dashboard</span>
                </a>
                
                <!-- Section Headers -->
                <div class="space-y-1">
                    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2">Management</h3>
                    
                    <!-- Active Navigation Item -->
                    <a href="view_chart.php" class="flex items-center space-x-3 px-4 py-3 text-white bg-blue-500/20 rounded-lg border border-blue-500/30">
                        <i class="fas fa-calendar w-5"></i>
                        <span>Calendar View</span>
                    </a>
                </div>
                
                <div class="space-y-1">
                    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2">Reports</h3>
                    
                    <a href="reports.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                        <i class="fas fa-file-alt w-5"></i>
                        <span>Reports</span>
                    </a>
                    
                    <a href="audit_logs.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                        <i class="fas fa-history w-5"></i>
                        <span>Audit Logs</span>
                    </a>
                </div>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="flex-1 ml-64 p-6 pt-24">
            <div class="max-w-7xl mx-auto">
                <!-- Page Header -->
                <div class="mb-8">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-gradient-to-r from-primary to-accent rounded-2xl flex items-center justify-center">
                            <i class="fas fa-calendar-alt text-2xl text-white"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-white mb-2">Department Leave Calendar</h1>
                            <p class="text-slate-400">View and manage all leave requests for <?php echo htmlspecialchars($me['department']); ?> department</p>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden mb-8">
                    <div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
                        <h3 class="text-xl font-semibold text-white flex items-center">
                            <i class="fas fa-filter text-primary mr-3"></i>Filters
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="departmentFilter" class="block text-sm font-semibold text-slate-300 mb-2">Filter by Department</label>
                                <select id="departmentFilter" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                    <option value="">All Departments</option>
                                    <?php
                                    $departments = array_unique(array_column($leave_requests, 'department'));
                                    foreach ($departments as $dept): ?>
                                        <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="leaveTypeFilter" class="block text-sm font-semibold text-slate-300 mb-2">Filter by Leave Type</label>
                                <select id="leaveTypeFilter" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                    <option value="">All Leave Types</option>
                                    <option value="mandatory">Mandatory/Forced Leave</option>
                                    <option value="special_privilege">Special Leave Privilege</option>
                                    <option value="solo_parent">Solo Parent Leave</option>
                                    <option value="vawc">10-Day VAWC Leave</option>
                                    <option value="rehabilitation">Rehabilitation Privilege</option>
                                    <option value="special_women">Special Leave Benefits for Women</option>
                                    <option value="special_emergency">Special Emergency Leave (Calamity)</option>
                                    <option value="adoption">Adoption Leave</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Legend -->
                <div class="legend">
                    <div class="legend-item">
                        <div class="legend-color leave-vacation"></div>
                        <span>Vacation Leave</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color leave-sick"></div>
                        <span>Sick Leave</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color leave-maternity"></div>
                        <span>Maternity Leave</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color leave-paternity"></div>
                        <span>Paternity Leave</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color leave-bereavement"></div>
                        <span>Bereavement Leave</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color leave-study"></div>
                        <span>Study Leave</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color leave-emergency"></div>
                        <span>Emergency Leave</span>
                    </div>
                </div>
            </div>

            <!-- Calendar Container -->
            <div class="calendar-container">
                <div id="calendar"></div>
            </div>
            </div>
        </main>
    </div>

    <script src="../assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src='../assets/libs/fullcalendar/js/main.min.js'></script>
    <script>
        // User dropdown toggle function
        function toggleUserDropdown() {
            const dropdown = document.getElementById('userDropdown');
            dropdown.classList.toggle('hidden');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const userDropdown = document.getElementById('userDropdown');
            const userButton = event.target.closest('[onclick="toggleUserDropdown()"]');
            
            if (userDropdown && !userDropdown.contains(event.target) && !userButton) {
                userDropdown.classList.add('hidden');
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            
            // Debug: Log calendar initialization
            console.log('Department View Chart - Initializing calendar...');
            console.log('Calendar element:', calendarEl);
            
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,listWeek'
                },
                height: 'auto',
                events: [
                    <?php 
                    // Debug: Log the number of leave requests
                    error_log("Department View Chart - Number of leave requests: " . count($leave_requests));
                    foreach ($leave_requests as $request): 
                        error_log("Leave request: " . $request['employee_name'] . " - " . $request['leave_type'] . " (" . $request['start_date'] . " to " . $request['end_date'] . ")");
                    ?>
                    {
                        id: '<?php echo $request['id']; ?>',
                        title: '<?php echo addslashes($request['employee_name']); ?> - <?php echo !empty($request['leave_type']) ? ucfirst(str_replace('_', ' ', $request['leave_type'])) : 'Leave Request'; ?>',
                        start: '<?php echo $request['start_date']; ?>',
                        end: '<?php echo date('Y-m-d', strtotime($request['end_date'] . ' +1 day')); ?>',
                        className: 'leave-<?php echo !empty($request['leave_type']) ? $request['leave_type'] : 'general'; ?>',
                        extendedProps: {
                            employee: '<?php echo addslashes($request['employee_name']); ?>',
                            position: '<?php echo addslashes($request['position']); ?>',
                            department: '<?php echo addslashes($request['department']); ?>',
                            reason: '<?php echo addslashes($request['reason']); ?>',
                            status: '<?php echo $request['status']; ?>',
                            leaveType: '<?php echo $request['leave_type']; ?>',
                            days: '<?php echo (strtotime($request['end_date']) - strtotime($request['start_date'])) / (60 * 60 * 24) + 1; ?>'
                        }
                    },
                    <?php endforeach; ?>
                ],
                eventClick: function(info) {
                    var event = info.event;
                    var props = event.extendedProps;
                    
                    // Create modal content
                    var modalContent = `
                        <div class="modal fade" id="leaveDetailsModal" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">
                                            <i class="fas fa-calendar-check me-2"></i>Leave Request Details
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6 class="text-primary mb-3">Employee Information</h6>
                                                <p><strong>Name:</strong> ${props.employee}</p>
                                                <p><strong>Position:</strong> ${props.position}</p>
                                                <p><strong>Department:</strong> ${props.department}</p>
                                            </div>
                                            <div class="col-md-6">
                                                <h6 class="text-primary mb-3">Leave Details</h6>
                                                <p><strong>Type:</strong> <span class="badge bg-primary">${props.leaveType.replace('_', ' ').toUpperCase()}</span></p>
                                                <p><strong>Duration:</strong> ${props.days} day(s)</p>
                                            </div>
                                        </div>
                                        <div class="row mt-3">
                                            <div class="col-12">
                                                <h6 class="text-primary mb-3">Reason</h6>
                                                <p class="bg-light p-3 rounded">${props.reason}</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        ${props.status === 'pending' ? `
                                            <a href="approve_leave.php?request_id=${event.id}" class="btn btn-success" onclick="return confirm('Approve this leave request?')">
                                                <i class="fas fa-check me-2"></i>Approve
                                            </a>
                                            <a href="reject_leave.php?request_id=${event.id}" class="btn btn-danger" onclick="return confirm('Reject this leave request?')">
                                                <i class="fas fa-times me-2"></i>Reject
                                            </a>
                                        ` : ''}
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    // Remove existing modal if any
                    const existingModal = document.getElementById('leaveDetailsModal');
                    if (existingModal) {
                        existingModal.remove();
                    }
                    
                    // Add modal to body
                    document.body.insertAdjacentHTML('beforeend', modalContent);
                    
                    // Show modal
                    const modal = new bootstrap.Modal(document.getElementById('leaveDetailsModal'));
                    modal.show();
                },
                eventDidMount: function(info) {
                    // Add tooltip
                    info.el.title = info.event.title;
                }
            });
            
            calendar.render();
            
            // Debug: Log calendar events after render
            console.log('Department View Chart - Calendar rendered');
            console.log('Number of events:', calendar.getEvents().length);
            console.log('Calendar element:', calendarEl);
            console.log('Calendar element dimensions:', calendarEl.offsetWidth, 'x', calendarEl.offsetHeight);
            calendar.getEvents().forEach(function(event, index) {
                console.log('Event ' + index + ':', event.title, event.start, event.end, event.extendedProps);
            });
            
            // Force calendar to render again
            calendar.render();
            
            // Filter functionality
            function applyFilters() {
                var selectedDepartment = document.getElementById('departmentFilter').value;
                var selectedLeaveType = document.getElementById('leaveTypeFilter').value;
                var events = calendar.getEvents();
                
                events.forEach(function(event) {
                    var showEvent = true;
                    var props = event.extendedProps;
                    
                    if (selectedDepartment !== '' && props.department !== selectedDepartment) {
                        showEvent = false;
                    }
                    if (selectedLeaveType !== '' && props.leaveType !== selectedLeaveType) {
                        showEvent = false;
                    }
                    
                    event.setProp('display', showEvent ? 'block' : 'none');
                });
            }
            
            // Add event listeners to both filters
            document.getElementById('departmentFilter').addEventListener('change', applyFilters);
            document.getElementById('leaveTypeFilter').addEventListener('change', applyFilters);
        });
    </script>
</body>
</html>
