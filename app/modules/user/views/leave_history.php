<?php
session_start();
require_once '../../../../config/database.php';
require_once '../../../../config/leave_types.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../auth/views/login.php');
    exit();
}

// Get user info
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$employee = $stmt->fetch();

// Get leave types configuration
$leaveTypes = getLeaveTypes();

// Get leave history
$stmt = $pdo->prepare("
    SELECT lr.*, 
           lr.leave_type as display_leave_type,
           lr.is_late,
           lr.late_justification
    FROM leave_requests lr 
    WHERE lr.employee_id = ? 
    ORDER BY lr.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$leave_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set page title
$page_title = "Leave History";

// Include user header
include '../../../../includes/user_header.php';
?>

<!-- Page Header -->
<h1 class="elms-h1" style="margin-bottom: 0.5rem; display: flex; align-items: center;">
    <i class="fas fa-history" style="color: #0891b2; margin-right: 0.75rem;"></i>Leave History
</h1>
<p class="elms-text-muted" style="margin-bottom: 2rem;">View all your leave requests and their status</p>
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="bg-green-500/20 border border-green-500/30 text-green-400 p-4 rounded-xl mb-6 flex items-center">
                        <i class="fas fa-check-circle mr-3"></i>
                        <?php 
                        echo $_SESSION['success'];
                        unset($_SESSION['success']);
                        ?>
                    </div>
                <?php endif; ?>

                <!-- Leave History Table -->
                <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-700/50 bg-slate-700/30">
                        <h3 class="text-xl font-semibold text-white flex items-center">
                            <i class="fas fa-list text-primary mr-3"></i>
                            Your Leave Requests
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-slate-700/30">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Leave Type</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Start Date</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">End Date</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Reason</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Applied On</th>
                                    <th class="px-6 py-4 text-center text-xs font-semibold text-slate-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/50">
                                <?php foreach ($leave_requests as $request): ?>
                                    <tr class="hover:bg-slate-700/30 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col gap-2">
                                                <span class="bg-primary/20 text-primary px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide">
                                                    <?php echo getLeaveTypeDisplayName($request['leave_type'], $request['original_leave_type'] ?? null, $leaveTypes); ?>
                                                </span>
                                                <?php if ($request['is_late'] == 1): ?>
                                                    <span class="bg-orange-500/20 text-orange-400 px-2 py-1 rounded-full text-xs font-semibold flex items-center">
                                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                                        Late Application
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-slate-300 text-sm"><?php echo date('M d, Y', strtotime($request['start_date'])); ?></td>
                                        <td class="px-6 py-4 text-slate-300 text-sm"><?php echo date('M d, Y', strtotime($request['end_date'])); ?></td>
                                        <td class="px-6 py-4 text-slate-300 text-sm max-w-xs truncate" title="<?php echo htmlspecialchars($request['reason']); ?>">
                                            <?php echo htmlspecialchars($request['reason']); ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide <?php 
                                                switch($request['status']) {
                                                    case 'approved':
                                                        echo 'bg-green-500/20 text-green-400';
                                                        break;
                                                    case 'rejected':
                                                        echo 'bg-red-500/20 text-red-400';
                                                        break;
                                                    case 'under_appeal':
                                                        echo 'bg-orange-500/20 text-orange-400';
                                                        break;
                                                    default:
                                                        echo 'bg-yellow-500/20 text-yellow-400';
                                                }
                                            ?>">
                                                <?php 
                                                $status_display = [
                                                    'under_appeal' => 'Under Appeal'
                                                ];
                                                echo $status_display[$request['status']] ?? ucfirst($request['status']); 
                                                ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-slate-300 text-sm"><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                                        <td class="px-6 py-4 text-center">
                                            <button onclick="viewLeaveDetails(<?php echo $request['id']; ?>)" 
                                                    class="bg-primary hover:bg-primary/90 text-white p-2 rounded-lg transition-colors">
                                                <i class="fas fa-eye text-xs"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($leave_requests)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-12">
                                            <i class="fas fa-inbox text-4xl text-slate-500 mb-4"></i>
                                            <p class="text-slate-400 text-lg">No leave requests found</p>
                                            <p class="text-slate-500 text-sm">Start by applying for your first leave request</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Leave Details Modal -->
    <div id="leaveDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-8 w-full max-w-2xl mx-4 max-h-screen overflow-y-auto border border-slate-700/50">
            <div class="flex items-center justify-between mb-6">
                <h5 class="text-2xl font-bold text-white flex items-center">
                    <i class="fas fa-eye text-primary mr-3"></i>Leave Request Details
                </h5>
                <div class="flex items-center gap-3">
                    <button type="button" onclick="closeLeaveDetailsModal()" class="text-slate-400 hover:text-white transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>
            <div id="leaveDetailsContent" class="text-slate-300">
                <!-- Content will be loaded here -->
            </div>
            <div class="flex justify-end pt-6">
                <button type="button" onclick="closeLeaveDetailsModal()" class="bg-slate-600 hover:bg-slate-500 text-white font-semibold py-3 px-6 rounded-xl transition-colors">
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
        // Pass leave types data to JavaScript
        window.leaveTypes = <?php echo json_encode($leaveTypes); ?>;
        
        // Helper function to get leave type display name in JavaScript
        function getLeaveTypeDisplayNameJS(leaveType, originalLeaveType = null) {
            const leaveTypes = window.leaveTypes;
            if (!leaveTypes) return leaveType;
            
            // Check if leave is without pay
            let isWithoutPay = false;
            
            // If leave_type is explicitly 'without_pay', it's without pay
            if (leaveType === 'without_pay') {
                isWithoutPay = true;
            }
            // If original_leave_type exists and current type is 'without_pay' or empty, it was converted to without pay
            else if (originalLeaveType && (leaveType === 'without_pay' || !leaveType)) {
                isWithoutPay = true;
            }
            // Check if the current leave type is inherently without pay
            else if (leaveTypes[leaveType] && leaveTypes[leaveType].without_pay) {
                isWithoutPay = true;
            }
            // Check if the original leave type was inherently without pay
            else if (originalLeaveType && leaveTypes[originalLeaveType] && leaveTypes[originalLeaveType].without_pay) {
                isWithoutPay = true;
            }
            
            // Determine the base leave type to display
            let baseType = null;
            if (originalLeaveType && (leaveType === 'without_pay' || !leaveType)) {
                // Use original type if it was converted to without pay
                baseType = originalLeaveType;
            } else {
                // Use current type
                baseType = leaveType;
            }
            
            // Get the display name
            if (leaveTypes[baseType]) {
                const leaveTypeConfig = leaveTypes[baseType];
                
                if (isWithoutPay) {
                    // Show name with without pay indicator
                    if (leaveTypeConfig.name_with_note) {
                        return leaveTypeConfig.name_with_note;
                    } else {
                        return leaveTypeConfig.name + ' (Without Pay)';
                    }
                } else {
                    // Show regular name
                    return leaveTypeConfig.name;
                }
            } else {
                // Fallback for unknown types
                const displayName = baseType.charAt(0).toUpperCase() + baseType.slice(1).replace(/_/g, ' ');
                return isWithoutPay ? displayName + ' (Without Pay)' : displayName;
            }
        }

        function openLeaveDetailsModal() {
            document.getElementById('leaveDetailsModal').classList.remove('hidden');
            document.getElementById('leaveDetailsModal').classList.add('flex');
        }

        function closeLeaveDetailsModal() {
            document.getElementById('leaveDetailsModal').classList.add('hidden');
            document.getElementById('leaveDetailsModal').classList.remove('flex');
        }

        let currentLeaveId = null;

        function viewLeaveDetails(leaveId) {
            currentLeaveId = leaveId;
            // Fetch leave details via AJAX
            fetch(`/ELMS/api/get_leave_details.php?id=${leaveId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const leave = data.leave;
                        const modalContent = document.getElementById('leaveDetailsContent');
                        
                        // Create comprehensive content with conditional fields
                        const isLate = leave.is_late == 1 || leave.is_late === true;
                        const requirements = leave.leave_requirements || {};
                        const statusInfo = leave.status_info || {};
                        
                        modalContent.innerHTML = `
                            ${isLate ? `
                                <div class="bg-orange-500/10 border border-orange-500/20 rounded-xl p-6 mb-6">
                                    <div class="flex items-center mb-4">
                                        <i class="fas fa-exclamation-triangle text-orange-400 text-2xl mr-3"></i>
                                        <h4 class="text-xl font-bold text-orange-400">Late Leave Application</h4>
                                    </div>
                                    <p class="text-orange-300 text-sm">This application was submitted after the required deadline and requires special consideration.</p>
                                </div>
                            ` : `
                                <div class="bg-blue-500/10 border border-blue-500/20 rounded-xl p-6 mb-6">
                                    <div class="flex items-center mb-4">
                                        <i class="fas fa-calendar-check text-blue-400 text-2xl mr-3"></i>
                                        <h4 class="text-xl font-bold text-blue-400">Regular Leave Application</h4>
                                    </div>
                                    <p class="text-blue-300 text-sm">This is a standard leave application submitted within the required timeframe.</p>
                                </div>
                            `}
                            
                            <!-- Status Information -->
                            <div class="mb-6 ${statusInfo.bg_color} border ${statusInfo.border_color} rounded-xl p-4">
                                <div class="flex items-center">
                                    <i class="${statusInfo.icon} ${statusInfo.color} text-xl mr-3"></i>
                                    <div>
                                        <h6 class="${statusInfo.color} font-semibold mb-1">Application Status</h6>
                                        <p class="text-slate-300 text-sm">${statusInfo.message}</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <h6 class="text-slate-400 mb-2 font-semibold">Leave Type</h6>
                                    <p class="mb-3">
                                        <span class="bg-blue-500/20 text-blue-400 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide">${getLeaveTypeDisplayNameJS(leave.leave_type, leave.original_leave_type)}</span>
                                    </p>
                                    
                                    <h6 class="text-slate-400 mb-2 font-semibold">Start Date</h6>
                                    <p class="mb-3 text-white">${new Date(leave.start_date).toLocaleDateString('en-US', { 
                                        year: 'numeric', 
                                        month: 'long', 
                                        day: 'numeric' 
                                    })}</p>
                                    
                                    <h6 class="text-slate-400 mb-2 font-semibold">End Date</h6>
                                    <p class="mb-3 text-white">${(() => {
                                        // Use approved end date if available, otherwise original end date
                                        if (leave.status === 'approved' && leave.approved_days && leave.approved_days !== leave.days_requested) {
                                            const startDate = new Date(leave.start_date);
                                            const approvedEndDate = new Date(startDate);
                                            approvedEndDate.setDate(startDate.getDate() + (leave.approved_days - 1));
                                            return approvedEndDate.toLocaleDateString('en-US', { 
                                                year: 'numeric', 
                                                month: 'long', 
                                                day: 'numeric' 
                                            });
                                        } else {
                                            return new Date(leave.end_date).toLocaleDateString('en-US', { 
                                                year: 'numeric', 
                                                month: 'long', 
                                                day: 'numeric' 
                                            });
                                        }
                                    })()}</p>
                                    
                                    <h6 class="text-slate-400 mb-2 font-semibold">Days Requested</h6>
                                    <p class="mb-3 text-white">${leave.days_requested || leave.days || 'N/A'} day(s)</p>
                                    
                                    ${leave.approved_days && leave.approved_days > 0 ? `
                                        <h6 class="text-slate-400 mb-2 font-semibold">Days Approved</h6>
                                        <p class="mb-3 text-green-400 font-semibold">
                                            ${leave.approved_days} day(s) 
                                            ${leave.pay_status ? `<span class="text-xs">(${leave.pay_status.replace('_', ' ')})</span>` : ''}
                                        </p>
                                        ${leave.approved_days != leave.days_requested ? `
                                            <div class="bg-yellow-500/10 border border-yellow-500/30 rounded-lg p-3 text-sm text-yellow-400">
                                                <i class="fas fa-info-circle mr-2"></i>
                                                Director approved ${leave.approved_days} days instead of ${leave.days_requested} requested days
                                            </div>
                                        ` : ''}
                                    ` : ''}
                                </div>
                                <div>
                                    <h6 class="text-slate-400 mb-2 font-semibold">Status</h6>
                                    <p class="mb-3">
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide ${getStatusClass(leave.status)}">
                                            ${getStatusDisplay(leave.status)}
                                        </span>
                                    </p>
                                    
                                    <h6 class="text-slate-400 mb-2 font-semibold">Applied On</h6>
                                    <p class="mb-3 text-white">${new Date(leave.created_at).toLocaleDateString('en-US', { 
                                        year: 'numeric', 
                                        month: 'long', 
                                        day: 'numeric' 
                                    })}</p>
                                    
                                    <h6 class="text-slate-400 mb-2 font-semibold">Employee</h6>
                                    <p class="mb-3 text-white">${leave.employee_name || 'N/A'}</p>
                                    
                                    <h6 class="text-slate-400 mb-2 font-semibold">Department</h6>
                                    <p class="mb-3 text-white">${leave.department || 'N/A'}</p>
                                </div>
                            </div>
                            
                            <!-- Leave Reason -->
                            <div class="mt-6">
                                <h6 class="text-slate-400 mb-2 font-semibold">Leave Reason</h6>
                                <p class="text-white bg-slate-700/50 p-4 rounded-lg">${leave.reason}</p>
                            </div>
                            
                            <!-- Location Details (for vacation leave) -->
                            ${leave.location_type ? `
                                <div class="mt-6">
                                    <h6 class="text-slate-400 mb-2 font-semibold flex items-center">
                                        <i class="fas fa-map-marker-alt text-blue-400 mr-2"></i>
                                        Location Details
                                    </h6>
                                    <div class="bg-slate-700/30 border border-slate-600/50 rounded-lg p-4">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label class="text-sm font-medium text-slate-400">Location Type</label>
                                                <p class="text-white">${leave.location_type ? leave.location_type.charAt(0).toUpperCase() + leave.location_type.slice(1).replace('_', ' ') : 'N/A'}</p>
                                            </div>
                                            ${leave.location_specify ? `
                                            <div>
                                                <label class="text-sm font-medium text-slate-400">Specific Location</label>
                                                <p class="text-white">${leave.location_specify}</p>
                                            </div>
                                            ` : ''}
                                        </div>
                                    </div>
                                </div>
                            ` : ''}
                            
                            <!-- Late Justification (if applicable) -->
                            ${isLate ? `
                                <div class="mt-6">
                                    <h6 class="text-slate-400 mb-2 font-semibold flex items-center">
                                        <i class="fas fa-exclamation-triangle text-orange-400 mr-2"></i>
                                        Late Justification
                                    </h6>
                                    <p class="text-white bg-orange-500/10 border border-orange-500/20 p-4 rounded-lg">${leave.late_justification || 'No justification provided'}</p>
                                </div>
                            ` : ''}
                            
                            <!-- Leave-Specific Requirements -->
                            ${requirements.description ? `
                                <div class="mt-6">
                                    <h6 class="text-slate-400 mb-2 font-semibold flex items-center">
                                        <i class="${requirements.icon} ${requirements.color} mr-2"></i>
                                        Leave Requirements
                                    </h6>
                                    <div class="bg-slate-700/30 border border-slate-600/50 rounded-lg p-4">
                                        <p class="text-slate-300 text-sm">${requirements.description}</p>
                                        ${requirements.medical_certificate ? `
                                            <div class="mt-3 flex items-center">
                                                <i class="fas fa-file-medical text-red-400 mr-2"></i>
                                                <span class="text-slate-300 text-sm">Medical Certificate: ${leave.medical_certificate_path ? 'Attached' : 'Not provided'}</span>
                                            </div>
                                        ` : ''}
                                        ${requirements.birth_certificate ? `
                                            <div class="mt-2 flex items-center">
                                                <i class="fas fa-certificate text-cyan-400 mr-2"></i>
                                                <span class="text-slate-300 text-sm">Birth Certificate: Required</span>
                                            </div>
                                        ` : ''}
                                        ${requirements.court_order ? `
                                            <div class="mt-2 flex items-center">
                                                <i class="fas fa-gavel text-red-600 mr-2"></i>
                                                <span class="text-slate-300 text-sm">Court Order/Police Report: Required</span>
                                            </div>
                                        ` : ''}
                                    </div>
                                </div>
                            ` : ''}
                            
                            <!-- Special Conditions (if applicable) -->
                            ${leave.special_women_condition ? `
                                <div class="mt-6">
                                    <h6 class="text-slate-400 mb-2 font-semibold">Special Women Condition</h6>
                                    <p class="text-white bg-purple-500/10 border border-purple-500/20 p-4 rounded-lg">
                                        ${leave.special_women_condition.charAt(0).toUpperCase() + leave.special_women_condition.slice(1)}
                                    </p>
                                </div>
                            ` : ''}
                            
                            ${leave.medical_condition ? `
                                <div class="mt-6">
                                    <h6 class="text-slate-400 mb-2 font-semibold">Medical Condition</h6>
                                    <p class="text-white bg-red-500/10 border border-red-500/20 p-4 rounded-lg">
                                        ${leave.medical_condition.charAt(0).toUpperCase() + leave.medical_condition.slice(1)}
                                        ${leave.illness_specify ? ` - ${leave.illness_specify}` : ''}
                                    </p>
                                </div>
                            ` : ''}
                            
                            ${leave.study_type ? `
                                <div class="mt-6">
                                    <h6 class="text-slate-400 mb-2 font-semibold">Study Type</h6>
                                    <p class="text-white bg-indigo-500/10 border border-indigo-500/20 p-4 rounded-lg">
                                        ${leave.study_type.charAt(0).toUpperCase() + leave.study_type.slice(1)}
                                    </p>
                                </div>
                            ` : ''}
                            
                            <!-- Action Buttons -->
                            <div class="mt-6 flex flex-wrap gap-3">
                                ${leave.medical_certificate_path ? `
                                    <button onclick="viewMedicalCertificate('${leave.medical_certificate_path}')" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors">
                                        <i class="fas fa-file-medical mr-2"></i>View Medical Certificate
                                    </button>
                                ` : ''}
                            </div>
                            
                            <!-- Important Notices -->
                            <div class="mt-6 ${isLate ? 'bg-yellow-500/10 border border-yellow-500/20' : 'bg-green-500/10 border border-green-500/20'} rounded-lg p-4">
                                <div class="flex items-start">
                                    <i class="fas ${isLate ? 'fa-info-circle text-yellow-400' : 'fa-check-circle text-green-400'} mr-3 mt-1"></i>
                                    <div>
                                        <h6 class="${isLate ? 'text-yellow-400' : 'text-green-400'} font-semibold mb-1">
                                            ${isLate ? 'Important Notice' : 'Application Status'}
                                        </h6>
                                        <p class="${isLate ? 'text-yellow-300' : 'text-green-300'} text-sm">
                                            ${isLate ? 'Late applications may require additional approval and may be subject to different processing times. Please ensure your justification is clear and valid.' : 'This application was submitted on time and follows standard processing procedures.'}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        openLeaveDetailsModal();
                    } else {
                        alert('Error loading leave details: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    alert('Error loading leave details: ' + error.message);
                });
        }

        // Action button functions

        function viewMedicalCertificate(certificatePath) {
            // Open medical certificate via secure API endpoint
            const timestamp = new Date().getTime();
            window.open(`/ELMS/app/modules/api/view_medical_certificate.php?file=${encodeURIComponent(certificatePath)}&v=${timestamp}`, '_blank');
        }

        // Helper functions for status display
        function getStatusClass(status) {
            switch(status) {
                case 'approved':
                    return 'bg-green-500/20 text-green-400';
                case 'rejected':
                    return 'bg-red-500/20 text-red-400';
                case 'under_appeal':
                    return 'bg-orange-500/20 text-orange-400';
                default:
                    return 'bg-yellow-500/20 text-yellow-400';
            }
        }

        function getStatusDisplay(status) {
            const statusMap = {
                'under_appeal': 'Under Appeal'
            };
            return statusMap[status] || status.charAt(0).toUpperCase() + status.slice(1);
        }

    </script>

<?php include '../../../../includes/user_footer.php'; ?> 