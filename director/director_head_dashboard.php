<?php
session_start();
require_once '../config/database.php';

// Director or Admin access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['director','admin'])) {
	header('Location: ../auth/index.php');
	exit();
}

$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$me = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>ELMS - Director Head Dashboard</title>
	<script src="https://cdn.tailwindcss.com"></script>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
							<i class="fas fa-user-tie text-white text-sm"></i>
						</div>
						<span class="text-xl font-bold text-white">ELMS Director</span>
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
				<!-- Active Navigation Item -->
				<a href="director_head_dashboard.php" class="flex items-center space-x-3 px-4 py-3 text-white bg-primary/20 rounded-lg border border-primary/30">
					<i class="fas fa-tachometer-alt w-5"></i>
					<span>Dashboard</span>
				</a>
				
				<a href="director_view_chart.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
					<i class="fas fa-calendar w-5"></i>
					<span>Calendar View</span>
				</a>
				
				<a href="reports.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
					<i class="fas fa-file-alt w-5"></i>
					<span>Reports</span>
				</a>
			</nav>
		</aside>
		
		<!-- Main Content -->
		<main class="flex-1 ml-64 p-6">
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
				<div class="mb-8">
					<div class="flex items-center justify-between">
						<div class="flex items-center gap-4">
							<div class="w-16 h-16 bg-gradient-to-r from-primary to-accent rounded-2xl flex items-center justify-center">
								<i class="fas fa-user-tie text-2xl text-white"></i>
							</div>
							<div>
								<h1 class="text-3xl font-bold text-white mb-2">Welcome, <?php echo htmlspecialchars($me['name'] ?? 'Director Head'); ?>!</h1>
								<p class="text-slate-400">Executive overview and actions</p>
							</div>
						</div>
						
						<!-- Notification Button -->
						<div class="relative">
							<button class="bg-slate-800 hover:bg-slate-700 text-white px-4 py-3 rounded-xl transition-colors flex items-center space-x-2" onclick="toggleNotifications()">
								<i class="fas fa-bell text-lg"></i>
								<span id="notification-count" class="font-semibold text-primary">0</span>
								<span id="notification-badge" class="bg-red-500 text-white text-xs px-2 py-1 rounded-full hidden">0</span>
							</button>
							
							<!-- Notification Dropdown -->
							<div id="notificationDropdown" class="absolute right-0 mt-2 w-80 bg-slate-800 border border-slate-700 rounded-xl shadow-xl z-50 hidden">
								<div class="p-4 border-b border-slate-700">
									<h3 class="text-lg font-semibold text-white flex items-center">
										<i class="fas fa-bell mr-2"></i>Executive Notifications
									</h3>
								</div>
								<div id="notifications-list" class="max-h-64 overflow-y-auto">
									<div class="text-center text-slate-400 py-8">
										<i class="fas fa-bell-slash text-3xl mb-3"></i>
										<p>No notifications</p>
									</div>
								</div>
								<div class="p-4 border-t border-slate-700">
									<a href="director_leave_management.php" class="text-primary hover:text-primary/80 text-sm font-medium">
										<i class="fas fa-clipboard-check mr-2"></i>Review Leave Requests
									</a>
								</div>
							</div>
						</div>
					</div>
				</div>

			<!-- Pending Leave Requests -->
			<div class="row mb-4">
				<div class="col-12">
					<div class="card">
						<div class="card-header">
							<h5 class="card-title mb-0"><i class="fas fa-clock me-2 text-warning"></i>Pending Leave Requests</h5>
						</div>
						<div class="card-body">
							<?php
							// Get pending leave requests (only those approved by department head but not yet decided by director)
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
							
							<?php if (empty($pending_requests)): ?>
								<div class="text-center py-4">
									<i class="fas fa-check-circle fa-3x text-success mb-3"></i>
									<p class="text-muted">No pending leave requests</p>
								</div>
							<?php else: ?>
								<div class="table-responsive">
									<table class="table table-hover">
										<thead>
											<tr>
												<th>Employee</th>
												<th>Leave Type</th>
												<th>Start Date</th>
												<th>End Date</th>
												<th>Days</th>
												<th>Reason</th>
												<th>Status</th>
												<th>Actions</th>
											</tr>
										</thead>
										<tbody>
											<?php foreach ($pending_requests as $request): ?>
												<tr>
													<td>
														<div>
															<strong><?php echo htmlspecialchars($request['employee_name']); ?></strong>
															<br><small class="text-muted"><?php echo htmlspecialchars($request['position']); ?></small>
														</div>
													</td>
													<td>
														<span class="badge bg-primary"><?php echo ucfirst(str_replace('_', ' ', $request['leave_type'])); ?></span>
													</td>
													<td><?php echo date('M d, Y', strtotime($request['start_date'])); ?></td>
													<td><?php echo date('M d, Y', strtotime($request['end_date'])); ?></td>
													<td>
														<?php 
														$start = new DateTime($request['start_date']);
														$end = new DateTime($request['end_date']);
														$days = $start->diff($end)->days + 1;
														echo $days;
														?>
													</td>
													<td>
														<span title="<?php echo htmlspecialchars($request['reason']); ?>">
															<?php echo strlen($request['reason']) > 30 ? substr(htmlspecialchars($request['reason']), 0, 30) . '...' : htmlspecialchars($request['reason']); ?>
														</span>
													</td>
													<td>
														<div class="d-flex align-items-center gap-2">
															<span class="badge bg-warning">Pending</span>
															<button type="button" class="btn btn-sm btn-outline-info" 
																	onclick="showStatusInfo(<?php echo $request['id']; ?>)"
																	title="View Status Details">
																<i class="fas fa-info-circle"></i>
															</button>
														</div>
													</td>
													<td>
														<div class="btn-group" role="group">
															<form method="POST" action="approve_leave.php" style="display: inline;">
																<input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
																<button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Approve this leave request?')">
																	<i class="fas fa-check"></i> Approve
																</button>
															</form>
															<form method="POST" action="reject_leave.php" style="display: inline;">
																<input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
																<button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Reject this leave request?')">
																	<i class="fas fa-times"></i> Reject
																</button>
															</form>
														</div>
													</td>
												</tr>
											<?php endforeach; ?>
										</tbody>
									</table>
								</div>
								<div class="text-center mt-3">
									<a href="director_leave_management.php" class="btn btn-outline-primary">View All Requests</a>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>

			<div class="row g-3">
				<div class="col-md-6">
					<div class="card h-100">
						<div class="card-body">
							<h5 class="card-title"><i class="fas fa-chart-bar me-2 text-secondary"></i>Analytics & Reports</h5>
							<p class="text-muted">Download and view detailed leave analytics.</p>
							<a href="reports.php" class="btn btn-outline-secondary"><i class="fas fa-file-export me-2"></i>Open Reports</a>
						</div>
					</div>
				</div>
				<div class="col-md-6">
					<div class="card h-100">
						<div class="card-body">
							<h5 class="card-title"><i class="fas fa-calendar-check me-2 text-success"></i>Leave Management</h5>
							<p class="text-muted">Review and manage all leave requests across the organization.</p>
							<a href="director_leave_management.php" class="btn btn-success"><i class="fas fa-clipboard-check me-2"></i>Manage Leaves</a>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
	<script>
		// Show status information modal
		function showStatusInfo(leaveId) {
			// Create modal HTML
			const modalHtml = `
				<div class="modal fade" id="statusInfoModal" tabindex="-1">
					<div class="modal-dialog">
						<div class="modal-content">
							<div class="modal-header">
								<h5 class="modal-title">
									<i class="fas fa-info-circle me-2"></i>Leave Request Status
								</h5>
								<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
							</div>
							<div class="modal-body">
								<div class="alert alert-info">
									<h6><i class="fas fa-clock me-2"></i>Current Status</h6>
									<p class="mb-0">This leave request has been <strong>approved by the department head</strong> and is now waiting for your decision.</p>
								</div>
								<div class="row">
									<div class="col-md-6">
										<h6 class="text-success mb-3">
											<i class="fas fa-user-tie me-2"></i>Department Head
										</h6>
										<p><strong>Status:</strong> 
											<span class="badge bg-success">Approved</span>
										</p>
										<p><strong>Completed:</strong> Already reviewed and approved</p>
									</div>
									<div class="col-md-6">
										<h6 class="text-primary mb-3">
											<i class="fas fa-user-tie me-2"></i>Director (You)
										</h6>
										<p><strong>Status:</strong> 
											<span class="badge bg-warning">Pending</span>
										</p>
										<p><strong>Action Required:</strong> Approve or Reject this request</p>
									</div>
								</div>
								<hr>
								<div class="row">
									<div class="col-md-6">
										<h6 class="text-muted mb-3">
											<i class="fas fa-user-shield me-2"></i>Admin
										</h6>
										<p><strong>Status:</strong> 
											<span class="badge bg-secondary">Waiting</span>
										</p>
										<p><strong>Next Step:</strong> Will review after your decision</p>
									</div>
									<div class="col-md-6">
										<h6 class="text-muted mb-3">
											<i class="fas fa-flag-checkered me-2"></i>Final Status
										</h6>
										<p><strong>Result:</strong> 
											<span class="badge bg-secondary">Pending</span>
										</p>
										<p><strong>Note:</strong> Depends on all approval levels</p>
									</div>
								</div>
							</div>
							<div class="modal-footer">
								<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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

			// Show modal
			const modal = new bootstrap.Modal(document.getElementById('statusInfoModal'));
			modal.show();
		}
		
		// Notification toggle function
		function toggleNotifications() {
			const dropdown = document.getElementById('notificationDropdown');
			dropdown.classList.toggle('hidden');
		}
	</script>
			</div>
		</main>
	</div>
</body>
</html>


