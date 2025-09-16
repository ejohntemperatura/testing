<?php
session_start();
session_destroy();
header('Location: ../auth/index.php');
exit();
?> 