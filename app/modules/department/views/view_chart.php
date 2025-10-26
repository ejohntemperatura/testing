<?php
session_start();
require_once '../../../../config/database.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin','manager','department_head'])) {
    header('Location: ../../../auth/views/login.php');
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$me = $stmt->fetch();

// Set page title
$page_title = "Department Leave Chart";

// Include department header
include '../../../../includes/department_header.php';
?>
<link href='../../../../assets/libs/fullcalendar/css/main.min.css' rel='stylesheet' />

<!-- Page Header -->
<h1 class="elms-h1" style="margin-bottom: 0.5rem; display: flex; align-items: center;">
    <i class="fas fa-calendar" style="color: #0891b2; margin-right: 0.75rem;"></i>Department Leave Chart
</h1>
<p class="elms-text-muted" style="margin-bottom: 2rem;">View and manage leave requests for <?php echo htmlspecialchars($me['department']); ?> department</p>

<!-- Use shared calendar component -->
<?php include '../../../../app/shared/components/calendar_component.php'; ?>

<script src="../../../../assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src='../../../../assets/libs/fullcalendar/js/main.min.js'></script>

<?php include '../../../../includes/department_footer.php'; ?>
