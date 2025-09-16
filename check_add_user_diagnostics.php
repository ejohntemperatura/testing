<?php
// Quick diagnostics for Add User flow
require_once __DIR__ . '/config/database.php';

header('Content-Type: text/plain');

echo "Add User Diagnostics\n======================\n";

try {
    // 1) Check required columns in employees
    $requiredCols = [
        'email_verified','verification_token','verification_expires','account_status',
        'position','department','contact','role','name','email','password'
    ];
    $colsStmt = $pdo->query("DESCRIBE employees");
    $columns = array_column($colsStmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
    $missing = array_values(array_diff($requiredCols, $columns));

    if ($missing) {
        echo "Missing columns in employees: " . implode(', ', $missing) . "\n";
        echo "Fix: Run your DB migration or re-import elms_db.sql to add these columns.\n";
        exit(0);
    }

    echo "Employees table columns OK.\n";

    // 2) Try a dry-run insert (prepare only)
    $sql = "INSERT INTO employees (name, email, password, position, department, contact, role, email_verified, verification_token, verification_expires, account_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, 'pending')";
    $stmt = $pdo->prepare($sql);

    $name = 'Diag Test';
    $email = 'diag+' . time() . '@example.com';
    $temporaryPassword = password_hash('temp_' . time(), PASSWORD_DEFAULT);
    $verificationToken = bin2hex(random_bytes(8));
    $verificationExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));

    // 3) Execute insert
    if ($stmt->execute([$name, $email, $temporaryPassword, 'Tester', 'Diagnostics', 'N/A', 'employee', $verificationToken, $verificationExpires])) {
        echo "Insert OK (id=" . $pdo->lastInsertId() . ")\n";
        // cleanup the test row
        $pdo->prepare("DELETE FROM employees WHERE email = ?")->execute([$email]);
        echo "Cleanup OK\n";
    } else {
        $err = $stmt->errorInfo();
        echo "Insert FAILED: " . implode(' | ', $err) . "\n";
    }

    echo "\nIf insert is OK, adding from Admin should work. If emails still don't arrive, it's a delivery/filtering issue.\n";

} catch (Throwable $t) {
    echo "ERROR: " . $t->getMessage() . "\n";
}
