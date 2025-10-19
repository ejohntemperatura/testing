<?php
// Shared Calendar Component
// This file provides calendar functionality for all roles
// Used by: admin, director, department heads, employees

// Session already started by including file
require_once '../../../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/index.php');
    exit();
}

$role = $_SESSION['role'] ?? 'employee';

// Get APPROVED leave requests only - with proper approved days
    $stmt = $pdo->prepare("
        SELECT 
            lr.*, 
            e.name as employee_name, 
            e.position, 
            e.department,
            CASE 
                WHEN lr.approved_days IS NOT NULL AND lr.approved_days > 0 
                THEN lr.approved_days
                ELSE DATEDIFF(lr.end_date, lr.start_date) + 1 
            END as actual_days_approved
        FROM leave_requests lr 
        JOIN employees e ON lr.employee_id = e.id 
        WHERE lr.status = 'approved'
        ORDER BY lr.start_date ASC
    ");
    $stmt->execute();

$leave_requests = $stmt->fetchAll();
?>

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
                title: '<?php echo addslashes($request['employee_name']); ?> - <?php echo ucfirst(str_replace('_', ' ', $request['leave_type'])); ?> (<?php echo $request['actual_days_approved']; ?> day<?php echo $request['actual_days_approved'] != 1 ? 's' : ''; ?>)',
                start: '<?php echo $request['start_date']; ?>',
                end: '<?php echo date('Y-m-d', strtotime($request['start_date'] . ' +' . $request['actual_days_approved'] . ' days')); ?>',
                className: 'leave-<?php echo $request['leave_type']; ?>',
                extendedProps: {
                    leave_type: '<?php echo $request['leave_type']; ?>',
                    employee_name: '<?php echo addslashes($request['employee_name']); ?>',
                    department: '<?php echo addslashes($request['department']); ?>',
                    position: '<?php echo addslashes($request['position']); ?>',
                    days_approved: <?php echo $request['actual_days_approved']; ?>,
                    pay_status: '<?php echo $request['pay_status'] ?? 'N/A'; ?>'
                }
            },
            <?php endforeach; ?>
        ],
        eventClick: function(info) {
            const props = info.event.extendedProps;
            const message = `
Leave Details:
Employee: ${props.employee_name}
Department: ${props.department}
Position: ${props.position}
Leave Type: ${props.leave_type.replace('_', ' ')}
Days Approved: ${props.days_approved}
Pay Status: ${props.pay_status}
Date: ${info.event.start.toLocaleDateString()}
            `;
            alert(message);
        }
    });
    
    calendar.render();
});
</script>
