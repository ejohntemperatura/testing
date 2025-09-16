<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/index.php');
    exit();
}

// Check if user is manager or admin
$stmt = $pdo->prepare("SELECT role FROM employees WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user['role'] !== 'manager' && $user['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // Validate required fields
                if (empty($_POST['name']) || empty($_POST['email']) || empty($_POST['password'])) {
                    $error_message = "Name, email, and password are required!";
                    break;
                }
                
                $name = trim($_POST['name']);
                $email = trim($_POST['email']);
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $position = trim($_POST['position'] ?? '');
                $department = trim($_POST['department'] ?? '');
                $contact = trim($_POST['contact'] ?? '');
                $role = $_POST['role'] ?? 'employee';

                // Validate email format
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error_message = "Invalid email format!";
                    break;
                }

                // Check if email already exists
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetchColumn() > 0) {
                    $error_message = "Email already exists!";
                    break;
                }

                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO employees (name, email, password, position, department, contact, role)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$name, $email, $password, $position, $department, $contact, $role]);
                    $success_message = "User added successfully!";
                } catch (PDOException $e) {
                    $error_message = "Error adding user: " . $e->getMessage();
                }
                break;

            case 'edit':
                // Validate required fields
                if (empty($_POST['id']) || empty($_POST['name']) || empty($_POST['email'])) {
                    $error_message = "ID, name, and email are required!";
                    break;
                }
                
                $id = $_POST['id'];
                $name = trim($_POST['name']);
                $email = trim($_POST['email']);
                $position = trim($_POST['position'] ?? '');
                $department = trim($_POST['department'] ?? '');
                $contact = trim($_POST['contact'] ?? '');
                $role = $_POST['role'] ?? 'employee';

                // Validate email format
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error_message = "Invalid email format!";
                    break;
                }

                // Check if email already exists for other users
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE email = ? AND id != ?");
                $stmt->execute([$email, $id]);
                if ($stmt->fetchColumn() > 0) {
                    $error_message = "Email already exists for another user!";
                    break;
                }

                try {
                    $stmt = $pdo->prepare("
                        UPDATE employees 
                        SET name = ?, email = ?, position = ?, department = ?, contact = ?, role = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $email, $position, $department, $contact, $role, $id]);
                    $success_message = "User updated successfully!";
                } catch (PDOException $e) {
                    $error_message = "Error updating user: " . $e->getMessage();
                }
                break;

            case 'delete':
                // Validate ID
                if (empty($_POST['id'])) {
                    $error_message = "User ID is required!";
                    break;
                }
                
                $id = $_POST['id'];
                
                // Check if user exists
                $stmt = $pdo->prepare("SELECT name FROM employees WHERE id = ?");
                $stmt->execute([$id]);
                $user = $stmt->fetch();
                
                if (!$user) {
                    $error_message = "User not found!";
                    break;
                }
                
                // Prevent deleting the current user
                if ($id == $_SESSION['user_id']) {
                    $error_message = "You cannot delete your own account!";
                    break;
                }
                
                try {
                    // Start transaction to ensure data consistency
                    $pdo->beginTransaction();
                    
                    // Check if user has related records
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE employee_id = ?");
                    $stmt->execute([$id]);
                    $leave_count = $stmt->fetchColumn();
                    
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM dtr WHERE user_id = ?");
                    $stmt->execute([$id]);
                    $dtr_count = $stmt->fetchColumn();
                    
                    if ($leave_count > 0 || $dtr_count > 0) {
                        // User has related records - delete them first
                        if ($leave_count > 0) {
                            $stmt = $pdo->prepare("DELETE FROM leave_requests WHERE employee_id = ?");
                            $stmt->execute([$id]);
                        }
                        
                        if ($dtr_count > 0) {
                            $stmt = $pdo->prepare("DELETE FROM dtr WHERE user_id = ?");
                            $stmt->execute([$id]);
                        }
                    }
                    
                    // Now delete the user
                    $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    // Commit transaction
                    $pdo->commit();
                    
                    $success_message = "User '{$user['name']}' and all related records deleted successfully!";
                } catch (PDOException $e) {
                    // Rollback transaction on error
                    $pdo->rollBack();
                    $error_message = "Error deleting user: " . $e->getMessage();
                }
                break;
        }
    }
}

