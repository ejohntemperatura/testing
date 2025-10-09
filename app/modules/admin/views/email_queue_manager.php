<?php
session_start();
require_once '../../../../config/database.php';
require_once '../../../../app/core/services/OfflineEmailManager.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../../auth/views/login.php');
    exit();
}

$offlineManager = new OfflineEmailManager($pdo);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'process_queue':
            $result = $offlineManager->processQueue();
            $message = $result ? "Queue processed: {$result['sent']} sent, {$result['failed']} failed" : "Failed to process queue";
            break;
            
        case 'test_smtp':
            $result = $offlineManager->testSMTPConnection();
            $message = $result ? "SMTP connection successful" : "SMTP connection failed";
            break;
            
        case 'cleanup':
            $deleted = $offlineManager->cleanupOldEmails();
            $message = "Cleaned up $deleted old emails";
            break;
            
        case 'toggle_offline_mode':
            $newMode = $_POST['offline_mode'] === '1' ? '0' : '1';
            $stmt = $pdo->prepare("UPDATE offline_email_settings SET setting_value = ? WHERE setting_key = 'offline_mode'");
            $stmt->execute([$newMode]);
            $message = "Offline mode " . ($newMode === '1' ? 'enabled' : 'disabled');
            break;
    }
}

// Get queue statistics
$stats = $offlineManager->getQueueStats();
$isOfflineMode = $offlineManager->isOfflineMode();
$isSMTPAvailable = $offlineManager->isSMTPAvailable();

