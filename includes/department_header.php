<?php
// Department Head Header - Dark Theme with Modern Design
// No notifications (employee-exclusive feature)

// Get current page name for active link highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// Get user info
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$dept_head = $stmt->fetch();

// Get department info
$dept_name = $dept_head['department'] ?? 'Technology';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'ELMS Department Head'; ?></title>
    <link rel="stylesheet" href="../../../../assets/css/tailwind.css">
    <link rel="stylesheet" href="../../../../assets/libs/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../../../../assets/css/elms-dark-theme.css">
</head>
<body style="background-color: #0f172a; margin: 0;">
    <!-- Top Navbar (NO Notification Bell) -->
    <nav class="elms-navbar">
        <div class="elms-navbar-content">
            <!-- Logo -->
            <div class="elms-logo">
                <span class="elms-logo-text">ELMS Department Head</span>
            </div>
            
            <!-- Right Side: Department Badge & User Dropdown (NO Notification Bell) -->
            <div style="display: flex; align-items: center; gap: 1rem; margin-left: auto;">
                <!-- Department Badge -->
                <div style="background: rgba(6, 182, 212, 0.1); border: 1px solid rgba(6, 182, 212, 0.3); padding: 0.375rem 0.75rem; border-radius: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-building" style="color: #06b6d4; font-size: 0.75rem;"></i>
                    <span style="color: #06b6d4; font-weight: 600; font-size: 0.75rem;">DE</span>
                    <span style="color: white; font-weight: 600; font-size: 0.75rem;">Department Head - <?php echo htmlspecialchars($dept_name); ?></span>
                </div>
                
                <!-- User Dropdown -->
                <div style="position: relative;">
                    <button id="deptUserDropdownBtn" style="display: flex; align-items: center; gap: 0.5rem; background: none; border: none; cursor: pointer; padding: 0;">
                        <div style="width: 32px; height: 32px; background: #10b981; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 0.875rem;">
                            <?php echo strtoupper(substr($dept_head['name'], 0, 1)); ?>
                        </div>
                        <div style="text-align: left;">
                            <div style="color: white; font-weight: 600; font-size: 0.875rem;">
                                <?php echo htmlspecialchars($dept_head['name']); ?>
                            </div>
                        </div>
                        <i class="fas fa-chevron-down" style="color: #cbd5e1; font-size: 0.625rem;"></i>
                    </button>
                    
                    <!-- Dropdown Menu -->
                    <div id="userDropdown" style="display: none; position: absolute; top: 100%; right: 0; margin-top: 0.5rem; width: 260px; background: #1e293b; border: 1px solid #334155; border-radius: 0.75rem; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5); z-index: 9999;">
                        <!-- Dropdown Header -->
                        <div style="padding: 1rem; border-bottom: 1px solid #334155;">
                            <div style="font-weight: 600; color: white; margin-bottom: 0.25rem; font-size: 0.9375rem;">
                                <?php echo htmlspecialchars($dept_head['name']); ?>
                            </div>
                            <div style="color: #94a3b8; font-size: 0.8125rem; margin-bottom: 0.5rem;">
                                <?php echo htmlspecialchars($dept_head['email'] ?? 'depthead@elms.com'); ?>
                            </div>
                            <span style="display: inline-block; background: #10b981; color: white; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.6875rem; font-weight: 600;">
                                Department Head
                            </span>
                        </div>
                        
                        <!-- Dropdown Items -->
                        <div style="padding: 0.5rem 0;">
                            <a href="../../../../auth/controllers/logout.php" style="display: block; padding: 0.75rem 1rem; color: #ef4444; text-decoration: none; transition: all 0.2s; border-top: 1px solid #334155; font-size: 0.875rem; font-weight: 500;" onmouseover="this.style.backgroundColor='#334155'" onmouseout="this.style.backgroundColor='transparent'">
                                <i class="fas fa-sign-out-alt" style="margin-right: 0.625rem; width: 18px; font-size: 0.875rem;"></i>
                                Sign Out
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <aside class="elms-sidebar">
        <nav>
            <!-- Dashboard Section -->
            <div class="elms-sidebar-section">
                <h3 class="elms-sidebar-header">Dashboard</h3>
                <a href="dashboard.php" class="elms-sidebar-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt elms-sidebar-icon"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            
            <!-- Management Section -->
            <div class="elms-sidebar-section">
                <h3 class="elms-sidebar-header">Management</h3>
                <a href="leave_chart.php" class="elms-sidebar-link <?php echo ($current_page == 'leave_chart.php' || $current_page == 'view_chart.php') ? 'active' : ''; ?>">
                    <i class="fas fa-calendar elms-sidebar-icon"></i>
                    <span>Leave Chart</span>
                </a>
            </div>
        </nav>
    </aside>
    
    <!-- Main Content -->
    <main class="elms-main">
    
    <script>
        // Wait for DOM to be ready
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Department header script loaded');
            
            const dropdownBtn = document.getElementById('deptUserDropdownBtn');
            const dropdown = document.getElementById('userDropdown');
            
            console.log('Button:', dropdownBtn);
            console.log('Dropdown:', dropdown);
            
            if (!dropdownBtn) {
                console.error('Department dropdown button not found!');
                return;
            }
            
            if (!dropdown) {
                console.error('Department dropdown menu not found!');
                return;
            }
            
            // Toggle dropdown when button is clicked
            dropdownBtn.addEventListener('click', function(event) {
                event.stopPropagation();
                console.log('Department dropdown button clicked');
                const isHidden = dropdown.style.display === 'none' || dropdown.style.display === '';
                dropdown.style.display = isHidden ? 'block' : 'none';
                console.log('Dropdown display changed to:', dropdown.style.display);
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(event) {
                if (!dropdown.contains(event.target) && event.target !== dropdownBtn) {
                    dropdown.style.display = 'none';
                }
            });
            
            // Prevent dropdown from closing when clicking inside it
            dropdown.addEventListener('click', function(event) {
                event.stopPropagation();
            });
        });
    </script>