// Fetch all users
$stmt = $pdo->query("SELECT * FROM employees ORDER BY name");
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager - User Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Sidebar Styles */
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0;
            position: fixed;
            width: 280px;
            transition: all 0.3s;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }

        .sidebar-header {
            padding: 30px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: rgba(255,255,255,0.1);
        }

        .sidebar-header h4 {
            color: white;
            font-weight: 600;
            margin: 0;
            font-size: 1.5rem;
        }

        .sidebar-header p {
            color: rgba(255,255,255,0.8);
            margin: 5px 0 0 0;
            font-size: 0.9rem;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .sidebar-menu a {
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 15px 25px;
            margin: 5px 15px;
            border-radius: 10px;
            transition: all 0.3s;
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }

        .sidebar-menu a:hover {
            background: rgba(255,255,255,0.2);
            color: white;
            transform: translateX(5px);
        }

        .sidebar-menu a.active {
            background: rgba(255,255,255,0.25);
            color: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .sidebar-menu i {
            margin-right: 15px;
            width: 20px;
            text-align: center;
            font-size: 1.1em;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 30px;
            background: #f8f9fa;
            min-height: 100vh;
        }

        /* Page Header */
        .page-header {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .page-title {
            color: #333;
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
        }

        .page-subtitle {
            color: #6c757d;
            font-size: 1rem;
            margin-top: 10px;
        }

        /* Search Section */
        .search-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .search-input {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 12px 20px;
            font-size: 1rem;
            width: 100%;
            transition: all 0.3s;
        }

        .search-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        /* User Cards */
        .user-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s;
            border: 1px solid #e9ecef;
        }

        .user-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            border-color: #667eea;
        }

        .user-info h5 {
            color: #333;
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .user-info p {
            color: #6c757d;
            font-size: 0.95rem;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
        }

        .user-info i {
            margin-right: 10px;
            width: 16px;
            color: #667eea;
        }

        .user-role {
            display: inline-block;
            padding: 6px 15px;
            border-radius: 25px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            background: #e9ecef;
            color: #495057;
            margin-top: 10px;
        }

        .user-role.admin {
            background: #dc3545;
            color: white;
        }

        .user-role.manager {
            background: #fd7e14;
            color: white;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .btn {
            border-radius: 8px;
            font-weight: 500;
            padding: 10px 20px;
            font-size: 0.9rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #51cf66 0%, #40c057 100%);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(81, 207, 102, 0.4);
        }

        /* Modal Styles */
        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 20px 25px;
        }

        .modal-title {
            color: white;
            font-weight: 600;
        }

        .form-label {
            color: #495057;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        /* Notifications */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            max-width: 400px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -280px;
            }
            
            .sidebar.active {
                margin-left: 0;
            }
            
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 8px;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* Mobile Toggle */
        .mobile-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 10px;
            border-radius: 8px;
        }

        @media (max-width: 768px) {
            .mobile-toggle {
                display: block;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Toggle Button -->
    <button class="mobile-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h4><i class="fas fa-user-tie me-2"></i>Manager Panel</h4>
            <p>User Management System</p>
        </div>
        <div class="sidebar-menu">
            <a href="manager_dashboard.php">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="manager_user.php" class="active">
                <i class="fas fa-users"></i>
                <span>Manage Users</span>
            </a>
            <a href="manager_leave.php">
                <i class="fas fa-calendar-check"></i>
                <span>Leave Requests</span>
            </a>
            <a href="manager_reports.php">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
            <a href="manager_profile.php">
                <i class="fas fa-user-circle"></i>
                <span>Profile</span>
            </a>
                            <a href="../auth/logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="page-title">
                        <i class="fas fa-users me-3"></i>User Management
                    </h1>
                    <p class="page-subtitle">Add, edit, and manage user accounts in the system</p>
                </div>
                <div class="col-md-4 text-end">
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fas fa-plus"></i>Add New User
                    </button>
                </div>
            </div>
        </div>

        <!-- Search Section -->
        <div class="search-section">
            <div class="row">
                <div class="col-md-8">
                    <input type="text" class="search-input" id="searchInput" placeholder="Search users by name, email, or department...">
                </div>
                <div class="col-md-4 text-end">
                    <button type="button" class="btn btn-primary" onclick="exportUsers()">
                        <i class="fas fa-download"></i>Export Users
                    </button>
                </div>
            </div>
        </div>

        <!-- Users Grid -->
        <div class="row" id="usersGrid">
            <?php foreach ($users as $user): ?>
            <div class="col-md-6 col-lg-4 mb-4 user-item">
                <div class="user-card">
                    <div class="user-info">
                        <h5><?php echo htmlspecialchars($user['name']); ?></h5>
                        <p><i class="fas fa-envelope"></i><?php echo htmlspecialchars($user['email']); ?></p>
                        <p><i class="fas fa-phone"></i><?php echo htmlspecialchars($user['contact'] ?? 'Not set'); ?></p>
                        <p><i class="fas fa-briefcase"></i><?php echo htmlspecialchars($user['position'] ?? 'Not set'); ?></p>
                        <p><i class="fas fa-building"></i><?php echo htmlspecialchars($user['department'] ?? 'Not set'); ?></p>
                        <span class="user-role <?php echo $user['role']; ?>">
                            <?php echo ucfirst($user['role']); ?>
                        </span>
                    </div>
                    <div class="action-buttons">
                        <button class="btn btn-primary" onclick="editUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>', '<?php echo htmlspecialchars($user['email']); ?>', '<?php echo htmlspecialchars($user['contact'] ?? ''); ?>', '<?php echo htmlspecialchars($user['position'] ?? ''); ?>', '<?php echo htmlspecialchars($user['department'] ?? ''); ?>', '<?php echo htmlspecialchars($user['role']); ?>')">
                            <i class="fas fa-edit"></i>Edit
                        </button>
                        <button class="btn btn-danger" onclick="deleteUser(<?php echo $user['id']; ?>)">
                            <i class="fas fa-trash"></i>Delete
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus me-2"></i>Add New User
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="addUserForm" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="addName" class="form-label">Name</label>
                            <input type="text" class="form-control" id="addName" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="addEmail" class="form-label">Email</label>
                            <input type="email" class="form-control" id="addEmail" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="addPassword" class="form-label">Password</label>
                            <input type="password" class="form-control" id="addPassword" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label for="addContact" class="form-label">Contact</label>
                            <input type="text" class="form-control" id="addContact" name="contact">
                        </div>
                        <div class="mb-3">
                            <label for="addPosition" class="form-label">Position</label>
                            <input type="text" class="form-control" id="addPosition" name="position">
                        </div>
                        <div class="mb-3">
                            <label for="addDepartment" class="form-label">Department</label>
                            <input type="text" class="form-control" id="addDepartment" name="department">
                        </div>
                        <div class="mb-3">
                            <label for="addRole" class="form-label">Role</label>
                            <select class="form-select" id="addRole" name="role" required>
                                <option value="employee">Employee</option>
                                <option value="manager">Manager</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Add User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-edit me-2"></i>Edit User
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="editUserForm" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="editId">
                        <div class="mb-3">
                            <label for="editName" class="form-label">Name</label>
                            <input type="text" class="form-control" id="editName" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="editEmail" class="form-label">Email</label>
                            <input type="email" class="form-control" id="editEmail" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="editContact" class="form-label">Contact</label>
                            <input type="text" class="form-control" id="editContact" name="contact">
                        </div>
                        <div class="mb-3">
                            <label for="editPosition" class="form-label">Position</label>
                            <input type="text" class="form-control" id="editPosition" name="position">
                        </div>
                        <div class="mb-3">
                            <label for="editDepartment" class="form-label">Department</label>
                            <input type="text" class="form-control" id="editDepartment" name="department">
                        </div>
                        <div class="mb-3">
                            <label for="editRole" class="form-label">Role</label>
                            <select class="form-select" id="editRole" name="role" required>
                                <option value="employee">Employee</option>
                                <option value="manager">Manager</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global functions
        function deleteUser(userId) {
            if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
                return;
            }
            
            showNotification('Deleting user...', 'info');
            
            fetch('manager_user.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'action=delete&id=' + encodeURIComponent(userId)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.text();
            })
            .then(data => {
                showNotification('User deleted successfully!', 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            })
            .catch(error => {
                showNotification('Error deleting user: ' + error.message, 'error');
            });
        }

        function editUser(id, name, email, contact, position, department, role) {
            document.getElementById('editId').value = id;
            document.getElementById('editName').value = name || '';
            document.getElementById('editEmail').value = email || '';
            document.getElementById('editContact').value = contact || '';
            document.getElementById('editPosition').value = position || '';
            document.getElementById('editDepartment').value = department || '';
            document.getElementById('editRole').value = role || 'employee';
            
            const editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
            editModal.show();
        }

        function showNotification(message, type = 'info') {
            const alertClass = type === 'success' ? 'alert-success' : 
                              type === 'error' ? 'alert-danger' : 
                              type === 'warning' ? 'alert-warning' : 'alert-info';
            
            const notification = document.createElement('div');
            notification.className = `alert ${alertClass} alert-dismissible fade show notification`;
            notification.innerHTML = `
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">${message}</div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }

        function exportUsers() {
            const table = document.createElement('table');
            const thead = document.createElement('thead');
            const tbody = document.createElement('tbody');
            
            // Create header
            const headerRow = document.createElement('tr');
            ['Name', 'Email', 'Contact', 'Position', 'Department', 'Role'].forEach(text => {
                const th = document.createElement('th');
                th.textContent = text;
                headerRow.appendChild(th);
            });
            thead.appendChild(headerRow);
            table.appendChild(thead);
            
            // Create body
            const userCards = document.querySelectorAll('.user-card');
            userCards.forEach(card => {
                const row = document.createElement('tr');
                const userInfo = card.querySelector('.user-info');
                const name = userInfo.querySelector('h5').textContent;
                const email = userInfo.querySelector('p:nth-child(2)').textContent.replace('📧', '').trim();
                const contact = userInfo.querySelector('p:nth-child(3)').textContent.replace('📞', '').trim();
                const position = userInfo.querySelector('p:nth-child(4)').textContent.replace('💼', '').trim();
                const department = userInfo.querySelector('p:nth-child(5)').textContent.replace('🏢', '').trim();
                const role = userInfo.querySelector('.user-role').textContent;
                
                [name, email, contact, position, department, role].forEach(text => {
                    const td = document.createElement('td');
                    td.textContent = text;
                    row.appendChild(td);
                });
                tbody.appendChild(row);
            });
            table.appendChild(tbody);
            
            // Convert to CSV
            let csv = 'Name,Email,Contact,Position,Department,Role\n';
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                const rowData = [];
                cells.forEach(cell => {
                    rowData.push('"' + cell.textContent.replace(/"/g, '""') + '"');
                });
                csv += rowData.join(',') + '\n';
            });
            
            // Download
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'users_' + new Date().toISOString().split('T')[0] + '.csv';
            a.click();
            window.URL.revokeObjectURL(url);
            
            showNotification('Users exported successfully!', 'success');
        }

        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize search functionality
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('keyup', function() {
                    const searchTerm = this.value.toLowerCase().trim();
                    const userItems = document.querySelectorAll('.user-item');
                    
                    userItems.forEach(item => {
                        const userInfo = item.querySelector('.user-info').textContent.toLowerCase();
                        if (searchTerm === '' || userInfo.includes(searchTerm)) {
                            item.style.display = '';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            }

            // Initialize form submissions
            const addUserForm = document.getElementById('addUserForm');
            if (addUserForm) {
                addUserForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    formData.append('action', 'add');
                    
                    showNotification('Adding new user...', 'info');
                    
                    fetch('manager_user.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok: ' + response.status);
                        }
                        return response.text();
                    })
                    .then(data => {
                        showNotification('User added successfully!', 'success');
                        
                        const addModal = bootstrap.Modal.getInstance(document.getElementById('addUserModal'));
                        if (addModal) {
                            addModal.hide();
                        }
                        
                        this.reset();
                        
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    })
                    .catch(error => {
                        showNotification('Error adding user: ' + error.message, 'error');
                    });
                });
            }

            const editUserForm = document.getElementById('editUserForm');
            if (editUserForm) {
                editUserForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    formData.append('action', 'edit');
                    
                    showNotification('Updating user...', 'info');
                    
                    fetch('manager_user.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok: ' + response.status);
                        }
                        return response.text();
                    })
                    .then(data => {
                        showNotification('User updated successfully!', 'success');
                        
                        const editModal = bootstrap.Modal.getInstance(document.getElementById('editUserModal'));
                        if (editModal) {
                            editModal.hide();
                        }
                        
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    })
                    .catch(error => {
                        showNotification('Error updating user: ' + error.message, 'error');
                    });
                });
            }

            // Show existing messages
            <?php if (isset($success_message)): ?>
                showNotification('<?php echo addslashes($success_message); ?>', 'success');
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                showNotification('<?php echo addslashes($error_message); ?>', 'error');
            <?php endif; ?>
        });
    </script>
</body>
</html> 