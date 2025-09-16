<?php
session_start();
require_once '../config/database.php';

// Strict access: Director only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'director') {
    header('Location: ../auth/index.php');
    exit();
}

// Get director info
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$me = $stmt->fetch();

// Get all leave requests (director can see all)
$stmt = $pdo->prepare("
    SELECT lr.*, e.name as employee_name, e.position, e.department
    FROM leave_requests lr 
    JOIN employees e ON lr.employee_id = e.id 
    ORDER BY lr.start_date ASC
");
$stmt->execute();
$leave_requests = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ELMS - Director Leave Calendar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    <link href="../assets/css/style.css" rel="stylesheet">
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
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h4><i class="fas fa-user-tie me-2"></i>Director</h4>
        </div>
        <div class="sidebar-menu">
            <a href="director_head_dashboard.php">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="view_chart.php" class="active">
                <i class="fas fa-calendar"></i>
                <span>Calendar View</span>
            </a>
            <a href="reports.php">
                <i class="fas fa-file-alt"></i>
                <span>Reports</span>
            </a>
            <a href="audit_logs.php">
                <i class="fas fa-history"></i>
                <span>Audit Logs</span>
            </a>
            <a href="../auth/logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <!-- Calendar Header -->
            <div class="calendar-header">
                <h2><i class="fas fa-calendar-alt me-2"></i>Organization Leave Calendar</h2>
                <p>View and manage all leave requests across the organization</p>
                
                <!-- Department Filter -->
                <div class="department-filter">
                    <label for="departmentFilter" class="form-label">Filter by Department:</label>
                    <select id="departmentFilter" class="form-select">
                        <option value="">All Departments</option>
                        <?php
                        $departments = array_unique(array_column($leave_requests, 'department'));
                        foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Legend -->
                <div class="legend">
                    <div class="legend-item">
                        <div class="legend-color leave-annual"></div>
                        <span>Annual Leave</span>
                    </div>
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
                        <div class="legend-color leave-unpaid"></div>
                        <span>Unpaid Leave</span>
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
            
            // Department filter functionality
            document.getElementById('departmentFilter').addEventListener('change', function() {
                var selectedDept = this.value;
                var events = calendar.getEvents();
                
                events.forEach(function(event) {
                    if (selectedDept === '' || event.extendedProps.department === selectedDept) {
                        event.setProp('display', 'block');
                    } else {
                        event.setProp('display', 'none');
                    }
                });
            });
        });
    </script>
</body>
</html>
