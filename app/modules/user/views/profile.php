<?php
session_start();
require_once '../../../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../auth/views/login.php');
    exit();
}

// Fetch employee information
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$employee = $stmt->fetch();

// Set default values for missing fields
$employee['name'] = $employee['name'] ?? '';
$employee['email'] = $employee['email'] ?? '';
$employee['position'] = $employee['position'] ?? '';
$employee['department'] = $employee['department'] ?? '';
$employee['contact'] = $employee['contact'] ?? '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $position = $_POST['position'];
    $department = $_POST['department'];
    $contact = $_POST['contact'];

    try {
        // First, check if the fields exist in the table
        $stmt = $pdo->query("SHOW COLUMNS FROM employees");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Build the update query dynamically based on existing columns
        $updates = [];
        $params = [];
        
        if (in_array('name', $columns)) {
            $updates[] = "name = ?";
            $params[] = $name;
        }
        if (in_array('email', $columns)) {
            $updates[] = "email = ?";
            $params[] = $email;
        }
        if (in_array('position', $columns)) {
            $updates[] = "position = ?";
            $params[] = $position;
        }
        if (in_array('department', $columns)) {
            $updates[] = "department = ?";
            $params[] = $department;
        }
        if (in_array('contact', $columns)) {
            $updates[] = "contact = ?";
            $params[] = $contact;
        }
        
        if (!empty($updates)) {
            $params[] = $_SESSION['user_id'];
            $sql = "UPDATE employees SET " . implode(", ", $updates) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $success_message = "Profile updated successfully!";
            
            // Refresh employee data
            $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $employee = $stmt->fetch();
            
            // Set default values again after refresh
            $employee['name'] = $employee['name'] ?? '';
            $employee['email'] = $employee['email'] ?? '';
            $employee['position'] = $employee['position'] ?? '';
            $employee['department'] = $employee['department'] ?? '';
            $employee['contact'] = $employee['contact'] ?? '';
        }
    } catch (PDOException $e) {
        $error_message = "Error updating profile: " . $e->getMessage();
    }
}

// Set page title
$page_title = "My Profile";

// Include user header
include '../../../../includes/user_header.php';
?>

<!-- Page Header -->
<h1 class="elms-h1" style="margin-bottom: 0.5rem; display: flex; align-items: center;">
    <i class="fas fa-user-circle" style="color: #0891b2; margin-right: 0.75rem;"></i>My Profile
</h1>
<p class="elms-text-muted" style="margin-bottom: 2rem;">View and edit your profile information</p>

<!-- Profile Content -->

                <!-- Success Message -->
            <?php if (isset($success_message)): ?>
                    <div class="bg-green-500/20 border border-green-500/30 text-green-400 p-4 rounded-xl mb-6 flex items-center">
                        <i class="fas fa-check-circle mr-3"></i>
                <?php echo $success_message; ?>
            </div>
            <?php endif; ?>

                <!-- Error Message -->
            <?php if (isset($error_message)): ?>
                    <div class="bg-red-500/20 border border-red-500/30 text-red-400 p-4 rounded-xl mb-6 flex items-center">
                        <i class="fas fa-exclamation-circle mr-3"></i>
                <?php echo $error_message; ?>
            </div>
            <?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Profile Info Card -->
                    <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 overflow-hidden">
                        <div class="p-6 text-center">
                            <div class="w-24 h-24 bg-gradient-to-r from-primary to-accent rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-user text-3xl text-white"></i>
                            </div>
                            <h3 class="text-xl font-bold text-white mb-2"><?php echo htmlspecialchars($employee['name']); ?></h3>
                            <p class="text-slate-400 mb-6"><?php echo htmlspecialchars($employee['position']); ?></p>
                            
                            <div class="space-y-3">
                                <div class="flex items-center text-slate-300">
                                    <i class="fas fa-envelope w-5 mr-3 text-primary"></i>
                                    <span class="text-sm"><?php echo htmlspecialchars($employee['email']); ?></span>
                                </div>
                                <div class="flex items-center text-slate-300">
                                    <i class="fas fa-phone w-5 mr-3 text-primary"></i>
                                    <span class="text-sm"><?php echo htmlspecialchars($employee['contact']); ?></span>
                                </div>
                                <div class="flex items-center text-slate-300">
                                    <i class="fas fa-building w-5 mr-3 text-primary"></i>
                                    <span class="text-sm"><?php echo htmlspecialchars($employee['department']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Profile Form -->
                    <div class="lg:col-span-2">
                        <div class="bg-slate-800/50 backdrop-blur-sm rounded-2xl border border-slate-700/50 overflow-hidden">
                            <div class="px-6 py-4 border-b border-slate-700/50 bg-slate-700/30">
                                <h3 class="text-xl font-semibold text-white flex items-center">
                                    <i class="fas fa-edit text-primary mr-3"></i>Edit Profile
                                </h3>
                            </div>
                            <div class="p-6">
                                <form method="POST" action="" class="space-y-6">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label class="block text-sm font-semibold text-slate-300 mb-2">Full Name</label>
                                            <input type="text" name="name" value="<?php echo htmlspecialchars($employee['name']); ?>" required
                                                   class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-semibold text-slate-300 mb-2">Email Address</label>
                                            <input type="email" name="email" value="<?php echo htmlspecialchars($employee['email']); ?>" required
                                                   class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label class="block text-sm font-semibold text-slate-300 mb-2">Position</label>
                                            <input type="text" name="position" value="<?php echo htmlspecialchars($employee['position']); ?>" required
                                                   class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-semibold text-slate-300 mb-2">Department</label>
                                            <input type="text" name="department" value="<?php echo htmlspecialchars($employee['department']); ?>" required
                                                   class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-semibold text-slate-300 mb-2">Contact Number</label>
                                        <input type="tel" name="contact" value="<?php echo htmlspecialchars($employee['contact']); ?>" required
                                               class="w-full bg-slate-700 border border-slate-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                    </div>
                                    
                                    <div class="flex justify-end">
                                        <button type="submit" class="bg-gradient-to-r from-primary to-accent hover:from-primary/90 hover:to-accent/90 text-white font-semibold py-3 px-8 rounded-xl transition-all duration-300 transform hover:scale-[1.02] hover:shadow-xl flex items-center">
                                            <i class="fas fa-save mr-2"></i>Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php include '../../../../includes/user_footer.php'; ?> 