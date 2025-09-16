<?php
require_once __DIR__ . '/../includes/EmailService.php';

$to = $argv[1] ?? null;
$status = $argv[2] ?? 'approved';
$name = $argv[3] ?? 'Test User';
$start = $argv[4] ?? date('Y-m-d');
$end = $argv[5] ?? date('Y-m-d', strtotime('+1 day'));
$type = $argv[6] ?? 'annual';

if (!$to) {
    fwrite(STDERR, "Usage: php scripts/send_test_leave_status.php <email> [status] [name] [start] [end] [type]\n");
    exit(1);
}

$emailService = new EmailService();
$ok = $emailService->sendLeaveStatusNotification($to, $name, $status, $start, $end, $type);

echo $ok ? "OK: sent to {$to}\n" : "FAIL: could not send to {$to}\n";
