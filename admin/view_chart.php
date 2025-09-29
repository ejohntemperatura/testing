<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/index.php');
    exit();
}

// Check if user is admin or manager
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin','manager','director'])) {
    header('Location: ../auth/index.php');
    exit();
}

// Get admin info
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// Admin can see all leave requests (excluding annual and unpaid leave types)
$stmt = $pdo->prepare("
    SELECT lr.*, e.name as employee_name, e.position, e.department
    FROM leave_requests lr 
    JOIN employees e ON lr.employee_id = e.id 
    WHERE lr.leave_type NOT IN ('annual', 'unpaid')
    ORDER BY lr.start_date ASC
");
$stmt->execute();
$leave_requests = $stmt->fetchAll();
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
    <title>ELMS - Admin Leave Calendar</title>
    <script>
    </script>
    
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dark-theme.css">
    <link rel="stylesheet" href="../assets/css/admin_style.css">
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
        
        /* FullCalendar Dark Theme */
        .fc {
            background: #1e293b !important;
            color: #f8fafc !important;
        }
        
        .fc-theme-standard td, .fc-theme-standard th {
            border-color: #334155 !important;
        }
        
        .fc-theme-standard .fc-scrollgrid {
            border-color: #334155 !important;
        }
        
        .fc-daygrid-day {
            background: #1e293b !important;
        }
        
        .fc-daygrid-day:hover {
            background: #334155 !important;
        }
        
        .fc-daygrid-day-number {
            color: #f8fafc !important;
        }
        
        .fc-daygrid-day.fc-day-today {
            background: #0f172a !important;
        }
        
        .fc-daygrid-day.fc-day-today .fc-daygrid-day-number {
            color: #06b6d4 !important;
            font-weight: 600 !important;
        }
        
        .fc-col-header-cell {
            background: #334155 !important;
            color: #f8fafc !important;
            font-weight: 600 !important;
        }
        
        .fc-daygrid-day-events {
            margin-top: 2px !important;
        }
        
        .fc-daygrid-event {
            margin: 1px 2px !important;
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
    </style>
</head>
<body class="bg-slate-900 text-white">
    <?php include '../includes/unified_navbar.php'; ?>

    <div class="flex">
        <!-- Left Sidebar -->
        <aside id="sidebar" class="fixed left-0 top-16 h-screen w-64 bg-slate-900 border-r border-slate-800 overflow-y-auto z-40 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out">
            <nav class="p-4 space-y-2">
                <!-- Active Navigation Item -->
                <a href="admin_dashboard.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                    <i class="fas fa-tachometer-alt w-5"></i>
                    <span>Dashboard</span>
                </a>
                
                
                <!-- Section Headers -->
                <div class="space-y-1">
                    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2">Management</h3>
                    
                    <!-- Navigation Items -->
                    <a href="manage_user.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                        <i class="fas fa-users-cog w-5"></i>
                        <span>Manage Users</span>
                    </a>
                    
                    <a href="leave_management.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                        <i class="fas fa-calendar-check w-5"></i>
                        <span>Leave Management</span>
                        <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full" id="pendingLeaveBadge" style="display: none;">0</span>
                    </a>
                
                <!-- Active Navigation Item -->
                <a href="leave_alerts.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                        <i class="fas fa-bell w-5"></i>
                        <span>Leave Alerts</span>
                    </a>
                
                </div>

                <div class="space-y-1">
                    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2">Reports</h3>
                    
                <!-- Active Navigation Item -->
                <a href="view_chart.php" class="flex items-center space-x-3 px-4 py-3 text-white bg-blue-500/20 rounded-lg border border-blue-500/30">
                    <i class="fas fa-calendar w-5"></i>
                    <span>Leave Chart</span>
                </a>
                <a href="reports.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                        <i class="fas fa-file-alt w-5"></i>
                        <span>Reports</span>
                    </a>
                </div>
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
                            <h1 class="text-3xl font-bold text-white mb-2">Admin Leave Calendar</h1>
                            <p class="text-slate-400">View and manage all leave requests across the organization</p>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-slate-800 rounded-2xl border border-slate-700/50 overflow-hidden mb-8">
                    <div class="px-6 py-4 border-b border-slate-700/50 bg-slate-700/30">
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
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,listWeek'
                },
                height: 'auto',
                events: [
                    <?php foreach ($leave_requests as $request): ?>
                    {
                        id: '<?php echo $request['id']; ?>',
                        title: '<?php echo addslashes($request['employee_name']); ?> - <?php echo ucfirst(str_replace('_', ' ', $request['leave_type'])); ?>',
                        start: '<?php echo $request['start_date']; ?>',
                        end: '<?php echo date('Y-m-d', strtotime($request['end_date'] . ' +1 day')); ?>',
                        className: 'leave-<?php echo $request['leave_type']; ?>',
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
                                            <a href="leave_management.php?approve=${event.id}" class="btn btn-success" onclick="return confirm('Approve this leave request?')">
                                                <i class="fas fa-check me-2"></i>Approve
                                            </a>
                                            <a href="leave_management.php?reject=${event.id}" class="btn btn-danger" onclick="return confirm('Reject this leave request?')">
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
            
            // Filter functionality
            function applyFilters() {
                var selectedDept = document.getElementById('departmentFilter').value;
                var selectedType = document.getElementById('leaveTypeFilter').value;
                var events = calendar.getEvents();
                
                events.forEach(function(event) {
                    var showEvent = true;
                    var props = event.extendedProps;
                    
                    if (selectedDept !== '' && props.department !== selectedDept) {
                        showEvent = false;
                    }
                    if (selectedType !== '' && props.leaveType !== selectedType) {
                        showEvent = false;
                    }
                    
                    event.setProp('display', showEvent ? 'block' : 'none');
                });
            }
            
            // Add event listeners to filters
            document.getElementById('departmentFilter').addEventListener('change', applyFilters);
            document.getElementById('leaveTypeFilter').addEventListener('change', applyFilters);

            // Function to fetch pending leave count
            function fetchPendingLeaveCount() {
                fetch('api/get_pending_leave_count.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const badge = document.getElementById('pendingLeaveBadge');
                            if (data.count > 0) {
                                badge.textContent = data.count;
                                badge.style.display = 'inline-block';
                            } else {
                                badge.style.display = 'none';
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching pending leave count:', error);
                    });
            }

            // Fetch pending leave count on page load
            fetchPendingLeaveCount();

            // Update pending leave count every 30 seconds
            setInterval(fetchPendingLeaveCount, 30000);
        });
    </script>
            </div>
        </main>
    </div>
</body>
</html>