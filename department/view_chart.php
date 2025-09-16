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

// Get leave requests for department head's department
// Department head can see all requests from their department for management
$stmt = $pdo->prepare("
    SELECT lr.*, e.name as employee_name, e.position, e.department
    FROM leave_requests lr 
    JOIN employees e ON lr.employee_id = e.id 
    WHERE e.department = ?
    ORDER BY lr.start_date ASC
");
$stmt->execute([$me['department']]);
$leave_requests = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ELMS - Department Leave Calendar</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#0891b2',    // Cyan-600 - Main brand color
                        secondary: '#f97316',  // Orange-500 - Accent/action color
                        accent: '#06b6d4',     // Cyan-500 - Highlight color
                        background: '#0f172a', // Slate-900 - Main background
                        foreground: '#f8fafc', // Slate-50 - Primary text
                        muted: '#64748b'       // Slate-500 - Secondary text
                    }
                }
            }
        }
    </script>
    <style>
        /* FullCalendar Custom Styling */
        .fc {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .fc-header-toolbar {
            margin-bottom: 1.5rem !important;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid rgba(0, 0, 0, 0.06);
        }
        
        .fc-toolbar-title {
            font-size: 1.5rem !important;
            font-weight: 600 !important;
            color: #212529 !important;
        }
        
        .fc-button {
            background: var(--primary) !important;
            border: 1px solid var(--primary) !important;
            border-radius: 6px !important;
            font-weight: 500 !important;
            padding: 0.5rem 1rem !important;
        }
        
        .fc-button:hover {
            background: var(--primary-hover) !important;
            border-color: var(--primary-hover) !important;
        }
        
        .fc-button:focus {
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1) !important;
        }
        
        .fc-button-primary:not(:disabled):active {
            background: var(--primary-hover) !important;
            border-color: var(--primary-hover) !important;
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
        .leave-annual { background: #0066cc !important; color: white !important; }      /* Blue */
        .leave-vacation { background: #00cc66 !important; color: white !important; }    /* Green */
        .leave-sick { background: #cc6600 !important; color: white !important; }        /* Orange */
        .leave-maternity { background: #cc0066 !important; color: white !important; }   /* Pink/Magenta */
        .leave-paternity { background: #0066cc !important; color: white !important; }   /* Light Blue */
        .leave-bereavement { background: #666666 !important; color: white !important; } /* Gray */
        .leave-study { background: #9900cc !important; color: white !important; }       /* Purple */
        .leave-unpaid { background: #ffcc00 !important; color: black !important; }      /* Yellow */
        .leave-emergency { background: #cc0000 !important; color: white !important; }   /* Red */
        
        /* Status-based colors removed - only leave type colors are used */
        
        .calendar-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(0, 0, 0, 0.06);
            overflow: hidden;
        }
        
        .calendar-header {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 1.5rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.06);
        }
        
        .calendar-header h2 {
            margin: 0;
            color: #212529;
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .calendar-header p {
            margin: 0.5rem 0 0 0;
            color: #6c757d;
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
    </style>
</head>
<body class="bg-slate-900 text-white">
    <!-- Top Navigation Bar -->
    <nav class="bg-slate-800 border-b border-slate-700 fixed top-0 left-0 right-0 z-50 h-16">
        <div class="px-6 py-4 h-full">
            <div class="flex items-center justify-between h-full">
                <!-- Logo and Title -->
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2">
                        <div class="w-8 h-8 bg-gradient-to-r from-primary to-accent rounded-lg flex items-center justify-center">
                            <i class="fas fa-calendar text-white text-sm"></i>
                        </div>
                        <span class="text-xl font-bold text-white">ELMS Department</span>
                    </div>
                </div>
                
                <!-- User Menu -->
                <div class="flex items-center space-x-4">
                    <a href="../auth/logout.php" class="text-slate-300 hover:text-white transition-colors flex items-center space-x-2">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex">
        <!-- Left Sidebar -->
        <aside class="fixed left-0 top-16 h-screen w-64 bg-slate-800 border-r border-slate-700 overflow-y-auto z-40">
            <nav class="p-4 space-y-2">
                <!-- Other Navigation Items -->
                <a href="department_head_dashboard.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                    <i class="fas fa-tachometer-alt w-5"></i>
                    <span>Dashboard</span>
                </a>
                
                <!-- Active Navigation Item -->
                <a href="view_chart.php" class="flex items-center space-x-3 px-4 py-3 text-white bg-primary/20 rounded-lg border border-primary/30">
                    <i class="fas fa-calendar w-5"></i>
                    <span>Calendar View</span>
                </a>
                
                <a href="reports.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                    <i class="fas fa-file-alt w-5"></i>
                    <span>Reports</span>
                </a>
                
                <a href="audit_logs.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                    <i class="fas fa-history w-5"></i>
                    <span>Audit Logs</span>
                </a>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="flex-1 ml-64 p-6">
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
                <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 overflow-hidden mb-8">
                    <div class="px-6 py-4 border-b border-slate-700/50 bg-slate-700/30">
                        <h3 class="text-xl font-semibold text-white flex items-center">
                            <i class="fas fa-filter text-primary mr-3"></i>Filters
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="employeeFilter" class="block text-sm font-semibold text-slate-300 mb-2">Filter by Employee</label>
                                <select id="employeeFilter" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                    <option value="">All Employees</option>
                                    <?php
                                    $employees = array_unique(array_column($leave_requests, 'employee_name'));
                                    foreach ($employees as $employee): ?>
                                        <option value="<?php echo htmlspecialchars($employee); ?>"><?php echo htmlspecialchars($employee); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="leaveTypeFilter" class="block text-sm font-semibold text-slate-300 mb-2">Filter by Leave Type</label>
                                <select id="leaveTypeFilter" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                    <option value="">All Leave Types</option>
                                    <option value="annual">Annual Leave</option>
                                    <option value="vacation">Vacation Leave</option>
                                    <option value="sick">Sick Leave</option>
                                    <option value="maternity">Maternity Leave</option>
                                    <option value="paternity">Paternity Leave</option>
                                    <option value="bereavement">Bereavement Leave</option>
                                    <option value="study">Study Leave</option>
                                    <option value="unpaid">Unpaid Leave</option>
                                    <option value="emergency">Emergency Leave</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Legend -->
                <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 overflow-hidden mb-8">
                    <div class="px-6 py-4 border-b border-slate-700/50 bg-slate-700/30">
                        <h3 class="text-xl font-semibold text-white flex items-center">
                            <i class="fas fa-palette text-primary mr-3"></i>Leave Type Legend
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                            <div class="flex items-center space-x-3">
                                <div class="w-4 h-4 bg-blue-500 rounded"></div>
                                <span class="text-slate-300 text-sm">Annual Leave</span>
                            </div>
                            <div class="flex items-center space-x-3">
                                <div class="w-4 h-4 bg-green-500 rounded"></div>
                                <span class="text-slate-300 text-sm">Vacation Leave</span>
                            </div>
                            <div class="flex items-center space-x-3">
                                <div class="w-4 h-4 bg-red-500 rounded"></div>
                                <span class="text-slate-300 text-sm">Sick Leave</span>
                            </div>
                            <div class="flex items-center space-x-3">
                                <div class="w-4 h-4 bg-pink-500 rounded"></div>
                                <span class="text-slate-300 text-sm">Maternity Leave</span>
                            </div>
                            <div class="flex items-center space-x-3">
                                <div class="w-4 h-4 bg-purple-500 rounded"></div>
                                <span class="text-slate-300 text-sm">Paternity Leave</span>
                            </div>
                            <div class="flex items-center space-x-3">
                                <div class="w-4 h-4 bg-gray-500 rounded"></div>
                                <span class="text-slate-300 text-sm">Bereavement Leave</span>
                            </div>
                            <div class="flex items-center space-x-3">
                                <div class="w-4 h-4 bg-yellow-500 rounded"></div>
                                <span class="text-slate-300 text-sm">Study Leave</span>
                            </div>
                            <div class="flex items-center space-x-3">
                                <div class="w-4 h-4 bg-orange-500 rounded"></div>
                                <span class="text-slate-300 text-sm">Unpaid Leave</span>
                            </div>
                            <div class="flex items-center space-x-3">
                                <div class="w-4 h-4 bg-indigo-500 rounded"></div>
                                <span class="text-slate-300 text-sm">Emergency Leave</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Calendar Container -->
                <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 overflow-hidden">
                    <div class="p-6">
                        <div id="calendar"></div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
    <script>
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
            
            // Filter functionality
            function applyFilters() {
                var selectedEmployee = document.getElementById('employeeFilter').value;
                var selectedLeaveType = document.getElementById('leaveTypeFilter').value;
                var events = calendar.getEvents();
                
                events.forEach(function(event) {
                    var showEvent = true;
                    var props = event.extendedProps;
                    
                    if (selectedEmployee !== '' && props.employee !== selectedEmployee) {
                        showEvent = false;
                    }
                    if (selectedLeaveType !== '' && props.leaveType !== selectedLeaveType) {
                        showEvent = false;
                    }
                    
                    event.setProp('display', showEvent ? 'block' : 'none');
                });
            }
            
            // Add event listeners to both filters
            document.getElementById('employeeFilter').addEventListener('change', applyFilters);
            document.getElementById('leaveTypeFilter').addEventListener('change', applyFilters);
        });
    </script>
</body>
</html>
