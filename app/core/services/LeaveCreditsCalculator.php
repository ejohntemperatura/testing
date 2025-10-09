<?php
/**
 * Leave Credits Calculator for Civil Service Compliance
 * Based on Civil Service Commission (CSC) Rules and Regulations
 * Updated to comply with official CSC leave entitlements
 */

class LeaveCreditsCalculator {
    private $pdo;
    
    // CSC Standard Leave Credits (Annual)
    const VACATION_LEAVE_ANNUAL = 15;      // 15 days per year with full pay
    const SICK_LEAVE_ANNUAL = 15;          // 15 days per year with full pay
    const SPECIAL_PRIVILEGE_ANNUAL = 3;    // 3 days per year, non-cumulative and non-commutable
    
    // Special Leave Allocations (CSC Standard)
    const MATERNITY_LEAVE_DAYS = 105;      // 105 days with full pay
    const MATERNITY_EXTENSION_DAYS = 30;   // 30 days without pay (optional)
    const PATERNITY_LEAVE_DAYS = 7;        // 7 working days for first four deliveries
    const SOLO_PARENT_LEAVE_DAYS = 7;      // 7 working days per year
    const VAWC_LEAVE_DAYS = 10;            // 10 days with full pay
    const REHABILITATION_LEAVE_DAYS = 180; // Up to 6 months (180 days) with pay
    const STUDY_LEAVE_DAYS = 180;          // Up to 6 months without pay
    
    // Credit calculation rates
    const VACATION_LEAVE_RATE = 1.25;      // 1.25 days per month
    const SICK_LEAVE_RATE = 1.25;          // 1.25 days per month
    const SPECIAL_PRIVILEGE_RATE = 0.25;   // 0.25 days per month
    const SERVICE_DAYS_FOR_CREDIT = 24;    // Every 24 days of service = 1 day credit
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Calculate leave credits for an employee based on CSC standards
     */
    public function calculateLeaveCredits($employeeId, $fromDate = null, $toDate = null) {
        $employee = $this->getEmployee($employeeId);
        if (!$employee) {
            return false;
        }
        
        $fromDate = $fromDate ?: $employee['service_start_date'];
        $toDate = $toDate ?: date('Y-m-d');
        
        $serviceDays = $this->calculateServiceDays($employeeId, $fromDate, $toDate);
        $months = $this->calculateMonths($fromDate, $toDate);
        $currentYear = date('Y');
        
        return [
            'vacation' => $this->calculateVacationLeave($serviceDays, $months, $currentYear),
            'sick' => $this->calculateSickLeave($serviceDays, $months, $currentYear),
            'special_privilege' => $this->calculateSpecialPrivilege($months, $currentYear),
            'maternity' => $this->getMaternityLeave($employee, $currentYear),
            'paternity' => $this->getPaternityLeave($employee, $currentYear),
            'solo_parent' => $this->getSoloParentLeave($employee, $currentYear),
            'vawc' => $this->getVAWCLeave($employee, $currentYear),
            'rehabilitation' => $this->getRehabilitationLeave($employee, $currentYear),
            'study' => $this->getStudyLeave($employee, $currentYear),
            'terminal' => $this->getTerminalLeave($employee, $currentYear)
        ];
    }
    
    /**
     * Update employee leave credits
     */
    public function updateEmployeeLeaveCredits($employeeId) {
        $credits = $this->calculateLeaveCredits($employeeId);
        $usedCredits = $this->getUsedLeaveCredits($employeeId);
        
        $balances = [];
        foreach ($credits as $type => $total) {
            $used = $usedCredits[$type] ?? 0;
            $balances[$type] = max(0, $total - $used);
        }
        
        $this->saveLeaveCredits($employeeId, $balances);
        return $balances;
    }
    
    /**
     * Calculate vacation leave credits (CSC Standard: 15 days per year)
     */
    private function calculateVacationLeave($serviceDays, $months, $currentYear) {
        // CSC rule: 15 days per year with full pay, cumulative
        $monthlyCredits = $months * self::VACATION_LEAVE_RATE;
        $serviceCredits = floor($serviceDays / self::SERVICE_DAYS_FOR_CREDIT);
        
        // Use the higher of monthly or service-based calculation
        $calculated = max($monthlyCredits, $serviceCredits);
        
        // Ensure minimum of 15 days for full year of service
        if ($months >= 12) {
            return max($calculated, self::VACATION_LEAVE_ANNUAL);
        }
        
        return $calculated > 0 ? $calculated : 0;
    }
    
