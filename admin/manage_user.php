<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin','manager','director'])) {
    header('Location: ../auth/index.php');
    exit();
}

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // Validate required fields
                if (empty($_POST['name']) || empty($_POST['email'])) {
                    $error_message = "Name and email are required!";
                    break;
                }
                
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

                // Check if email already exists
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetchColumn() > 0) {
                    $error_message = "Email already exists!";
                    break;
                }

                try {
                    // Generate verification token
                    $verificationToken = bin2hex(random_bytes(32));
                    $verificationExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));
                    
                    // Create temporary password (will be changed after email verification)
                    $temporaryPassword = password_hash('temp_' . time(), PASSWORD_DEFAULT);
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO employees (name, email, password, position, department, contact, role, 
                                             email_verified, verification_token, verification_expires, account_status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, 'pending')
                    ");
                    $stmt->execute([$name, $email, $temporaryPassword, $position, $department, $contact, $role, 
                                  $verificationToken, $verificationExpires]);
                    
                    $userId = $pdo->lastInsertId();
                    
                    // Log verification attempt
                    $stmt = $pdo->prepare("
                        INSERT INTO email_verification_logs (employee_id, email, verification_token, expires_at, ip_address, user_agent)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$userId, $email, $verificationToken, $verificationExpires, 
                                  $_SERVER['REMOTE_ADDR'] ?? 'unknown', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown']);
                    
                    // Send verification email
                    require_once '../includes/EmailService.php';
                    $emailService = new EmailService();
                    
                    if ($emailService->sendVerificationEmail($email, $name, $verificationToken)) {
                        $success_message = "User added successfully! A verification email has been sent to {$email}";
                    } else {
                        $success_message = "User added successfully! However, there was an issue sending the verification email. Please contact the user directly.";
                    }
                    
                } catch (PDOException $e) {
                    $error_message = "Error adding user: " . $e->getMessage();
                } catch (Exception $e) {
                    $error_message = "Error sending verification email: " . $e->getMessage();
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
$stmt = $pdo->query("SELECT * FROM employees ORDER BY position ASC, name ASC");
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ELMS - Manage Users</title>
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
                            <i class="fas fa-user-shield text-white text-sm"></i>
                        </div>
                        <span class="text-xl font-bold text-white">ELMS Admin</span>
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
                <?php 
                    $role = $_SESSION['role'];
                    $panelTitle = $role === 'director' ? 'Director Panel' : ($role === 'manager' ? 'Department Head' : 'Admin Panel');
                    $dashboardLink = $role === 'director' ? 'director_head_dashboard.php' : ($role === 'manager' ? 'department_head_dashboard.php' : 'admin_dashboard.php');
                ?>
                
                <!-- Active Navigation Item -->
                <a href="manage_user.php" class="flex items-center space-x-3 px-4 py-3 text-white bg-primary/20 rounded-lg border border-primary/30">
                    <i class="fas fa-users-cog w-5"></i>
                    <span>Manage Users</span>
                </a>
                
                <!-- Other Navigation Items -->
                <a href="<?php echo $dashboardLink; ?>" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                    <i class="fas fa-tachometer-alt w-5"></i>
                    <span>Dashboard</span>
                </a>
                
                <a href="leave_management.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                    <i class="fas fa-calendar-check w-5"></i>
                    <span>Leave Management</span>
                    <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full" id="pendingLeaveBadge" style="display: none;">0</span>
                </a>
                
                <a href="leave_alerts.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                    <i class="fas fa-bell w-5"></i>
                    <span>Leave Alerts</span>
                </a>
                
                <a href="view_chart.php" class="flex items-center space-x-3 px-4 py-3 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                    <i class="fas fa-calendar w-5"></i>
                    <span>Leave Chart</span>
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
                <!-- Page Header -->
                <div class="mb-8">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-3xl font-bold text-white mb-2 flex items-center">
                                <i class="fas fa-users-cog text-primary mr-3"></i>Manage Users
                            </h1>
                            <p class="text-slate-400">Add, edit, and manage user accounts</p>
                        </div>
                        <button type="button" onclick="openAddUserModal()" class="bg-gradient-to-r from-primary to-accent hover:from-primary/90 hover:to-accent/90 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-300 transform hover:scale-[1.02] hover:shadow-xl">
                            <i class="fas fa-plus mr-2"></i>Add New User
                        </button>
                    </div>
                </div>

                <!-- Search Section -->
                <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 mb-8 border border-slate-700/50">
                    <div class="relative">
                        <input type="text" 
                               id="searchInput" 
                               placeholder="Search users by name, email, or department..." 
                               class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 pl-12 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                        <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
                    </div>
                </div>

                <!-- Users Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="usersRow">
                    <?php foreach ($users as $user): ?>
                    <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700/50 hover:border-slate-600/50 transition-all duration-300 hover:scale-[1.02] hover:shadow-xl">
                        <div class="flex justify-between items-start mb-4">
                            <div class="flex items-center space-x-3">
                                <div class="w-12 h-12 bg-gradient-to-r from-primary to-accent rounded-xl flex items-center justify-center">
                                    <i class="fas fa-user text-white text-lg"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-white"><?php echo htmlspecialchars($user['name']); ?></h3>
                                    <p class="text-slate-400 text-sm"><?php echo htmlspecialchars($user['email']); ?></p>
                                </div>
                            </div>
                            <span class="px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wide <?php 
                                echo $user['role'] === 'admin' ? 'bg-red-500/20 text-red-400' : 
                                    ($user['role'] === 'manager' ? 'bg-orange-500/20 text-orange-400' : 'bg-green-500/20 text-green-400'); 
                            ?>">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </div>
                        
                        <div class="space-y-3 mb-6">
                            <div class="flex items-center text-slate-300">
                                <i class="fas fa-phone w-5 text-slate-400 mr-3"></i>
                                <span class="text-sm"><?php echo htmlspecialchars($user['contact'] ?? 'Not set'); ?></span>
                            </div>
                            <div class="flex items-center text-slate-300">
                                <i class="fas fa-briefcase w-5 text-slate-400 mr-3"></i>
                                <span class="text-sm"><?php echo htmlspecialchars($user['position'] ?? 'Not set'); ?></span>
                            </div>
                            <div class="flex items-center text-slate-300">
                                <i class="fas fa-building w-5 text-slate-400 mr-3"></i>
                                <span class="text-sm"><?php echo htmlspecialchars($user['department'] ?? 'Not set'); ?></span>
                            </div>
                        </div>
                        
                        <div class="flex gap-2">
                            <button onclick="editUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>', '<?php echo htmlspecialchars($user['email']); ?>', '<?php echo htmlspecialchars($user['contact'] ?? ''); ?>', '<?php echo htmlspecialchars($user['position'] ?? ''); ?>', '<?php echo htmlspecialchars($user['department'] ?? ''); ?>', '<?php echo htmlspecialchars($user['role']); ?>')" 
                                    class="flex-1 bg-primary hover:bg-primary/90 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                                <i class="fas fa-edit mr-2"></i>Edit
                            </button>
                            <button onclick="deleteUser(<?php echo $user['id']; ?>)" 
                                    class="flex-1 bg-red-500 hover:bg-red-600 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                                <i class="fas fa-trash mr-2"></i>Delete
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div id="addUserModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-slate-800 rounded-2xl p-8 w-full max-w-2xl mx-4 max-h-screen overflow-y-auto border border-slate-700">
            <div class="flex items-center justify-between mb-6">
                <h5 class="text-2xl font-bold text-white flex items-center">
                    <i class="fas fa-user-plus text-primary mr-3"></i>Add New User
                </h5>
                <button type="button" onclick="closeAddUserModal()" class="text-slate-400 hover:text-white transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="addUserForm" method="POST" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="addName" class="block text-sm font-semibold text-slate-300 mb-2">Name</label>
                        <input type="text" id="addName" name="name" required class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label for="addEmail" class="block text-sm font-semibold text-slate-300 mb-2">Email</label>
                        <input type="email" id="addEmail" name="email" required class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                </div>
                
                <div class="bg-blue-500/20 border border-blue-500/30 text-blue-400 p-4 rounded-xl">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle mr-3 mt-1"></i>
                        <div>
                            <strong>Note:</strong> A verification email will be sent to the user's email address. 
                            The user will receive a temporary password after email verification.
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="addContact" class="block text-sm font-semibold text-slate-300 mb-2">Contact</label>
                        <input type="text" id="addContact" name="contact" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label for="addPosition" class="block text-sm font-semibold text-slate-300 mb-2">Position</label>
                        <input type="text" id="addPosition" name="position" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="addDepartment" class="block text-sm font-semibold text-slate-300 mb-2">Department</label>
                        <input type="text" id="addDepartment" name="department" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label for="addRole" class="block text-sm font-semibold text-slate-300 mb-2">Role</label>
                        <select id="addRole" name="role" required class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="employee">Employee</option>
                            <option value="manager">Manager</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-4 pt-6">
                    <button type="button" onclick="closeAddUserModal()" class="bg-slate-600 hover:bg-slate-500 text-white font-semibold py-3 px-6 rounded-xl transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="bg-gradient-to-r from-primary to-accent hover:from-primary/90 hover:to-accent/90 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-300 transform hover:scale-[1.02]">
                        <i class="fas fa-plus mr-2"></i>Add User
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-slate-800 rounded-2xl p-8 w-full max-w-2xl mx-4 max-h-screen overflow-y-auto border border-slate-700">
            <div class="flex items-center justify-between mb-6">
                <h5 class="text-2xl font-bold text-white flex items-center">
                    <i class="fas fa-user-edit text-primary mr-3"></i>Edit User
                </h5>
                <button type="button" onclick="closeEditUserModal()" class="text-slate-400 hover:text-white transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="editUserForm" method="POST" class="space-y-6">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editId">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="editName" class="block text-sm font-semibold text-slate-300 mb-2">Name</label>
                        <input type="text" id="editName" name="name" required class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label for="editEmail" class="block text-sm font-semibold text-slate-300 mb-2">Email</label>
                        <input type="email" id="editEmail" name="email" required class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="editContact" class="block text-sm font-semibold text-slate-300 mb-2">Contact</label>
                        <input type="text" id="editContact" name="contact" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label for="editPosition" class="block text-sm font-semibold text-slate-300 mb-2">Position</label>
                        <input type="text" id="editPosition" name="position" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="editDepartment" class="block text-sm font-semibold text-slate-300 mb-2">Department</label>
                        <input type="text" id="editDepartment" name="department" class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label for="editRole" class="block text-sm font-semibold text-slate-300 mb-2">Role</label>
                        <select id="editRole" name="role" required class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="employee">Employee</option>
                            <option value="manager">Manager</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-4 pt-6">
                    <button type="button" onclick="closeEditUserModal()" class="bg-slate-600 hover:bg-slate-500 text-white font-semibold py-3 px-6 rounded-xl transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="bg-gradient-to-r from-primary to-accent hover:from-primary/90 hover:to-accent/90 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-300 transform hover:scale-[1.02]">
                        <i class="fas fa-save mr-2"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openAddUserModal() {
            document.getElementById('addUserModal').classList.remove('hidden');
            document.getElementById('addUserModal').classList.add('flex');
        }

        function closeAddUserModal() {
            document.getElementById('addUserModal').classList.add('hidden');
            document.getElementById('addUserModal').classList.remove('flex');
        }

        function openEditUserModal() {
            document.getElementById('editUserModal').classList.remove('hidden');
            document.getElementById('editUserModal').classList.add('flex');
        }

        function closeEditUserModal() {
            document.getElementById('editUserModal').classList.add('hidden');
            document.getElementById('editUserModal').classList.remove('flex');
        }

        // Global functions
        function deleteUser(userId) {
            if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
                return;
            }
            
            showNotification('Deleting user...', 'info');
            
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', userId);
            
            fetch('manage_user.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
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
            
            openEditUserModal();
        }

        function showNotification(message, type = 'info') {
            const bgColor = type === 'success' ? 'bg-green-500/20 border-green-500/30 text-green-400' : 
                           type === 'error' ? 'bg-red-500/20 border-red-500/30 text-red-400' : 
                           type === 'warning' ? 'bg-yellow-500/20 border-yellow-500/30 text-yellow-400' : 
                           'bg-blue-500/20 border-blue-500/30 text-blue-400';
            
            const icon = type === 'success' ? 'fa-check-circle' : 
                        type === 'error' ? 'fa-exclamation-circle' : 
                        type === 'warning' ? 'fa-exclamation-triangle' : 
                        'fa-info-circle';
            
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 ${bgColor} border rounded-xl p-4 max-w-sm shadow-xl`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${icon} mr-3"></i>
                    <div class="flex-1">${message}</div>
                    <button type="button" onclick="this.parentElement.parentElement.remove()" class="ml-3 text-current hover:opacity-75">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }

        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize search functionality
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('keyup', function() {
                    const searchTerm = this.value.toLowerCase().trim();
                    const userItems = document.querySelectorAll('#usersRow > div');
                    
                    userItems.forEach(item => {
                        const userInfo = item.textContent.toLowerCase();
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
                    
                    fetch('manage_user.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(data => {
                        showNotification('User added successfully!', 'success');
                        closeAddUserModal();
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
                    
                    fetch('manage_user.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(data => {
                        showNotification('User updated successfully!', 'success');
                        closeEditUserModal();
                        
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

            // Function to fetch pending leave count
            function fetchPendingLeaveCount() {
                fetch('api/get_pending_leave_count.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const badge = document.getElementById('pendingLeaveBadge');
                            if (data.count > 0) {
                                badge.textContent = data.count;
                                badge.style.display = 'inline-block';
                            } else {
                                badge.style.display = 'none';
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching pending leave count:', error);
                    });
            }

            // Fetch pending leave count on page load
            fetchPendingLeaveCount();

            // Update pending leave count every 30 seconds
            setInterval(fetchPendingLeaveCount, 30000);
        });
    </script>
</body>
</html> 