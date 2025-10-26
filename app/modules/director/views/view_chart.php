<?php
session_start();
require_once '../../../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'director') {
    header('Location: ../../../auth/views/login.php');
    exit();
}

// Basic user info
$stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$me = $stmt->fetch();

// Set page title
$page_title = "Director Leave Chart";

// Include director header with modern design
include '../../../../includes/director_header.php';
?>
<link href='../../../../assets/libs/fullcalendar/css/main.min.css' rel='stylesheet' />

<!-- Page Header -->
<h1 class="elms-h1" style="margin-bottom: 0.5rem;">
    Director Leave Chart
</h1>
<p class="elms-text-muted" style="margin-bottom: 2rem;">View and manage all leave requests across the organization</p>

<!-- Use shared calendar component -->
<?php include '../../../../app/shared/components/calendar_component.php'; ?>

<script src="../../../../assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src='../../../../assets/libs/fullcalendar/js/main.min.js'></script>

<?php include '../../../../includes/director_footer.php'; ?>
