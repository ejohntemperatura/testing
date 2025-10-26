<?php
session_start();
require_once '../../../../config/database.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin','manager','director'])) {
    header('Location: ../../../auth/views/login.php');
    exit();
}

// Redirect to view_chart.php for consistency
header('Location: view_chart.php');
exit();

$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// Set page title
$page_title = "Leave Calendar";

// Include admin header
include '../../../../includes/admin_header.php';
?>
<link href='../../../../assets/libs/fullcalendar/css/main.min.css' rel='stylesheet' />
                <div class="mb-8">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-gradient-to-r from-primary to-accent rounded-2xl flex items-center justify-center">
                            <i class="fas fa-calendar-alt text-2xl text-white"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-white mb-2">Admin Leave Calendar</h1>
                            <p class="text-slate-400">View and manage all leave requests across the organization</p>
                        </div>
                    </div>
                </div>
                
                <!-- Use shared calendar component -->
                <?php include '../../../shared/components/calendar_component.php'; ?>
            </div>
        </main>
    </div>
    
    <script src="../../../../assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src='../../../../assets/libs/fullcalendar/js/main.min.js'></script>
    <script>
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
    </script>
</body>
</html>