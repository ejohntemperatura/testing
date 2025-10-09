<?php
session_start();
session_destroy();
header('Location: ../../auth/views/login.php');
exit();
?> 