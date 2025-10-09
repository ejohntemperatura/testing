<?php
/**
 * Enhanced Report Generator for ELMS
 * Generates comprehensive Excel reports with leave details and DTR information
 */

require_once __DIR__ . '/../../../config/leave_types.php';

class EnhancedReportGenerator {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Generate comprehensive Excel report with leave and DTR details
     */
    public function generateComprehensiveReport($startDate, $endDate, $department = null, $employeeId = null) {
        // Get leave requests data
        $leaveData = $this->getLeaveRequestsData($startDate, $endDate, $department, $employeeId);
        
        // Get DTR data for the same period
        $dtrData = $this->getDTRData($startDate, $endDate, $department, $employeeId);
        
        // Get employee information
        $employeeData = $this->getEmployeeData($department, $employeeId);
        
        // Generate Excel file
        return $this->createExcelFile($leaveData, $dtrData, $employeeData, $startDate, $endDate);
    }
    
    /**
     * Get leave requests data with detailed information
     */
    public function getLeaveRequestsData($startDate, $endDate, $department = null, $employeeId = null) {
        $whereConditions = ["lr.start_date BETWEEN ? AND ?"];
        $params = [$startDate, $endDate];
        
        if ($department) {
            $whereConditions[] = "e.department = ?";
            $params[] = $department;
        }
        
        if ($employeeId) {
            $whereConditions[] = "e.id = ?";
            $params[] = $employeeId;
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $sql = "
            SELECT 
                e.id as employee_id,
                e.name as employee_name,
                e.department,
                e.position,
                e.contact,
                lr.id as leave_request_id,
                lr.leave_type,
                lr.start_date,
                lr.end_date,
                lr.days_requested,
                lr.reason,
                lr.status,
                lr.location_type,
                lr.location_specify,
                lr.medical_condition,
                lr.illness_specify,
                lr.special_women_condition,
                lr.study_type,
                lr.medical_certificate_path,
                lr.is_late,
                lr.late_justification,
                lr.created_at as request_created_at,
                CASE 
                    WHEN lr.status = 'approved' THEN 'Approved'
                    WHEN lr.status = 'rejected' THEN 'Rejected'
                    WHEN lr.status = 'pending' THEN 'Pending'
                    WHEN lr.status = 'under_appeal' THEN 'Under Appeal'
                    ELSE lr.status
                END as status_display
            FROM leave_requests lr
            JOIN employees e ON lr.employee_id = e.id
            WHERE $whereClause
            ORDER BY e.department, e.name, lr.start_date
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get DTR data for the specified period
     */
    public function getDTRData($startDate, $endDate, $department = null, $employeeId = null) {
        $whereConditions = ["d.date BETWEEN ? AND ?"];
        $params = [$startDate, $endDate];
        
        if ($department) {
            $whereConditions[] = "e.department = ?";
            $params[] = $department;
        }
        
        if ($employeeId) {
            $whereConditions[] = "e.id = ?";
            $params[] = $employeeId;
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $sql = "
            SELECT 
                e.id as employee_id,
                e.name as employee_name,
                e.department,
                d.date,
                d.morning_time_in,
                d.morning_time_out,
                d.afternoon_time_in,
                d.afternoon_time_out,
                CASE 
                    WHEN d.morning_time_in IS NOT NULL AND d.morning_time_out IS NOT NULL 
                         AND d.afternoon_time_in IS NOT NULL AND d.afternoon_time_out IS NOT NULL 
                    THEN 'Complete'
                    WHEN d.morning_time_in IS NOT NULL AND d.morning_time_out IS NOT NULL 
                    THEN 'Half Day (Morning)'
                    WHEN d.afternoon_time_in IS NOT NULL AND d.afternoon_time_out IS NOT NULL 
                    THEN 'Half Day (Afternoon)'
                    WHEN d.morning_time_in IS NOT NULL OR d.afternoon_time_in IS NOT NULL 
                    THEN 'Incomplete'
                    ELSE 'Absent'
                END as attendance_status,
                CASE 
                    WHEN d.morning_time_in IS NOT NULL AND d.morning_time_out IS NOT NULL 
                    THEN TIMESTAMPDIFF(MINUTE, d.morning_time_in, d.morning_time_out) / 60.0
                    ELSE 0
                END as morning_hours,
                CASE 
                    WHEN d.afternoon_time_in IS NOT NULL AND d.afternoon_time_out IS NOT NULL 
                    THEN TIMESTAMPDIFF(MINUTE, d.afternoon_time_in, d.afternoon_time_out) / 60.0
                    ELSE 0
                END as afternoon_hours,
                CASE 
                    WHEN d.morning_time_in IS NOT NULL AND d.morning_time_out IS NOT NULL 
                         AND d.afternoon_time_in IS NOT NULL AND d.afternoon_time_out IS NOT NULL 
                    THEN (TIMESTAMPDIFF(MINUTE, d.morning_time_in, d.morning_time_out) + 
                          TIMESTAMPDIFF(MINUTE, d.afternoon_time_in, d.afternoon_time_out)) / 60.0
                    ELSE 0
                END as total_hours
            FROM dtr d
            JOIN employees e ON d.user_id = e.id
            WHERE $whereClause
            ORDER BY e.department, e.name, d.date
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get employee data with leave balances
     */
    public function getEmployeeData($department = null, $employeeId = null) {
        $whereConditions = ["1=1"];
        $params = [];
        
        if ($department) {
            $whereConditions[] = "department = ?";
            $params[] = $department;
        }
        
        if ($employeeId) {
            $whereConditions[] = "id = ?";
            $params[] = $employeeId;
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $sql = "
            SELECT 
                id,
                name,
                email,
                department,
                position,
                contact,
                gender,
                is_solo_parent,
                service_start_date,
                vacation_leave_balance,
                sick_leave_balance,
                special_leave_privilege_balance,
                maternity_leave_balance,
                paternity_leave_balance,
                solo_parent_leave_balance,
                vawc_leave_balance,
                rehabilitation_leave_balance,
                terminal_leave_balance,
                last_leave_credit_update,
                created_at
            FROM employees 
            WHERE $whereClause
            ORDER BY department, name
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create Excel file with multiple sheets
     */
    private function createExcelFile($leaveData, $dtrData, $employeeData, $startDate, $endDate) {
        $filename = "ELMS_Comprehensive_Report_" . date('Y-m-d_H-i-s') . ".xlsx";
        
        // Set headers for Excel download
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        // Create a temporary file for Excel generation
        $tempFile = tempnam(sys_get_temp_dir(), 'elms_report');
        
        // Generate Excel content
        $this->generateExcelContent($tempFile, $leaveData, $dtrData, $employeeData, $startDate, $endDate);
        
        // Output the file
        readfile($tempFile);
        unlink($tempFile);
        exit();
    }
    
    /**
     * Generate Excel content using simple XML format
     */
    private function generateExcelContent($filename, $leaveData, $dtrData, $employeeData, $startDate, $endDate) {
        $excelContent = $this->createExcelXML($leaveData, $dtrData, $employeeData, $startDate, $endDate);
        file_put_contents($filename, $excelContent);
    }
    
    /**
     * Create Excel XML content
     */
    private function createExcelXML($leaveData, $dtrData, $employeeData, $startDate, $endDate) {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" xmlns:html="http://www.w3.org/TR/REC-html40">';
        
        // Styles
        $xml .= '<Styles>';
        $xml .= '<Style ss:ID="Header">';
        $xml .= '<Font ss:Bold="1" ss:Size="12"/>';
        $xml .= '<Interior ss:Color="#CCCCCC" ss:Pattern="Solid"/>';
        $xml .= '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/></Borders>';
        $xml .= '</Style>';
        $xml .= '<Style ss:ID="Title">';
        $xml .= '<Font ss:Bold="1" ss:Size="14"/>';
        $xml .= '<Alignment ss:Horizontal="Center"/>';
        $xml .= '</Style>';
        $xml .= '</Styles>';
        
        // Sheet 1: Leave Requests Summary
        $xml .= '<Worksheet ss:Name="Leave Requests Summary">';
        $xml .= '<Table>';
        
        // Title
        $xml .= '<Row>';
        $xml .= '<Cell ss:StyleID="Title" ss:MergeAcross="7">';
        $xml .= '<Data ss:Type="String">ELMS Comprehensive Report - Leave Requests & DTR Details</Data>';
        $xml .= '</Cell>';
        $xml .= '</Row>';
        
        $xml .= '<Row>';
        $xml .= '<Cell ss:StyleID="Title" ss:MergeAcross="7">';
        $xml .= '<Data ss:Type="String">Period: ' . $startDate . ' to ' . $endDate . '</Data>';
        $xml .= '</Cell>';
        $xml .= '</Row>';
        
        $xml .= '<Row></Row>'; // Empty row
        
        // Leave Requests Headers
        $xml .= '<Row>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Employee ID</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Employee Name</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Department</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Position</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Leave Type</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Start Date</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">End Date</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Days</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Status</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Reason</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Location</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Medical Condition</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Late Submission</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Request Date</Data></Cell>';
        $xml .= '</Row>';
        
        // Leave Requests Data
        foreach ($leaveData as $row) {
            $xml .= '<Row>';
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($row['employee_id']) . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($row['employee_name']) . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($row['department']) . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($row['position']) . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($row['leave_type']) . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . $row['start_date'] . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . $row['end_date'] . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="Number">' . $row['days_requested'] . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($row['status_display']) . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($row['reason']) . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($row['location_type'] . ' - ' . $row['location_specify']) . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($row['medical_condition'] . ' - ' . $row['illness_specify']) . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . ($row['is_late'] ? 'Yes - ' . $row['late_justification'] : 'No') . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . $row['request_created_at'] . '</Data></Cell>';
            $xml .= '</Row>';
        }
        
        $xml .= '</Table>';
        $xml .= '</Worksheet>';
        
        // Sheet 2: DTR Details
        $xml .= '<Worksheet ss:Name="DTR Details">';
        $xml .= '<Table>';
        
        // DTR Headers
        $xml .= '<Row>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Employee ID</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Employee Name</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Department</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Date</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Morning In</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Morning Out</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Afternoon In</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Afternoon Out</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Status</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Morning Hours</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Afternoon Hours</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Total Hours</Data></Cell>';
        $xml .= '</Row>';
        
        // DTR Data
        foreach ($dtrData as $row) {
            $xml .= '<Row>';
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($row['employee_id']) . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($row['employee_name']) . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($row['department']) . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . $row['date'] . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . ($row['morning_time_in'] ? date('H:i:s', strtotime($row['morning_time_in'])) : '') . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . ($row['morning_time_out'] ? date('H:i:s', strtotime($row['morning_time_out'])) : '') . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . ($row['afternoon_time_in'] ? date('H:i:s', strtotime($row['afternoon_time_in'])) : '') . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . ($row['afternoon_time_out'] ? date('H:i:s', strtotime($row['afternoon_time_out'])) : '') . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($row['attendance_status']) . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="Number">' . number_format($row['morning_hours'], 2) . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="Number">' . number_format($row['afternoon_hours'], 2) . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="Number">' . number_format($row['total_hours'], 2) . '</Data></Cell>';
            $xml .= '</Row>';
        }
        
        $xml .= '</Table>';
        $xml .= '</Worksheet>';
        
        // Sheet 3: Employee Leave Balances
        $xml .= '<Worksheet ss:Name="Employee Leave Balances">';
        $xml .= '<Table>';
        
        // Leave Balances Headers
        $xml .= '<Row>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Employee ID</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Employee Name</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Department</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Position</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Gender</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Solo Parent</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Service Start</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Vacation Leave</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Sick Leave</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Special Privilege</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Maternity Leave</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Paternity Leave</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Solo Parent Leave</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">VAWC Leave</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Rehabilitation Leave</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Terminal Leave</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Last Update</Data></Cell>';
        $xml .= '</Row>';
        
        // Leave Balances Data
        foreach ($employeeData as $row) {
            $xml .= '<Row>';
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($row['id']) . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($row['name']) . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($row['department']) . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($row['position']) . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($row['gender']) . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . ($row['is_solo_parent'] ? 'Yes' : 'No') . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . $row['service_start_date'] . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="Number">' . $row['vacation_leave_balance'] . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="Number">' . $row['sick_leave_balance'] . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="Number">' . $row['special_leave_privilege_balance'] . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="Number">' . $row['maternity_leave_balance'] . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="Number">' . $row['paternity_leave_balance'] . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="Number">' . $row['solo_parent_leave_balance'] . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="Number">' . $row['vawc_leave_balance'] . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="Number">' . $row['rehabilitation_leave_balance'] . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="Number">' . $row['terminal_leave_balance'] . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . $row['last_leave_credit_update'] . '</Data></Cell>';
            $xml .= '</Row>';
        }
        
        $xml .= '</Table>';
        $xml .= '</Worksheet>';
        
        $xml .= '</Workbook>';
        
        return $xml;
    }
    
    /**
     * Generate summary statistics
     */
    public function generateSummaryStats($startDate, $endDate, $department = null) {
        $stats = [];
        
        // Leave statistics
        $whereConditions = ["start_date BETWEEN ? AND ?"];
        $params = [$startDate, $endDate];
        
        if ($department) {
            $whereConditions[] = "e.department = ?";
            $params[] = $department;
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $sql = "
            SELECT 
                COUNT(*) as total_requests,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
                SUM(days_requested) as total_days_requested
            FROM leave_requests lr
            JOIN employees e ON lr.employee_id = e.id
            WHERE $whereClause
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $stats['leave'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // DTR statistics
        $dtrWhereConditions = ["d.date BETWEEN ? AND ?"];
        $dtrParams = [$startDate, $endDate];
        
        if ($department) {
            $dtrWhereConditions[] = "e.department = ?";
            $dtrParams[] = $department;
        }
        
        $dtrWhereClause = implode(' AND ', $dtrWhereConditions);
        
        $sql = "
            SELECT 
                COUNT(DISTINCT d.user_id) as total_employees,
                COUNT(*) as total_dtr_records,
                SUM(CASE WHEN d.morning_time_in IS NOT NULL AND d.morning_time_out IS NOT NULL 
                         AND d.afternoon_time_in IS NOT NULL AND d.afternoon_time_out IS NOT NULL 
                    THEN 1 ELSE 0 END) as complete_days,
                SUM(CASE WHEN d.morning_time_in IS NULL AND d.afternoon_time_in IS NULL 
                    THEN 1 ELSE 0 END) as absent_days,
                AVG(CASE WHEN d.morning_time_in IS NOT NULL AND d.morning_time_out IS NOT NULL 
                         AND d.afternoon_time_in IS NOT NULL AND d.afternoon_time_out IS NOT NULL 
                    THEN (TIMESTAMPDIFF(MINUTE, d.morning_time_in, d.morning_time_out) + 
                          TIMESTAMPDIFF(MINUTE, d.afternoon_time_in, d.afternoon_time_out)) / 60.0 
                    ELSE 0 END) as avg_daily_hours
            FROM dtr d
            JOIN employees e ON d.user_id = e.id
            WHERE $dtrWhereClause
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($dtrParams);
        $stats['dtr'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $stats;
    }
}
?>
