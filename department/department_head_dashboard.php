<?php
session_start();
require_once '../config/database.php';

// Auto-process emails when internet is available
require_once '../includes/auto_email_processor.php';

// Allow admin or manager (department head) to access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin','manager'])) {
	header('Location: ../auth/index.php');
	exit();
}

// Basic user info
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$me = $stmt->fetch();
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
	
</head>
<body class="bg-slate-900 text-white">
	<?php include '../includes/unified_navbar.php'; ?>

	<div class="flex">
		<!-- Left Sidebar -->
		<aside id="sidebar" class="fixed left-0 top-16 h-screen w-64 bg-slate-900 border-r border-slate-800 overflow-y-auto z-40">
			<nav class="p-4 space-y-2">
				<!-- Active Navigation Item -->
				<a href="department_head_dashboard.php" class="flex items-center space-x-3 px-4 py-3 text-white bg-blue-500/20 rounded-lg border border-blue-500/30">
					<i class="fas fa-tachometer-alt w-5"></i>
					<span>Dashboard</span>
				</a>
				
				<!-- Section Headers -->
				<div class="space-y-1">
					<h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2">Management</h3>
					
					<!-- Navigation Items -->
					<a href="view_chart.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
						<i class="fas fa-chart-line w-5"></i>
						<span>View Chart</span>
					</a>
				</div>
				
				<!-- Quick Actions Section -->
				<div class="space-y-1 mt-6">
					<h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2">Quick Actions</h3>
					
					<!-- View Chart Card in Sidebar -->
					<div class="bg-slate-800/50 backdrop-blur-sm rounded-xl border border-slate-700/50 overflow-hidden hover:border-slate-600/50 transition-all duration-300 group mx-2 mb-4">
						<div class="p-4">
							<div class="flex items-center mb-3">
								<div class="w-10 h-10 bg-gradient-to-r from-primary to-accent rounded-lg flex items-center justify-center mr-3 group-hover:scale-110 transition-transform duration-300">
									<i class="fas fa-chart-line text-white text-sm"></i>
								</div>
								<h4 class="text-lg font-semibold text-white">View Chart</h4>
							</div>
							<p class="text-slate-400 text-sm mb-4">See department leave patterns and timelines in the unified calendar with visual analytics.</p>
							<a href="view_chart.php" class="inline-flex items-center px-3 py-2 bg-primary hover:bg-primary/80 text-white rounded-lg transition-colors text-sm w-full justify-center">
								<i class="fas fa-calendar mr-2"></i>Open Calendar
							</a>
						</div>
					</div>
				</div>
				
				<div class="space-y-1">
					<h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider px-4 py-2">Reports</h3>
					
					<a href="reports.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
						<i class="fas fa-file-alt w-5"></i>
						<span>Reports</span>
					</a>
					
					<a href="audit_logs.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
						<i class="fas fa-history w-5"></i>
						<span>Audit Logs</span>
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
							// Get pending leave requests (only those not yet decided by department head)
							$stmt = $pdo->prepare("
								SELECT lr.*, e.name as employee_name, e.position, e.department 
								FROM leave_requests lr 
								JOIN employees e ON lr.employee_id = e.id 
								WHERE (lr.dept_head_approval IS NULL OR lr.dept_head_approval = 'pending')
								AND lr.status != 'rejected'
								ORDER BY lr.created_at DESC 
								LIMIT 10
							");
							$stmt->execute();
							$pending_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
													<span class="bg-primary/20 text-primary px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide">
														<?php echo ucfirst(str_replace('_', ' ', $request['leave_type'])); ?>
													</span>
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
													<div class="flex gap-2 justify-center">
														<form method="POST" action="approve_leave.php" class="inline">
															<input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
															<input type="hidden" name="action" value="approve">
															<button type="submit" onclick="return confirm('Mark this leave request for approval?')" class="bg-slate-600 hover:bg-slate-700 text-white px-3 py-1 rounded-lg text-sm transition-colors">
																<i class="fas fa-check mr-1"></i> For Approval
															</button>
														</form>
														<button onclick="openDisapprovalModal(<?php echo $request['id']; ?>)" class="bg-slate-700 hover:bg-slate-800 text-white px-3 py-1 rounded-lg text-sm transition-colors">
															<i class="fas fa-times mr-1"></i> For Disapproval
														</button>
													</div>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
							<div class="text-center mt-6">
								<a href="leave_management.php" class="bg-primary/20 hover:bg-primary/30 text-primary border border-primary/30 font-semibold py-3 px-6 rounded-xl transition-colors">
									View All Requests
								</a>
							</div>
						<?php endif; ?>
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
										<i class="fas fa-user-shield mr-2"></i>Admin
									</h4>
									<p class="text-slate-300"><strong class="text-white">Status:</strong> 
										<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-slate-500/20 text-slate-400 border border-slate-500/30 ml-2">Waiting</span>
									</p>
									<p class="text-slate-400 text-sm">Will make final decision after all approvals</p>
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


		// Disapproval Modal Functions
		function openDisapprovalModal(requestId) {
			const modalHtml = `
				<div id="disapprovalModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
					<div class="bg-slate-800 rounded-2xl p-8 w-full max-w-md mx-4 border border-slate-700">
						<div class="flex items-center justify-between mb-6">
							<h5 class="text-2xl font-bold text-white flex items-center">
								<i class="fas fa-times-circle text-red-400 mr-3"></i>
								For Disapproval
							</h5>
							<button type="button" onclick="closeDisapprovalModal()" class="text-slate-400 hover:text-white transition-colors">
								<i class="fas fa-times text-xl"></i>
							</button>
						</div>
						
						<form method="POST" action="approve_leave.php" class="space-y-6">
							<input type="hidden" name="request_id" value="${requestId}">
							<input type="hidden" name="action" value="reject">
							
							<div>
								<label for="reason" class="block text-sm font-semibold text-slate-300 mb-2">
									<i class="fas fa-comment-alt mr-2"></i>
									Disapproval Reason <span class="text-red-400">*</span>
								</label>
								<textarea id="reason" name="reason" rows="4" 
									placeholder="Please provide a detailed reason for disapproval..." 
									required 
									class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent resize-none"></textarea>
							</div>
							
							<div class="flex justify-end space-x-4 pt-6">
								<button type="button" onclick="closeDisapprovalModal()" class="bg-slate-600 hover:bg-slate-500 text-white font-semibold py-3 px-6 rounded-xl transition-colors">
									<i class="fas fa-times mr-2"></i>Cancel
								</button>
								<button type="submit" class="bg-gradient-to-r from-slate-700 to-slate-800 hover:from-slate-800 hover:to-slate-900 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-300 transform hover:scale-[1.02]">
									<i class="fas fa-times mr-2"></i>Mark for Disapproval
								</button>
							</div>
						</form>
					</div>
				</div>
			`;

			// Remove existing modal if any
			const existingModal = document.getElementById('disapprovalModal');
			if (existingModal) {
				existingModal.remove();
			}

			// Add modal to body
			document.body.insertAdjacentHTML('beforeend', modalHtml);
		}

		function closeDisapprovalModal() {
			const modal = document.getElementById('disapprovalModal');
			if (modal) {
				modal.remove();
			}
		}
	</script>
</body>
</html>
