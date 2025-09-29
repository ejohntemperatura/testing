<?php
session_start();
require_once '../config/database.php';

// Auto-process emails when internet is available
require_once '../includes/auto_email_processor.php';

// Director or Admin access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['director','admin'])) {
	header('Location: ../auth/index.php');
	exit();
}

$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$me = $stmt->fetch();

// Get stats for dashboard
$stmt = $pdo->query("SELECT COUNT(*) as total FROM employees");
$total_employees = $stmt->fetch()['total'];

$stmt = $pdo->query("
    SELECT COUNT(*) as approved 
    FROM leave_requests 
    WHERE director_approval = 'approved'
");
$approved_this_month = $stmt->fetch()['approved'];

// Get pending leave requests for stats
$stmt = $pdo->prepare("
    SELECT lr.*, e.name as employee_name, e.position, e.department 
    FROM leave_requests lr 
    JOIN employees e ON lr.employee_id = e.id 
    WHERE lr.dept_head_approval = 'approved'
    AND (lr.director_approval IS NULL OR lr.director_approval = 'pending')
    AND lr.status != 'rejected'
    ORDER BY lr.created_at DESC 
    LIMIT 10
");
$stmt->execute();
$pending_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- OFFLINE Tailwind CSS - No internet required! -->
    <link rel="stylesheet" href="../assets/css/tailwind.css">
        <!-- Font Awesome Local - No internet required! -->
    <link rel="stylesheet" href="../assets/libs/fontawesome/css/all.min.css">
    
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dark-theme.css">
    <script src="../assets/libs/chartjs/chart.umd.min.js"></script>



    
	
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dark-theme.css">
    <style>
        /* Fix navbar dropdown z-index for director dashboard */
        #userDropdown {
            z-index: 1300 !important;
        }
        nav {
            z-index: 1200 !important;
        }
    </style>
</head>
<body class="bg-slate-900 text-white">
	<?php include '../includes/unified_navbar.php'; ?>

	<div class="flex">
		<!-- Left Sidebar -->
		<aside id="sidebar" class="fixed left-0 top-16 h-screen w-64 bg-slate-900 border-r border-slate-800 overflow-y-auto z-40">
			<nav class="p-4 space-y-2">
				<!-- Active Navigation Item -->
				<a href="director_head_dashboard.php" class="flex items-center space-x-3 px-4 py-3 text-white bg-blue-500/20 rounded-lg border border-blue-500/30">
					<i class="fas fa-tachometer-alt w-5"></i>
					<span>Dashboard</span>
				</a>
				
				<!-- Section Headers -->
				<div class="space-y-1">
					<h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2">Management</h3>
					
					<!-- Navigation Items -->
					<a href="director_view_chart.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
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
							<div class="w-16 h-16 bg-gradient-to-r from-slate-700 to-slate-800 rounded-xl flex items-center justify-center shadow-lg flex-shrink-0">
								<i class="fas fa-crown text-2xl text-white"></i>
							</div>
							<div class="flex-1">
								<h1 class="text-3xl font-bold text-white mb-2 leading-tight">Welcome, <?php echo htmlspecialchars($me['name'] ?? 'Director Head'); ?>!</h1>
								<p class="text-slate-400 text-lg leading-relaxed">Executive overview and actions</p>
							</div>
						</div>
						
						<!-- Notification Button -->
					</div>
				</div>

				<!-- Quick Stats -->
				<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
					<div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 p-6">
						<div class="flex items-center justify-between">
							<div>
								<p class="text-slate-400 text-sm font-medium">Total Employees</p>
								<p class="text-2xl font-bold text-white"><?php echo $total_employees; ?></p>
							</div>
							<div class="w-12 h-12 bg-blue-500/20 rounded-lg flex items-center justify-center">
								<i class="fas fa-users text-blue-400 text-xl"></i>
							</div>
						</div>
					</div>
					
					<div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 p-6">
						<div class="flex items-center justify-between">
							<div>
								<p class="text-slate-400 text-sm font-medium">Pending Approvals</p>
								<p class="text-2xl font-bold text-white"><?php echo count($pending_requests); ?></p>
							</div>
							<div class="w-12 h-12 bg-yellow-500/20 rounded-lg flex items-center justify-center">
								<i class="fas fa-clock text-yellow-400 text-xl"></i>
							</div>
						</div>
					</div>
					
					<div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 p-6">
						<div class="flex items-center justify-between">
							<div>
								<p class="text-slate-400 text-sm font-medium">Total Approved</p>
								<p class="text-2xl font-bold text-white"><?php echo $approved_this_month; ?></p>
							</div>
							<div class="w-12 h-12 bg-slate-600/20 rounded-lg flex items-center justify-center">
								<i class="fas fa-check-circle text-slate-400 text-xl"></i>
							</div>
						</div>
					</div>
				</div>

				<!-- Pending Leave Requests -->
				<div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 overflow-hidden mb-8">
					<div class="px-6 py-5 border-b border-slate-700/50 bg-slate-700/30">
						<h3 class="text-xl font-semibold text-white flex items-center">
							<i class="fas fa-clock text-yellow-400 mr-3"></i>Pending Leave Requests
						</h3>
					</div>
					<div class="p-8">
						
						<?php if (empty($pending_requests)): ?>
							<div class="text-center py-16">
								<div class="w-20 h-20 bg-gradient-to-r from-slate-600/20 to-slate-700/20 rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg">
									<i class="fas fa-check-circle text-slate-400 text-3xl"></i>
								</div>
								<h4 class="text-xl font-semibold text-white mb-3">All Caught Up!</h4>
								<p class="text-slate-400 text-lg mb-4">No pending leave requests at the moment</p>
								<div class="inline-flex items-center px-4 py-2 bg-slate-600/10 border border-slate-600/20 rounded-lg">
									<i class="fas fa-thumbs-up text-slate-400 mr-2"></i>
									<span class="text-slate-400 font-medium">Great job staying on top of approvals!</span>
								</div>
							</div>
						<?php else: ?>
							<div class="overflow-x-auto">
								<table class="w-full">
									<thead>
										<tr class="border-b border-slate-700/50">
											<th class="text-left py-3 px-4 text-sm font-semibold text-slate-300">Employee</th>
											<th class="text-left py-3 px-4 text-sm font-semibold text-slate-300">Leave Type</th>
											<th class="text-left py-3 px-4 text-sm font-semibold text-slate-300">Start Date</th>
											<th class="text-left py-3 px-4 text-sm font-semibold text-slate-300">End Date</th>
											<th class="text-left py-3 px-4 text-sm font-semibold text-slate-300">Days</th>
											<th class="text-left py-3 px-4 text-sm font-semibold text-slate-300">Reason</th>
											<th class="text-left py-3 px-4 text-sm font-semibold text-slate-300">Status</th>
											<th class="text-left py-3 px-4 text-sm font-semibold text-slate-300">Actions</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($pending_requests as $request): ?>
											<tr class="border-b border-slate-700/50/30 hover:bg-slate-700/30 transition-colors">
												<td class="py-4 px-4">
													<div>
														<div class="font-semibold text-white"><?php echo htmlspecialchars($request['employee_name']); ?></div>
														<div class="text-sm text-slate-400"><?php echo htmlspecialchars($request['position']); ?></div>
													</div>
												</td>
												<td class="py-4 px-4">
													<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-primary/20 text-primary border border-primary/30">
														<?php echo ucfirst(str_replace('_', ' ', $request['leave_type'])); ?>
													</span>
												</td>
												<td class="py-4 px-4 text-white"><?php echo date('M d, Y', strtotime($request['start_date'])); ?></td>
												<td class="py-4 px-4 text-white"><?php echo date('M d, Y', strtotime($request['end_date'])); ?></td>
												<td class="py-4 px-4">
													<span class="inline-flex items-center justify-center w-8 h-8 bg-slate-700 rounded-full text-sm font-semibold text-white">
														<?php 
														$start = new DateTime($request['start_date']);
														$end = new DateTime($request['end_date']);
														$days = $start->diff($end)->days + 1;
														echo $days;
														?>
													</span>
												</td>
												<td class="py-4 px-4">
													<span class="text-slate-300" title="<?php echo htmlspecialchars($request['reason']); ?>">
														<?php echo strlen($request['reason']) > 30 ? substr(htmlspecialchars($request['reason']), 0, 30) . '...' : htmlspecialchars($request['reason']); ?>
													</span>
												</td>
												<td class="py-4 px-4">
													<div class="flex items-center gap-2">
														<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-500/20 text-yellow-400 border border-yellow-500/30">
															Pending
														</span>
														<button type="button" class="p-1 text-slate-400 hover:text-white transition-colors" 
																onclick="showStatusInfo(<?php echo $request['id']; ?>)"
																title="View Status Details">
															<i class="fas fa-info-circle text-sm"></i>
														</button>
													</div>
												</td>
												<td class="py-4 px-4">
                                                <div class="flex items-center gap-2">
                                                    <button onclick="openDirectorApprovalModal(<?php echo $request['id']; ?>)" class="inline-flex items-center px-4 py-2 bg-primary hover:bg-primary/80 text-white text-sm font-medium rounded-lg transition-colors">
                                                        <i class="fas fa-gavel mr-2"></i> Process Request
                                                    </button>
                                                </div>
												</td>
												</tr>
											<?php endforeach; ?>
										</tbody>
									</table>
								</div>
								<div class="text-center mt-6">
									<a href="director_leave_management.php" class="inline-flex items-center px-4 py-2 bg-primary/20 hover:bg-primary/30 text-primary border border-primary/30 rounded-xl transition-colors">
										<i class="fas fa-eye mr-2"></i>View All Requests
									</a>
								</div>
							<?php endif; ?>
					</div>
				</div>

				<!-- Quick Actions Grid -->
				<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
					<!-- Analytics & Reports Card -->
					<div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 overflow-hidden hover:border-slate-600/50 transition-all duration-300 group">
						<div class="p-6">
							<div class="flex items-center mb-4">
								<div class="w-12 h-12 bg-gradient-to-r from-slate-600 to-slate-700 rounded-xl flex items-center justify-center mr-4 group-hover:scale-110 transition-transform duration-300">
									<i class="fas fa-chart-bar text-white text-lg"></i>
								</div>
								<h3 class="text-xl font-semibold text-white">Analytics & Reports</h3>
							</div>
							<p class="text-slate-400 mb-6">Download and view detailed leave analytics and comprehensive reports across the organization.</p>
							<a href="reports.php" class="inline-flex items-center px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-xl transition-colors group-hover:bg-secondary group-hover:text-white">
								<i class="fas fa-file-export mr-2"></i>Open Reports
							</a>
						</div>
					</div>

					<!-- Leave Management Card -->
					<div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 overflow-hidden hover:border-slate-600/50 transition-all duration-300 group">
						<div class="p-6">
							<div class="flex items-center mb-4">
								<div class="w-12 h-12 bg-gradient-to-r from-slate-600 to-slate-700 rounded-xl flex items-center justify-center mr-4 group-hover:scale-110 transition-transform duration-300">
									<i class="fas fa-calendar-check text-white text-lg"></i>
								</div>
								<h3 class="text-xl font-semibold text-white">Leave Management</h3>
							</div>
							<p class="text-slate-400 mb-6">Review and manage all leave requests across the organization with comprehensive oversight tools.</p>
							<a href="director_leave_management.php" class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-xl transition-colors">
								<i class="fas fa-clipboard-check mr-2"></i>Manage Leaves
							</a>
						</div>
					</div>
				</div>
			</div>
		</main>
	</div>

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

		// Show status information modal
		function showStatusInfo(leaveId) {
			// Create modal HTML
			const modalHtml = `
				<div id="statusInfoModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
					<div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 max-w-2xl w-full max-h-[90vh] overflow-y-auto">
						<div class="px-6 py-4 border-b border-slate-700/50 bg-slate-700/30">
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
								<p class="text-slate-300">This leave request has been <strong class="text-white">approved by the department head</strong> and is now waiting for your decision.</p>
							</div>
							<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
								<div class="bg-slate-700/50 rounded-xl p-4">
									<h4 class="text-green-400 font-semibold mb-3 flex items-center">
										<i class="fas fa-user-tie mr-2"></i>Department Head
									</h4>
									<p class="text-slate-300 mb-2"><strong class="text-white">Status:</strong> 
										<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-500/20 text-green-400 border border-green-500/30 ml-2">Approved</span>
									</p>
									<p class="text-slate-400 text-sm">Already reviewed and approved</p>
								</div>
								<div class="bg-slate-700/50 rounded-xl p-4">
									<h4 class="text-primary font-semibold mb-3 flex items-center">
										<i class="fas fa-user-tie mr-2"></i>Director (You)
									</h4>
									<p class="text-slate-300 mb-2"><strong class="text-white">Status:</strong> 
										<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-500/20 text-yellow-400 border border-yellow-500/30 ml-2">Pending</span>
									</p>
									<p class="text-slate-400 text-sm">Action Required: Approve or Reject this request</p>
								</div>
							</div>
							<div class="border-t border-slate-700/50 pt-6">
								<div class="bg-slate-700/50 rounded-xl p-4">
									<h4 class="text-slate-400 font-semibold mb-3 flex items-center">
										<i class="fas fa-user-shield mr-2"></i>Admin
									</h4>
									<p class="text-slate-300"><strong class="text-white">Status:</strong> 
										<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-slate-500/20 text-slate-400 border border-slate-500/30 ml-2">Waiting</span>
									</p>
									<p class="text-slate-400 text-sm">Will review after your decision</p>
								</div>
							</div>
							<div class="border-t border-slate-700/50 pt-6">
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
		

		// Director Approval Modal Functions
		function openDirectorApprovalModal(requestId) {
			const modalHtml = `
				<div id="directorApprovalModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
					<div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-8 w-full max-w-lg mx-4 border border-slate-700/50">
						<div class="flex items-center justify-between mb-6">
							<h5 class="text-2xl font-bold text-white flex items-center">
								<i class="fas fa-gavel text-primary mr-3"></i>
								Process Leave Request
							</h5>
							<button type="button" onclick="closeDirectorApprovalModal()" class="text-slate-400 hover:text-white transition-colors">
								<i class="fas fa-times text-xl"></i>
							</button>
						</div>
						
						<form method="POST" action="approve_leave.php" class="space-y-6" id="directorApprovalForm">
							<input type="hidden" name="request_id" value="${requestId}">
							<input type="hidden" name="action" value="" id="selectedAction">
							
							<!-- Approval Type Selection -->
							<div>
								<label class="block text-sm font-semibold text-slate-300 mb-3">
									<i class="fas fa-list-check mr-2"></i>
									Select Approval Type <span class="text-red-400">*</span>
								</label>
								<div class="space-y-3">
									<label class="flex items-center p-3 bg-slate-700/50 rounded-lg border border-slate-600 hover:bg-slate-700 cursor-pointer transition-colors">
										<input type="radio" name="approval_type" value="approve_with_pay" class="mr-3 text-green-500 focus:ring-green-500" onchange="toggleDaysInput('with_pay')">
										<div class="flex-1">
											<div class="text-white font-medium">Approve with Pay</div>
											<div class="text-slate-400 text-sm">Employee will receive salary during leave</div>
										</div>
									</label>
									
									<label class="flex items-center p-3 bg-slate-700/50 rounded-lg border border-slate-600 hover:bg-slate-700 cursor-pointer transition-colors">
										<input type="radio" name="approval_type" value="approve_without_pay" class="mr-3 text-blue-500 focus:ring-blue-500" onchange="toggleDaysInput('without_pay')">
										<div class="flex-1">
											<div class="text-white font-medium">Approve without Pay</div>
											<div class="text-slate-400 text-sm">Employee will not receive salary during leave</div>
										</div>
									</label>
									
									<label class="flex items-center p-3 bg-slate-700/50 rounded-lg border border-slate-600 hover:bg-slate-700 cursor-pointer transition-colors">
										<input type="radio" name="approval_type" value="reject" class="mr-3 text-red-500 focus:ring-red-500" onchange="toggleDaysInput('reject')">
										<div class="flex-1">
											<div class="text-white font-medium">Reject Request</div>
											<div class="text-slate-400 text-sm">Deny the leave request</div>
										</div>
									</label>
									
									<label class="flex items-center p-3 bg-slate-700/50 rounded-lg border border-slate-600 hover:bg-slate-700 cursor-pointer transition-colors">
										<input type="radio" name="approval_type" value="others" class="mr-3 text-purple-500 focus:ring-purple-500" onchange="toggleDaysInput('others')">
										<div class="flex-1">
											<div class="text-white font-medium">Others (Specify)</div>
											<div class="text-slate-400 text-sm">Other approval decision</div>
										</div>
									</label>
								</div>
							</div>
							
							<!-- Days Input for With Pay/Without Pay -->
							<div id="daysInputSection" class="hidden">
								<label for="days_input" class="block text-sm font-semibold text-slate-300 mb-2">
									<i class="fas fa-calendar-days mr-2"></i>
									Number of Days <span class="text-red-400">*</span>
								</label>
								<input type="number" id="days_input" name="days_input" min="1" max="365" 
									placeholder="Enter number of days" 
									class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
							</div>
							
							<!-- Others Specify Input -->
							<div id="othersSpecifySection" class="hidden">
								<label for="others_specify" class="block text-sm font-semibold text-slate-300 mb-2">
									<i class="fas fa-edit mr-2"></i>
									Specify Approval Decision <span class="text-red-400">*</span>
								</label>
								<input type="text" id="others_specify" name="others_specify" 
									placeholder="Enter custom approval decision..." 
									class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
							</div>
							
							<!-- Rejection Reason -->
							<div id="rejectionReasonSection" class="hidden">
								<label for="rejection_reason" class="block text-sm font-semibold text-slate-300 mb-2">
									<i class="fas fa-comment-alt mr-2"></i>
									Rejection Reason <span class="text-red-400">*</span>
								</label>
								<textarea id="rejection_reason" name="rejection_reason" rows="4" 
									placeholder="Please provide a detailed reason for rejection..." 
									class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent resize-none"></textarea>
							</div>
							
							
							<div class="flex justify-end space-x-4 pt-6">
								<button type="button" onclick="closeDirectorApprovalModal()" class="bg-slate-600 hover:bg-slate-500 text-white font-semibold py-3 px-6 rounded-xl transition-colors">
									<i class="fas fa-times mr-2"></i>Cancel
								</button>
								<button type="submit" id="submitButton" class="bg-gradient-to-r from-primary to-primary/80 hover:from-primary/80 hover:to-primary text-white font-semibold py-3 px-6 rounded-xl transition-all duration-300 transform hover:scale-[1.02]">
									<i class="fas fa-check mr-2"></i>Process Request
								</button>
							</div>
						</form>
					</div>
				</div>
			`;

			// Remove existing modal if any
			const existingModal = document.getElementById('directorApprovalModal');
			if (existingModal) {
				existingModal.remove();
			}

			// Add modal to body
			document.body.insertAdjacentHTML('beforeend', modalHtml);
		}
		
		// Toggle form sections based on approval type
		function toggleDaysInput(type) {
			const daysSection = document.getElementById('daysInputSection');
			const othersSection = document.getElementById('othersSpecifySection');
			const rejectionSection = document.getElementById('rejectionReasonSection');
			const submitButton = document.getElementById('submitButton');
			const actionInput = document.getElementById('selectedAction');
			
			// Hide all sections first
			daysSection.classList.add('hidden');
			othersSection.classList.add('hidden');
			rejectionSection.classList.add('hidden');
			
			// Show relevant section and update button
			if (type === 'with_pay' || type === 'without_pay') {
				daysSection.classList.remove('hidden');
				actionInput.value = type === 'with_pay' ? 'approve_with_pay' : 'approve_without_pay';
				submitButton.innerHTML = '<i class="fas fa-check mr-2"></i>' + (type === 'with_pay' ? 'Approve with Pay' : 'Approve without Pay');
				submitButton.className = 'bg-gradient-to-r ' + (type === 'with_pay' ? 'from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700' : 'from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700') + ' text-white font-semibold py-3 px-6 rounded-xl transition-all duration-300 transform hover:scale-[1.02]';
			} else if (type === 'reject') {
				rejectionSection.classList.remove('hidden');
				actionInput.value = 'reject';
				submitButton.innerHTML = '<i class="fas fa-times mr-2"></i>Reject Request';
				submitButton.className = 'bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-300 transform hover:scale-[1.02]';
			} else if (type === 'others') {
				othersSection.classList.remove('hidden');
				actionInput.value = 'others';
				submitButton.innerHTML = '<i class="fas fa-edit mr-2"></i>Process Others';
				submitButton.className = 'bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-300 transform hover:scale-[1.02]';
			}
		}

		function closeDirectorApprovalModal() {
			const modal = document.getElementById('directorApprovalModal');
			if (modal) {
				modal.remove();
			}
		}
	</script>
</body>
</html>


