<?php
// Redirect to shared leave actions component
header('Location: ../app/shared/leave_actions.php?action=approve&request_id=' . ($_GET['request_id'] ?? ''));
exit();
?>
