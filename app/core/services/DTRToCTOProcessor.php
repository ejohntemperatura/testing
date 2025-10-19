<?php
/**
 * DTR to CTO Processor
 * Automatically processes DTR data to generate CTO earnings
 * based on overtime, holiday, and weekend work
 */

class DTRToCTOProcessor {
    private $pdo;
    private $calculator;
    
    // Standard working hours per day
    const STANDARD_HOURS_PER_DAY = 8;
    
    // Overtime thresholds
    const OVERTIME_THRESHOLD_HOURS = 8;
    const MAX_OVERTIME_HOURS = 4; // Maximum 4 hours overtime per day
    
    // Holiday and weekend rates
    const HOLIDAY_RATE = 1.5;
    const WEEKEND_RATE = 1.0;
    const OVERTIME_RATE = 1.0;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        require_once dirname(__DIR__, 3) . '/app/core/services/LeaveCreditsCalculator.php';
        $this->calculator = new LeaveCreditsCalculator($pdo);
    }
    
    /**
     * Process DTR data for a specific date range and generate CTO earnings
     */
    public function processDTRForCTO($startDate, $endDate, $employeeId = null) {
        $processedCount = 0;
        $errors = [];
        
        try {
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
            }
            
            // Get DTR data for the period
            $dtrData = $this->getDTRData($startDate, $endDate, $employeeId);
            
            foreach ($dtrData as $record) {
                $result = $this->processDTRRecord($record);
                if ($result['success']) {
                    $processedCount++;
                } else {
                    $errors[] = $result['error'];
                }
            }
            
            if ($this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
            
            return [
                'success' => true,
                'processed' => $processedCount,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'processed' => $processedCount,
                'errors' => $errors
            ];
        }
    }
    
    /**
     * Get DTR data for processing
     */
    private function getDTRData($startDate, $endDate, $employeeId = null) {
        $whereConditions = ["d.date BETWEEN ? AND ?"];
        $params = [$startDate, $endDate];
        
        if ($employeeId) {
            $whereConditions[] = "d.user_id = ?";
            $params[] = $employeeId;
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $sql = "
            SELECT 
                d.user_id as employee_id,
                e.name as employee_name,
                e.department,
                d.date,
                d.morning_time_in,
                d.morning_time_out,
                d.afternoon_time_in,
                d.afternoon_time_out,
                CASE 
                    WHEN d.morning_time_in IS NOT NULL AND d.morning_time_out IS NOT NULL 
                    THEN TIMESTAMPDIFF(MINUTE, d.morning_time_in, d.morning_time_out) / 60.0
                    ELSE 0
                END as morning_hours,
                CASE 
                    WHEN d.afternoon_time_in IS NOT NULL AND d.afternoon_time_out IS NOT NULL 
                    THEN TIMESTAMPDIFF(MINUTE, d.afternoon_time_in, d.afternoon_time_out) / 60.0
                    ELSE 0
                END as afternoon_hours
            FROM dtr d
            JOIN employees e ON d.user_id = e.id
            WHERE $whereClause
            AND (d.morning_time_in IS NOT NULL OR d.afternoon_time_in IS NOT NULL)
            ORDER BY d.date, e.name
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Process individual DTR record for CTO
     */
    private function processDTRRecord($record) {
        try {
            $employeeId = $record['employee_id'];
            $date = $record['date'];
            $morningHours = $record['morning_hours'];
            $afternoonHours = $record['afternoon_hours'];
            $totalHours = $morningHours + $afternoonHours;
            
            // Skip if no hours worked
            if ($totalHours <= 0) {
                return ['success' => true, 'message' => 'No hours worked'];
            }
            
            // Check if CTO already processed for this date
            if ($this->isCTOAlreadyProcessed($employeeId, $date)) {
                return ['success' => true, 'message' => 'CTO already processed for this date'];
            }
            
            // Determine work type and calculate CTO
            $workType = $this->determineWorkType($date, $totalHours);
            $ctoEarnings = [];
            
            if ($workType['type'] === 'overtime') {
                $overtimeHours = $this->calculateOvertimeHours($totalHours);
                if ($overtimeHours > 0) {
                    $ctoEarnings[] = [
                        'type' => 'overtime',
                        'hours' => $overtimeHours,
                        'rate' => self::OVERTIME_RATE,
                        'description' => "Overtime work on " . date('M d, Y', strtotime($date))
                    ];
                }
            } elseif ($workType['type'] === 'holiday') {
                $ctoEarnings[] = [
                    'type' => 'holiday',
                    'hours' => $totalHours,
                    'rate' => self::HOLIDAY_RATE,
                    'description' => "Holiday work on " . date('M d, Y', strtotime($date)) . " ({$workType['holiday_name']})"
                ];
            } elseif ($workType['type'] === 'weekend') {
                $ctoEarnings[] = [
                    'type' => 'weekend',
                    'hours' => $totalHours,
                    'rate' => self::WEEKEND_RATE,
                    'description' => "Weekend work on " . date('M d, Y', strtotime($date))
                ];
            }
            
            // Process each CTO earning
            foreach ($ctoEarnings as $earning) {
                $this->addCTOEarning($employeeId, $earning, $date);
            }
            
            return [
                'success' => true,
                'message' => 'CTO processed successfully',
                'earnings' => $ctoEarnings
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Determine work type based on date and hours
     */
    private function determineWorkType($date, $totalHours) {
        $dayOfWeek = date('N', strtotime($date)); // 1 = Monday, 7 = Sunday
        $isHoliday = $this->isHoliday($date);
        $isOvertime = $totalHours > self::OVERTIME_THRESHOLD_HOURS;
        
        if ($isHoliday) {
            return [
                'type' => 'holiday',
                'holiday_name' => $this->getHolidayName($date)
            ];
        } elseif ($dayOfWeek >= 6) { // Saturday (6) or Sunday (7)
            return ['type' => 'weekend'];
        } elseif ($isOvertime) {
            return ['type' => 'overtime'];
        }
        
        return ['type' => 'regular'];
    }
    
    /**
     * Calculate overtime hours
     */
    private function calculateOvertimeHours($totalHours) {
        $overtime = $totalHours - self::OVERTIME_THRESHOLD_HOURS;
        return min($overtime, self::MAX_OVERTIME_HOURS); // Cap at max overtime
    }
    
    /**
     * Check if date is a holiday
     */
    private function isHoliday($date) {
        // You can expand this with a holidays table or configuration
        $holidays = [
            '2024-01-01', // New Year's Day
            '2024-03-29', // Good Friday
            '2024-04-09', // Araw ng Kagitingan
            '2024-05-01', // Labor Day
            '2024-06-12', // Independence Day
            '2024-08-26', // National Heroes Day
            '2024-11-30', // Bonifacio Day
            '2024-12-25', // Christmas Day
            '2024-12-30', // Rizal Day
            // Add more holidays as needed
        ];
        
        return in_array($date, $holidays);
    }
    
    /**
     * Get holiday name
     */
    private function getHolidayName($date) {
        $holidayNames = [
            '2024-01-01' => 'New Year\'s Day',
            '2024-03-29' => 'Good Friday',
            '2024-04-09' => 'Araw ng Kagitingan',
            '2024-05-01' => 'Labor Day',
            '2024-06-12' => 'Independence Day',
            '2024-08-26' => 'National Heroes Day',
            '2024-11-30' => 'Bonifacio Day',
            '2024-12-25' => 'Christmas Day',
            '2024-12-30' => 'Rizal Day',
        ];
        
        return $holidayNames[$date] ?? 'Holiday';
    }
    
    /**
     * Check if CTO already processed for this date
     */
    private function isCTOAlreadyProcessed($employeeId, $date) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM cto_earnings 
            WHERE employee_id = ? AND earned_date = ?
        ");
        $stmt->execute([$employeeId, $date]);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Add CTO earning to database
     */
    private function addCTOEarning($employeeId, $earning, $date) {
        $ctoEarned = $this->calculator->calculateCTOEarned($earning['hours'], $earning['type']);
        
        // Add CTO earning
        $this->calculator->addCTOEarnings(
            $employeeId,
            $earning['hours'],
            $earning['type'],
            $earning['description'],
            null // Auto-approved for DTR-based earnings
        );
        
        // Mark as auto-processed
        $stmt = $this->pdo->prepare("
            UPDATE cto_earnings 
            SET status = 'approved', approved_by = 1, approved_at = NOW()
            WHERE employee_id = ? AND earned_date = ? AND work_type = ?
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$employeeId, $date, $earning['type']]);
    }
    
    /**
     * Get processing summary for a period
     */
    public function getProcessingSummary($startDate, $endDate, $employeeId = null) {
        $whereConditions = ["ce.earned_date BETWEEN ? AND ?"];
        $params = [$startDate, $endDate];
        
        if ($employeeId) {
            $whereConditions[] = "ce.employee_id = ?";
            $params[] = $employeeId;
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $sql = "
            SELECT 
                ce.work_type,
                COUNT(*) as count,
                SUM(ce.hours_worked) as total_hours_worked,
                SUM(ce.cto_earned) as total_cto_earned,
                AVG(ce.rate_applied) as avg_rate
            FROM cto_earnings ce
            WHERE $whereClause
            GROUP BY ce.work_type
            ORDER BY ce.work_type
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Process DTR for today (for real-time processing)
     */
    public function processTodayDTR() {
        $today = date('Y-m-d');
        return $this->processDTRForCTO($today, $today);
    }
    
    /**
     * Process DTR for yesterday (for end-of-day processing)
     */
    public function processYesterdayDTR() {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        return $this->processDTRForCTO($yesterday, $yesterday);
    }
}
?>
