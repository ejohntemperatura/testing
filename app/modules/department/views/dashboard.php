<?php
session_start();
require_once '../../../../config/database.php';
require_once '../../../../config/leave_types.php';

// Auto-process emails when internet is available
require_once '../../../../app/core/services/auto_email_processor.php';

// Allow admin or manager (department head) to access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin','manager'])) {
	header('Location: ../../../../auth/views/login.php');
	exit();
}

// Basic user info
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$me = $stmt->fetch();

// Get department head's department for filtering
$dept_head_department = $me['department'] ?? null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- OFFLINE Tailwind CSS - No internet required! -->
    <link rel="stylesheet" href="../../../../assets/css/tailwind.css">
        <!-- Font Awesome Local - No internet required! -->
    <link rel="stylesheet" href="../../../../assets/libs/fontawesome/css/all.min.css">
    
    <link rel="stylesheet" href="../../../../assets/css/style.css">
    <link rel="stylesheet" href="../../../../assets/css/dark-theme.css">
    <script src="../../../../assets/libs/chartjs/chart.umd.min.js"></script>



    
	
    <link rel="stylesheet" href="../../../../assets/css/style.css">
    <link rel="stylesheet" href="../../../../assets/css/dark-theme.css">
	
</head>
<body class="bg-slate-900 text-white" data-user-role="manager">
	<?php include '../../../../includes/unified_navbar.php'; ?>

	<div class="flex">
		<!-- Left Sidebar -->
		<aside id="sidebar" class="fixed left-0 top-16 h-screen w-64 bg-slate-900 border-r border-slate-800 overflow-y-auto z-40">
			<nav class="p-4 space-y-2">
				<!-- Active Navigation Item -->
				<a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 text-white bg-blue-500/20 rounded-lg border border-blue-500/30">
					<i class="fas fa-tachometer-alt w-5"></i>
					<span>Dashboard</span>
				</a>
				
				<!-- Section Headers -->
				<div class="space-y-1">
					<h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2">Management</h3>
					
					<!-- Navigation Items -->
					<a href="view_chart.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
					<i class="fas fa-calendar w-5"></i>
						<span>Leave Chart</span>
					</a>
				</div>
				
			
			</nav>
		</aside>
		
		<!-- Main Content -->
		<main class="flex-1 ml-64 p-6 pt-24">
			<div class="max-w-7xl mx-auto">

				<!-- Success Message -->
				<?php if (isset($_SESSION['success'])): ?>
					<div class="bg-green-500/20 border border-green-500/30 text-green-400 p-4 rounded-xl mb-6 flex items-center">
						<i class="fas fa-check-circle mr-3"></i>
						<?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
					</div>
				<?php endif; ?>
				
				<!-- Error Message -->
				<?php if (isset($_SESSION['error'])): ?>
					<div class="bg-red-500/20 border border-red-500/30 text-red-400 p-4 rounded-xl mb-6 flex items-center">
						<i class="fas fa-exclamation-circle mr-3"></i>
						<?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
					</div>
				<?php endif; ?>
			
				<!-- Welcome Section -->
				<div class="mb-10 mt-16">
					<div class="flex items-start justify-between">
						<div class="flex items-center gap-5">
							<div class="w-16 h-16 bg-gradient-to-r from-slate-600 to-slate-700 rounded-xl flex items-center justify-center shadow-lg flex-shrink-0">
								<i class="fas fa-user-tie text-2xl text-white"></i>
							</div>
							<div class="flex-1">
								<h1 class="text-3xl font-bold text-white mb-2 leading-tight">
									Welcome, <?php echo htmlspecialchars($me['name'] ?? 'Department Head'); ?>!
								</h1>
								<p class="text-slate-400 text-lg leading-relaxed">Manage department leave requests and view analytics</p>
							</div>
						</div>
						
					</div>
				</div>

				<!-- Pending Leave Requests -->
				<div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 overflow-hidden mb-8">
					<div class="px-6 py-4 border-b border-slate-700/50 bg-slate-700/30">
						<h3 class="text-xl font-semibold text-white flex items-center">
							<i class="fas fa-clock text-yellow-400 mr-3"></i>Pending Leave Requests
						</h3>
					</div>
					<div class="p-6">
							<?php
							// Get total count of pending leave requests for this department only
							$stmt = $pdo->prepare("
								SELECT COUNT(*) as total_count
								FROM leave_requests lr 
								JOIN employees e ON lr.employee_id = e.id 
								WHERE (lr.dept_head_approval IS NULL OR lr.dept_head_approval = 'pending')
								AND lr.status != 'rejected'
								AND e.department = ?
							");
							$stmt->execute([$dept_head_department]);
							$total_pending = $stmt->fetch(PDO::FETCH_ASSOC)['total_count'];
							
							// Get pending leave requests (only those not yet decided by department head)
							// Initially show only 5 requests - filtered by department
							$initial_limit = 5;
							$stmt = $pdo->prepare("
								SELECT lr.*, e.name as employee_name, e.position, e.department 
								FROM leave_requests lr 
								JOIN employees e ON lr.employee_id = e.id 
								WHERE (lr.dept_head_approval IS NULL OR lr.dept_head_approval = 'pending')
								AND lr.status != 'rejected'
								AND e.department = ?
								ORDER BY lr.is_late DESC, lr.created_at DESC 
								LIMIT " . intval($initial_limit)
							);
							$stmt->execute([$dept_head_department]);
							$pending_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
							
							// Get leave types configuration
							$leaveTypes = getLeaveTypes();
							?>
							
						<?php if (empty($pending_requests)): ?>
							<div class="text-center py-12">
								<i class="fas fa-check-circle text-4xl text-green-400 mb-4"></i>
								<p class="text-slate-400 text-lg">No pending leave requests</p>
							</div>
						<?php else: ?>
							<div class="overflow-x-auto">
								<table class="w-full">
									<thead class="bg-slate-700/30">
										<tr>
											<th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Employee</th>
											<th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Leave Type</th>
											<th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Start Date</th>
											<th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">End Date</th>
											<th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Days</th>
											<th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Reason</th>
											<th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Status</th>
											<th class="px-6 py-4 text-center text-xs font-semibold text-slate-300 uppercase tracking-wider">Actions</th>
										</tr>
									</thead>
									<tbody class="divide-y divide-slate-700/50">
										<?php foreach ($pending_requests as $request): ?>
											<tr class="hover:bg-slate-700/30 transition-colors">
												<td class="px-6 py-4">
													<div>
														<div class="font-semibold text-white"><?php echo htmlspecialchars($request['employee_name']); ?></div>
														<div class="text-sm text-slate-400"><?php echo htmlspecialchars($request['position']); ?></div>
													</div>
												</td>
												<td class="px-6 py-4">
													<div class="flex flex-col gap-2">
														<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-primary/20 text-primary border border-primary/30">
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
												<td class="px-6 py-4 text-slate-300 text-sm">
													<?php 
													$start = new DateTime($request['start_date']);
													$end = new DateTime($request['end_date']);
													$days = $start->diff($end)->days + 1;
													echo $days;
													?>
												</td>
												<td class="px-6 py-4 text-slate-300 text-sm max-w-xs truncate" title="<?php echo htmlspecialchars($request['reason']); ?>">
													<?php echo strlen($request['reason']) > 30 ? substr(htmlspecialchars($request['reason']), 0, 30) . '...' : htmlspecialchars($request['reason']); ?>
												</td>
												<td class="px-6 py-4">
													<div class="flex items-center gap-2">
														<span class="bg-yellow-500/20 text-yellow-400 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide">Pending</span>
														<button type="button" onclick="showStatusInfo(<?php echo $request['id']; ?>)" title="View Status Details" class="text-slate-400 hover:text-white transition-colors">
															<i class="fas fa-info-circle"></i>
														</button>
													</div>
												</td>
												<td class="px-6 py-4 text-center">
													<div class="flex justify-center">
														<button onclick="openDepartmentApprovalModal(<?php echo $request['id']; ?>)" class="inline-flex items-center px-4 py-2 bg-primary hover:bg-primary/80 text-white text-sm font-medium rounded-lg transition-colors">
															<i class="fas fa-gavel mr-2"></i> Process Request
														</button>
													</div>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
							<?php if ($total_pending > $initial_limit): ?>
								<div class="text-center mt-6">
									<button id="loadMoreBtn" class="bg-primary/20 hover:bg-primary/30 text-primary border border-primary/30 font-semibold py-3 px-6 rounded-xl transition-colors">
										<i class="fas fa-plus mr-2"></i>View More (<?php echo $total_pending - $initial_limit; ?> more)
									</button>
									<button id="showLessBtn" class="hidden bg-slate-600/20 hover:bg-slate-600/30 text-slate-400 border border-slate-600/30 font-semibold py-3 px-6 rounded-xl transition-colors ml-4">
										<i class="fas fa-minus mr-2"></i>Show Less
									</button>
								</div>
								<!-- Hidden container for additional requests -->
								<div id="additionalRequests" class="hidden mt-6"></div>
							<?php endif; ?>
						<?php endif; ?>
					</div>
				</div>

				<!-- Leave Chart Quick Action Card -->
				<div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 overflow-hidden hover:border-slate-600/50 transition-all duration-300 group">
					<div class="p-6">
						<div class="flex items-center mb-4">
							<div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl flex items-center justify-center mr-4 group-hover:scale-110 transition-transform duration-300">
								<i class="fas fa-chart-line text-white text-lg"></i>
							</div>
							<h3 class="text-xl font-semibold text-white">Leave Chart</h3>
						</div>
						<p class="text-slate-400 mb-6">View comprehensive leave analytics and calendar overview across the department.</p>
						<a href="view_chart.php" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl transition-colors">
							<i class="fas fa-chart-line mr-2"></i>View Leave Chart
						</a>
					</div>
				</div>
			</div>
		</main>
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

		// Show status information modal
		function showStatusInfo(leaveId) {
			// Create modal HTML
			const modalHtml = `
				<div id="statusInfoModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
					<div class="bg-slate-800 rounded-2xl border border-slate-700 max-w-2xl w-full max-h-[90vh] overflow-y-auto">
						<div class="px-6 py-4 border-b border-slate-700 bg-slate-700/30">
							<div class="flex items-center justify-between">
								<h3 class="text-xl font-semibold text-white flex items-center">
									<i class="fas fa-info-circle text-primary mr-3"></i>Leave Request Status
								</h3>
								<button type="button" class="text-slate-400 hover:text-white transition-colors" onclick="closeStatusModal()">
									<i class="fas fa-times text-xl"></i>
								</button>
							</div>
						</div>
						<div class="p-6">
							<div class="bg-blue-500/20 border border-blue-500/30 rounded-xl p-4 mb-6">
								<h4 class="text-lg font-semibold text-white mb-2 flex items-center">
									<i class="fas fa-clock text-blue-400 mr-2"></i>Current Status
								</h4>
								<p class="text-slate-300">This leave request is currently <strong class="text-white">pending</strong> and waiting for your decision.</p>
							</div>
							<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
								<div class="bg-slate-700/50 rounded-xl p-4">
									<h4 class="text-primary font-semibold mb-3 flex items-center">
										<i class="fas fa-user-tie mr-2"></i>Department Head (You)
									</h4>
									<p class="text-slate-300 mb-2"><strong class="text-white">Status:</strong> 
										<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-500/20 text-yellow-400 border border-yellow-500/30 ml-2">Pending</span>
									</p>
									<p class="text-slate-400 text-sm">Action Required: Approve or Reject this request</p>
								</div>
								<div class="bg-slate-700/50 rounded-xl p-4">
									<h4 class="text-slate-400 font-semibold mb-3 flex items-center">
										<i class="fas fa-user-tie mr-2"></i>Director
									</h4>
									<p class="text-slate-300 mb-2"><strong class="text-white">Status:</strong> 
										<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-slate-500/20 text-slate-400 border border-slate-500/30 ml-2">Waiting</span>
									</p>
									<p class="text-slate-400 text-sm">Will review after your decision</p>
								</div>
							</div>
							<div class="border-t border-slate-700 pt-6">
								<div class="bg-slate-700/50 rounded-xl p-4">
									<h4 class="text-slate-400 font-semibold mb-3 flex items-center">
										<i class="fas fa-flag-checkered mr-2"></i>Final Status
									</h4>
									<p class="text-slate-300 mb-2"><strong class="text-white">Result:</strong> 
										<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-slate-500/20 text-slate-400 border border-slate-500/30 ml-2">Pending</span>
									</p>
									<p class="text-slate-400 text-sm">Depends on all approval levels</p>
								</div>
							</div>
							<div class="flex justify-end mt-6">
								<button type="button" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-xl transition-colors" onclick="closeStatusModal()">Close</button>
							</div>
						</div>
					</div>
				</div>
			`;

			// Remove existing modal if any
			const existingModal = document.getElementById('statusInfoModal');
			if (existingModal) {
				existingModal.remove();
			}

			// Add modal to body
			document.body.insertAdjacentHTML('beforeend', modalHtml);
		}

		// Close status modal
		function closeStatusModal() {
			const modal = document.getElementById('statusInfoModal');
			if (modal) {
				modal.remove();
			}
		}


		// Department Approval Modal Functions
		function openDepartmentApprovalModal(requestId) {
			
			// First, get the leave request details via AJAX
			fetch(`../api/get_leave_request_details.php?id=${requestId}`)
				.then(response => {
					return response.json();
				})
				.then(data => {
					if (data.success) {
						const request = data.leave;
						const modalHtml = `
							<div id="departmentApprovalModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
								<div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 w-full max-w-4xl max-h-[90vh] overflow-y-auto">
									<div class="px-6 py-4 border-b border-slate-700/50 bg-slate-700/30">
										<div class="flex items-center justify-between">
											<h3 class="text-2xl font-bold text-white flex items-center">
												<i class="fas fa-gavel text-primary mr-3"></i>
												Department Head Approval - Leave Request
											</h3>
											<button type="button" onclick="closeDepartmentApprovalModal()" class="text-slate-400 hover:text-white transition-colors">
												<i class="fas fa-times text-xl"></i>
											</button>
										</div>
									</div>
									
									<div class="p-6">
										<!-- Status Information -->
										${request.is_late == 1 || request.is_late === true ? `
										<div class="bg-orange-500/20 border border-orange-500/30 rounded-xl p-4 mb-6">
											<div class="flex items-center">
												<i class="fas fa-exclamation-triangle text-orange-400 mr-3 text-xl"></i>
												<div>
													<h4 class="text-lg font-semibold text-white mb-1">Late Leave Application</h4>
													<p class="text-orange-400">This is a late leave application that requires special consideration. Please review the late justification carefully.</p>
												</div>
											</div>
										</div>
										` : `
										<div class="bg-blue-500/20 border border-blue-500/30 rounded-xl p-4 mb-6">
											<div class="flex items-center">
												<i class="fas fa-info-circle text-blue-400 mr-3 text-xl"></i>
												<div>
													<h4 class="text-lg font-semibold text-white mb-1">Approval Status</h4>
													<p class="text-blue-400">This leave request is awaiting your decision as Department Head.</p>
												</div>
											</div>
										</div>
										`}
										
										<!-- Leave Request Details -->
										<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
											<!-- Employee Information -->
											<div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600/50">
												<h4 class="text-lg font-semibold text-white mb-4 flex items-center">
													<i class="fas fa-user-circle text-blue-500 mr-3"></i>
													Employee Information
												</h4>
												<div class="space-y-3">
													<div>
														<label class="block text-sm font-semibold text-slate-300 mb-1">Employee Name</label>
														<p class="text-white font-medium">${request.employee_name}</p>
													</div>
													<div>
														<label class="block text-sm font-semibold text-slate-300 mb-1">Department</label>
														<p class="text-white">${request.department || 'N/A'}</p>
													</div>
													<div>
														<label class="block text-sm font-semibold text-slate-300 mb-1">Position</label>
														<p class="text-white">${request.position || 'N/A'}</p>
													</div>
												</div>
											</div>
											
											<!-- Leave Details -->
											<div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600/50">
												<h4 class="text-lg font-semibold text-white mb-4 flex items-center">
													<i class="fas fa-calendar-alt text-green-500 mr-3"></i>
													Leave Details
												</h4>
												<div class="space-y-3">
													<div>
														<label class="block text-sm font-semibold text-slate-300 mb-1">Leave Type</label>
														<p class="text-white font-medium">${request.leave_type}</p>
													</div>
													<div>
														<label class="block text-sm font-semibold text-slate-300 mb-1">Start Date</label>
														<p class="text-white">${request.start_date}</p>
													</div>
													<div>
														<label class="block text-sm font-semibold text-slate-300 mb-1">End Date</label>
														<p class="text-white">${request.end_date}</p>
													</div>
													<div>
														<label class="block text-sm font-semibold text-slate-300 mb-1">Days Requested</label>
														<p class="text-white">${request.days_requested} day(s)</p>
													</div>
												</div>
											</div>
										</div>
										
										<!-- Reason -->
										<div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600/50 mb-6">
											<h4 class="text-lg font-semibold text-white mb-4 flex items-center">
												<i class="fas fa-comment-alt text-purple-500 mr-3"></i>
												Reason for Leave
											</h4>
											<p class="text-slate-300 leading-relaxed">${request.reason}</p>
										</div>
										
										<!-- Late Justification (only for late applications) -->
										${request.is_late == 1 || request.is_late === true ? `
										<div class="bg-orange-500/10 border border-orange-500/20 rounded-xl p-6 mb-6">
											<h4 class="text-lg font-semibold text-white mb-4 flex items-center">
												<i class="fas fa-exclamation-triangle text-orange-400 mr-3"></i>
												Late Justification
											</h4>
											<p class="text-slate-300 leading-relaxed bg-orange-500/5 p-4 rounded-lg">${request.late_justification || 'No justification provided'}</p>
										</div>
										` : ''}
										
										<!-- Conditional Details -->
										<div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600/50 mb-6" id="conditionalDetailsSection">
											<h4 class="text-lg font-semibold text-white mb-4 flex items-center">
												<i class="fas fa-info-circle text-cyan-500 mr-3"></i>
												Additional Details
											</h4>
											<div id="conditionalDetailsContent">
												<!-- Vacation/Special Privilege Details -->
												<div id="vacationDetails" style="display: none;">
													<div class="space-y-3">
														<div>
															<label class="block text-sm font-semibold text-slate-300 mb-1">Location Type</label>
															<p class="text-white">${request.location_type || 'N/A'}</p>
														</div>
														<div>
															<label class="block text-sm font-semibold text-slate-300 mb-1">Specific Address</label>
															<p class="text-slate-300">${request.location_specify || 'N/A'}</p>
														</div>
													</div>
												</div>
												
												<!-- Sick Leave Details -->
												<div id="sickDetails" style="display: none;">
													<div class="space-y-3">
														<div>
															<label class="block text-sm font-semibold text-slate-300 mb-1">Medical Condition</label>
															<p class="text-white">${request.medical_condition || 'N/A'}</p>
														</div>
														<div>
															<label class="block text-sm font-semibold text-slate-300 mb-1">Illness Specification</label>
															<p class="text-slate-300">${request.illness_specify || 'N/A'}</p>
														</div>
														${request.medical_certificate_path ? `
														<div>
															<label class="block text-sm font-semibold text-slate-300 mb-1">Medical Certificate</label>
															<div class="flex items-center space-x-3">
																<a href="../../api/view_medical_certificate.php?file=${encodeURIComponent(request.medical_certificate_path.replace(/^.*uploads\/medical_certificates\//, ''))}" target="_blank" 
																   class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
																	<i class="fas fa-file-medical mr-2"></i>View Medical Certificate
																</a>
																<span class="text-slate-400 text-sm">Medical certificate attached</span>
															</div>
														</div>
														` : `
														<div>
															<label class="block text-sm font-semibold text-slate-300 mb-1">Medical Certificate</label>
															<p class="text-slate-400">No medical certificate provided</p>
														</div>
														`}
													</div>
												</div>
												
												<!-- Special Women Leave Details -->
												<div id="specialWomenDetails" style="display: none;">
													<div class="space-y-3">
														<div>
															<label class="block text-sm font-semibold text-slate-300 mb-1">Illness Specification</label>
															<p class="text-slate-300">${request.special_women_condition || 'N/A'}</p>
														</div>
													</div>
												</div>
												
												<!-- Study Leave Details -->
												<div id="studyDetails" style="display: none;">
													<div class="space-y-3">
														<div>
															<label class="block text-sm font-semibold text-slate-300 mb-1">Study Type</label>
															<p class="text-white">${request.study_type || 'N/A'}</p>
														</div>
													</div>
												</div>
											</div>
										</div>
										
										<!-- Rejection Options -->
										<div class="bg-slate-700/30 rounded-xl p-6 border border-slate-600/50 mb-6" id="rejectionOptionsSection" style="display: none;">
											<h4 class="text-lg font-semibold text-white mb-4 flex items-center">
												<i class="fas fa-times-circle text-red-500 mr-3"></i>
												Rejection Options
											</h4>
											
											<!-- Rejection Reason -->
											<div>
												<label class="block text-sm font-semibold text-slate-300 mb-2">Reason for Rejection:</label>
												<textarea id="rejectionReasonText" rows="3" 
														  class="w-full px-3 py-2 bg-slate-600 border border-slate-500 rounded-lg text-white focus:ring-2 focus:ring-red-500 focus:border-transparent"
														  placeholder="Please provide a reason for rejection..." required></textarea>
											</div>
										</div>
										
										<!-- Action Buttons -->
										<div class="flex justify-center space-x-4 pt-6 border-t border-slate-700/50">
											<button type="button" onclick="closeDepartmentApprovalModal()" class="bg-slate-600 hover:bg-slate-500 text-white font-semibold py-3 px-8 rounded-xl transition-colors">
												<i class="fas fa-times mr-2"></i>Cancel
											</button>
											
											<!-- Initial Action Buttons -->
											<div id="initialActionButtons">
												<button onclick="approveRequest(${requestId})" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-8 rounded-xl transition-colors">
													<i class="fas fa-check mr-2"></i>Approve Request
												</button>
												<button onclick="showRejectionOptions()" class="bg-red-600 hover:bg-red-700 text-white font-semibold py-3 px-8 rounded-xl transition-colors">
													<i class="fas fa-times mr-2"></i>Reject Request
												</button>
											</div>
											
											<!-- Rejection Action Button -->
											<div id="rejectionActionButton" style="display: none;">
												<button onclick="rejectRequest(${requestId})" class="bg-red-600 hover:bg-red-700 text-white font-semibold py-3 px-8 rounded-xl transition-colors">
													<i class="fas fa-times mr-2"></i>Submit Rejection
												</button>
											</div>
										</div>
									</div>
								</div>
							</div>
						`;

						// Remove existing modal if any
						const existingModal = document.getElementById('departmentApprovalModal');
						if (existingModal) {
							existingModal.remove();
						}

						// Add modal to body
						document.body.insertAdjacentHTML('beforeend', modalHtml);
						
						// Show conditional details based on leave type
						showConditionalDetails(request.leave_type);
					} else {
						console.error('API Error:', data);
						alert('Error loading leave request details: ' + (data.message || 'Unknown error'));
					}
				})
				.catch(error => {
					console.error('Fetch Error:', error);
					alert('Error loading leave request details: ' + error.message);
				});
		}

		function closeDepartmentApprovalModal() {
			const modal = document.getElementById('departmentApprovalModal');
			if (modal) {
				modal.remove();
			}
		}

		function showRejectionOptions() {
			document.getElementById('rejectionOptionsSection').style.display = 'block';
			document.getElementById('initialActionButtons').style.display = 'none';
			document.getElementById('rejectionActionButton').style.display = 'inline-block';
		}

		function approveRequest(requestId) {
			if (confirm('Are you sure you want to approve this leave request?')) {
				// Disable the button to prevent multiple clicks
				const approveButton = event.target;
				approveButton.disabled = true;
				approveButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
				
				// Create and show processing modal
				const processingModalHtml = `
					<div id="processingModal" class="fixed inset-0 bg-black bg-opacity-30 flex items-center justify-center z-50">
						<div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl max-w-md w-full mx-4">
							<div class="p-6 text-center">
								<div class="mb-4">
									<div class="inline-flex items-center justify-center w-16 h-16 bg-slate-700 rounded-full mb-4">
										<i class="fas fa-spinner fa-spin text-blue-400 text-2xl"></i>
									</div>
									<h3 class="text-lg font-semibold text-white mb-2">Processing Your Approval</h3>
									<p class="text-slate-300 mb-4">Please wait while we process the leave request approval...</p>
								</div>
								<div class="flex items-center justify-center space-x-2 text-sm text-slate-400">
									<div class="w-2 h-2 bg-blue-400 rounded-full animate-bounce"></div>
									<div class="w-2 h-2 bg-blue-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
									<div class="w-2 h-2 bg-blue-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
								</div>
							</div>
						</div>
					</div>
				`;
				
				// Remove existing processing modal if any
				const existingProcessingModal = document.getElementById('processingModal');
				if (existingProcessingModal) {
					existingProcessingModal.remove();
				}
				
				// Add processing modal to body
				document.body.insertAdjacentHTML('beforeend', processingModalHtml);
				
				// Add rendering delay: keep approval modal visible for 1.5 seconds
				setTimeout(() => {
					// Hide the approval modal
					closeDepartmentApprovalModal();
					
					// Submit the form after delay
					const form = document.createElement('form');
					form.method = 'POST';
					form.action = 'approve_leave.php';
					
					const requestIdInput = document.createElement('input');
					requestIdInput.type = 'hidden';
					requestIdInput.name = 'request_id';
					requestIdInput.value = requestId;
					
					const actionInput = document.createElement('input');
					actionInput.type = 'hidden';
					actionInput.name = 'action';
					actionInput.value = 'approve';
					
					form.appendChild(requestIdInput);
					form.appendChild(actionInput);
					document.body.appendChild(form);
					form.submit();
				}, 1500); // Keep approval modal visible for 1.5 seconds
			}
		}

		function rejectRequest(requestId) {
			const reason = document.getElementById('rejectionReasonText').value.trim();
			if (!reason) {
				alert('Please provide a reason for rejection');
				return;
			}
			
			if (confirm('Are you sure you want to reject this leave request?')) {
				// Disable the button to prevent multiple clicks
				const rejectButton = event.target;
				rejectButton.disabled = true;
				rejectButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
				
				// Create and show processing modal
				const processingModalHtml = `
					<div id="processingModal" class="fixed inset-0 bg-black bg-opacity-30 flex items-center justify-center z-50">
						<div class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl max-w-md w-full mx-4">
							<div class="p-6 text-center">
								<div class="mb-4">
									<div class="inline-flex items-center justify-center w-16 h-16 bg-slate-700 rounded-full mb-4">
										<i class="fas fa-spinner fa-spin text-blue-400 text-2xl"></i>
									</div>
									<h3 class="text-lg font-semibold text-white mb-2">Processing Your Rejection</h3>
									<p class="text-slate-300 mb-4">Please wait while we process the leave request rejection...</p>
								</div>
								<div class="flex items-center justify-center space-x-2 text-sm text-slate-400">
									<div class="w-2 h-2 bg-blue-400 rounded-full animate-bounce"></div>
									<div class="w-2 h-2 bg-blue-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
									<div class="w-2 h-2 bg-blue-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
								</div>
							</div>
						</div>
					</div>
				`;
				
				// Remove existing processing modal if any
				const existingProcessingModal = document.getElementById('processingModal');
				if (existingProcessingModal) {
					existingProcessingModal.remove();
				}
				
				// Add processing modal to body
				document.body.insertAdjacentHTML('beforeend', processingModalHtml);
				
				// Add rendering delay: keep approval modal visible for 1.5 seconds
				setTimeout(() => {
					// Hide the approval modal
					closeDepartmentApprovalModal();
					
					// Submit the form after delay
					const form = document.createElement('form');
					form.method = 'POST';
					form.action = 'approve_leave.php';
					
					const requestIdInput = document.createElement('input');
					requestIdInput.type = 'hidden';
					requestIdInput.name = 'request_id';
					requestIdInput.value = requestId;
					
					const actionInput = document.createElement('input');
					actionInput.type = 'hidden';
					actionInput.name = 'action';
					actionInput.value = 'reject';
					
					const reasonInput = document.createElement('input');
					reasonInput.type = 'hidden';
					reasonInput.name = 'reason';
					reasonInput.value = reason;
					
					form.appendChild(requestIdInput);
					form.appendChild(actionInput);
					form.appendChild(reasonInput);
					document.body.appendChild(form);
					form.submit();
				}, 1500); // Keep approval modal visible for 1.5 seconds
			}
		}
		
		// Function to show conditional details based on leave type
		function showConditionalDetails(leaveType) {
			// Hide all conditional detail sections first
			const vacationDetails = document.getElementById('vacationDetails');
			const sickDetails = document.getElementById('sickDetails');
			const specialWomenDetails = document.getElementById('specialWomenDetails');
			const studyDetails = document.getElementById('studyDetails');
			const conditionalSection = document.getElementById('conditionalDetailsSection');
			
			if (vacationDetails) vacationDetails.style.display = 'none';
			if (sickDetails) sickDetails.style.display = 'none';
			if (specialWomenDetails) specialWomenDetails.style.display = 'none';
			if (studyDetails) studyDetails.style.display = 'none';
			
			// Show relevant section based on leave type
			if (leaveType === 'vacation' || leaveType === 'special_privilege' || leaveType === 'Vacation' || leaveType === 'Special Privilege') {
				if (vacationDetails) vacationDetails.style.display = 'block';
			} else if (leaveType === 'sick' || leaveType === 'Sick') {
				if (sickDetails) sickDetails.style.display = 'block';
			} else if (leaveType === 'special_women' || leaveType === 'Special Women') {
				if (specialWomenDetails) specialWomenDetails.style.display = 'block';
			} else if (leaveType === 'study' || leaveType === 'Study') {
				if (studyDetails) studyDetails.style.display = 'block';
			}
			
			// Hide the entire conditional section if no relevant details
			const hasRelevantDetails = (leaveType === 'vacation' || leaveType === 'special_privilege' || 
										leaveType === 'sick' || leaveType === 'special_women' || leaveType === 'study' ||
										leaveType === 'Vacation' || leaveType === 'Special Privilege' || 
										leaveType === 'Sick' || leaveType === 'Special Women' || leaveType === 'Study');
			if (conditionalSection) {
				conditionalSection.style.display = hasRelevantDetails ? 'block' : 'none';
			}
		}

		// View More and Show Less functionality
		document.addEventListener('DOMContentLoaded', function() {
			const loadMoreBtn = document.getElementById('loadMoreBtn');
			const showLessBtn = document.getElementById('showLessBtn');
			const additionalRequestsContainer = document.getElementById('additionalRequests');
			const tableBody = document.querySelector('tbody');
			
			if (loadMoreBtn && showLessBtn && additionalRequestsContainer && tableBody) {
				let currentOffset = 5; // Start from offset 5 (since we initially show 5)
				let isLoading = false;
				let totalAdditionalRows = 0; // Store count of additional rows for show less functionality
				
				loadMoreBtn.addEventListener('click', async function() {
					if (isLoading) return;
					
					isLoading = true;
					loadMoreBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Loading...';
					loadMoreBtn.disabled = true;
					
					try {
						const response = await fetch(`../api/get_more_requests.php?offset=${currentOffset}`);
						const data = await response.json();
						
						if (data.success && data.html) {
							// Append new rows to the existing table
							tableBody.insertAdjacentHTML('beforeend', data.html);
							
							// Store the count of additional rows added
							totalAdditionalRows += data.count;
							
							// Update offset for next request
							currentOffset += data.count;
							
							// Show the Show Less button
							showLessBtn.classList.remove('hidden');
							
							// Hide the View More button if no more requests
							if (!data.hasMore || data.count < 10) {
								loadMoreBtn.style.display = 'none';
							} else {
								// Update button text with remaining count
								const remainingCount = data.hasMore ? 'more' : '0';
								loadMoreBtn.innerHTML = `<i class="fas fa-plus mr-2"></i>View More (${remainingCount} more)`;
								loadMoreBtn.disabled = false;
							}
						} else {
							console.error('Failed to load more requests:', data.message);
							loadMoreBtn.innerHTML = '<i class="fas fa-exclamation-triangle mr-2"></i>Error loading requests';
						}
					} catch (error) {
						console.error('Error:', error);
						loadMoreBtn.innerHTML = '<i class="fas fa-exclamation-triangle mr-2"></i>Error loading requests';
					}
					
					isLoading = false;
				});
				
				showLessBtn.addEventListener('click', function() {
					// Remove all additional rows by removing the last N rows
					const allRows = tableBody.querySelectorAll('tr');
					const rowsToRemove = totalAdditionalRows;
					
					// Remove rows from the end
					for (let i = allRows.length - 1; i >= allRows.length - rowsToRemove; i--) {
						if (allRows[i]) {
							allRows[i].remove();
						}
					}
					
					// Reset the counter
					totalAdditionalRows = 0;
					
					// Reset offset
					currentOffset = 5;
					
					// Hide Show Less button
					showLessBtn.classList.add('hidden');
					
					// Show View More button again
					loadMoreBtn.style.display = 'inline-flex';
					loadMoreBtn.innerHTML = '<i class="fas fa-plus mr-2"></i>View More (<?php echo $total_pending - $initial_limit; ?> more)';
					loadMoreBtn.disabled = false;
				});
			}
		});
	</script>
</body>
</html>
