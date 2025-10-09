<?php
session_start();
require_once '../../../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../auth/views/login.php');
    exit();
}

// Get user info
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$me = $stmt->fetch();

// Users can only see their own leave requests (excluding annual and unpaid leave types)
$stmt = $pdo->prepare("
    SELECT lr.*, e.name as employee_name, e.position, e.department
    FROM leave_requests lr 
    JOIN employees e ON lr.employee_id = e.id 
    WHERE lr.employee_id = ? 
    AND lr.leave_type NOT IN ('annual', 'unpaid')
    ORDER BY lr.start_date ASC
");
$stmt->execute([$_SESSION['user_id']]);
$leave_requests = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- OFFLINE Tailwind CSS - No internet required! -->
    <link rel="stylesheet" href="../../../../assets/css/tailwind.css">
    <!-- Font Awesome Local - No internet required! -->
    <link rel="stylesheet" href="../../../../assets/libs/fontawesome/css/all.min.css">
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ELMS - My Leave Calendar</title>
    
    <link rel="stylesheet" href="../../../../assets/css/style.css">
    <link rel="stylesheet" href="../../../../assets/css/dark-theme.css">
    <link href='../../../../assets/libs/fullcalendar/css/main.min.css' rel='stylesheet' />
    
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

        /* Leave Type Colors - Solid Colors (matching leave credits) */
        .leave-vacation { background: #3b82f6 !important; color: white !important; }
        .leave-sick { background: #ef4444 !important; color: white !important; }
        .leave-mandatory { background: #6b7280 !important; color: white !important; }
        .leave-special_privilege { background: #eab308 !important; color: white !important; }
        .leave-maternity { background: #ec4899 !important; color: white !important; }
        .leave-paternity { background: #06b6d4 !important; color: white !important; }
        .leave-solo_parent { background: #f97316 !important; color: white !important; }
        .leave-vawc { background: #dc2626 !important; color: white !important; }
        .leave-rehabilitation { background: #22c55e !important; color: white !important; }
        .leave-special_women { background: #a855f7 !important; color: white !important; }
        .leave-special_emergency { background: #ea580c !important; color: white !important; }
        .leave-adoption { background: #10b981 !important; color: white !important; }
        .leave-study { background: #6366f1 !important; color: white !important; }

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
    <?php include '../../../../includes/unified_navbar.php'; ?>

    <div class="flex">
        <!-- Left Sidebar -->
        <aside class="fixed left-0 top-16 h-screen w-64 bg-slate-900 border-r border-slate-800 overflow-y-auto z-40">
            <nav class="p-4 space-y-2">
                <!-- Active Navigation Item (Dashboard) -->
                <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                    <i class="fas fa-tachometer-alt w-5"></i>
                    <span>Dashboard</span>
                </a>
                
                <!-- Section Headers -->
                <div class="space-y-1">
                    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2">Leave Management</h3>
                    
                    <!-- Navigation Items -->
                    <a href="leave_history.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                        <i class="fas fa-history w-5"></i>
                        <span>Leave History</span>
                    </a>
                    
                    <a href="leave_credits.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                        <i class="fas fa-calculator w-5"></i>
                        <span>Leave Credits</span>
                    </a>
                 
                </div>
                
                <div class="space-y-1">
                    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2">Reports</h3>
                    
                    <a href="calendar.php" class="flex items-center space-x-3 px-4 py-3 text-white bg-blue-500/20 rounded-lg border border-blue-500/30">
                        <i class="fas fa-calendar w-5"></i>
                        <span>Leave Chart</span>
                    </a>
                </div>
                
                <div class="space-y-1">
                    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2">Account</h3>
                    
                    <a href="profile.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                        <i class="fas fa-user w-5"></i>
                        <span>Profile</span>
                    </a>
                </div>
                
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 ml-64 pt-24 px-6 pb-6">
            <div class="max-w-7xl mx-auto">
                <!-- Page Header -->
                <div class="mb-8">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-gradient-to-r from-primary to-accent rounded-2xl flex items-center justify-center">
                            <i class="fas fa-calendar-alt text-2xl text-white"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-white mb-2">My Leave Calendar</h1>
                            <p class="text-slate-400">View your personal leave requests and schedule</p>
                        </div>
                    </div>
                </div>

                <!-- Calendar Container -->
                <div class="bg-slate-800 rounded-2xl border border-slate-700/50 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-700/50 bg-slate-700/30">
                        <h3 class="text-xl font-semibold text-white flex items-center">
                            <i class="fas fa-chart-line text-primary mr-3"></i>Leave Chart
                        </h3>
                    </div>
                    <div class="p-6">
                        <!-- Leave Type Legend -->
                        <div class="mb-6">
                            <h4 class="text-lg font-semibold text-white mb-4">Leave Type Legend</h4>
                            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                                <div class="flex items-center space-x-2">
                                    <div class="w-4 h-4 rounded leave-vacation"></div>
                                    <span class="text-sm text-slate-300">Vacation</span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <div class="w-4 h-4 rounded leave-special_privilege"></div>
                                    <span class="text-sm text-slate-300">Special Privilege</span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <div class="w-4 h-4 rounded leave-sick"></div>
                                    <span class="text-sm text-slate-300">Sick Leave</span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <div class="w-4 h-4 rounded leave-maternity"></div>
                                    <span class="text-sm text-slate-300">Maternity</span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <div class="w-4 h-4 rounded leave-paternity"></div>
                                    <span class="text-sm text-slate-300">Paternity</span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <div class="w-4 h-4 rounded leave-study"></div>
                                    <span class="text-sm text-slate-300">Study Leave</span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <div class="w-4 h-4 rounded leave-solo_parent"></div>
                                    <span class="text-sm text-slate-300">Solo Parent</span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <div class="w-4 h-4 rounded leave-vawc"></div>
                                    <span class="text-sm text-slate-300">VAWC Leave</span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <div class="w-4 h-4 rounded leave-rehabilitation"></div>
                                    <span class="text-sm text-slate-300">Rehabilitation</span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <div class="w-4 h-4 rounded leave-special_women"></div>
                                    <span class="text-sm text-slate-300">Special Women</span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <div class="w-4 h-4 rounded leave-special_emergency"></div>
                                    <span class="text-sm text-slate-300">Special Emergency</span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <div class="w-4 h-4 rounded leave-adoption"></div>
                                    <span class="text-sm text-slate-300">Adoption</span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <div class="w-4 h-4 rounded leave-mandatory"></div>
                                    <span class="text-sm text-slate-300">Mandatory Leave</span>
                                </div>
                            </div>
                        </div>
                        
                        <div id="calendar"></div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../../../../assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src='../../../../assets/libs/fullcalendar/js/main.min.js'></script>
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
                            days: '<?php echo (strtotime($request['end_date']) - strtotime($request['start_date'])) / (60 * 60 * 24) + 1; ?>',
                            isOwn: true
                        }
                    },
                    <?php endforeach; ?>
                ],
                eventClick: function(info) {
                    var event = info.event;
                    var props = event.extendedProps;
                    
                    const modalContent = `
                        <div class="modal fade" id="leaveDetailsModal" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content bg-slate-800 border-slate-700">
                                    <div class="modal-header border-slate-700">
                                        <h5 class="modal-title text-white">
                                            <i class="fas fa-calendar-alt me-2"></i>Leave Details
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <p class="mb-2"><strong class="text-slate-300">Employee:</strong> <span class="text-white">${props.employee}</span></p>
                                                <p class="mb-2"><strong class="text-slate-300">Leave Type:</strong> <span class="text-white">${props.leaveType.replace('_', ' ').toUpperCase()}</span></p>
                                                <p class="mb-2"><strong class="text-slate-300">Duration:</strong> <span class="text-white">${props.days} day(s)</span></p>
                                            </div>
                                            <div class="col-md-6">
                                                <p class="mb-2"><strong class="text-slate-300">Start Date:</strong> <span class="text-white">${event.start.toLocaleDateString()}</span></p>
                                                <p class="mb-2"><strong class="text-slate-300">End Date:</strong> <span class="text-white">${event.end ? event.end.toLocaleDateString() : 'N/A'}</span></p>
                                                <p class="mb-2"><strong class="text-slate-300">Status:</strong> <span class="badge bg-${props.status === 'approved' ? 'success' : props.status === 'pending' ? 'warning' : 'danger'}">${props.status.toUpperCase()}</span></p>
                                            </div>
                                        </div>
                                        <div class="row mt-3">
                                            <div class="col-12">
                                                <p class="mb-2"><strong class="text-slate-300">Reason:</strong></p>
                                                <p class="text-white">${props.reason || 'No reason provided'}</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer border-slate-700">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        ${props.isOwn && props.status === 'pending' ? `
                                            <a href="submit_leave.php?edit=${event.id}" class="btn btn-warning">
                                                <i class="fas fa-edit me-2"></i>Edit Request
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
        });
    </script>
</body>
</html>