    /**
     * Calculate sick leave credits (CSC Standard: 15 days per year)
     */
    private function calculateSickLeave($serviceDays, $months, $currentYear) {
        // CSC rule: 15 days per year with full pay, cumulative
        $monthlyCredits = $months * self::SICK_LEAVE_RATE;
        $serviceCredits = floor($serviceDays / self::SERVICE_DAYS_FOR_CREDIT);
        
        // Use the higher of monthly or service-based calculation
        $calculated = max($monthlyCredits, $serviceCredits);
        
        // Ensure minimum of 15 days for full year of service
        if ($months >= 12) {
            return max($calculated, self::SICK_LEAVE_ANNUAL);
        }
        
        return $calculated > 0 ? $calculated : 0;
    }
    
    /**
     * Calculate special privilege leave credits (CSC Standard: 3 days per year, non-cumulative)
     */
    private function calculateSpecialPrivilege($months, $currentYear) {
        // CSC rule: 3 days per year, non-cumulative and non-commutable
        // Reset to 3 days at the start of each year
        if ($months >= 12) {
            return self::SPECIAL_PRIVILEGE_ANNUAL;
        }
        
        // For partial year, calculate proportionally
        return $months * self::SPECIAL_PRIVILEGE_RATE;
    }
    
    /**
     * Get maternity leave (CSC Standard: 105 days with full pay)
     */
    private function getMaternityLeave($employee, $currentYear) {
        // CSC rule: 105 days with full pay, with option to extend for 30 days without pay
        // This is typically a one-time allocation per pregnancy
        $current = $employee['maternity_leave_balance'] ?? self::MATERNITY_LEAVE_DAYS;
        return $current > 0 ? $current : self::MATERNITY_LEAVE_DAYS;
    }
    
    /**
     * Get paternity leave (CSC Standard: 7 working days for first four deliveries)
     */
    private function getPaternityLeave($employee, $currentYear) {
        // CSC rule: 7 working days for the first four deliveries of the legitimate spouse
        // Check if employee has used paternity leave before
        $usedCount = $this->getPaternityLeaveUsageCount($employee['id']);
        
        if ($usedCount >= 4) {
            return 0; // No more paternity leave after 4 deliveries
        }
        
        return self::PATERNITY_LEAVE_DAYS;
    }
    
    /**
     * Get solo parent leave (CSC Standard: 7 working days per year)
     */
    private function getSoloParentLeave($employee, $currentYear) {
        // CSC rule: 7 working days per year
        // Check if employee qualifies as solo parent
        if (!$this->isSoloParent($employee)) {
            return 0;
        }
        
        return self::SOLO_PARENT_LEAVE_DAYS;
    }
    
    /**
     * Get VAWC leave (CSC Standard: 10 days with full pay)
     */
    private function getVAWCLeave($employee, $currentYear) {
        // CSC rule: 10 days with full pay for Violence Against Women and Their Children
        return self::VAWC_LEAVE_DAYS;
    }
    
    /**
     * Get rehabilitation leave (CSC Standard: Up to 6 months with pay)
     */
    private function getRehabilitationLeave($employee, $currentYear) {
        // CSC rule: Up to 6 months with pay, for job-related injuries or illnesses
        // This is typically used as needed, not allocated annually
        $current = $employee['rehabilitation_leave_balance'] ?? 0;
        return $current > 0 ? $current : 0;
    }
    
    /**
     * Get study leave (CSC Standard: Up to 6 months without pay)
     */
    private function getStudyLeave($employee, $currentYear) {
        // CSC rule: Up to 6 months for qualified government employees pursuing studies
        // This is without pay, so no credits are allocated
        return 0;
    }
    
    /**
     * Get terminal leave (CSC Standard: Accumulated VL and SL convertible to cash)
     */
    private function getTerminalLeave($employee, $currentYear) {
        // CSC rule: Accumulated Vacation and Sick Leave credits convertible to cash upon separation
        // This is calculated from accumulated VL and SL credits
        $vacationCredits = $this->calculateVacationLeave(0, 12, $currentYear);
        $sickCredits = $this->calculateSickLeave(0, 12, $currentYear);
        
        return $vacationCredits + $sickCredits;
    }
    
    /**
     * Calculate actual service days (excluding absences)
     */
    private function calculateServiceDays($employeeId, $fromDate, $toDate) {
        // This would need to integrate with DTR system
        // For now, return estimated service days
        $start = new DateTime($fromDate);
        $end = new DateTime($toDate);
        $interval = $start->diff($end);
        $totalDays = $interval->days;
        
        // Subtract weekends (approximately 2/7 of days)
        $weekendDays = floor($totalDays * 2 / 7);
        $workingDays = $totalDays - $weekendDays;
        
        return $workingDays;
    }
    
