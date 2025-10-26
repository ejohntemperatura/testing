<?php
/**
 * Demo Page - Modern Sidebar Navigation
 * This page demonstrates the new modern sidebar design
 */
session_start();

// Set demo role (change to 'admin', 'director', 'department_head', or 'user')
$_SESSION['role'] = 'admin';
$_SESSION['user_name'] = 'Store Administrator';
$_SESSION['user_email'] = 'admin@elms.com';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modern Sidebar Demo - ELMS</title>
    
    <?php include 'includes/styles/elms_css_system.php'; ?>
    
    <style>
        /* Additional demo styles */
        .demo-section {
            margin-bottom: 2rem;
        }
        
        .demo-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            margin-bottom: 1.5rem;
        }
        
        .demo-card h3 {
            color: #2c3e50;
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .demo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card h4 {
            font-size: 0.875rem;
            font-weight: 600;
            opacity: 0.9;
            margin-bottom: 0.5rem;
        }
        
        .stat-card .value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        
        .stat-card .label {
            font-size: 0.875rem;
            opacity: 0.8;
        }
        
        .feature-list {
            list-style: none;
            padding: 0;
        }
        
        .feature-list li {
            padding: 0.75rem 0;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .feature-list li:last-child {
            border-bottom: none;
        }
        
        .feature-list i {
            color: #10b981;
            width: 20px;
        }
        
        .color-swatch {
            display: inline-block;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            border: 2px solid #e5e7eb;
            margin-right: 0.5rem;
        }
        
        .badge-demo {
            display: inline-flex;
            gap: 0.5rem;
            margin: 0.5rem 0;
        }
    </style>
</head>
<body>
    <?php 
    include 'includes/components/navigation.php';
    renderNavigation($_SESSION['role']);
    ?>
    
    <div class="main-content">
        <div class="container">
            <!-- Page Header -->
            <div style="margin-bottom: 2rem;">
                <h1 style="color: #2c3e50; font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem;">
                    <i class="fas fa-palette"></i> Modern Sidebar Demo
                </h1>
                <p style="color: #64748b; font-size: 1rem;">
                    Preview of the new modern sidebar navigation design with Inter font and clean UI
                </p>
            </div>
            
            <!-- Statistics Cards -->
            <div class="demo-section">
                <div class="demo-grid">
                    <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <h4>Total Users</h4>
                        <div class="value">1,234</div>
                        <div class="label">Active employees</div>
                    </div>
                    
                    <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <h4>Pending Leaves</h4>
                        <div class="value">45</div>
                        <div class="label">Awaiting approval</div>
                    </div>
                    
                    <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                        <h4>Approved Today</h4>
                        <div class="value">23</div>
                        <div class="label">Leave requests</div>
                    </div>
                    
                    <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                        <h4>System Status</h4>
                        <div class="value">99.9%</div>
                        <div class="label">Uptime</div>
                    </div>
                </div>
            </div>
            
            <!-- Features Section -->
            <div class="demo-section">
                <div class="demo-card">
                    <h3><i class="fas fa-check-circle" style="color: #10b981;"></i> New Design Features</h3>
                    <ul class="feature-list">
                        <li>
                            <i class="fas fa-check-circle"></i>
                            <span><strong>Modern Font:</strong> Inter font family for clean, professional look</span>
                        </li>
                        <li>
                            <i class="fas fa-check-circle"></i>
                            <span><strong>Dark Sidebar:</strong> Navy blue (#2c3e50) background like modern inventory systems</span>
                        </li>
                        <li>
                            <i class="fas fa-check-circle"></i>
                            <span><strong>Section Headers:</strong> Organized navigation with uppercase category labels</span>
                        </li>
                        <li>
                            <i class="fas fa-check-circle"></i>
                            <span><strong>Notification Badges:</strong> Red badges for alerts, blue badges for special items</span>
                        </li>
                        <li>
                            <i class="fas fa-check-circle"></i>
                            <span><strong>Smooth Animations:</strong> Hover effects and transitions for better UX</span>
                        </li>
                        <li>
                            <i class="fas fa-check-circle"></i>
                            <span><strong>Responsive Design:</strong> Mobile-friendly with collapsible sidebar</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Color Palette -->
            <div class="demo-section">
                <div class="demo-card">
                    <h3><i class="fas fa-palette"></i> Color Palette</h3>
                    <div style="margin-top: 1rem;">
                        <div style="margin-bottom: 1rem;">
                            <span class="color-swatch" style="background: #2c3e50;"></span>
                            <strong>Sidebar Background:</strong> #2c3e50 (Navy Blue)
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <span class="color-swatch" style="background: #3498db;"></span>
                            <strong>Active/Hover Accent:</strong> #3498db (Sky Blue)
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <span class="color-swatch" style="background: #e74c3c;"></span>
                            <strong>Notification Badge:</strong> #e74c3c (Red)
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <span class="color-swatch" style="background: #16a085;"></span>
                            <strong>Special Badge:</strong> #16a085 (Teal)
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <span class="color-swatch" style="background: #ecf0f1;"></span>
                            <strong>Main Content:</strong> #ecf0f1 (Light Gray)
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Badge Examples -->
            <div class="demo-section">
                <div class="demo-card">
                    <h3><i class="fas fa-tag"></i> Badge Examples</h3>
                    <p style="color: #64748b; margin-bottom: 1rem;">Different badge styles for various use cases:</p>
                    
                    <div style="margin-bottom: 1rem;">
                        <strong>Notification Badge (Red):</strong>
                        <div class="badge-demo">
                            <span class="nav-badge">2</span>
                            <span class="nav-badge">5</span>
                            <span class="nav-badge">12</span>
                            <span class="nav-badge">99+</span>
                        </div>
                    </div>
                    
                    <div>
                        <strong>Special Badge (Blue/Teal):</strong>
                        <div class="badge-demo">
                            <span class="nav-badge-blue">Admin</span>
                            <span class="nav-badge-blue">New</span>
                            <span class="nav-badge-blue">Pro</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Usage Instructions -->
            <div class="demo-section">
                <div class="demo-card">
                    <h3><i class="fas fa-code"></i> Quick Start</h3>
                    <p style="color: #64748b; margin-bottom: 1rem;">To use this design in your pages:</p>
                    
                    <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; border-left: 4px solid #3498db; margin-bottom: 1rem;">
                        <strong>1. Include CSS System:</strong>
                        <pre style="margin: 0.5rem 0 0 0; overflow-x: auto;"><code>&lt;?php include 'includes/styles/elms_css_system.php'; ?&gt;</code></pre>
                    </div>
                    
                    <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; border-left: 4px solid #3498db; margin-bottom: 1rem;">
                        <strong>2. Render Navigation:</strong>
                        <pre style="margin: 0.5rem 0 0 0; overflow-x: auto;"><code>&lt;?php 
include 'includes/components/navigation.php';
renderNavigation('admin'); // or 'user', 'director', 'department_head'
?&gt;</code></pre>
                    </div>
                    
                    <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; border-left: 4px solid #3498db;">
                        <strong>3. Add Main Content:</strong>
                        <pre style="margin: 0.5rem 0 0 0; overflow-x: auto;"><code>&lt;div class="main-content"&gt;
    &lt;div class="container"&gt;
        &lt;!-- Your content here --&gt;
    &lt;/div&gt;
&lt;/div&gt;</code></pre>
                    </div>
                    
                    <div style="margin-top: 1.5rem; padding: 1rem; background: #e8f5e9; border-radius: 8px;">
                        <i class="fas fa-info-circle" style="color: #2e7d32;"></i>
                        <strong style="color: #2e7d32;"> Pro Tip:</strong> 
                        Check <code>docs/MODERN_SIDEBAR_GUIDE.md</code> for complete documentation and customization options.
                    </div>
                </div>
            </div>
            
            <!-- Test Different Roles -->
            <div class="demo-section">
                <div class="demo-card">
                    <h3><i class="fas fa-users-cog"></i> Test Different Roles</h3>
                    <p style="color: #64748b; margin-bottom: 1rem;">Change the role at the top of this file to see different navigation layouts:</p>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        <div style="padding: 1rem; background: #f8f9fa; border-radius: 8px; text-align: center;">
                            <i class="fas fa-shield-alt" style="font-size: 2rem; color: #3498db; margin-bottom: 0.5rem;"></i>
                            <div><strong>Admin</strong></div>
                            <small style="color: #64748b;">Full access</small>
                        </div>
                        
                        <div style="padding: 1rem; background: #f8f9fa; border-radius: 8px; text-align: center;">
                            <i class="fas fa-briefcase" style="font-size: 2rem; color: #9b59b6; margin-bottom: 0.5rem;"></i>
                            <div><strong>Director</strong></div>
                            <small style="color: #64748b;">Management view</small>
                        </div>
                        
                        <div style="padding: 1rem; background: #f8f9fa; border-radius: 8px; text-align: center;">
                            <i class="fas fa-sitemap" style="font-size: 2rem; color: #e67e22; margin-bottom: 0.5rem;"></i>
                            <div><strong>Department Head</strong></div>
                            <small style="color: #64748b;">Department access</small>
                        </div>
                        
                        <div style="padding: 1rem; background: #f8f9fa; border-radius: 8px; text-align: center;">
                            <i class="fas fa-user" style="font-size: 2rem; color: #27ae60; margin-bottom: 0.5rem;"></i>
                            <div><strong>Employee</strong></div>
                            <small style="color: #64748b;">User view</small>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
    
    <!-- Mobile Toggle Button -->
    <button class="mobile-sidebar-toggle" onclick="toggleMobileSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Sidebar Backdrop -->
    <div class="sidebar-backdrop" onclick="toggleMobileSidebar()"></div>
    
    <script>
    function toggleMobileSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const backdrop = document.querySelector('.sidebar-backdrop');
        
        if (sidebar) {
            sidebar.classList.toggle('show');
        }
        
        if (backdrop) {
            backdrop.classList.toggle('show');
        }
    }
    </script>
</body>
</html>
