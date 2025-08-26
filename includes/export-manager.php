<?php
require_once(__DIR__ . '/../config/config.php');
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Dompdf\Dompdf;
use Dompdf\Options;

class ExportManager {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    /**
     * Export data to Excel format
     */
    public function exportToExcel($data, $headers, $filename, $title = '') {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set document properties
        $spreadsheet->getProperties()
            ->setCreator(APP_NAME)
            ->setTitle($title)
            ->setSubject($title)
            ->setDescription('Generated from ' . APP_NAME);
        
        // Add title if provided
        $startRow = 1;
        if ($title) {
            $sheet->setCellValue('A1', $title);
            $sheet->mergeCells('A1:' . $this->getColumnLetter(count($headers)) . '1');
            $sheet->getStyle('A1')->applyFromArray([
                'font' => ['bold' => true, 'size' => 16],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFE0E0E0']
                ]
            ]);
            $startRow = 3;
        }
        
        // Add headers
        $col = 1;
        foreach ($headers as $header) {
            $sheet->setCellValueByColumnAndRow($col, $startRow, $header);
            $col++;
        }
        
        // Style headers
        $headerRange = 'A' . $startRow . ':' . $this->getColumnLetter(count($headers)) . $startRow;
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF1976D2']
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN]
            ]
        ]);
        
        // Add data
        $row = $startRow + 1;
        foreach ($data as $rowData) {
            $col = 1;
            foreach ($rowData as $value) {
                $sheet->setCellValueByColumnAndRow($col, $row, $value);
                $col++;
            }
            $row++;
        }
        
        // Auto-size columns
        foreach (range(1, count($headers)) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }
        
        // Generate file
        $writer = new Xlsx($spreadsheet);
        
        if (headers_sent()) {
            throw new Exception('Headers already sent');
        }
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        
        $writer->save('php://output');
        exit;
    }
    
    /**
     * Export data to PDF format
     */
    public function exportToPDF($data, $headers, $filename, $title = '', $orientation = 'portrait') {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', true);
        
        $dompdf = new Dompdf($options);
        
        // Generate HTML content
        $html = $this->generatePDFHTML($data, $headers, $title, $orientation);
        
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', $orientation);
        $dompdf->render();
        
        if (headers_sent()) {
            throw new Exception('Headers already sent');
        }
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
        
        echo $dompdf->output();
        exit;
    }
    
    /**
     * Generate HTML for PDF export
     */
    private function generatePDFHTML($data, $headers, $title, $orientation) {
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>' . htmlspecialchars($title) . '</title>
            <style>
                @page { margin: 0.5in; }
                body { 
                    font-family: DejaVu Sans, sans-serif; 
                    font-size: ' . ($orientation === 'landscape' ? '10px' : '12px') . ';
                    margin: 0; 
                    padding: 20px; 
                }
                h1 { 
                    color: #1976d2; 
                    text-align: center; 
                    margin-bottom: 20px; 
                    font-size: 18px;
                }
                table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    margin-top: 10px; 
                }
                th, td { 
                    border: 1px solid #ddd; 
                    padding: 8px; 
                    text-align: left; 
                    word-wrap: break-word;
                }
                th { 
                    background-color: #1976d2; 
                    color: white; 
                    font-weight: bold; 
                }
                tr:nth-child(even) { 
                    background-color: #f2f2f2; 
                }
                .footer { 
                    position: fixed; 
                    bottom: 0; 
                    width: 100%; 
                    text-align: center; 
                    font-size: 10px; 
                    color: #666; 
                }
            </style>
        </head>
        <body>';
        
        if ($title) {
            $html .= '<h1>' . htmlspecialchars($title) . '</h1>';
        }
        
        $html .= '<table><thead><tr>';
        
        // Add headers
        foreach ($headers as $header) {
            $html .= '<th>' . htmlspecialchars($header) . '</th>';
        }
        
        $html .= '</tr></thead><tbody>';
        
        // Add data
        foreach ($data as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . htmlspecialchars($cell) . '</td>';
            }
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        
        $html .= '<div class="footer">
            Generated on ' . date('F j, Y g:i A') . ' by ' . APP_NAME . '
        </div>';
        
        $html .= '</body></html>';
        
        return $html;
    }
    
    /**
     * Get Excel column letter from number
     */
    private function getColumnLetter($columnNumber) {
        $dividend = $columnNumber;
        $columnName = '';
        
        while ($dividend > 0) {
            $modulo = ($dividend - 1) % 26;
            $columnName = chr(65 + $modulo) . $columnName;
            $dividend = (int)(($dividend - $modulo) / 26);
        }
        
        return $columnName;
    }
    
    /**
     * Export teacher salary report
     */
    public function exportTeacherSalaryReport($month, $year, $format = 'excel') {
        $query = "SELECT 
                    t.employee_id,
                    CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
                    t.email,
                    sd.basic_salary,
                    sd.allowances,
                    sd.deductions,
                    sd.attendance_bonus,
                    sd.attendance_penalty,
                    sd.net_salary,
                    sd.status,
                    sd.payment_date
                  FROM salary_disbursements sd
                  LEFT JOIN teachers t ON sd.teacher_id = t.id
                  WHERE sd.month = ? AND sd.year = ?
                  ORDER BY t.first_name, t.last_name";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$month, $year]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $headers = [
            'Employee ID', 'Teacher Name', 'Email', 'Basic Salary', 
            'Allowances', 'Deductions', 'Attendance Bonus', 
            'Attendance Penalty', 'Net Salary', 'Status', 'Payment Date'
        ];
        
        $exportData = [];
        foreach ($data as $row) {
            $exportData[] = [
                $row['employee_id'],
                $row['teacher_name'],
                $row['email'],
                number_format($row['basic_salary'], 2),
                number_format($row['allowances'], 2),
                number_format($row['deductions'], 2),
                number_format($row['attendance_bonus'], 2),
                number_format($row['attendance_penalty'], 2),
                number_format($row['net_salary'], 2),
                ucfirst($row['status']),
                $row['payment_date'] ? date('M j, Y', strtotime($row['payment_date'])) : 'N/A'
            ];
        }
        
        $monthName = date('F', mktime(0, 0, 0, $month, 1));
        $filename = "Salary_Report_{$monthName}_{$year}";
        $title = "Salary Report - {$monthName} {$year}";
        
        if ($format === 'pdf') {
            $this->exportToPDF($exportData, $headers, $filename, $title, 'landscape');
        } else {
            $this->exportToExcel($exportData, $headers, $filename, $title);
        }
    }
}
?>