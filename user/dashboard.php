<?php
session_start();
require_once '../config/database.php';

// Auto-process emails when internet is available
require_once '../includes/auto_email_processor.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/index.php');
    exit();
}

// Get user information
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$employee = $stmt->fetch();

// Check if employee exists
if (!$employee) {
    // Clear invalid session and redirect to login
    session_destroy();
    $_SESSION['error'] = 'Your session has expired or is invalid. Please log in again.';
    header('Location: ../auth/index.php');
    exit();
}

// Get today's DTR record
$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT * FROM dtr WHERE user_id = ? AND date = ?");
$stmt->execute([$_SESSION['user_id'], $today]);
$today_record = $stmt->fetch();

// Handle time out only
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $current_time = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $formatted_time = $current_time->format('Y-m-d H:i:s');
    $current_hour = (int)$current_time->format('H');

    if ($_POST['action'] === 'time_out') {
        if ($today_record && $today_record['morning_time_in'] && !$today_record['morning_time_out']) {
            // Morning time out
            $stmt = $pdo->prepare("UPDATE dtr SET morning_time_out = ? WHERE user_id = ? AND date = CURDATE()");
            if ($stmt->execute([$formatted_time, $_SESSION['user_id']])) {
                $_SESSION['message'] = "Time Out recorded successfully at " . $current_time->format('h:i A');
                unset($_SESSION['logged_in_this_session']); // Clear session flag after time out
            }
        } else if ($today_record && $today_record['afternoon_time_in'] && !$today_record['afternoon_time_out']) {
            // Afternoon time out
            $stmt = $pdo->prepare("UPDATE dtr SET afternoon_time_out = ? WHERE user_id = ? AND date = CURDATE()");
            if ($stmt->execute([$formatted_time, $_SESSION['user_id']])) {
                $_SESSION['message'] = "Afternoon Time Out recorded successfully at " . $current_time->format('h:i A');
                unset($_SESSION['logged_in_this_session']); // Clear session flag after time out
            }
        } else {
            $_SESSION['error'] = "Invalid time out request. You need to time in first from the DTR page.";
        }
    }
    header('Location: dashboard.php'); // Redirect back to dashboard to refresh status
    exit();
}

