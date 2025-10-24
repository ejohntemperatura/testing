<?php
session_start();
require_once '../../../../config/database.php';

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../../auth/views/login.php');
    exit();
}

// Get leave request ID
$leave_id = $_GET['id'] ?? null;

if (!$leave_id || !is_numeric($leave_id)) {
    die('Invalid leave request ID');
}

try {
    // Get leave request details with employee information
    $stmt = $pdo->prepare("
        SELECT 
            lr.*,
            e.name as employee_name,
            e.position,
            e.department,
            e.id as emp_id
        FROM leave_requests lr
        JOIN employees e ON lr.employee_id = e.id
        WHERE lr.id = ?
    ");
    $stmt->execute([$leave_id]);
    $leaveRequest = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$leaveRequest) {
        die('Leave request not found');
    }
    
    
    // Format dates
    function formatDate($dateString) {
        if (!$dateString) return 'N/A';
        return date('F j, Y', strtotime($dateString));
    }
    
    function formatDateTime($dateString) {
        if (!$dateString) return 'N/A';
        return date('F j, Y \a\t g:i A', strtotime($dateString));
    }
    
    // Get status badge HTML
    function getStatusBadge($status) {
        $statusMap = [
            'approved' => ['class' => 'status-approved', 'text' => 'APPROVED'],
            'rejected' => ['class' => 'status-rejected', 'text' => 'REJECTED'],
            'pending' => ['class' => 'status-pending', 'text' => 'PENDING']
        ];
        $statusInfo = $statusMap[strtolower($status)] ?? ['class' => 'status-pending', 'text' => strtoupper($status)];
        return '<span class="status-badge ' . $statusInfo['class'] . '">' . $statusInfo['text'] . '</span>';
    }
    
} catch (Exception $e) {
    die('Error fetching leave request: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
    <!-- OFFLINE Tailwind CSS - No internet required! -->
    <link rel="stylesheet" href="../../../../assets/css/tailwind.css">
        <!-- Font Awesome Local - No internet required! -->
    <link rel="stylesheet" href="../../../../assets/libs/fontawesome/css/all.min.css">
    

    <title>Leave Request - <?php echo htmlspecialchars($leaveRequest['employee_name']); ?></title>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: Arial, sans-serif; 
            line-height: 1.6; 
            color: #333; 
            background: white;
        }
        
        .print-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #333;
        }
        
        .header h1 {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .header .subtitle {
            font-size: 16px;
            color: #666;
        }
        
        .content {
            padding: 0;
        }
        
        .section {
            margin-bottom: 25px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 15px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
        }
        
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .info-table td {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }
        
        .info-table td:first-child {
            width: 200px;
            font-weight: bold;
            color: #555;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-approved {
            background: #d4edda;
            color: #155724;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .reason-section {
            background: #f8f9fa;
            padding: 15px;
            border: 1px solid #ddd;
            margin: 15px 0;
        }
        
        .reason-text {
            font-style: italic;
            color: #555;
        }
        
        .approval-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #333;
        }
        
        .approval-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .approval-table th,
        .approval-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        
        .approval-table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ccc;
            text-align: center;
            color: #666;
            font-size: 12px;
        }
        
        @media print {
            body { background: white; }
            .print-container { box-shadow: none; }
        }
        
        @page {
            margin: 0.5in;
            size: A4;
        }
    </style>
