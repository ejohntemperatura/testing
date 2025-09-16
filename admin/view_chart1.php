<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/index.php');
    exit();
}

// Define leave type colors
$leaveTypeColors = [
    'Sick Leave' => '#007bff',
    'Vacation Leave' => '#17a2b8',
    'Emergency Leave' => '#fd7e14',
    'Maternity Leave' => '#6f42c1',
    // Add more leave types and colors as needed
];

// Fetch all leave requests
$stmt = $pdo->prepare("
    SELECT lr.*, e.name as employee_name 
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
    <title>ELMS - Leave Calendar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        body {
            background: #f5f6fa;
        }
        .main-content {
            margin-left: 250px;
            padding: 40px 0 40px 0;
            background: #f5f6fa;
            min-height: 100vh;
        }
        .calendar-card {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 4px 24px rgba(44, 62, 80, 0.08);
            padding: 32px 28px 28px 28px;
            margin: 0 auto;
            max-width: 100vw;
            width: 100%;
        }
        .calendar-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 18px;
        }
        .calendar-header h2 {
            font-size: 2.1rem;
            font-weight: 700;
            color: #2c3e50;
            margin: 0;
        }
        .legend {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            margin-bottom: 18px;
            margin-top: 8px;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1rem;
            background: #f8f9fa;
            border-radius: 20px;
            padding: 4px 14px 4px 8px;
            box-shadow: 0 1px 4px rgba(44,62,80,0.04);
        }
        .legend-color {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: inline-block;
            border: 2px solid #e0e0e0;
        }
        .calendar-container {
            background: #f8f9fa;
            border-radius: 14px;
            box-shadow: 0 2px 10px rgba(44,62,80,0.06);
            padding: 18px 10px 10px 10px;
            width: 100%;
            min-height: 70vh;
            display: flex;
            flex-direction: column;
            justify-content: stretch;
        }
        #calendar {
            background: #fff;
            border-radius: 10px;
            padding: 10px;
            width: 100%;
            min-height: 65vh;
        }
        @media (max-width: 1100px) {
            .calendar-card {
                max-width: 99vw;
                padding: 18px 4vw 18px 4vw;
            }
        }
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px 0 20px 0;
            }
            .calendar-card {
                padding: 10px 2vw 10px 2vw;
            }
            .calendar-header h2 {
                font-size: 1.3rem;
            }
            .calendar-container {
                min-height: 50vh;
            }
            #calendar {
                min-height: 45vh;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h4><i class="fas fa-user-shield me-2"></i>Admin Panel</h4>
        </div>
        <div class="sidebar-menu">
            <a href="admin_dashboard.php" class="active">
                <i class="fas fa-tachometer-alt"></i>Dashboard
            </a>
            <a href="manage_user.php">
                <i class="fas fa-users-cog"></i>Manage User
            </a>
            
            <a href="leave_management.php">
                <i class="fas fa-calendar-check"></i>Leave Management
            </a>
            <a href="view_chart1.php">
                <i class="fas fa-chart-bar"></i>View Chart
            </a>
            <a href="department_management.php">
                <i class="fas fa-building"></i>Departments
            </a>
            <a href="leave_policies.php">
                <i class="fas fa-book"></i>Leave Policies
            </a>
            <a href="reports.php">
                <i class="fas fa-chart-bar"></i>Reports
            </a>
            <a href="system_settings.php">
                <i class="fas fa-cogs"></i>System Settings
            </a>
            <a href="audit_logs.php">
                <i class="fas fa-history"></i>Audit Logs
            </a>
                            <a href="../auth/logout.php">
                    <i class="fas fa-sign-out-alt"></i>Logout
                </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="calendar-card">
                <div class="calendar-header">
                    <i class="fas fa-calendar fa-2x text-primary"></i>
                    <h2>Leave Calendar</h2>
                </div>
                <!-- Legend -->
                <div class="legend">
                    <div class="legend-item">
                        <div class="legend-color" style="background-color: #28a745;"></div>
                        <span>Approved</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background-color: #ffc107;"></div>
                        <span>Pending</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background-color: #dc3545;"></div>
                        <span>Rejected</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background-color: #007bff;"></div>
                        <span>Sick Leave</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background-color: #17a2b8;"></div>
                        <span>Vacation Leave</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background-color: #fd7e14;"></div>
                        <span>Emergency Leave</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background-color: #6f42c1;"></div>
                        <span>Maternity Leave</span>
                    </div>
                </div>
                <!-- Calendar -->
                <div class="calendar-container">
                    <div id="calendar"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const calendarEl = document.getElementById('calendar');
            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                height: 'auto',
                contentHeight: 'auto',
                aspectRatio: 2.2,
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                events: [
                    <?php foreach ($leave_requests as $request):
                        $color = isset($leaveTypeColors[$request['leave_type']])
                            ? $leaveTypeColors[$request['leave_type']]
                            : ($request['status'] == 'approved' ? '#28a745' : ($request['status'] == 'pending' ? '#ffc107' : '#dc3545'));
                    ?>
                    {
                        title: '<?php echo addslashes($request['employee_name']); ?> - <?php echo addslashes($request['leave_type']); ?>',
                        start: '<?php echo $request['start_date']; ?>',
                        end: '<?php echo $request['end_date']; ?>',
                        backgroundColor: '<?php echo $color; ?>',
                        borderColor: '<?php echo $color; ?>',
                        textColor: '#fff',
                        extendedProps: {
                            reason: '<?php echo addslashes($request['reason']); ?>',
                            status: '<?php echo $request['status']; ?>',
                            leave_type: '<?php echo addslashes($request['leave_type']); ?>'
                        }
                    },
                    <?php endforeach; ?>
                ],
                eventClick: function(info) {
                    const status = info.event.extendedProps.status;
                    const reason = info.event.extendedProps.reason;
                    const leaveType = info.event.extendedProps.leave_type;
                    alert(`Type: ${leaveType}\nStatus: ${status.charAt(0).toUpperCase() + status.slice(1)}\nReason: ${reason}`);
                }
            });
            calendar.render();
        });
    </script>
</body>
</html> 