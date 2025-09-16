<?php
// Shared Header Component for ELMS
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? 'employee';

// Determine panel title and logo based on role
$panelTitle = $role === 'director' ? 'ELMS Director' : 
              ($role === 'manager' ? 'ELMS Department Head' : 
              ($role === 'admin' ? 'ELMS Admin' : 'ELMS Employee'));

$logoIcon = $role === 'director' ? 'fas fa-crown' : 
            ($role === 'manager' ? 'fas fa-user-tie' : 
            ($role === 'admin' ? 'fas fa-user-shield' : 'fas fa-user'));

$logoColor = $role === 'director' ? 'from-purple-500 to-pink-500' : 
             ($role === 'manager' ? 'from-orange-500 to-red-500' : 
             ($role === 'admin' ? 'from-primary to-accent' : 'from-primary to-accent'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'ELMS'; ?></title>
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
                        <div class="w-8 h-8 bg-gradient-to-r <?php echo $logoColor; ?> rounded-lg flex items-center justify-center">
                            <i class="<?php echo $logoIcon; ?> text-white text-sm"></i>
                        </div>
                        <span class="text-xl font-bold text-white"><?php echo $panelTitle; ?></span>
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
                // Include the sidebar based on role
                if ($role === 'admin') {
                    include 'admin_sidebar.php';
                } elseif ($role === 'manager') {
                    include 'manager_sidebar.php';
                } elseif ($role === 'director') {
                    include 'director_sidebar.php';
                } else {
                    include 'employee_sidebar.php';
                }
                ?>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="flex-1 ml-64 p-6">
            <div class="max-w-7xl mx-auto">