<?php
/**
 * Leave Credits Manager
 * Handles leave credits deduction and restoration based on leave status
 */
class LeaveCreditsManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Map leave types to their corresponding credit fields
     * Based on Civil Service Commission (CSC) rules and regulations
     */
    private function getLeaveTypeMapping() {
        require_once dirname(__DIR__, 3) . '/config/leave_types.php';
        $leaveTypes = getLeaveTypes();
        
        $mapping = [];
        foreach ($leaveTypes as $type => $config) {
            if ($config['requires_credits']) {
                $mapping[$type] = $config['credit_field'];
            }
        }
        
        return $mapping;
    }
    
    /**
     * Calculate days between start and end date
     */
    private function calculateDays($start_date, $end_date) {
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        return $end->diff($start)->days + 1;
    }
    
    /**
     * Check if employee has sufficient leave credits (CSC Compliant)
     */
    public function checkLeaveCredits($employee_id, $leave_type, $start_date, $end_date) {
        $days_requested = $this->calculateDays($start_date, $end_date);
        
        // Handle CTO specially - it uses hours instead of days
        if ($leave_type === 'cto') {
            $hours_requested = $days_requested * 8; // Convert days to hours
            
            // Check if CTO balance column exists
            $stmt = $this->pdo->query("DESCRIBE employees");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!in_array('cto_balance', $columns)) {
                return ['sufficient' => false, 'message' => 'CTO balance tracking not available'];
            }
            
            // Get current CTO balance
            $stmt = $this->pdo->prepare("SELECT cto_balance FROM employees WHERE id = ?");
            $stmt->execute([$employee_id]);
            $available_credits = $stmt->fetchColumn() ?: 0;
            
            // Check if user has any CTO credits available
            if ($available_credits <= 0) {
                return [
                    'sufficient' => false,
                    'available' => 0,
                    'requested' => $hours_requested,
                    'message' => "You have no CTO credits available. Current balance: {$available_credits} hours"
                ];
            }
            
            // Check if requested hours exceed available credits
            if ($hours_requested > $available_credits) {
                return [
                    'sufficient' => false,
                    'available' => $available_credits,
                    'requested' => $hours_requested,
                    'message' => "Insufficient CTO balance. Available: {$available_credits} hours, Requested: {$hours_requested} hours"
                ];
            }
            
            return [
                'sufficient' => true,
                'available' => $available_credits,
                'requested' => $hours_requested,
                'message' => 'Sufficient CTO credits available'
            ];
        }
        
        $mapping = $this->getLeaveTypeMapping();
        
        // Check if leave type requires credits
        if (!array_key_exists($leave_type, $mapping)) {
            // Check if it's a leave type that doesn't require credits
            require_once dirname(__DIR__, 3) . '/config/leave_types.php';
            $leaveTypes = getLeaveTypes();
            
            if (isset($leaveTypes[$leave_type]) && !$leaveTypes[$leave_type]['requires_credits']) {
                return [
                    'sufficient' => true,
                    'available' => 'N/A',
                    'requested' => $days_requested,
                    'message' => 'This leave type does not require credits (CSC compliant)'
                ];
            }
            
            return ['sufficient' => false, 'message' => 'Invalid leave type'];
        }
        
        $credit_field = $mapping[$leave_type];
        
        // Check if column exists
        $stmt = $this->pdo->query("DESCRIBE employees");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array($credit_field, $columns)) {
            return ['sufficient' => false, 'message' => 'Leave type not supported in current system'];
        }
        
        $stmt = $this->pdo->prepare("SELECT {$credit_field} FROM employees WHERE id = ?");
        $stmt->execute([$employee_id]);
        $available_credits = $stmt->fetchColumn() ?: 0;
        
        // Apply CSC-specific validation rules
        $validation = $this->validateCSCLeaveRules($leave_type, $employee_id, $days_requested, $available_credits);
        if (!$validation['valid']) {
            return [
                'sufficient' => false,
                'available' => $available_credits,
                'requested' => $days_requested,
                'message' => $validation['message']
            ];
        }
        
        return [
            'sufficient' => $available_credits >= $days_requested,
            'available' => $available_credits,
            'requested' => $days_requested,
            'message' => $available_credits >= $days_requested ? 
                'Sufficient credits available (CSC compliant)' : 
                "Insufficient leave credits. Available: {$available_credits} days, Requested: {$days_requested} days"
        ];
    }
    
    /**
     * Deduct leave credits when leave is applied
     */
    public function deductLeaveCredits($employee_id, $leave_type, $start_date, $end_date) {
        // Handle CTO specially - it uses hours instead of days
        if ($leave_type === 'cto') {
            return $this->handleCTOCredits($employee_id, $leave_type, $start_date, $end_date, 'deduct');
        }
        
        $days_requested = $this->calculateDays($start_date, $end_date);
        $mapping = $this->getLeaveTypeMapping();
        
        if (!array_key_exists($leave_type, $mapping)) {
            throw new Exception('Invalid leave type');
        }
        
        $credit_field = $mapping[$leave_type];
        
        // If credit_field is null, this leave type doesn't require credits
        if ($credit_field === null) {
            return true; // No credits to deduct
        }
        
        // Check if column exists
        $stmt = $this->pdo->query("DESCRIBE employees");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array($credit_field, $columns)) {
            throw new Exception('Leave type not supported in current system');
        }
        
        // Check if sufficient credits available
        $check = $this->checkLeaveCredits($employee_id, $leave_type, $start_date, $end_date);
        if (!$check['sufficient']) {
            throw new Exception($check['message']);
        }
        
        // Deduct credits
        $stmt = $this->pdo->prepare("UPDATE employees SET {$credit_field} = {$credit_field} - ? WHERE id = ?");
        $result = $stmt->execute([$days_requested, $employee_id]);
        
        if (!$result) {
            throw new Exception('Failed to deduct leave credits');
        }
        
        return true;
    }
    
    /**
     * Restore leave credits when leave is rejected or cancelled
     */
    public function restoreLeaveCredits($employee_id, $leave_type, $start_date, $end_date) {
        // Handle CTO specially - it uses hours instead of days
        if ($leave_type === 'cto') {
            return $this->handleCTOCredits($employee_id, $leave_type, $start_date, $end_date, 'restore');
        }
        
        $days_requested = $this->calculateDays($start_date, $end_date);
        $mapping = $this->getLeaveTypeMapping();
        
        if (!array_key_exists($leave_type, $mapping)) {
            throw new Exception('Invalid leave type');
        }
        
        $credit_field = $mapping[$leave_type];
        
        // If credit_field is null, this leave type doesn't require credits
        if ($credit_field === null) {
            return true; // No credits to restore
        }
        
        // Check if column exists
        $stmt = $this->pdo->query("DESCRIBE employees");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array($credit_field, $columns)) {
            throw new Exception('Leave type not supported in current system');
        }
        
        // Restore credits
        $stmt = $this->pdo->prepare("UPDATE employees SET {$credit_field} = {$credit_field} + ? WHERE id = ?");
        $result = $stmt->execute([$days_requested, $employee_id]);
        
        if (!$result) {
            throw new Exception('Failed to restore leave credits');
        }
        
        return true;
    }
    
    /**
     * Handle leave status change (approve/reject)
     */
    public function handleLeaveStatusChange($leave_request_id, $new_status) {
        try {
            $this->pdo->beginTransaction();
            
            // Get leave request details
            $stmt = $this->pdo->prepare("
                SELECT employee_id, leave_type, start_date, end_date, status, days_requested 
                FROM leave_requests 
                WHERE id = ?
            ");
            $stmt->execute([$leave_request_id]);
            $leave_request = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$leave_request) {
                throw new Exception('Leave request not found');
            }
            
            $old_status = $leave_request['status'];
            $employee_id = $leave_request['employee_id'];
            $leave_type = $leave_request['leave_type'];
            $start_date = $leave_request['start_date'];
            $end_date = $leave_request['end_date'];
            $days_requested = $leave_request['days_requested'];
            
            // Handle status changes
            if ($new_status === 'approved' && $old_status === 'pending') {
                // Credits were already deducted when applied, just update status
                $stmt = $this->pdo->prepare("UPDATE leave_requests SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $leave_request_id]);
                
            } elseif ($new_status === 'rejected' && $old_status === 'pending') {
                // Restore credits and update status
                $this->restoreLeaveCredits($employee_id, $leave_type, $start_date, $end_date);
                $stmt = $this->pdo->prepare("UPDATE leave_requests SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $leave_request_id]);
                
            } elseif ($new_status === 'cancelled' && in_array($old_status, ['pending', 'approved'])) {
                // Restore credits if it was approved, or just update status if pending
                if ($old_status === 'approved') {
                    $this->restoreLeaveCredits($employee_id, $leave_type, $start_date, $end_date);
                }
                $stmt = $this->pdo->prepare("UPDATE leave_requests SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $leave_request_id]);
            }
            
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Validate CSC-specific leave rules
     */
    private function validateCSCLeaveRules($leave_type, $employee_id, $days_requested, $available_credits) {
        require_once dirname(__DIR__, 3) . '/config/leave_types.php';
        $leaveTypes = getLeaveTypes();
        
        if (!isset($leaveTypes[$leave_type])) {
            return ['valid' => false, 'message' => 'Invalid leave type'];
        }
        
        $config = $leaveTypes[$leave_type];
        
        // Check gender restrictions
        if (isset($config['gender_restricted'])) {
            $employee = $this->getEmployee($employee_id);
            if ($employee && $employee['gender'] !== $config['gender_restricted']) {
                return [
                    'valid' => false, 
                    'message' => "This leave type is restricted to {$config['gender_restricted']} employees only"
                ];
            }
        }
        
        // Check paternity leave delivery limit
        if ($leave_type === 'paternity' && isset($config['delivery_limit'])) {
            $usedCount = $this->getPaternityLeaveUsageCount($employee_id);
            if ($usedCount >= $config['delivery_limit']) {
                return [
                    'valid' => false,
                    'message' => "Paternity leave limit reached (maximum {$config['delivery_limit']} deliveries)"
                ];
            }
        }
        
        // Check solo parent qualification
        if ($leave_type === 'solo_parent') {
            $employee = $this->getEmployee($employee_id);
            if (!$this->isSoloParent($employee)) {
                return [
                    'valid' => false,
                    'message' => 'Employee does not qualify for solo parent leave'
                ];
            }
        }
        
        // Check if leave type is non-cumulative and reset annually
        if (isset($config['cumulative']) && !$config['cumulative']) {
            $currentYear = date('Y');
            $lastUsed = $this->getLastLeaveUsage($employee_id, $leave_type, $currentYear);
            
            if ($lastUsed && $lastUsed['year'] == $currentYear) {
                $remaining = $config['annual_credits'] - $lastUsed['days_used'];
                if ($days_requested > $remaining) {
                    return [
                        'valid' => false,
                        'message' => "Only {$remaining} days remaining for this non-cumulative leave type this year"
                    ];
                }
            }
        }
        
        return ['valid' => true, 'message' => 'Valid leave request'];
    }
    
    /**
     * Get employee information
     */
    private function getEmployee($employee_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM employees WHERE id = ?");
        $stmt->execute([$employee_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get paternity leave usage count
     */
    private function getPaternityLeaveUsageCount($employee_id) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM leave_requests 
            WHERE employee_id = ? 
            AND leave_type = 'paternity' 
            AND status = 'approved'
        ");
        $stmt->execute([$employee_id]);
        return $stmt->fetchColumn();
    }
    
    /**
     * Check if employee qualifies as solo parent
     */
    private function isSoloParent($employee) {
        return isset($employee['is_solo_parent']) && $employee['is_solo_parent'] == 1;
    }
    
    /**
     * Get last leave usage for non-cumulative leave types
     */
    private function getLastLeaveUsage($employee_id, $leave_type, $year) {
        $stmt = $this->pdo->prepare("
            SELECT YEAR(start_date) as year, SUM(DATEDIFF(end_date, start_date) + 1) as days_used
            FROM leave_requests 
            WHERE employee_id = ? 
            AND leave_type = ? 
            AND YEAR(start_date) = ?
            AND status = 'approved'
            GROUP BY YEAR(start_date)
        ");
        $stmt->execute([$employee_id, $leave_type, $year]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Handle CTO-specific leave credit operations
     */
    public function handleCTOCredits($employee_id, $leave_type, $start_date, $end_date, $action = 'deduct') {
        if ($leave_type !== 'cto') {
            return false;
        }
        
        $hours_requested = $this->calculateDays($start_date, $end_date) * 8; // Convert days to hours
        
        // Check if CTO balance column exists
        $stmt = $this->pdo->query("DESCRIBE employees");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('cto_balance', $columns)) {
            throw new Exception('CTO balance tracking not available');
        }
        
        // Get current CTO balance
        $stmt = $this->pdo->prepare("SELECT cto_balance FROM employees WHERE id = ?");
        $stmt->execute([$employee_id]);
        $current_balance = $stmt->fetchColumn() ?: 0;
        
        if ($action === 'deduct') {
            if ($current_balance < $hours_requested) {
                throw new Exception("Insufficient CTO balance. Available: {$current_balance} hours, Requested: {$hours_requested} hours");
            }
            
            // Deduct CTO credits
            $stmt = $this->pdo->prepare("UPDATE employees SET cto_balance = cto_balance - ? WHERE id = ?");
            $result = $stmt->execute([$hours_requested, $employee_id]);
            
            if (!$result) {
                throw new Exception('Failed to deduct CTO credits');
            }
            
            // Record CTO usage
            $this->recordCTOUsage($employee_id, $hours_requested, $start_date, $end_date);
            
        } elseif ($action === 'restore') {
            // Restore CTO credits
            $stmt = $this->pdo->prepare("UPDATE employees SET cto_balance = cto_balance + ? WHERE id = ?");
            $result = $stmt->execute([$hours_requested, $employee_id]);
            
            if (!$result) {
                throw new Exception('Failed to restore CTO credits');
            }
        }
        
        return true;
    }
    
    /**
     * Record CTO usage for tracking
     */
    private function recordCTOUsage($employee_id, $hours_used, $start_date, $end_date) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO cto_usage 
                (employee_id, hours_used, used_date, description) 
                VALUES (?, ?, ?, ?)
            ");
            
            $description = "CTO used from {$start_date} to {$end_date}";
            $stmt->execute([$employee_id, $hours_used, $start_date, $description]);
            
        } catch (Exception $e) {
            // Log error but don't fail the main operation
            error_log("Failed to record CTO usage: " . $e->getMessage());
        }
    }
    
    /**
     * Check CTO balance for employee
     */
    public function checkCTOBalance($employee_id) {
        $stmt = $this->pdo->query("DESCRIBE employees");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('cto_balance', $columns)) {
            return ['available' => 0, 'message' => 'CTO tracking not available'];
        }
        
        $stmt = $this->pdo->prepare("SELECT cto_balance FROM employees WHERE id = ?");
        $stmt->execute([$employee_id]);
        $balance = $stmt->fetchColumn() ?: 0;
        
        return [
            'available' => $balance,
            'message' => "CTO Balance: {$balance} hours"
        ];
    }
    
    /**
     * Get CTO earnings history for employee
     */
    public function getCTOEarningsHistory($employee_id, $limit = 20) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT ce.*, e.name as approved_by_name
                FROM cto_earnings ce
                LEFT JOIN employees e ON ce.approved_by = e.id
                WHERE ce.employee_id = ?
                ORDER BY ce.earned_date DESC, ce.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$employee_id, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Get CTO usage history for employee
     */
    public function getCTOUsageHistory($employee_id, $limit = 20) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT cu.*, lr.start_date, lr.end_date, lr.leave_type
                FROM cto_usage cu
                LEFT JOIN leave_requests lr ON cu.leave_request_id = lr.id
                WHERE cu.employee_id = ?
                ORDER BY cu.used_date DESC, cu.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$employee_id, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Enhanced deductLeaveCredits to handle CTO
     */
    public function deductLeaveCreditsEnhanced($employee_id, $leave_type, $start_date, $end_date) {
        if ($leave_type === 'cto') {
            return $this->handleCTOCredits($employee_id, $leave_type, $start_date, $end_date, 'deduct');
        }
        
        // Use original logic for other leave types
        return $this->deductLeaveCredits($employee_id, $leave_type, $start_date, $end_date);
    }
    
    /**
     * Enhanced restoreLeaveCredits to handle CTO
     */
    public function restoreLeaveCreditsEnhanced($employee_id, $leave_type, $start_date, $end_date) {
        if ($leave_type === 'cto') {
            return $this->handleCTOCredits($employee_id, $leave_type, $start_date, $end_date, 'restore');
        }
        
        // Use original logic for other leave types
        return $this->restoreLeaveCredits($employee_id, $leave_type, $start_date, $end_date);
    }
    
    /**
     * Get leave credits summary for an employee (CSC Compliant)
     */
    public function getLeaveCreditsSummary($employee_id) {
        $mapping = $this->getLeaveTypeMapping();
        $summary = [];
        
        // First, check which columns actually exist in the database
        $stmt = $this->pdo->query("DESCRIBE employees");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($mapping as $leave_type => $field) {
            if (in_array($field, $columns)) {
                $stmt = $this->pdo->prepare("SELECT {$field} FROM employees WHERE id = ?");
                $stmt->execute([$employee_id]);
                $summary[$field] = $stmt->fetchColumn() ?: 0;
            } else {
                // If column doesn't exist, set to 0
                $summary[$field] = 0;
            }
        }
        
        return $summary;
    }
}
?>