// Get recent emails
$stmt = $pdo->query("
    SELECT * FROM email_queue 
    ORDER BY created_at DESC 
    LIMIT 20
");
$recentEmails = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ELMS - Email Queue Manager</title>
    <!-- OFFLINE Tailwind CSS - No internet required! -->
    <link rel="stylesheet" href="../../../../assets/css/tailwind.css">
    <!-- Font Awesome Local - No internet required! -->
    <link rel="stylesheet" href="../../../../assets/libs/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../../../../assets/css/style.css">
    <link rel="stylesheet" href="../../../../assets/css/admin_style.css">
    <link rel="stylesheet" href="../../../../assets/css/dark-theme.css">
</head>
<body class="bg-slate-900 text-white">
    <?php include '../../../../includes/unified_navbar.php'; ?>

    <!-- Main Content -->
    <div class="pt-20 px-4 md:px-6 py-8">
        <div class="max-w-7xl mx-auto">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-white mb-2">
                    <i class="fas fa-envelope-open-text text-primary mr-3"></i>
                    Email Queue Manager
                </h1>
                <p class="text-slate-400">Manage offline email queue and SMTP settings</p>
            </div>

            <?php if (isset($message)): ?>
                <div class="bg-green-600 text-white p-4 rounded-lg mb-6">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Status Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Offline Mode Status -->
                <div class="bg-slate-800 rounded-2xl border border-slate-700/50 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-slate-400 text-sm">Offline Mode</p>
                            <p class="text-2xl font-bold text-white">
                                <?php echo $isOfflineMode ? 'ON' : 'OFF'; ?>
                            </p>
                        </div>
                        <div class="w-12 h-12 rounded-full flex items-center justify-center <?php echo $isOfflineMode ? 'bg-orange-500' : 'bg-green-500'; ?>">
                            <i class="fas <?php echo $isOfflineMode ? 'fa-wifi-slash' : 'fa-wifi'; ?> text-white text-xl"></i>
                        </div>
                    </div>
                </div>

                <!-- SMTP Status -->
                <div class="bg-slate-800 rounded-2xl border border-slate-700/50 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-slate-400 text-sm">SMTP Status</p>
                            <p class="text-2xl font-bold text-white">
                                <?php echo $isSMTPAvailable ? 'ONLINE' : 'OFFLINE'; ?>
                            </p>
                        </div>
                        <div class="w-12 h-12 rounded-full flex items-center justify-center <?php echo $isSMTPAvailable ? 'bg-green-500' : 'bg-red-500'; ?>">
                            <i class="fas <?php echo $isSMTPAvailable ? 'fa-check' : 'fa-times'; ?> text-white text-xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Pending Emails -->
                <div class="bg-slate-800 rounded-2xl border border-slate-700/50 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-slate-400 text-sm">Pending</p>
                            <p class="text-2xl font-bold text-white"><?php echo $stats['pending'] ?? 0; ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-full flex items-center justify-center bg-yellow-500">
                            <i class="fas fa-clock text-white text-xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Sent Today -->
                <div class="bg-slate-800 rounded-2xl border border-slate-700/50 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-slate-400 text-sm">Sent Today</p>
                            <p class="text-2xl font-bold text-white"><?php echo $stats['sent'] ?? 0; ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-full flex items-center justify-center bg-green-500">
                            <i class="fas fa-paper-plane text-white text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="bg-slate-800 rounded-2xl border border-slate-700/50 p-6 mb-8">
                <h3 class="text-xl font-semibold text-white mb-4">
                    <i class="fas fa-cogs text-primary mr-2"></i>
                    Quick Actions
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="process_queue">
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-3 rounded-lg transition-colors flex items-center justify-center">
                            <i class="fas fa-play mr-2"></i>
                            Process Queue
                        </button>
                    </form>

                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="test_smtp">
                        <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-3 rounded-lg transition-colors flex items-center justify-center">
                            <i class="fas fa-wifi mr-2"></i>
                            Test SMTP
                        </button>
                    </form>

                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="cleanup">
                        <button type="submit" class="w-full bg-orange-600 hover:bg-orange-700 text-white px-4 py-3 rounded-lg transition-colors flex items-center justify-center">
                            <i class="fas fa-trash mr-2"></i>
                            Cleanup Old
                        </button>
                    </form>

                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="toggle_offline_mode">
                        <input type="hidden" name="offline_mode" value="<?php echo $isOfflineMode ? '1' : '0'; ?>">
                        <button type="submit" class="w-full <?php echo $isOfflineMode ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700'; ?> text-white px-4 py-3 rounded-lg transition-colors flex items-center justify-center">
                            <i class="fas <?php echo $isOfflineMode ? 'fa-wifi' : 'fa-wifi-slash'; ?> mr-2"></i>
                            <?php echo $isOfflineMode ? 'Enable Online' : 'Enable Offline'; ?>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Recent Emails Table -->
            <div class="bg-slate-800 rounded-2xl border border-slate-700/50 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-700/50">
                    <h3 class="text-xl font-semibold text-white flex items-center gap-3">
                        <i class="fas fa-list text-primary"></i>
                        Recent Emails
                    </h3>
                    <p class="text-slate-400 text-sm mt-1">Latest email queue entries</p>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-slate-700/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-300 uppercase tracking-wider">To</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-300 uppercase tracking-wider">Subject</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-300 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-300 uppercase tracking-wider">Priority</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-300 uppercase tracking-wider">Created</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-300 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/50">
                            <?php foreach ($recentEmails as $email): ?>
                                <tr class="hover:bg-slate-700/30">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-white">
                                        <?php echo htmlspecialchars($email['to_email']); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-300 max-w-xs truncate">
                                        <?php echo htmlspecialchars($email['subject']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            <?php
                                            switch ($email['status']) {
                                                case 'sent': echo 'bg-green-100 text-green-800'; break;
                                                case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                                case 'sending': echo 'bg-blue-100 text-blue-800'; break;
                                                case 'failed': echo 'bg-red-100 text-red-800'; break;
                                                case 'cancelled': echo 'bg-gray-100 text-gray-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                            <?php echo ucfirst($email['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-300">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            <?php
                                            switch ($email['priority']) {
                                                case 'urgent': echo 'bg-red-100 text-red-800'; break;
                                                case 'high': echo 'bg-orange-100 text-orange-800'; break;
                                                case 'normal': echo 'bg-blue-100 text-blue-800'; break;
                                                case 'low': echo 'bg-gray-100 text-gray-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                            <?php echo ucfirst($email['priority']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-300">
                                        <?php echo date('M j, Y g:i A', strtotime($email['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-300">
                                        <div class="flex space-x-2">
                                            <button class="text-blue-400 hover:text-blue-300" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($email['status'] === 'pending'): ?>
                                                <button class="text-green-400 hover:text-green-300" title="Send Now">
                                                    <i class="fas fa-paper-plane"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Queue Statistics -->
            <div class="mt-8 bg-slate-800 rounded-2xl border border-slate-700/50 p-6">
                <h3 class="text-xl font-semibold text-white mb-4">
                    <i class="fas fa-chart-bar text-primary mr-2"></i>
                    Queue Statistics
                </h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-yellow-400"><?php echo $stats['pending'] ?? 0; ?></div>
                        <div class="text-sm text-slate-400">Pending</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-green-400"><?php echo $stats['sent'] ?? 0; ?></div>
                        <div class="text-sm text-slate-400">Sent</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-red-400"><?php echo $stats['failed'] ?? 0; ?></div>
                        <div class="text-sm text-slate-400">Failed</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-blue-400"><?php echo $stats['urgent'] ?? 0; ?></div>
                        <div class="text-sm text-slate-400">Urgent</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh every 30 seconds
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