</head>
<body>

    <div class="print-container">
        <div class="header">
            <h1>LEAVE REQUEST FORM</h1>
            <p class="subtitle">Employee Leave Management System</p>
            <p style="font-size: 12px; color: #666; margin-top: 10px;">
                Request #<?php echo $leaveRequest['id']; ?> | Printed: <?php echo date('F j, Y \a\t g:i A'); ?>
            </p>
            <?php if ($leaveRequest['is_late'] == 1): ?>
            <p style="color: #f59e0b; font-weight: bold; margin-top: 10px;">⚠️ LATE LEAVE APPLICATION</p>
            <?php endif; ?>
        </div>
        
        <div class="content">
            <div class="section">
                <h2 class="section-title">Employee Information</h2>
                <table class="info-table">
                    <tr>
                        <td>Employee Name:</td>
                        <td><?php echo htmlspecialchars($leaveRequest['employee_name']); ?></td>
                    </tr>
                    <tr>
                        <td>Position:</td>
                        <td><?php echo htmlspecialchars(!empty($leaveRequest['position']) ? $leaveRequest['position'] : 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <td>Department:</td>
                        <td><?php echo htmlspecialchars(!empty($leaveRequest['department']) ? $leaveRequest['department'] : 'N/A'); ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="section">
                <h2 class="section-title">Leave Details</h2>
                <table class="info-table">
                    <tr>
                        <td>Leave Type:</td>
                        <td>
                            <?php echo strtoupper(str_replace('_', ' ', $leaveRequest['leave_type'])); ?>
                            <?php if ($leaveRequest['is_late'] == 1): ?>
                                <span style="background: #f59e0b; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; margin-left: 5px; font-weight: bold;">LATE</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Total Days:</td>
                        <td><?php echo !empty($leaveRequest['days_requested']) ? $leaveRequest['days_requested'] : 'N/A'; ?> day(s)</td>
                    </tr>
                    <tr>
                        <td>Start Date:</td>
                        <td><?php echo formatDate($leaveRequest['start_date']); ?></td>
                    </tr>
                    <tr>
                        <td>End Date:</td>
                        <td><?php echo formatDate($leaveRequest['end_date']); ?></td>
                    </tr>
                    <tr>
                        <td>Application Date:</td>
                        <td><?php echo formatDate($leaveRequest['created_at']); ?></td>
                    </tr>
                    <tr>
                        <td>Current Status:</td>
                        <td><?php echo getStatusBadge($leaveRequest['status']); ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="section">
                <h2 class="section-title">Reason for Leave</h2>
                <p style="padding: 10px; background: #f8f9fa; border: 1px solid #ddd; font-style: italic;">
                    "<?php echo htmlspecialchars($leaveRequest['reason']); ?>"
                </p>
            </div>
            
            <?php if ($leaveRequest['is_late'] == 1): ?>
            <div class="section">
                <h2 class="section-title" style="color: #f59e0b;">Late Application Justification</h2>
                <p style="padding: 10px; background: #fef3c7; border: 1px solid #f59e0b; color: #92400e; font-style: italic;">
                    "<?php echo htmlspecialchars($leaveRequest['late_justification'] ?: 'No justification provided'); ?>"
                </p>
                <p style="font-size: 12px; color: #92400e; margin-top: 10px; padding: 8px; background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3);">
                    ⚠️ This application was submitted after the required deadline and requires special consideration.
                </p>
            </div>
            <?php endif; ?>
            
            <?php
            // Show conditional details based on leave type
            $leaveType = strtolower($leaveRequest['leave_type']);
            $hasConditionalDetails = false;
            ?>
            
            <?php if (in_array($leaveType, ['vacation', 'special_privilege', 'sick', 'special_women', 'study'])): ?>
            <div class="section">
                <h2 class="section-title">Additional Details</h2>
                <table class="info-table">
                    <?php if (in_array($leaveType, ['vacation', 'special_privilege'])): ?>
                        <tr>
                            <td>Location Type:</td>
                            <td><?php echo !empty($leaveRequest['location_type']) ? ucfirst(str_replace('_', ' ', $leaveRequest['location_type'])) : 'N/A'; ?></td>
                        </tr>
                        <tr>
                            <td>Specific Address:</td>
                            <td><?php echo !empty($leaveRequest['location_specify']) ? htmlspecialchars($leaveRequest['location_specify']) : 'N/A'; ?></td>
                        </tr>
                    <?php endif; ?>
                    
                    <?php if ($leaveType === 'sick'): ?>
                        <tr>
                            <td>Medical Condition:</td>
                            <td><?php echo !empty($leaveRequest['medical_condition']) ? ucfirst(str_replace('_', ' ', $leaveRequest['medical_condition'])) : 'N/A'; ?></td>
                        </tr>
                        <tr>
                            <td>Illness Specification:</td>
                            <td><?php echo !empty($leaveRequest['illness_specify']) ? htmlspecialchars($leaveRequest['illness_specify']) : 'N/A'; ?></td>
                        </tr>
                        <tr>
                            <td>Medical Certificate:</td>
                            <td>
                                <?php if (!empty($leaveRequest['medical_certificate_path'])): ?>
                                    <span style="color: #10b981; font-weight: bold;">✓ Medical Certificate Attached</span>
                                    <br><small style="color: #666;">File: <?php echo basename($leaveRequest['medical_certificate_path']); ?></small>
                                <?php else: ?>
                                    <span style="color: #ef4444;">No Medical Certificate Provided</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                    
                    <?php if ($leaveType === 'special_women'): ?>
                        <tr>
                            <td>Illness Specification:</td>
                            <td><?php echo !empty($leaveRequest['special_women_condition']) ? htmlspecialchars($leaveRequest['special_women_condition']) : 'N/A'; ?></td>
                        </tr>
                    <?php endif; ?>
                    
                    <?php if ($leaveType === 'study'): ?>
                        <tr>
                            <td>Study Type:</td>
                            <td><?php echo !empty($leaveRequest['study_type']) ? ucfirst(str_replace('_', ' ', $leaveRequest['study_type'])) : 'N/A'; ?></td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
            <?php endif; ?>
            
            
            <div class="approval-section">
                <h2 class="section-title">Approval Section</h2>
                <table class="approval-table">
                    <thead>
                        <tr>
                            <th>Approver</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Notes</th>
                            <th>Signature</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Department Head</strong></td>
                            <td><?php echo getStatusBadge($leaveRequest['dept_head_approval'] ?: 'pending'); ?></td>
                            <td><?php echo !empty($leaveRequest['dept_head_approved_at']) ? formatDateTime($leaveRequest['dept_head_approved_at']) : 'Pending'; ?></td>
                            <td>
                                <?php if (!empty($leaveRequest['dept_head_rejection_reason'])): ?>
                                    <?php echo htmlspecialchars($leaveRequest['dept_head_rejection_reason']); ?>
                                <?php elseif (!empty($leaveRequest['approved_days_with_pay']) || !empty($leaveRequest['approved_days_without_pay'])): ?>
                                    <?php if (!empty($leaveRequest['approved_days_with_pay'])): ?>
                                        <?php echo $leaveRequest['approved_days_with_pay']; ?> day(s) with pay
                                    <?php endif; ?>
                                    <?php if (!empty($leaveRequest['approved_days_without_pay'])): ?>
                                        <?php if (!empty($leaveRequest['approved_days_with_pay'])): ?><br><?php endif; ?>
                                        <?php echo $leaveRequest['approved_days_without_pay']; ?> day(s) without pay
                                    <?php endif; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td style="height: 40px; vertical-align: bottom;">
                                <div style="border-bottom: 1px solid #333; width: 150px; margin-bottom: 5px;"></div>
                                <small>Signature</small>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Director</strong></td>
                            <td><?php 
                                $director_display_status = ($leaveRequest['dept_head_approval'] === 'rejected') ? 'rejected' : ($leaveRequest['director_approval'] ?: 'pending');
                                echo getStatusBadge($director_display_status); 
                            ?></td>
                            <td><?php echo !empty($leaveRequest['director_approved_at']) ? formatDateTime($leaveRequest['director_approved_at']) : 'Pending'; ?></td>
                            <td>
                                <?php if (!empty($leaveRequest['director_rejection_reason'])): ?>
                                    <?php echo htmlspecialchars($leaveRequest['director_rejection_reason']); ?>
                                <?php elseif (!empty($leaveRequest['approved_days_with_pay']) || !empty($leaveRequest['approved_days_without_pay'])): ?>
                                    <?php if (!empty($leaveRequest['approved_days_with_pay'])): ?>
                                        <?php echo $leaveRequest['approved_days_with_pay']; ?> day(s) with pay
                                    <?php endif; ?>
                                    <?php if (!empty($leaveRequest['approved_days_without_pay'])): ?>
                                        <?php if (!empty($leaveRequest['approved_days_with_pay'])): ?><br><?php endif; ?>
                                        <?php echo $leaveRequest['approved_days_without_pay']; ?> day(s) without pay
                                    <?php endif; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td style="height: 40px; vertical-align: bottom;">
                                <div style="border-bottom: 1px solid #333; width: 150px; margin-bottom: 5px;"></div>
                                <small>Signature</small>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="footer">
            <p><strong>Employee Leave Management System</strong> - Official Leave Request Document</p>
            <p>Document ID: LR-<?php echo date('Ymd'); ?>-<?php echo str_pad($leaveRequest['id'], 4, '0', STR_PAD_LEFT); ?></p>
            <p>For any inquiries, please contact the Human Resources Department</p>
        </div>
    </div>
    
    <script>
        // Add print button functionality
        window.onload = function() {
            // Add a print button to the page
            const printButton = document.createElement('button');
            printButton.innerHTML = '<i class="fas fa-print"></i> Print Document';
            printButton.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #3b82f6;
                color: white;
                border: none;
                padding: 12px 20px;
                border-radius: 8px;
                cursor: pointer;
                font-weight: 600;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 1000;
            `;
            printButton.onclick = function() {
                // Hide the print button immediately before printing
                printButton.style.display = 'none';
                // Small delay to ensure button is hidden before print dialog
                setTimeout(function() {
                    window.print();
                }, 100);
            };
            document.body.appendChild(printButton);
        }
    </script>
</body>
</html>
