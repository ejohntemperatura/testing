<?php
session_start();
require_once '../../../../config/database.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin','manager','director'])) {
    header('Location: ../../../auth/views/login.php');
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<?php
// Set page title and include shared admin header (dark theme + sidebar)
$page_title = 'Leave Chart';
include '../../../../includes/admin_header.php';
?>
<link href='../../../../assets/libs/fullcalendar/css/main.min.css' rel='stylesheet' />

<!-- Page Header -->
<h1 class="elms-h1" style="margin-bottom: 0.5rem; display: flex; align-items: center;">
    <i class="fas fa-calendar" style="color: #0891b2; margin-right: 0.75rem;"></i>Admin Leave Chart
</h1>
<p class="elms-text-muted" style="margin-bottom: 2rem;">View and manage all leave requests across the organization</p>

<!-- Shared calendar component -->
<?php include '../../../../app/shared/components/calendar_component.php'; ?>

<script src='../../../../assets/libs/fullcalendar/js/main.min.js'></script>

<?php include '../../../../includes/admin_footer.php'; ?>
