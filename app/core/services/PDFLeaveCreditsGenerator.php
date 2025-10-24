<?php
/**
 * PDF Leave Credits Generator for ELMS
 * Generates PDF reports specifically for leave credits
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

class PDFLeaveCreditsGenerator {
    private $pdo;
    private $pdf;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Generate PDF report for leave credits
     */
    public function generateLeaveCreditsReport($department = null, $employeeId = null) {
        // Clear any previous output
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Get leave credits data
        $creditsData = $this->getLeaveCreditsData($department, $employeeId);
        
        // Create PDF
        $this->createPDF($creditsData);
    }
    
    /**
     * Get leave credits data with detailed information
     */
    private function getLeaveCreditsData($department = null, $employeeId = null) {
        // First, check which columns actually exist in the database
        $stmt = $this->pdo->query("DESCRIBE employees");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Build dynamic SQL based on available columns
        $selectFields = [
            'e.id as employee_id',
            'e.name as employee_name',
            'e.email',
            'e.department',
            'e.position',
            'e.created_at'
        ];
        
        // Add leave credit fields if they exist
        $leaveCreditFields = [
            'annual_leave_balance' => 'vacation_leave',
            'sick_leave_balance' => 'sick_leave',
            'vacation_leave_balance' => 'vacation_leave',
            'special_leave_privilege_balance' => 'special_privilege',
            'maternity_leave_balance' => 'maternity_leave',
            'paternity_leave_balance' => 'paternity_leave',
            'solo_parent_leave_balance' => 'solo_parent_leave',
            'vawc_leave_balance' => 'vawc_leave',
            'rehabilitation_leave_balance' => 'rehabilitation_leave',
            'terminal_leave_balance' => 'terminal_leave',
            'cto_balance' => 'cto_leave',
            'last_leave_credit_update' => 'last_updated'
        ];
        
        foreach ($leaveCreditFields as $dbField => $alias) {
            if (in_array($dbField, $columns)) {
                $selectFields[] = "e.{$dbField} as {$alias}";
            } else {
                $selectFields[] = "0 as {$alias}";
            }
        }
        
        $sql = "SELECT " . implode(', ', $selectFields) . " FROM employees e 
                WHERE e.role = 'employee' 
                AND e.department NOT IN ('Executive', 'Operations')
                AND e.position NOT LIKE '%Department Head%'
                AND e.position NOT LIKE '%Director Head%'";
        
        $params = [];
        
        if ($department) {
            $sql .= " AND e.department = ?";
            $params[] = $department;
        }
        
        if ($employeeId) {
            $sql .= " AND e.id = ?";
            $params[] = $employeeId;
        }
        
        $sql .= " ORDER BY e.department ASC, e.name ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create PDF document
     */
    private function createPDF($creditsData) {
        // Create new PDF document
        $this->pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $this->pdf->SetCreator('ELMS - Employee Leave Management System');
        $this->pdf->SetAuthor('ELMS System');
        $this->pdf->SetTitle('Leave Credits Report');
        $this->pdf->SetSubject('Leave Credits Report');
        
        // Set default header data
        $this->pdf->SetHeaderData('', 0, 'ELMS Leave Credits Report', 'Generated on ' . date('Y-m-d H:i:s'));
        
        // Set header and footer fonts
        $this->pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        $this->pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
        
        // Set default monospaced font
        $this->pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        
        // Set margins
        $this->pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $this->pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $this->pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
        
        // Set auto page breaks
        $this->pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
        
        // Set image scale factor
        $this->pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        
        // Add a page
        $this->pdf->AddPage();
        
        // Set font
        $this->pdf->SetFont('helvetica', 'B', 16);
        
        // Report title
        $this->pdf->Cell(0, 15, 'LEAVE CREDITS REPORT', 0, 1, 'C');
        $this->pdf->Ln(5);
        
        // Report date
        $this->pdf->SetFont('helvetica', '', 12);
        $this->pdf->Cell(0, 10, 'Generated on: ' . date('F j, Y H:i:s'), 0, 1, 'C');
        $this->pdf->Ln(10);
        
        // Summary statistics
        $this->addSummarySection($creditsData);
        
        // Leave credits organized by department
        $this->addLeaveCreditsSection($creditsData);
        
        // Output PDF
        $filename = "ELMS_Leave_Credits_" . date('Y-m-d_H-i-s') . ".pdf";
        $this->pdf->Output($filename, 'D');
        exit();
    }
    
    /**
     * Add summary section
     */
    private function addSummarySection($creditsData) {
        $totalEmployees = count($creditsData);
        $employeesWithCredits = count(array_filter($creditsData, function($emp) { 
            return ($emp['vacation_leave'] ?? 0) > 0 || ($emp['sick_leave'] ?? 0) > 0; 
        }));
        
        $this->pdf->SetFont('helvetica', 'B', 14);
        $this->pdf->SetFillColor(245, 158, 11);
        $this->pdf->SetTextColor(255, 255, 255);
        $this->pdf->Cell(0, 10, 'SUMMARY', 0, 1, 'C', true);
        $this->pdf->Ln(5);
        
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->SetFont('helvetica', '', 10);
        
        // Create table for summary
        $this->pdf->SetFillColor(240, 240, 240);
        $this->pdf->Cell(60, 8, 'Total Employees', 1, 0, 'L', true);
        $this->pdf->Cell(30, 8, $totalEmployees, 1, 0, 'C');
        $this->pdf->Ln();
        
        $this->pdf->Cell(60, 8, 'Employees with Leave Credits', 1, 0, 'L', true);
        $this->pdf->Cell(30, 8, $employeesWithCredits, 1, 0, 'C');
        $this->pdf->Ln();
        
        $this->pdf->Cell(60, 8, 'Employees without Credits', 1, 0, 'L', true);
        $this->pdf->Cell(30, 8, $totalEmployees - $employeesWithCredits, 1, 0, 'C');
        $this->pdf->Ln(15);
    }
    
    /**
     * Add leave credits details section organized by department
     */
    private function addLeaveCreditsSection($creditsData) {
        $this->pdf->SetFont('helvetica', 'B', 14);
        $this->pdf->SetFillColor(139, 69, 19);
        $this->pdf->SetTextColor(255, 255, 255);
        $this->pdf->Cell(0, 10, 'LEAVE CREDITS BY DEPARTMENT', 0, 1, 'C', true);
        $this->pdf->Ln(5);
        
        // Group credits by department
        $groupedData = [];
        foreach ($creditsData as $employee) {
            $groupedData[$employee['department']][] = $employee;
        }
        
        // Sort departments alphabetically
        ksort($groupedData);
        
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->SetFont('helvetica', '', 8);
        
        foreach ($groupedData as $department => $employees) {
            // Department header
            $this->pdf->SetFont('helvetica', 'B', 12);
            $this->pdf->SetFillColor(59, 130, 246);
            $this->pdf->SetTextColor(255, 255, 255);
            $this->pdf->Cell(0, 8, $department . ' (' . count($employees) . ' employees)', 0, 1, 'L', true);
            $this->pdf->Ln(2);
            
            // Table header
            $this->pdf->SetFont('helvetica', 'B', 8);
            $this->pdf->SetFillColor(200, 200, 200);
            $this->pdf->SetTextColor(0, 0, 0);
            $this->pdf->Cell(25, 8, 'Employee', 1, 0, 'C', true);
            $this->pdf->Cell(15, 8, 'Vacation', 1, 0, 'C', true);
            $this->pdf->Cell(15, 8, 'Sick', 1, 0, 'C', true);
            $this->pdf->Cell(15, 8, 'Special', 1, 0, 'C', true);
            $this->pdf->Cell(15, 8, 'Maternity', 1, 0, 'C', true);
            $this->pdf->Cell(15, 8, 'Paternity', 1, 0, 'C', true);
            $this->pdf->Cell(15, 8, 'Solo Parent', 1, 0, 'C', true);
            $this->pdf->Cell(15, 8, 'VAWC', 1, 0, 'C', true);
            $this->pdf->Cell(15, 8, 'Rehab', 1, 0, 'C', true);
            $this->pdf->Cell(15, 8, 'Terminal', 1, 0, 'C', true);
            $this->pdf->Ln();
            
            // Table data for this department
            $this->pdf->SetFont('helvetica', '', 7);
            foreach ($employees as $employee) {
                $this->pdf->Cell(25, 8, substr($employee['employee_name'], 0, 12), 1, 0, 'L');
                $this->pdf->Cell(15, 8, $employee['vacation_leave'] ?? '0', 1, 0, 'C');
                $this->pdf->Cell(15, 8, $employee['sick_leave'] ?? '0', 1, 0, 'C');
                $this->pdf->Cell(15, 8, $employee['special_privilege'] ?? '0', 1, 0, 'C');
                $this->pdf->Cell(15, 8, $employee['maternity_leave'] ?? '0', 1, 0, 'C');
                $this->pdf->Cell(15, 8, $employee['paternity_leave'] ?? '0', 1, 0, 'C');
                $this->pdf->Cell(15, 8, $employee['solo_parent_leave'] ?? '0', 1, 0, 'C');
                $this->pdf->Cell(15, 8, $employee['vawc_leave'] ?? '0', 1, 0, 'C');
                $this->pdf->Cell(15, 8, $employee['rehabilitation_leave'] ?? '0', 1, 0, 'C');
                $this->pdf->Cell(15, 8, $employee['terminal_leave'] ?? '0', 1, 0, 'C');
                $this->pdf->Ln();
            }
            
            $this->pdf->Ln(5);
        }
    }
}
?>
