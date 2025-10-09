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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            line-height: 1.6; 
            color: #333; 
            background: #f8f9fa;
        }
        
        .print-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .header {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 50%, #8b5cf6 100%);
            color: white;
            padding: 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.1"/><circle cx="10" cy="60" r="0.5" fill="white" opacity="0.1"/><circle cx="90" cy="40" r="0.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }
        
        .header-content {
            position: relative;
            z-index: 1;
        }
        
        .header h1 {
            font-size: 2.8rem;
            font-weight: 800;
            margin-bottom: 15px;
            text-shadow: 2px 2px 8px rgba(0,0,0,0.3);
            letter-spacing: 2px;
        }
        
        .header .subtitle {
            font-size: 1.2rem;
            opacity: 0.95;
            font-weight: 400;
            letter-spacing: 1px;
        }
        
        .header .request-id {
            position: absolute;
            top: 25px;
            right: 35px;
            background: rgba(255,255,255,0.25);
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 0.95rem;
            font-weight: 700;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .print-date {
            position: absolute;
            top: 25px;
            left: 35px;
            font-size: 0.85rem;
            opacity: 0.85;
            font-weight: 500;
        }
        
        .content {
            padding: 40px;
        }
        
        .section {
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #1e40af;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e5e7eb;
            position: relative;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 50px;
            height: 2px;
            background: #3b82f6;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #3b82f6;
        }
        
        .info-label {
            font-size: 0.85rem;
            color: #6b7280;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 1rem;
            color: #1f2937;
            font-weight: 500;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-approved {
            background: #dcfce7;
            color: #166534;
        }
        
        .status-rejected {
            background: #fef2f2;
            color: #dc2626;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #d97706;
        }
        
        .reason-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #3b82f6;
        }
        
        .reason-text {
            font-size: 1rem;
            line-height: 1.6;
            color: #374151;
            font-style: italic;
        }
        
        .approval-section {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 12px;
            margin-top: 30px;
        }
        
        .approval-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 20px;
        }
        
        .approval-box {
            background: white;
            padding: 25px;
            border-radius: 8px;
            border: 2px solid #e5e7eb;
            position: relative;
        }
        
        .approval-box.approved {
            border-color: #10b981;
            background: #f0fdf4;
        }
        
        .approval-box.rejected {
            border-color: #ef4444;
            background: #fef2f2;
        }
        
        .approval-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .approval-status {
            margin-bottom: 10px;
        }
        
        .approval-date {
            font-size: 0.9rem;
            color: #6b7280;
            margin-bottom: 15px;
        }
        
        .approval-details {
            font-size: 0.9rem;
            color: #374151;
            margin-bottom: 20px;
        }
        
        .signature-area {
            margin-top: 30px;
            text-align: center;
        }
        
        .signature-line {
            width: 200px;
            height: 1px;
            background: #374151;
            margin: 0 auto 10px;
        }
        
        .signature-label {
            font-size: 0.8rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            color: #6b7280;
            font-size: 0.9rem;
            border-top: 1px solid #e5e7eb;
        }
        
        @media print {
            body { background: white; }
            .print-container { box-shadow: none; }
            .header { -webkit-print-color-adjust: exact; }
            .status-badge { -webkit-print-color-adjust: exact; }
            .approval-box.approved { -webkit-print-color-adjust: exact; }
            .approval-box.rejected { -webkit-print-color-adjust: exact; }
            /* Ensure late application styling prints correctly */
            .late-application-indicator { -webkit-print-color-adjust: exact; }
            .late-justification-section { -webkit-print-color-adjust: exact; }
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
            <div class="print-date">Printed: <?php echo date('F j, Y \a\t g:i A'); ?></div>
            <div class="request-id">Request #<?php echo $leaveRequest['id']; ?></div>
            <div class="header-content">
                <div style="margin-bottom: 20px;">
                    <div style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 50%; margin: 0 auto; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                        <i class="fas fa-building" style="color: white;"></i>
                    </div>
                </div>
                <h1>LEAVE REQUEST FORM</h1>
                <?php if ($leaveRequest['is_late'] == 1): ?>
                <div class="late-application-indicator" style="background: rgba(255, 165, 0, 0.2); border: 2px solid rgba(255, 165, 0, 0.5); border-radius: 8px; padding: 15px; margin: 20px 0; display: flex; align-items: center; justify-content: center; gap: 10px;">
                    <i class="fas fa-exclamation-triangle" style="color: #f59e0b; font-size: 1.2rem;"></i>
                    <span style="color: #f59e0b; font-weight: 700; font-size: 1.1rem;">LATE LEAVE APPLICATION</span>
                </div>
                <?php endif; ?>
                <p class="subtitle">Employee Leave Management System</p>
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.2);">
                    <p style="font-size: 0.9rem; opacity: 0.8; margin: 0;">Official Document</p>
                </div>
            </div>
        </div>
        
        <div class="content">
            <div class="section">
                <h2 class="section-title">Employee Information</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Employee Name</div>
                        <div class="info-value"><?php echo htmlspecialchars($leaveRequest['employee_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Position</div>
                        <div class="info-value"><?php echo htmlspecialchars(!empty($leaveRequest['position']) ? $leaveRequest['position'] : 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Department</div>
                        <div class="info-value"><?php echo htmlspecialchars(!empty($leaveRequest['department']) ? $leaveRequest['department'] : 'N/A'); ?></div>
                    </div>
                </div>
            </div>
            
            <div class="section">
                <h2 class="section-title">Leave Details</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Leave Type</div>
                        <div class="info-value">
                            <?php echo strtoupper(str_replace('_', ' ', $leaveRequest['leave_type'])); ?>
                            <?php if ($leaveRequest['is_late'] == 1): ?>
                                <span style="background: #f59e0b; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.7rem; margin-left: 8px; font-weight: 600;">LATE</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Total Days</div>
                        <div class="info-value"><?php echo !empty($leaveRequest['days_requested']) ? $leaveRequest['days_requested'] : 'N/A'; ?> day(s)</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Start Date</div>
                        <div class="info-value"><?php echo formatDate($leaveRequest['start_date']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">End Date</div>
                        <div class="info-value"><?php echo formatDate($leaveRequest['end_date']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Application Date</div>
                        <div class="info-value"><?php echo formatDate($leaveRequest['created_at']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Current Status</div>
                        <div class="info-value"><?php echo getStatusBadge($leaveRequest['status']); ?></div>
                    </div>
                </div>
            </div>
            
            <div class="section">
                <h2 class="section-title">Reason for Leave</h2>
                <div class="reason-section">
                    <p class="reason-text">"<?php echo htmlspecialchars($leaveRequest['reason']); ?>"</p>
                </div>
            </div>
            
            <?php if ($leaveRequest['is_late'] == 1): ?>
            <div class="section">
                <h2 class="section-title" style="color: #f59e0b;">Late Application Justification</h2>
                <div class="late-justification-section reason-section" style="border-left-color: #f59e0b; background: #fef3c7;">
                    <p class="reason-text" style="color: #92400e; font-weight: 500;">
                        "<?php echo htmlspecialchars($leaveRequest['late_justification'] ?: 'No justification provided'); ?>"
                    </p>
                    <div style="margin-top: 15px; padding: 10px; background: rgba(245, 158, 11, 0.1); border-radius: 6px; border: 1px solid rgba(245, 158, 11, 0.3);">
                        <p style="margin: 0; font-size: 0.9rem; color: #92400e; font-weight: 600;">
                            <i class="fas fa-info-circle" style="margin-right: 5px;"></i>
                            This application was submitted after the required deadline and requires special consideration.
                        </p>
                    </div>
                </div>
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
                <div class="reason-section">
                    <?php if (in_array($leaveType, ['vacation', 'special_privilege'])): ?>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Location Type</div>
                                <div class="info-value"><?php echo !empty($leaveRequest['location_type']) ? ucfirst(str_replace('_', ' ', $leaveRequest['location_type'])) : 'N/A'; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Specific Address</div>
                                <div class="info-value"><?php echo !empty($leaveRequest['location_specify']) ? htmlspecialchars($leaveRequest['location_specify']) : 'N/A'; ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($leaveType === 'sick'): ?>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Medical Condition</div>
                                <div class="info-value"><?php echo !empty($leaveRequest['medical_condition']) ? ucfirst(str_replace('_', ' ', $leaveRequest['medical_condition'])) : 'N/A'; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Illness Specification</div>
                                <div class="info-value"><?php echo !empty($leaveRequest['illness_specify']) ? htmlspecialchars($leaveRequest['illness_specify']) : 'N/A'; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Medical Certificate</div>
                                <div class="info-value">
                                    <?php if (!empty($leaveRequest['medical_certificate_path'])): ?>
                                        <span style="color: #10b981; font-weight: 600;">✓ Medical Certificate Attached</span>
                                        <br><small style="color: #6b7280;">File: <?php echo basename($leaveRequest['medical_certificate_path']); ?></small>
                                    <?php else: ?>
                                        <span style="color: #ef4444;">No Medical Certificate Provided</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($leaveType === 'special_women'): ?>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Illness Specification</div>
                                <div class="info-value"><?php echo !empty($leaveRequest['special_women_condition']) ? htmlspecialchars($leaveRequest['special_women_condition']) : 'N/A'; ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($leaveType === 'study'): ?>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Study Type</div>
                                <div class="info-value"><?php echo !empty($leaveRequest['study_type']) ? ucfirst(str_replace('_', ' ', $leaveRequest['study_type'])) : 'N/A'; ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="approval-section">
                <h2 class="section-title">Approval Section</h2>
                <div class="approval-grid">
                    <div class="approval-box <?php echo $leaveRequest['dept_head_approval'] === 'approved' ? 'approved' : ($leaveRequest['dept_head_approval'] === 'rejected' ? 'rejected' : ''); ?>">
                        <div class="approval-title">
                            <span>Department Head Approval</span>
                        </div>
                        <div class="approval-status">
                            <?php echo getStatusBadge($leaveRequest['dept_head_approval'] ?: 'pending'); ?>
                        </div>
                        <div class="approval-date">
                            <?php echo !empty($leaveRequest['dept_head_approved_at']) ? 'Date: ' . formatDateTime($leaveRequest['dept_head_approved_at']) : 'Pending'; ?>
                        </div>
                        <?php if (!empty($leaveRequest['dept_head_notes'])): ?>
                            <div class="approval-details">Notes: <?php echo htmlspecialchars($leaveRequest['dept_head_notes']); ?></div>
                        <?php endif; ?>
                        <div class="signature-area">
                            <div class="signature-line"></div>
                            <div class="signature-label">Department Head Signature</div>
                        </div>
                    </div>
                    
                    <div class="approval-box <?php echo $leaveRequest['director_approval'] === 'approved' ? 'approved' : ($leaveRequest['director_approval'] === 'rejected' ? 'rejected' : ''); ?>">
                        <div class="approval-title">
                            <span>Director Approval</span>
                        </div>
                        <div class="approval-status">
                            <?php echo getStatusBadge($leaveRequest['director_approval'] ?: 'pending'); ?>
                        </div>
                        <div class="approval-date">
                            <?php echo !empty($leaveRequest['director_approved_at']) ? 'Date: ' . formatDateTime($leaveRequest['director_approved_at']) : 'Pending'; ?>
                        </div>
                        <div class="approval-details">
                            <?php if (!empty($leaveRequest['approved_days_with_pay'])): ?>
                                Days with Pay: <?php echo $leaveRequest['approved_days_with_pay']; ?><br>
                            <?php endif; ?>
                            <?php if (!empty($leaveRequest['approved_days_without_pay'])): ?>
                                Days without Pay: <?php echo $leaveRequest['approved_days_without_pay']; ?><br>
                            <?php endif; ?>
                            <?php if (!empty($leaveRequest['director_notes'])): ?>
                                Notes: <?php echo htmlspecialchars($leaveRequest['director_notes']); ?>
                            <?php endif; ?>
                        </div>
                        <div class="signature-area">
                            <div class="signature-line"></div>
                            <div class="signature-label">Director Signature</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <div style="text-align: left;">
                    <p style="font-weight: 600; margin: 0; color: #374151;">Employee Leave Management System</p>
                    <p style="margin: 5px 0 0 0; font-size: 0.85rem;">Official Leave Request Document</p>
                </div>
                <div style="text-align: right;">
                    <p style="font-size: 0.8rem; margin: 0; color: #6b7280;">Document ID: LR-<?php echo date('Ymd'); ?>-<?php echo str_pad($leaveRequest['id'], 4, '0', STR_PAD_LEFT); ?></p>
                </div>
            </div>
            <div style="border-top: 1px solid #e5e7eb; padding-top: 15px; text-align: center;">
                <p style="margin: 0; font-size: 0.85rem;">For any inquiries, please contact the Human Resources Department</p>
            </div>
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
                window.print();
            };
            document.body.appendChild(printButton);
        }
    </script>
</body>
</html>
