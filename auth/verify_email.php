<?php
session_start();
require_once '../config/database.php';
require_once '../includes/EmailService.php';
// Optional app base URL for navigation
$appConfig = @require __DIR__ . '/../config/app_config.php';
$baseUrl = '';
if (is_array($appConfig) && !empty($appConfig['base_url'])) {
    $baseUrl = rtrim($appConfig['base_url'], '/');
}

$verificationStatus = '';
$verificationMessage = '';
$verificationClass = '';

// Allow redirect-based success state (after POST) to avoid resubmission issues
if (isset($_GET['status']) && $_GET['status'] === 'success') {
    $verificationStatus = 'success';
    $verificationMessage = 'Your email has been verified and your password is set. You can now log in.';
    $verificationClass = 'alert-success';
}

// Handle password set submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['token'])) {
    $token = $_POST['token'];
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    try {
        $stmt = $pdo->prepare("SELECT id, name, email, verification_expires, email_verified FROM employees WHERE verification_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) {
            $verificationStatus = 'invalid';
            $verificationMessage = 'Invalid verification link. Please check your email or contact your administrator.';
            $verificationClass = 'alert-danger';
        } elseif (strtotime($user['verification_expires']) < time()) {
            $verificationStatus = 'expired';
            $verificationMessage = 'Verification link has expired. Please contact your administrator for a new verification link.';
            $verificationClass = 'alert-warning';
            $stmt = $pdo->prepare("UPDATE email_verification_logs SET status = 'expired' WHERE verification_token = ?");
            $stmt->execute([$token]);
        } elseif ($user['email_verified']) {
            $verificationStatus = 'already_verified';
            $verificationMessage = 'This email has already been verified. You can now log in to your account.';
            $verificationClass = 'alert-info';
        } else {
            // Validate passwords
            if (strlen($password) < 8) {
                $verificationStatus = 'prompt_password';
                $verificationMessage = 'Password must be at least 8 characters.';
                $verificationClass = 'alert-danger';
            } elseif ($password !== $confirm) {
                $verificationStatus = 'prompt_password';
                $verificationMessage = 'Passwords do not match.';
                $verificationClass = 'alert-danger';
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE employees SET 
                    email_verified = 1,
                    verification_token = NULL,
                    verification_expires = NULL,
                    account_status = 'active',
                    password = ?
                    WHERE id = ?");
                if ($stmt->execute([$hashed, $user['id']])) {
                    // Log successful verification
                    $stmt = $pdo->prepare("UPDATE email_verification_logs SET status = 'verified', verified_at = CURRENT_TIMESTAMP WHERE verification_token = ?");
                    $stmt->execute([$token]);

                    // Optionally send welcome email (no temp password)
                    $emailService = new EmailService();
                    $emailService->sendWelcomeEmail($user['email'], $user['name'], '');

                    // Redirect to GET success state to prevent form resubmission and ensure correct view
                    header('Location: verify_email.php?status=success');
                    exit();
                } else {
                    $verificationStatus = 'error';
                    $verificationMessage = 'An error occurred while setting your password. Please try again.';
                    $verificationClass = 'alert-danger';
                }
            }
        }
    } catch (Exception $e) {
        error_log('Email verification (set password) error: ' . $e->getMessage());
        $verificationStatus = 'error';
        $verificationMessage = 'An unexpected error occurred. Please try again or contact your administrator.';
        $verificationClass = 'alert-danger';
    }
}

if ($verificationStatus === '' && isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];
    
    try {
        // Find user with this verification token
        $stmt = $pdo->prepare("SELECT id, name, email, verification_expires, email_verified FROM employees WHERE verification_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Check if token is expired
            if (strtotime($user['verification_expires']) < time()) {
                $verificationStatus = 'expired';
                $verificationMessage = 'Verification link has expired. Please contact your administrator for a new verification link.';
                $verificationClass = 'alert-warning';
                
                // Log the expired verification attempt
                $stmt = $pdo->prepare("UPDATE email_verification_logs SET status = 'expired' WHERE verification_token = ?");
                $stmt->execute([$token]);
                
            } elseif ($user['email_verified']) {
                $verificationStatus = 'already_verified';
                $verificationMessage = 'This email has already been verified. You can now log in to your account.';
                $verificationClass = 'alert-info';
                
            } else {
                // Prompt user to set password instead of auto-generating
                $verificationStatus = 'prompt_password';
                $verificationMessage = 'Please create a password to activate your account.';
                $verificationClass = 'alert-info';
            }
        } else {
            $verificationStatus = 'invalid';
            $verificationMessage = 'Invalid verification link. Please check your email or contact your administrator.';
            $verificationClass = 'alert-danger';
        }
        
    } catch (Exception $e) {
        error_log("Email verification error: " . $e->getMessage());
        $verificationStatus = 'error';
        $verificationMessage = 'An unexpected error occurred. Please try again or contact your administrator.';
        $verificationClass = 'alert-danger';
    }
} else {
    $verificationStatus = 'no_token';
    $verificationMessage = 'No verification token provided. Please check your email for the verification link.';
    $verificationClass = 'alert-warning';
}

function generateTemporaryPassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[rand(0, strlen($chars) - 1)];
    }
    
    return $password;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - ELMS System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .verification-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            padding: 3rem;
            max-width: 500px;
            width: 100%;
            text-align: center;
        }
        .verification-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        .verification-title {
            color: #1f2937;
            margin-bottom: 1.5rem;
        }
        .verification-message {
            margin-bottom: 2rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .status-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        .status-success { color: #10b981; }
        .status-warning { color: #f59e0b; }
        .status-danger { color: #ef4444; }
        .status-info { color: #3b82f6; }
    </style>
</head>
<body>
    <div class="verification-container">
        <?php if ($verificationStatus === 'prompt_password'): ?>
            <!-- Prompt user to set password -->
            <div class="status-icon status-info">
                <i class="fas fa-key"></i>
            </div>
            <h2 class="verification-title">Create Your Password</h2>
            <div class="verification-message">
                <p class="text-muted"><?php echo htmlspecialchars($verificationMessage); ?></p>
            </div>
            <form method="POST" class="text-start">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token ?? ($_POST['token'] ?? '')); ?>">
                <div class="mb-3">
                    <label class="form-label">New Password</label>
                    <input type="password" name="password" class="form-control" required minlength="8" placeholder="At least 8 characters">
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control" required minlength="8">
                </div>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-save me-2"></i>Set Password & Activate Account
                </button>
            </form>

        <?php elseif ($verificationStatus === 'success' || $verificationStatus === 'success_no_email'): ?>
            <!-- Success State -->
            <div class="status-icon status-success">
                <i class="fas fa-check-circle"></i>
            </div>
            <h2 class="verification-title">🎉 Email Verified Successfully!</h2>
            <div class="verification-message">
                <p class="text-muted">Your account has been activated and you can now access the ELMS System.</p>
                <?php if ($verificationStatus === 'success'): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-envelope me-2"></i>
                        A welcome email with your temporary password has been sent to your email address.
                    </div>
                <?php endif; ?>
            </div>
            <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
                <a href="../auth/index.php" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt me-2"></i>Go to Login
                </a>
                <?php if (!empty($baseUrl)): ?>
                <a href="<?php echo htmlspecialchars($baseUrl); ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-home me-2"></i>Go to the Site
                </a>
                <?php endif; ?>
            </div>
            
        <?php elseif ($verificationStatus === 'already_verified'): ?>
            <!-- Already Verified State -->
            <div class="status-icon status-info">
                <i class="fas fa-info-circle"></i>
            </div>
            <h2 class="verification-title">ℹ️ Already Verified</h2>
            <div class="verification-message">
                <p class="text-muted">This email address has already been verified. You can log in to your account.</p>
            </div>
            <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
                <a href="../auth/index.php" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt me-2"></i>Go to Login
                </a>
                <?php if (!empty($baseUrl)): ?>
                <a href="<?php echo htmlspecialchars($baseUrl); ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-home me-2"></i>Go to the Site
                </a>
                <?php endif; ?>
            </div>
            
        <?php elseif ($verificationStatus === 'expired'): ?>
            <!-- Expired Token State -->
            <div class="status-icon status-warning">
                <i class="fas fa-clock"></i>
            </div>
            <h2 class="verification-title">⏰ Link Expired</h2>
            <div class="verification-message">
                <p class="text-muted">The verification link has expired. Please contact your administrator for a new verification link.</p>
            </div>
            <a href="../auth/index.php" class="btn btn-primary">
                <i class="fas fa-home me-2"></i>Go to Home
            </a>
            
        <?php elseif ($verificationStatus === 'invalid'): ?>
            <!-- Invalid Token State -->
            <div class="status-icon status-danger">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h2 class="verification-title">❌ Invalid Link</h2>
            <div class="verification-message">
                <p class="text-muted">The verification link is invalid. Please check your email or contact your administrator.</p>
            </div>
            <a href="../auth/index.php" class="btn btn-primary">
                <i class="fas fa-home me-2"></i>Go to Home
            </a>
            
        <?php else: ?>
            <!-- Default/Error State -->
            <div class="status-icon status-warning">
                <i class="fas fa-question-circle"></i>
            </div>
            <h2 class="verification-title">❓ Verification Required</h2>
            <div class="verification-message">
                <p class="text-muted">Please check your email for the verification link to activate your account.</p>
            </div>
            <a href="../auth/index.php" class="btn btn-primary">
                <i class="fas fa-home me-2"></i>Go to Home
            </a>
        <?php endif; ?>
        
        <div class="mt-4">
            <small class="text-muted">
                <i class="fas fa-shield-alt me-1"></i>
                Secure verification process
            </small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