// Fetch user's leave requests
$stmt = $pdo->prepare("SELECT * FROM leave_requests WHERE employee_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$leave_requests = $stmt->fetchAll();

// Fetch recent leave alerts
$stmt = $pdo->prepare("
    SELECT la.*, e.name as sent_by_name 
    FROM leave_alerts la 
    LEFT JOIN employees e ON la.sent_by = e.id 
    WHERE la.employee_id = ? 
    ORDER BY la.created_at DESC 
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$leave_alerts = $stmt->fetchAll();

// Count unread alerts
$stmt = $pdo->prepare("
    SELECT COUNT(*) as unread_count
    FROM leave_alerts 
    WHERE employee_id = ? 
    AND is_read = 0
");
$stmt->execute([$_SESSION['user_id']]);
$unread_alert_count = $stmt->fetch()['unread_count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ELMS - User Dashboard</title>
    <link rel="stylesheet" href="../assets/css/tailwind.css">
    <link rel="stylesheet" href="../assets/libs/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dark-theme.css">
    <script src="../assets/libs/chartjs/chart.umd.min.js"></script>
</head>
<body class="bg-slate-900 text-white min-h-screen">
    <?php include '../includes/unified_navbar.php'; ?>

    <!-- Apply Leave Modal -->
    <div id="applyLeaveModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="px-6 py-4 border-b border-slate-700/50 bg-slate-700/30">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-semibold text-white flex items-center">
                        <i class="fas fa-calendar-plus mr-3 text-blue-500"></i>
                        Apply for Leave
                    </h3>
                    <button onclick="closeApplyLeaveModal()" class="text-slate-400 hover:text-white transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>
            <div class="p-6">
                <form id="applyLeaveForm" method="POST" action="submit_leave.php" class="space-y-6">
                    <!-- Employee Information -->
                    <div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600/50">
                        <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                            <i class="fas fa-user-circle text-blue-500 mr-3"></i>
                            Employee Information
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-slate-300 mb-2">Employee Name</label>
                                <input type="text" value="<?php echo htmlspecialchars($employee['name']); ?>" readonly class="w-full bg-slate-600 border border-slate-600 rounded-lg px-3 py-2 text-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-300 mb-2">Position</label>
                                <input type="text" value="<?php echo htmlspecialchars($employee['position']); ?>" readonly class="w-full bg-slate-600 border border-slate-600 rounded-lg px-3 py-2 text-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-300 mb-2">Department</label>
                                <input type="text" value="<?php echo htmlspecialchars($employee['department'] ?? 'N/A'); ?>" readonly class="w-full bg-slate-600 border border-slate-600 rounded-lg px-3 py-2 text-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-300 mb-2">Date of Filing</label>
                                <input type="text" value="<?php echo date('F j, Y'); ?>" readonly class="w-full bg-slate-600 border border-slate-600 rounded-lg px-3 py-2 text-slate-300 text-sm">
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="modal_leave_type" class="block text-sm font-semibold text-slate-300 mb-2">
                                <i class="fas fa-calendar-check mr-2"></i>Leave Type
                            </label>
                            <select id="modal_leave_type" name="leave_type" required class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" onchange="toggleModalConditionalFields()">
                                <option value="">Select Leave Type</option>
                                <option value="vacation">Vacation Leave</option>
                                <option value="special_privilege">Special Leave Privilege</option>
                                <option value="sick">Sick Leave</option>
                                <option value="study">Study Leave</option>
                                <option value="other">Other Purpose</option>
                                <option value="solo_parent">Solo Parent Leave</option>
                                <option value="vawc">10-Day VAWC Leave</option>
                                <option value="rehabilitation">Rehabilitation Privilege</option>
                                <option value="special_women">Special Leave Benefits for Women</option>
                                <option value="special_emergency">Special Emergency Leave (Calamity)</option>
                                <option value="adoption">Adoption Leave</option>
                            </select>
                        </div>
                        <div>
                            <label for="modal_start_date" class="block text-sm font-semibold text-slate-300 mb-2">
                                <i class="fas fa-calendar-day mr-2"></i>Start Date
                            </label>
                            <input type="date" id="modal_start_date" name="start_date" required class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="modal_end_date" class="block text-sm font-semibold text-slate-300 mb-2">
                                <i class="fas fa-calendar-day mr-2"></i>End Date
                            </label>
                            <input type="date" id="modal_end_date" name="end_date" required class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-300 mb-2">
                                <i class="fas fa-calculator mr-2"></i>Total Days
                            </label>
                            <input type="text" id="modal_total_days" readonly class="w-full bg-slate-600 border border-slate-600 rounded-xl px-4 py-3 text-slate-400">
                        </div>
                    </div>
                    
                    <!-- Conditional Fields for Apply Leave Modal -->
                    <div id="modalConditionalFields" class="hidden">
                        <!-- Vacation Leave Fields -->
                        <div id="modalVacationFields" class="hidden bg-slate-700/30 rounded-xl p-6 border border-slate-600/50">
                            <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                                <i class="fas fa-map-marker-alt text-blue-500 mr-3"></i>
                                Vacation Location Details
                            </h4>
                            <div class="space-y-4">
                                <div>
                                    <label for="modal_vacation_location" class="block text-sm font-semibold text-slate-300 mb-2">
                                        <i class="fas fa-globe mr-2"></i>Location Type
                                    </label>
                                    <select id="modal_vacation_location" name="location_type" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                        <option value="">Select location type</option>
                                        <option value="within_philippines">Within Philippines</option>
                                        <option value="outside_philippines">Outside Philippines</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="modal_vacation_address" class="block text-sm font-semibold text-slate-300 mb-2">
                                        <i class="fas fa-map-pin mr-2"></i>Specific Address
                                    </label>
                                    <input type="text" id="modal_vacation_address" name="location_specify" placeholder="Enter the specific address where you will spend your vacation..." class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>
                            </div>
                        </div>

                        <!-- Sick Leave Fields -->
                        <div id="modalSickFields" class="hidden bg-slate-700/30 rounded-xl p-6 border border-slate-600/50">
                            <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                                <i class="fas fa-user-md text-blue-500 mr-3"></i>
                                Medical Information
                            </h4>
                            <div class="space-y-4">
                                <div>
                                    <label for="modal_medical_certificate" class="block text-sm font-semibold text-slate-300 mb-2">
                                        <i class="fas fa-file-medical mr-2"></i>Medical Condition
                                    </label>
                                    <select id="modal_medical_certificate" name="medical_condition" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                        <option value="">Select condition</option>
                                        <option value="in_hospital">In hospital (specify illness)</option>
                                        <option value="out_patient">Out patient (Specify illness)</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="modal_illness_description" class="block text-sm font-semibold text-slate-300 mb-2">
                                        <i class="fas fa-stethoscope mr-2"></i>Specify Illness
                                    </label>
                                    <input type="text" id="modal_illness_description" name="illness_specify" placeholder="Specify your illness or medical condition..." class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>
                            </div>
                        </div>

                        <!-- Special Leave Benefits for Women Fields -->
                        <div id="modalSpecialWomenFields" class="hidden bg-slate-700/30 rounded-xl p-6 border border-slate-600/50">
                            <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                                <i class="fas fa-female text-blue-500 mr-3"></i>
                                Special Leave Benefits for Women
                            </h4>
                            <div class="space-y-4">
                                <div>
                                    <label for="modal_special_women_condition" class="block text-sm font-semibold text-slate-300 mb-2">
                                        <i class="fas fa-stethoscope mr-2"></i>Specify Illness
                                    </label>
                                    <input type="text" id="modal_special_women_condition" name="illness_specify" placeholder="Specify your illness..." class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>
                            </div>
                        </div>

                        <!-- Study Leave Fields -->
                        <div id="modalStudyFields" class="hidden bg-slate-700/30 rounded-xl p-6 border border-slate-600/50">
                            <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                                <i class="fas fa-graduation-cap text-blue-500 mr-3"></i>
                                Study Information
                            </h4>
                            <div class="space-y-4">
                                <div>
                                    <label for="modal_course_program" class="block text-sm font-semibold text-slate-300 mb-2">
                                        <i class="fas fa-book mr-2"></i>Course/Program Type
                                    </label>
                                    <select id="modal_course_program" name="study_type" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                        <option value="">Select study type</option>
                                        <option value="masters_degree">Master's degree</option>
                                        <option value="bar_board">BAR/Board Examination Review</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label for="modal_reason" class="block text-sm font-semibold text-slate-300 mb-2">
                            <i class="fas fa-comment-alt mr-2"></i>Reason for Leave
                        </label>
                        <textarea id="modal_reason" name="reason" rows="4" placeholder="Please provide a detailed reason for your leave request..." required class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                    </div>
                    
                    <div class="flex gap-4 justify-end pt-6">
                        <button type="button" onclick="closeApplyLeaveModal()" class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-3 px-6 rounded-xl transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-300 transform hover:scale-[1.02] hover:shadow-xl">
                            <i class="fas fa-paper-plane mr-2"></i>Submit Leave Application
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Late Application Modal -->
    <div id="lateApplicationModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="px-6 py-4 border-b border-slate-700/50 bg-slate-700/30">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-semibold text-white flex items-center">
                        <i class="fas fa-exclamation-triangle mr-3 text-gray-500"></i>
                        Late Leave Application
                    </h3>
                    <button onclick="closeLateApplicationModal()" class="text-slate-400 hover:text-white transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>
            <div class="p-6">
                <form id="lateApplicationForm" method="POST" action="late_leave_application.php" class="space-y-6">
                    <!-- Employee Information -->
                    <div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600/50">
                        <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                            <i class="fas fa-user-circle text-gray-500 mr-3"></i>
                            Employee Information
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-slate-300 mb-2">Employee Name</label>
                                <input type="text" value="<?php echo htmlspecialchars($employee['name']); ?>" readonly class="w-full bg-slate-600 border border-slate-600 rounded-lg px-3 py-2 text-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-300 mb-2">Position</label>
                                <input type="text" value="<?php echo htmlspecialchars($employee['position']); ?>" readonly class="w-full bg-slate-600 border border-slate-600 rounded-lg px-3 py-2 text-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-300 mb-2">Department</label>
                                <input type="text" value="<?php echo htmlspecialchars($employee['department'] ?? 'N/A'); ?>" readonly class="w-full bg-slate-600 border border-slate-600 rounded-lg px-3 py-2 text-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-300 mb-2">Date of Filing</label>
                                <input type="text" value="<?php echo date('F j, Y'); ?>" readonly class="w-full bg-slate-600 border border-slate-600 rounded-lg px-3 py-2 text-slate-300 text-sm">
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="modal_late_leave_type" class="block text-sm font-semibold text-slate-300 mb-2">
                                <i class="fas fa-calendar-check mr-2"></i>Leave Type
                            </label>
                            <select id="modal_late_leave_type" name="leave_type" required class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-gray-500 focus:border-transparent" onchange="toggleModalLateConditionalFields()">
                                <option value="">Select Leave Type</option>
                                <option value="vacation">Vacation Leave</option>
                                <option value="special_privilege">Special Leave Privilege</option>
                                <option value="sick">Sick Leave</option>
                                <option value="study">Study Leave</option>
                                <option value="other">Other Purpose</option>
                                <option value="solo_parent">Solo Parent Leave</option>
                                <option value="vawc">10-Day VAWC Leave</option>
                                <option value="rehabilitation">Rehabilitation Privilege</option>
                                <option value="special_women">Special Leave Benefits for Women</option>
                                <option value="special_emergency">Special Emergency Leave (Calamity)</option>
                                <option value="adoption">Adoption Leave</option>
                            </select>
                        </div>
                        <div>
                            <label for="modal_late_start_date" class="block text-sm font-semibold text-slate-300 mb-2">
                                <i class="fas fa-calendar-day mr-2"></i>Start Date
                            </label>
                            <input type="date" id="modal_late_start_date" name="start_date" required class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-gray-500 focus:border-transparent">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="modal_late_end_date" class="block text-sm font-semibold text-slate-300 mb-2">
                                <i class="fas fa-calendar-day mr-2"></i>End Date
                            </label>
                            <input type="date" id="modal_late_end_date" name="end_date" required class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-gray-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-300 mb-2">
                                <i class="fas fa-calculator mr-2"></i>Total Days
                            </label>
                            <input type="text" id="modal_late_total_days" readonly class="w-full bg-slate-600 border border-slate-600 rounded-xl px-4 py-3 text-slate-400">
                        </div>
                    </div>
                    
                    <div>
                        <label for="modal_late_reason" class="block text-sm font-semibold text-slate-300 mb-2">
                            <i class="fas fa-comment-alt mr-2"></i>Reason for Leave
                        </label>
                        <textarea id="modal_late_reason" name="reason" rows="4" placeholder="Please provide a detailed reason for your leave request..." required class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:border-transparent"></textarea>
                    </div>
                    
                    <!-- Conditional Fields for Late Application Modal -->
                    <div id="modalLateConditionalFields" class="hidden">
                        <!-- Vacation Leave Fields -->
                        <div id="modalLateVacationFields" class="hidden bg-slate-700/30 rounded-xl p-6 border border-slate-600/50">
                            <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                                <i class="fas fa-map-marker-alt text-gray-500 mr-3"></i>
                                Vacation Location Details
                            </h4>
                            <div class="space-y-4">
                                <div>
                                    <label for="modal_late_vacation_location" class="block text-sm font-semibold text-slate-300 mb-2">
                                        <i class="fas fa-globe mr-2"></i>Location Type
                                    </label>
                                    <select id="modal_late_vacation_location" name="location_type" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-gray-500 focus:border-transparent">
                                        <option value="">Select location type</option>
                                        <option value="within_philippines">Within Philippines</option>
                                        <option value="outside_philippines">Outside Philippines</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="modal_late_vacation_address" class="block text-sm font-semibold text-slate-300 mb-2">
                                        <i class="fas fa-map-pin mr-2"></i>Specific Address
                                    </label>
                                    <input type="text" id="modal_late_vacation_address" name="location_specify" placeholder="Enter the specific address where you will spend your vacation..." class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:border-transparent">
                                </div>
                            </div>
                        </div>

                        <!-- Sick Leave Fields -->
                        <div id="modalLateSickFields" class="hidden bg-slate-700/30 rounded-xl p-6 border border-slate-600/50">
                            <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                                <i class="fas fa-user-md text-gray-500 mr-3"></i>
                                Medical Information
                            </h4>
                            <div class="space-y-4">
                                <div>
                                    <label for="modal_late_medical_certificate" class="block text-sm font-semibold text-slate-300 mb-2">
                                        <i class="fas fa-file-medical mr-2"></i>Medical Condition
                                    </label>
                                    <select id="modal_late_medical_certificate" name="medical_condition" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-gray-500 focus:border-transparent">
                                        <option value="">Select condition</option>
                                        <option value="in_hospital">In hospital (specify illness)</option>
                                        <option value="out_patient">Out patient (Specify illness)</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="modal_late_illness_description" class="block text-sm font-semibold text-slate-300 mb-2">
                                        <i class="fas fa-stethoscope mr-2"></i>Specify Illness
                                    </label>
                                    <input type="text" id="modal_late_illness_description" name="illness_specify" placeholder="Specify your illness or medical condition..." class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:border-transparent">
                                </div>
                            </div>
                        </div>

                        <!-- Special Leave Benefits for Women Fields -->
                        <div id="modalLateSpecialWomenFields" class="hidden bg-slate-700/30 rounded-xl p-6 border border-slate-600/50">
                            <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                                <i class="fas fa-female text-gray-500 mr-3"></i>
                                Special Leave Benefits for Women
                            </h4>
                            <div class="space-y-4">
                                <div>
                                    <label for="modal_late_special_women_condition" class="block text-sm font-semibold text-slate-300 mb-2">
                                        <i class="fas fa-stethoscope mr-2"></i>Specify Illness
                                    </label>
                                    <input type="text" id="modal_late_special_women_condition" name="illness_specify" placeholder="Specify your illness..." class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:border-transparent">
                                </div>
                            </div>
                        </div>

                        <!-- Study Leave Fields -->
                        <div id="modalLateStudyFields" class="hidden bg-slate-700/30 rounded-xl p-6 border border-slate-600/50">
                            <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                                <i class="fas fa-graduation-cap text-gray-500 mr-3"></i>
                                Study Information
                            </h4>
                            <div class="space-y-4">
                                <div>
                                    <label for="modal_late_course_program" class="block text-sm font-semibold text-slate-300 mb-2">
                                        <i class="fas fa-book mr-2"></i>Course/Program Type
                                    </label>
                                    <select id="modal_late_course_program" name="study_type" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-gray-500 focus:border-transparent">
                                        <option value="">Select study type</option>
                                        <option value="masters_degree">Master's degree</option>
                                        <option value="bar_board">BAR/Board Examination Review</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label for="modal_late_justification" class="block text-sm font-semibold text-slate-300 mb-2">
                            <i class="fas fa-exclamation-triangle mr-2"></i>Late Justification
                        </label>
                        <textarea id="modal_late_justification" name="late_justification" rows="4" placeholder="Please explain why you are submitting this leave application late..." required class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:border-transparent"></textarea>
                    </div>
                    
                    <div class="flex gap-4 justify-end pt-6">
                        <button type="button" onclick="closeLateApplicationModal()" class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-3 px-6 rounded-xl transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-300 transform hover:scale-[1.02] hover:shadow-xl">
                            <i class="fas fa-paper-plane mr-2"></i>Submit Late Application
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="flex">
        <!-- Left Sidebar -->
        <aside id="sidebar" class="fixed left-0 top-16 h-[calc(100vh-4rem)] w-64 bg-slate-900 border-r border-slate-800 overflow-y-auto z-40">
            <nav class="p-4 space-y-2">
                <!-- Active Navigation Item -->
                <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 text-white bg-blue-500/20 rounded-lg border border-blue-500/30">
                    <i class="fas fa-tachometer-alt w-5"></i>
                    <span>Dashboard</span>
                </a>
                
                <div class="space-y-1">
                    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2">Leave Management</h3>
                    <a href="leave_history.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700/50 rounded-lg transition-colors">
                        <i class="fas fa-history w-5"></i>
                        <span>Leave History</span>
                    </a>
                    <a href="leave_credits.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700/50 rounded-lg transition-colors">
                        <i class="fas fa-calculator w-5"></i>
                        <span>Leave Credits</span>
                    </a>
                </div>
                
                <div class="space-y-1">
                    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2">Reports</h3>
                    <a href="view_chart.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700/50 rounded-lg transition-colors">
                        <i class="fas fa-calendar-alt w-5"></i>
                        <span>Leave Chart</span>
                    </a>
                </div>
                
                <div class="space-y-1">
                    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2">Account</h3>
                    <a href="profile.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700/50 rounded-lg transition-colors">
                        <i class="fas fa-user w-5"></i>
                        <span>Profile</span>
                    </a>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 ml-64 pt-24 px-6 pb-6">
            <div class="max-w-7xl mx-auto">
                <!-- Welcome Section -->
                <div class="mb-10 mt-16">
                    <div class="flex items-start justify-between">
                        <div>
                            <h1 class="text-4xl font-bold text-white mb-2">Welcome back, <?php echo htmlspecialchars($employee['name']); ?>!</h1>
                            <p class="text-slate-400 text-lg">Here's what's happening with your leave requests today.</p>
                        </div>
                        <div class="text-right">
                            <div class="text-slate-400 text-sm">Today is</div>
                            <div class="text-white text-lg font-semibold"><?php echo date('l, F j, Y'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Messages -->
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="mb-6 p-4 bg-green-500/20 border border-green-500/30 rounded-lg">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-400 mr-3"></i>
                            <span class="text-green-400"><?php echo $_SESSION['message']; ?></span>
                        </div>
                    </div>
                    <?php
                    unset($_SESSION['message']);
                    ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="mb-6 p-4 bg-red-500/20 border border-red-500/30 rounded-lg">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle text-red-400 mr-3"></i>
                            <span class="text-red-400"><?php echo $_SESSION['error']; ?></span>
                        </div>
                    </div>
                    <?php
                    unset($_SESSION['error']);
                    ?>
                <?php endif; ?>

                <!-- Quick Actions -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <button onclick="openApplyLeaveModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-6 px-6 rounded-2xl transition-all duration-300 transform hover:scale-[1.02] hover:shadow-xl text-center">
                        <i class="fas fa-calendar-plus text-2xl mb-3 block"></i>
                        <h3 class="text-lg font-semibold mb-2">Apply for Leave</h3>
                        <p class="text-sm opacity-90">Submit a new leave request</p>
                    </button>

                    <button onclick="openLateApplicationModal()" class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-6 px-6 rounded-2xl transition-all duration-300 transform hover:scale-[1.02] hover:shadow-xl text-center">
                        <i class="fas fa-exclamation-triangle text-2xl mb-3 block"></i>
                        <h3 class="text-lg font-semibold mb-2">Late Application</h3>
                        <p class="text-sm opacity-90">Submit late leave request</p>
                    </button>

                    <a href="leave_history.php" class="bg-slate-800/50 backdrop-blur-sm/50 hover:bg-slate-700/50 backdrop-blur-sm border border-slate-700/50 hover:border-slate-600/50 text-white font-semibold py-6 px-6 rounded-2xl transition-all duration-300 transform hover:scale-[1.02] hover:shadow-xl text-center">
                        <i class="fas fa-history text-2xl mb-3 block text-blue-500"></i>
                        <h3 class="text-lg font-semibold mb-2">Leave History</h3>
                        <p class="text-sm text-slate-400">View all your leave requests</p>
                    </a>

                    <a href="dtr.php" class="bg-slate-800/50 backdrop-blur-sm/50 hover:bg-slate-700/50 backdrop-blur-sm border border-slate-700/50 hover:border-slate-600/50 text-white font-semibold py-6 px-6 rounded-2xl transition-all duration-300 transform hover:scale-[1.02] hover:shadow-xl text-center">
                        <i class="fas fa-clock text-2xl mb-3 block text-green-500"></i>
                        <h3 class="text-lg font-semibold mb-2">DTR</h3>
                        <p class="text-sm text-slate-400">Time in/out and attendance</p>
                    </a>
                </div>

                <!-- Recent Leave Requests -->
                <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 p-8 mb-8">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-bold text-white flex items-center">
                            <i class="fas fa-calendar-alt text-blue-500 mr-3"></i>
                            Recent Leave Requests
                        </h2>
                        <a href="leave_history.php" class="text-blue-400 hover:text-blue-300 text-sm font-medium flex items-center">
                            View All <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                    
                    <?php if (empty($leave_requests)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-calendar-times text-4xl text-slate-500 mb-4"></i>
                            <p class="text-slate-400 text-lg">No leave requests yet</p>
                            <p class="text-slate-500 text-sm">Click "Apply for Leave" to submit your first request</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach (array_slice($leave_requests, 0, 5) as $request): ?>
                                <div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600/50">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-4">
                                            <div class="w-12 h-12 bg-slate-600 rounded-full flex items-center justify-center">
                                                <i class="fas fa-calendar text-slate-300"></i>
                                            </div>
                                            <div>
                                                <h3 class="text-lg font-semibold text-white">
                                                    <?php echo ucfirst(str_replace('_', ' ', $request['leave_type'])); ?>
                                                </h3>
                                                <p class="text-slate-400 text-sm">
                                                    <?php echo date('M j, Y', strtotime($request['start_date'])); ?> - 
                                                    <?php echo date('M j, Y', strtotime($request['end_date'])); ?>
                                                    (<?php echo $request['days_requested']; ?> days)
                                                </p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium
                                                <?php
                                                switch($request['status']) {
                                                    case 'approved':
                                                        echo 'bg-green-500/20 text-green-400 border border-green-500/30';
                                                        break;
                                                    case 'rejected':
                                                        echo 'bg-red-500/20 text-red-400 border border-red-500/30';
                                                        break;
                                                    case 'pending':
                                                        echo 'bg-yellow-500/20 text-yellow-400 border border-yellow-500/30';
                                                        break;
                                                    default:
                                                        echo 'bg-slate-500/20 text-slate-400 border border-slate-500/30';
                                                }
                                                ?>">
                                                <?php echo ucfirst($request['status']); ?>
                                            </span>
                                            <p class="text-slate-500 text-xs mt-1">
                                                <?php echo date('M j, Y', strtotime($request['created_at'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Leave Chart Section -->
                <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 p-8 mb-8">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-bold text-white flex items-center">
                            <i class="fas fa-chart-bar text-blue-500 mr-3"></i>
                            Leave Overview
                        </h2>
                        <a href="view_chart.php" class="text-blue-400 hover:text-blue-300 text-sm font-medium flex items-center">
                            View Detailed Chart <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                    
                    <!-- Simple Leave Statistics -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <?php
                        // Calculate leave statistics
                        $total_requests = count($leave_requests);
                        $approved_requests = count(array_filter($leave_requests, function($req) { return $req['status'] === 'approved'; }));
                        $pending_requests = count(array_filter($leave_requests, function($req) { return $req['status'] === 'pending'; }));
                        $rejected_requests = count(array_filter($leave_requests, function($req) { return $req['status'] === 'rejected'; }));
                        ?>
                        
                        <div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600/50 text-center">
                            <div class="w-12 h-12 bg-blue-500/20 rounded-full flex items-center justify-center mx-auto mb-3">
                                <i class="fas fa-calendar-alt text-blue-400 text-xl"></i>
                            </div>
                            <h3 class="text-2xl font-bold text-white mb-1"><?php echo $total_requests; ?></h3>
                            <p class="text-slate-400 text-sm">Total Requests</p>
                        </div>
                        
                        <div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600/50 text-center">
                            <div class="w-12 h-12 bg-green-500/20 rounded-full flex items-center justify-center mx-auto mb-3">
                                <i class="fas fa-check-circle text-green-400 text-xl"></i>
                            </div>
                            <h3 class="text-2xl font-bold text-white mb-1"><?php echo $approved_requests; ?></h3>
                            <p class="text-slate-400 text-sm">Approved</p>
                        </div>
                        
                        <div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600/50 text-center">
                            <div class="w-12 h-12 bg-yellow-500/20 rounded-full flex items-center justify-center mx-auto mb-3">
                                <i class="fas fa-clock text-yellow-400 text-xl"></i>
                            </div>
                            <h3 class="text-2xl font-bold text-white mb-1"><?php echo $pending_requests; ?></h3>
                            <p class="text-slate-400 text-sm">Pending</p>
                        </div>
                        
                        <div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600/50 text-center">
                            <div class="w-12 h-12 bg-red-500/20 rounded-full flex items-center justify-center mx-auto mb-3">
                                <i class="fas fa-times-circle text-red-400 text-xl"></i>
                            </div>
                            <h3 class="text-2xl font-bold text-white mb-1"><?php echo $rejected_requests; ?></h3>
                            <p class="text-slate-400 text-sm">Rejected</p>
                        </div>
                    </div>
                    
                    <!-- Chart Preview -->
                    <div class="mt-6 p-4 bg-slate-700/20 rounded-xl border border-slate-600/30">
                        <div class="text-center">
                            <i class="fas fa-chart-pie text-4xl text-slate-500 mb-4"></i>
                            <h3 class="text-lg font-semibold text-white mb-2">Leave Distribution</h3>
                            <p class="text-slate-400 text-sm mb-4">View your detailed leave calendar and analytics</p>
                            <a href="view_chart.php" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors">
                                <i class="fas fa-chart-bar mr-2"></i>
                                Open Leave Chart
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script>
        // Modal Functions
        function openApplyLeaveModal() {
            const modal = document.getElementById('applyLeaveModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeApplyLeaveModal() {
            const modal = document.getElementById('applyLeaveModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            // Reset form
            document.getElementById('applyLeaveForm').reset();
        }

        function openLateApplicationModal() {
            const modal = document.getElementById('lateApplicationModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeLateApplicationModal() {
            const modal = document.getElementById('lateApplicationModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            // Reset form
            document.getElementById('lateApplicationForm').reset();
        }

        // Close modals when clicking outside
        document.addEventListener('click', function(event) {
            const applyModal = document.getElementById('applyLeaveModal');
            const lateModal = document.getElementById('lateApplicationModal');
            
            if (event.target === applyModal) {
                closeApplyLeaveModal();
            }
            if (event.target === lateModal) {
                closeLateApplicationModal();
            }
        });

        // Calculate days between dates
        function calculateDays() {
            const startDate = document.getElementById('modal_start_date').value;
            const endDate = document.getElementById('modal_end_date').value;
            const totalDaysInput = document.getElementById('modal_total_days');
            
            if (startDate && endDate) {
                const start = new Date(startDate);
                const end = new Date(endDate);
                const timeDiff = end.getTime() - start.getTime();
                const daysDiff = Math.ceil(timeDiff / (1000 * 3600 * 24)) + 1;
                totalDaysInput.value = daysDiff + ' day' + (daysDiff !== 1 ? 's' : '');
            }
        }

        function calculateLateDays() {
            const startDate = document.getElementById('modal_late_start_date').value;
            const endDate = document.getElementById('modal_late_end_date').value;
            const totalDaysInput = document.getElementById('modal_late_total_days');
            
            if (startDate && endDate) {
                const start = new Date(startDate);
                const end = new Date(endDate);
                const timeDiff = end.getTime() - start.getTime();
                const daysDiff = Math.ceil(timeDiff / (1000 * 3600 * 24)) + 1;
                totalDaysInput.value = daysDiff + ' day' + (daysDiff !== 1 ? 's' : '');
            }
        }

        // Show/hide conditional fields for Apply Leave Modal
        function toggleModalConditionalFields() {
            const leaveType = document.getElementById('modal_leave_type').value;
            const conditionalFields = document.getElementById('modalConditionalFields');
            const vacationFields = document.getElementById('modalVacationFields');
            const sickFields = document.getElementById('modalSickFields');
            const specialWomenFields = document.getElementById('modalSpecialWomenFields');
            const studyFields = document.getElementById('modalStudyFields');
            
            // Hide all conditional fields first
            if (vacationFields) vacationFields.classList.add('hidden');
            if (sickFields) sickFields.classList.add('hidden');
            if (specialWomenFields) specialWomenFields.classList.add('hidden');
            if (studyFields) studyFields.classList.add('hidden');
            if (conditionalFields) conditionalFields.classList.add('hidden');
            
            // Show relevant fields based on leave type
            if (leaveType === 'vacation' || leaveType === 'special_privilege') {
                if (vacationFields) vacationFields.classList.remove('hidden');
                if (conditionalFields) conditionalFields.classList.remove('hidden');
            } else if (leaveType === 'sick') {
                if (sickFields) sickFields.classList.remove('hidden');
                if (conditionalFields) conditionalFields.classList.remove('hidden');
            } else if (leaveType === 'special_women') {
                if (specialWomenFields) specialWomenFields.classList.remove('hidden');
                if (conditionalFields) conditionalFields.classList.remove('hidden');
            } else if (leaveType === 'study') {
                if (studyFields) studyFields.classList.remove('hidden');
                if (conditionalFields) conditionalFields.classList.remove('hidden');
            }
        }

        // Show/hide conditional fields for Late Application Modal
        function toggleModalLateConditionalFields() {
            const leaveType = document.getElementById('modal_late_leave_type').value;
            const conditionalFields = document.getElementById('modalLateConditionalFields');
            const vacationFields = document.getElementById('modalLateVacationFields');
            const sickFields = document.getElementById('modalLateSickFields');
            const specialWomenFields = document.getElementById('modalLateSpecialWomenFields');
            const studyFields = document.getElementById('modalLateStudyFields');
            
            // Hide all conditional fields first
            if (vacationFields) vacationFields.classList.add('hidden');
            if (sickFields) sickFields.classList.add('hidden');
            if (specialWomenFields) specialWomenFields.classList.add('hidden');
            if (studyFields) studyFields.classList.add('hidden');
            if (conditionalFields) conditionalFields.classList.add('hidden');
            
            // Show relevant fields based on leave type
            if (leaveType === 'vacation' || leaveType === 'special_privilege') {
                if (vacationFields) vacationFields.classList.remove('hidden');
                if (conditionalFields) conditionalFields.classList.remove('hidden');
            } else if (leaveType === 'sick') {
                if (sickFields) sickFields.classList.remove('hidden');
                if (conditionalFields) conditionalFields.classList.remove('hidden');
            } else if (leaveType === 'special_women') {
                if (specialWomenFields) specialWomenFields.classList.remove('hidden');
                if (conditionalFields) conditionalFields.classList.remove('hidden');
            } else if (leaveType === 'study') {
                if (studyFields) studyFields.classList.remove('hidden');
                if (conditionalFields) conditionalFields.classList.remove('hidden');
            }
        }

        // Clear conditional fields when modal is closed
        function clearModalConditionalFields() {
            const conditionalFields = document.getElementById('modalConditionalFields');
            const vacationFields = document.getElementById('modalVacationFields');
            const sickFields = document.getElementById('modalSickFields');
            const specialWomenFields = document.getElementById('modalSpecialWomenFields');
            const studyFields = document.getElementById('modalStudyFields');
            
            if (vacationFields) vacationFields.classList.add('hidden');
            if (sickFields) sickFields.classList.add('hidden');
            if (specialWomenFields) specialWomenFields.classList.add('hidden');
            if (studyFields) studyFields.classList.add('hidden');
            if (conditionalFields) conditionalFields.classList.add('hidden');
        }

        function clearModalLateConditionalFields() {
            const conditionalFields = document.getElementById('modalLateConditionalFields');
            const vacationFields = document.getElementById('modalLateVacationFields');
            const sickFields = document.getElementById('modalLateSickFields');
            const specialWomenFields = document.getElementById('modalLateSpecialWomenFields');
            const studyFields = document.getElementById('modalLateStudyFields');
            
            if (vacationFields) vacationFields.classList.add('hidden');
            if (sickFields) sickFields.classList.add('hidden');
            if (specialWomenFields) specialWomenFields.classList.add('hidden');
            if (studyFields) studyFields.classList.add('hidden');
            if (conditionalFields) conditionalFields.classList.add('hidden');
        }

        // Update close functions to clear conditional fields
        function closeApplyLeaveModal() {
            const modal = document.getElementById('applyLeaveModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            // Reset form
            document.getElementById('applyLeaveForm').reset();
            clearModalConditionalFields();
        }

        function closeLateApplicationModal() {
            const modal = document.getElementById('lateApplicationModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            // Reset form
            document.getElementById('lateApplicationForm').reset();
            clearModalLateConditionalFields();
        }

        // Add event listeners
        document.getElementById('modal_start_date').addEventListener('change', calculateDays);
        document.getElementById('modal_end_date').addEventListener('change', calculateDays);
        document.getElementById('modal_late_start_date').addEventListener('change', calculateLateDays);
        document.getElementById('modal_late_end_date').addEventListener('change', calculateLateDays);
    </script>
</body>
</html>