    /**
     * Calculate months of service
     */
    private function calculateMonths($fromDate, $toDate) {
        $start = new DateTime($fromDate);
        $end = new DateTime($toDate);
        $interval = $start->diff($end);
        
        return ($interval->y * 12) + $interval->m + ($interval->d / 30);
    }
    
    /**
     * Get used leave credits for current year
     */
    private function getUsedLeaveCredits($employeeId) {
        $year = date('Y');
        $stmt = $this->pdo->prepare("
            SELECT 
                leave_type,
                SUM(DATEDIFF(end_date, start_date) + 1) as days_used
            FROM leave_requests 
            WHERE employee_id = ? 
            AND YEAR(start_date) = ? 
            AND status = 'approved'
            GROUP BY leave_type
        ");
        $stmt->execute([$employeeId, $year]);
        
        $usage = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $usage[$row['leave_type']] = $row['days_used'];
        }
        
        return $usage;
    }
    
    /**
     * Get employee information
     */
    private function getEmployee($employeeId) {
        $stmt = $this->pdo->prepare("SELECT * FROM employees WHERE id = ?");
        $stmt->execute([$employeeId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Save leave credits to database (Updated for CSC compliance)
     */
    private function saveLeaveCredits($employeeId, $balances) {
        // Check which columns exist in the database
        $stmt = $this->pdo->query("DESCRIBE employees");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Build dynamic SQL based on available columns
        $updateFields = [];
        $values = [];
        
        $fieldMappings = [
            'vacation_leave_balance' => $balances['vacation'],
            'sick_leave_balance' => $balances['sick'],
            'special_leave_privilege_balance' => $balances['special_privilege'],
            'maternity_leave_balance' => $balances['maternity'],
            'paternity_leave_balance' => $balances['paternity'],
            'solo_parent_leave_balance' => $balances['solo_parent'],
            'vawc_leave_balance' => $balances['vawc'],
            'rehabilitation_leave_balance' => $balances['rehabilitation'],
            'terminal_leave_balance' => $balances['terminal']
        ];
        
        foreach ($fieldMappings as $field => $value) {
            if (in_array($field, $columns)) {
                $updateFields[] = "{$field} = ?";
                $values[] = $value;
            }
        }
        
        // Add last_leave_credit_update if column exists
        if (in_array('last_leave_credit_update', $columns)) {
            $updateFields[] = "last_leave_credit_update = CURDATE()";
        }
        
        if (empty($updateFields)) {
            // Fallback to basic columns if CSC columns don't exist
            $updateFields = [];
            $values = [];
            
            if (in_array('annual_leave_balance', $columns)) {
                $updateFields[] = "annual_leave_balance = ?";
                $values[] = $balances['vacation'];
            }
            
            if (in_array('sick_leave_balance', $columns)) {
                $updateFields[] = "sick_leave_balance = ?";
                $values[] = $balances['sick'];
            }
        }
        
        if (!empty($updateFields)) {
            $sql = "UPDATE employees SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $values[] = $employeeId;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($values);
        }
    }
    
    /**
     * Get count of paternity leave usage for employee
     */
    private function getPaternityLeaveUsageCount($employeeId) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM leave_requests 
            WHERE employee_id = ? 
            AND leave_type = 'paternity' 
            AND status = 'approved'
        ");
        $stmt->execute([$employeeId]);
        return $stmt->fetchColumn();
    }
    
    /**
     * Check if employee qualifies as solo parent
     */
    private function isSoloParent($employee) {
        // This would need to be implemented based on your employee data structure
        // For now, return false as a default
        return isset($employee['is_solo_parent']) && $employee['is_solo_parent'] == 1;
    }
    
    /**
     * Get leave credit summary for display
     */
    public function getLeaveCreditSummary($employeeId) {
        $credits = $this->calculateLeaveCredits($employeeId);
        $used = $this->getUsedLeaveCredits($employeeId);
        
        $summary = [];
        foreach ($credits as $type => $total) {
            $usedAmount = $used[$type] ?? 0;
            $remaining = max(0, $total - $usedAmount);
            
            $summary[$type] = [
                'total' => $total,
                'used' => $usedAmount,
                'remaining' => $remaining,
                'percentage' => $total > 0 ? round(($remaining / $total) * 100, 1) : 0
            ];
        }
        
        return $summary;
    }
}
?>
