<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['logged_in_this_session'] = true;
        
        // For employees, always redirect to DTR page first
        if ($user['role'] === 'employee') {
            header('Location: ../user/dtr.php');
            exit();
        }
        
        // For admin, go directly to admin dashboard
        if ($user['role'] === 'admin') {
            header('Location: ../admin/admin_dashboard.php');
            exit();
        }

        // For department head/manager
        if ($user['role'] === 'manager') {
            header('Location: ../department/department_head_dashboard.php');
            exit();
        }

        // For director head
        if ($user['role'] === 'director') {
            header('Location: ../director/director_head_dashboard.php');
            exit();
        }

        // Default fallback
        header('Location: ../user/dashboard.php');
        exit();
    } else {
        $error = "Invalid email or password";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ELMS - Login</title>
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
                    },
                    animation: {
                        'float': 'float 6s ease-in-out infinite',
                        'slide-up': 'slideUp 0.5s ease-out',
                        'fade-in': 'fadeIn 0.6s ease-out',
                        'pulse-slow': 'pulse 3s infinite'
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0px) rotate(0deg)' },
                            '50%': { transform: 'translateY(-20px) rotate(180deg)' }
                        },
                        slideUp: {
                            '0%': { transform: 'translateY(20px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' }
                        },
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' }
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-900 min-h-screen flex items-center justify-center relative overflow-x-hidden">
    <!-- Animated Background -->
    <div class="fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute w-72 h-72 bg-primary/20 rounded-full -top-36 -left-36 animate-float"></div>
        <div class="absolute w-48 h-48 bg-accent/20 rounded-full top-20 -right-24 animate-float" style="animation-delay: 2s;"></div>
        <div class="absolute w-36 h-36 bg-secondary/20 rounded-full -bottom-18 left-20 animate-float" style="animation-delay: 4s;"></div>
        <div class="absolute w-60 h-60 bg-primary/10 rounded-full bottom-10 right-10 animate-float" style="animation-delay: 1s;"></div>
    </div>

    <div class="w-full max-w-6xl mx-auto px-8 grid grid-cols-1 lg:grid-cols-2 gap-16 items-center min-h-screen py-8">
        <!-- Left Side - Branding -->
        <div class="text-white z-10 order-2 lg:order-1">
            <div class="w-20 h-20 bg-slate-800/50 backdrop-blur-xl border border-slate-700/50 rounded-2xl flex items-center justify-center mb-8 shadow-2xl animate-pulse-slow">
                <i class="fas fa-calendar-check text-4xl bg-gradient-to-r from-primary to-accent bg-clip-text text-transparent"></i>
            </div>
            <h1 class="text-6xl lg:text-7xl font-black leading-tight mb-4 bg-gradient-to-r from-white to-slate-300 bg-clip-text text-transparent">
                ELMS
            </h1>
            <p class="text-xl text-slate-300 mb-8 leading-relaxed">
                Employee Leave Management System
            </p>
            <ul class="space-y-4">
                <li class="flex items-center text-slate-300">
                    <div class="w-5 h-5 bg-gradient-to-r from-primary to-accent rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-check text-xs text-white"></i>
                    </div>
                    Streamlined leave request process
                </li>
                <li class="flex items-center text-slate-300">
                    <div class="w-5 h-5 bg-gradient-to-r from-primary to-accent rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-check text-xs text-white"></i>
                    </div>
                    Real-time approval workflow
                </li>
                <li class="flex items-center text-slate-300">
                    <div class="w-5 h-5 bg-gradient-to-r from-primary to-accent rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-check text-xs text-white"></i>
                    </div>
                    Comprehensive reporting dashboard
                </li>
            </ul>
        </div>

        <!-- Right Side - Login Form -->
        <div class="z-10 order-1 lg:order-2">
            <div class="bg-slate-800/50 backdrop-blur-xl border border-slate-700/50 rounded-3xl p-8 w-full max-w-md mx-auto shadow-2xl animate-slide-up">
                <div class="text-center mb-8">
                    <h2 class="text-3xl font-bold text-white mb-2">Welcome Back</h2>
                    <p class="text-slate-400">Sign in to your account to continue</p>
                </div>

                <?php if (isset($error)): ?>
                    <div class="bg-red-500/20 border border-red-500/30 text-red-400 p-4 rounded-xl mb-6 flex items-center animate-shake">
                        <i class="fas fa-exclamation-circle mr-3"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="login-form" class="space-y-6">
                    <div>
                        <label for="email" class="block text-sm font-semibold text-slate-300 mb-2 uppercase tracking-wide">Email Address</label>
                        <div class="relative">
                            <input type="email" 
                                   id="email" 
                                   name="email" 
                                   placeholder="Enter your email" 
                                   required 
                                   autocomplete="username"
                                   class="w-full bg-slate-700/50 border border-slate-600 rounded-xl px-4 py-4 pl-12 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-300">
                            <i class="fas fa-envelope absolute left-4 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
                        </div>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-semibold text-slate-300 mb-2 uppercase tracking-wide">Password</label>
                        <div class="relative">
                            <input type="password" 
                                   id="password" 
                                   name="password" 
                                   placeholder="Enter your password" 
                                   required 
                                   autocomplete="current-password"
                                   class="w-full bg-slate-700/50 border border-slate-600 rounded-xl px-4 py-4 pl-12 pr-12 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-300">
                            <i class="fas fa-lock absolute left-4 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
                            <button type="button" 
                                    onclick="togglePassword()" 
                                    aria-label="Toggle password visibility"
                                    class="absolute right-4 top-1/2 transform -translate-y-1/2 text-slate-400 hover:text-white transition-colors p-2 rounded-lg hover:bg-slate-600/50">
                                <i class="fas fa-eye" id="toggle-icon"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" 
                            id="login-btn"
                            class="w-full bg-gradient-to-r from-primary to-accent hover:from-primary/90 hover:to-accent/90 text-white font-semibold py-4 px-6 rounded-xl transition-all duration-300 transform hover:scale-[1.02] hover:shadow-xl relative overflow-hidden group">
                        <span class="relative z-10 flex items-center justify-center">
                            <i class="fas fa-sign-in-alt mr-2"></i>
                            <span id="login-btn-text">Sign In</span>
                        </span>
                        <div class="absolute inset-0 bg-gradient-to-r from-primary/20 to-accent/20 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                    </button>
                </form>

                <div class="text-center mt-6">
                    <a href="#" class="text-slate-400 hover:text-white transition-colors text-sm font-medium">
                        Forgot your password?
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Password toggle functionality
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const icon = document.getElementById('toggle-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Enhanced form submission with loading states
        document.getElementById('login-form').addEventListener('submit', function(e) {
            const btn = document.getElementById('login-btn');
            const btnText = document.getElementById('login-btn-text');
            
            // Disable button and show loading state
            btn.disabled = true;
            btnText.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Signing In...';
            btn.classList.add('opacity-75', 'cursor-not-allowed');
        });

        // Add focus effects to form inputs
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('ring-2', 'ring-primary/50');
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('ring-2', 'ring-primary/50');
            });
        });

        // Add keyboard navigation support
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && e.target.classList.contains('password-toggle')) {
                togglePassword();
            }
        });

        // Add ripple effect to button
        document.getElementById('login-btn').addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            ripple.classList.add('absolute', 'bg-white', 'bg-opacity-30', 'rounded-full', 'pointer-events-none', 'animate-ping');
            
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });

        // Add shake animation for error messages
        const errorDiv = document.querySelector('.bg-red-500/20');
        if (errorDiv) {
            errorDiv.classList.add('animate-bounce');
        }
    </script>
</body>
</html> 