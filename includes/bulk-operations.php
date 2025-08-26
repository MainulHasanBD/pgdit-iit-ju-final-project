<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';


class BulkOperations {
    private $db;
    private $conn;
    
    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }
    
    /**
     * Bulk update application status
     */
    public function bulkUpdateApplicationStatus($applicationIds, $status, $notes = '') {
        try {
            $this->conn->beginTransaction();
            
            $placeholders = str_repeat('?,', count($applicationIds) - 1) . '?';
            $query = "UPDATE cv_applications SET status = ?, notes = CONCAT(IFNULL(notes, ''), ?, '\n') WHERE id IN ($placeholders)";
            
            $params = array_merge([$status, "Bulk update: " . $notes . " [" . date('Y-m-d H:i:s') . "]"], $applicationIds);
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            
            $affectedRows = $stmt->rowCount();
            
            // Send email notifications to candidates
            $this->sendBulkStatusUpdateEmails($applicationIds, $status);
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => "Successfully updated {$affectedRows} application(s)",
                'affected_rows' => $affectedRows
            ];
            
        } catch (PDOException $e) {
            $this->conn->rollBack();
            return [
                'success' => false,
                'message' => 'Error in bulk status update: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Bulk salary processing for teachers
     */
    public function bulkProcessSalaries($teacherIds, $month, $year, $options = []) {
        try {
            $this->conn->beginTransaction();
            
            $processedCount = 0;
            $errors = [];
            
            foreach ($teacherIds as $teacherId) {
                $result = $this->processSingleTeacherSalary($teacherId, $month, $year, $options);
                if ($result['success']) {
                    $processedCount++;
                } else {
                    $errors[] = "Teacher ID {$teacherId}: " . $result['message'];
                }
            }
            
            if ($processedCount > 0) {
                $this->conn->commit();
                
                $message = "Successfully processed {$processedCount} salary(ies)";
                if (!empty($errors)) {
                    $message .= ". Errors: " . implode('; ', $errors);
                }
                
                return [
                    'success' => true,
                    'message' => $message,
                    'processed_count' => $processedCount,
                    'errors' => $errors
                ];
            } else {
                $this->conn->rollBack();
                return [
                    'success' => false,
                    'message' => 'No salaries were processed. Errors: ' . implode('; ', $errors)
                ];
            }
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            return [
                'success' => false,
                'message' => 'Bulk salary processing failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Process single teacher salary
     */
    private function processSingleTeacherSalary($teacherId, $month, $year, $options = []) {
        try {
            // Check if salary already processed
            $checkQuery = "SELECT id FROM salary_disbursements WHERE teacher_id = ? AND month = ? AND year = ?";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->execute([$teacherId, $month, $year]);
            
            if ($checkStmt->rowCount() > 0) {
                return [
                    'success' => false,
                    'message' => 'Salary already processed for this month'
                ];
            }
            
            // Get teacher salary configuration
            $configQuery = "SELECT * FROM salary_config WHERE teacher_id = ? AND is_active = 1";
            $configStmt = $this->conn->prepare($configQuery);
            $configStmt->execute([$teacherId]);
            $salaryConfig = $configStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$salaryConfig) {
                return [
                    'success' => false,
                    'message' => 'No salary configuration found'
                ];
            }
            
            // Calculate attendance bonus/penalty
            $attendanceData = $this->calculateAttendanceBonus($teacherId, $month, $year);
            
            // Apply bulk adjustments if provided
            $basicSalary = $salaryConfig['basic_salary'];
            $allowances = $salaryConfig['allowances'];
            $deductions = $salaryConfig['deductions'];
            
            if (isset($options['salary_increase_percent'])) {
                $basicSalary *= (1 + $options['salary_increase_percent'] / 100);
            }
            
            if (isset($options['salary_increase_amount'])) {
                $basicSalary += $options['salary_increase_amount'];
            }
            
            if (isset($options['bonus_amount'])) {
                $allowances += $options['bonus_amount'];
            }
            
            if (isset($options['additional_deduction'])) {
                $deductions += $options['additional_deduction'];
            }
            
            // Calculate net salary
            $netSalary = $basicSalary + $allowances + $attendanceData['bonus'] - $deductions - $attendanceData['penalty'];
            
            // Insert salary disbursement record
            $insertQuery = "INSERT INTO salary_disbursements 
                            (teacher_id, month, year, basic_salary, allowances, deductions, 
                             attendance_bonus, attendance_penalty, net_salary, status, processed_by) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'processed', ?)";
            
            $insertStmt = $this->conn->prepare($insertQuery);
            $insertStmt->execute([
                $teacherId, $month, $year, $basicSalary, $allowances, $deductions,
                $attendanceData['bonus'], $attendanceData['penalty'], $netSalary, $_SESSION['user_id']
            ]);
            
            return [
                'success' => true,
                'message' => 'Salary processed successfully',
                'net_salary' => $netSalary
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Calculate attendance bonus/penalty
     */
    private function calculateAttendanceBonus($teacherId, $month, $year) {
        $query = "SELECT 
                    COUNT(CASE WHEN status = 'present' THEN 1 END) as present_days,
                    COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_days,
                    COUNT(CASE WHEN status = 'late' THEN 1 END) as late_days,
                    COUNT(*) as total_days
                  FROM teacher_attendance 
                  WHERE teacher_id = ? AND MONTH(date) = ? AND YEAR(date) = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$teacherId, $month, $year]);
        $attendance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $bonus = 0;
        $penalty = 0;
        
        if ($attendance['total_days'] > 0) {
            $attendanceRate = ($attendance['present_days'] / $attendance['total_days']) * 100;
            
            // Bonus structure
            if ($attendanceRate == 100) {
                $bonus = 2000; // Perfect attendance bonus
            } elseif ($attendanceRate >= 95) {
                $bonus = 1000; // Good attendance bonus
            }
            
            // Penalty for poor attendance
            if ($attendanceRate < 80) {
                $penalty = $attendance['absent_days'] * 500; // 500 per absent day
            }
            
            // Late penalty
            $penalty += $attendance['late_days'] * 100; // 100 per late day
        }
        
        return [
            'bonus' => $bonus,
            'penalty' => $penalty,
            'attendance_rate' => $attendanceRate ?? 0
        ];
    }
    
    /**
     * Bulk salary increase
     */
    public function bulkSalaryIncrease($teacherIds, $increaseType, $increaseValue) {
        try {
            $this->conn->beginTransaction();
            
            $updatedCount = 0;
            
            foreach ($teacherIds as $teacherId) {
                // Get current salary config
                $configQuery = "SELECT * FROM salary_config WHERE teacher_id = ? AND is_active = 1";
                $configStmt = $this->conn->prepare($configQuery);
                $configStmt->execute([$teacherId]);
                $currentConfig = $configStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($currentConfig) {
                    // Calculate new salary based on increase type
                    $newSalary = $currentConfig['basic_salary'];
                    
                    if ($increaseType === 'percentage') {
                        $newSalary *= (1 + $increaseValue / 100);
                    } else {
                        $newSalary += $increaseValue;
                    }
                    
                    // Deactivate current config
                    $deactivateQuery = "UPDATE salary_config SET is_active = 0, effective_to = CURDATE() WHERE teacher_id = ? AND is_active = 1";
                    $deactivateStmt = $this->conn->prepare($deactivateQuery);
                    $deactivateStmt->execute([$teacherId]);
                    
                    // Create new config
                    $newConfigQuery = "INSERT INTO salary_config 
                                       (teacher_id, basic_salary, allowances, deductions, effective_from, is_active, created_by) 
                                       VALUES (?, ?, ?, ?, CURDATE(), 1, ?)";
                    $newConfigStmt = $this->conn->prepare($newConfigQuery);
                    $newConfigStmt->execute([
                        $teacherId, 
                        $newSalary, 
                        $currentConfig['allowances'], 
                        $currentConfig['deductions'], 
                        $_SESSION['user_id']
                    ]);
                    
                    // Update teachers table
                    $updateTeacherQuery = "UPDATE teachers SET salary = ? WHERE id = ?";
                    $updateTeacherStmt = $this->conn->prepare($updateTeacherQuery);
                    $updateTeacherStmt->execute([$newSalary, $teacherId]);
                    
                    $updatedCount++;
                }
            }
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => "Successfully updated {$updatedCount} teacher salary(ies)",
                'updated_count' => $updatedCount
            ];
            
        } catch (PDOException $e) {
            $this->conn->rollBack();
            return [
                'success' => false,
                'message' => 'Bulk salary increase failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Bulk payment disbursement
     */
    public function bulkPaymentDisbursement($disbursementIds, $paymentMethod = 'bank_transfer', $paymentDate = null) {
        try {
            $this->conn->beginTransaction();
            
            if (!$paymentDate) {
                $paymentDate = date('Y-m-d');
            }
            
            $placeholders = str_repeat('?,', count($disbursementIds) - 1) . '?';
            $query = "UPDATE salary_disbursements 
                      SET status = 'paid', payment_method = ?, payment_date = ? 
                      WHERE id IN ($placeholders) AND status = 'processed'";
            
            $params = array_merge([$paymentMethod, $paymentDate], $disbursementIds);
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            
            $paidCount = $stmt->rowCount();
            
            // Send payment notifications
            $this->sendBulkPaymentNotifications($disbursementIds);
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => "Successfully processed {$paidCount} payment(s)",
                'paid_count' => $paidCount
            ];
            
        } catch (PDOException $e) {
            $this->conn->rollBack();
            return [
                'success' => false,
                'message' => 'Bulk payment disbursement failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Bulk teacher status update
     */
    public function bulkUpdateTeacherStatus($teacherIds, $status, $reason = '') {
        try {
            $this->conn->beginTransaction();
            
            $placeholders = str_repeat('?,', count($teacherIds) - 1) . '?';
            $query = "UPDATE teachers SET status = ? WHERE id IN ($placeholders)";
            
            $params = array_merge([$status], $teacherIds);
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            
            $updatedCount = $stmt->rowCount();
            
            // If deactivating teachers, also deactivate their user accounts
            if ($status === 'inactive') {
                $userQuery = "UPDATE users SET status = 'inactive' WHERE id IN (SELECT user_id FROM teachers WHERE id IN ($placeholders))";
                $userStmt = $this->conn->prepare($userQuery);
                $userStmt->execute($teacherIds);
            }
            
            // Log the bulk operation
            $this->logBulkOperation('teacher_status_update', [
                'teacher_ids' => $teacherIds,
                'new_status' => $status,
                'reason' => $reason
            ]);
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => "Successfully updated {$updatedCount} teacher status(es) to {$status}",
                'updated_count' => $updatedCount
            ];
            
        } catch (PDOException $e) {
            $this->conn->rollBack();
            return [
                'success' => false,
                'message' => 'Bulk teacher status update failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Bulk delete old applications
     */
    public function bulkDeleteOldApplications($daysOld = 365, $statuses = ['rejected']) {
        try {
            $this->conn->beginTransaction();
            
            $statusPlaceholders = str_repeat('?,', count($statuses) - 1) . '?';
            $query = "DELETE FROM cv_applications 
                      WHERE application_date < DATE_SUB(CURDATE(), INTERVAL ? DAY) 
                      AND status IN ($statusPlaceholders)";
            
            $params = array_merge([$daysOld], $statuses);
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            
            $deletedCount = $stmt->rowCount();
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => "Successfully deleted {$deletedCount} old application(s)",
                'deleted_count' => $deletedCount
            ];
            
        } catch (PDOException $e) {
            $this->conn->rollBack();
            return [
                'success' => false,
                'message' => 'Bulk delete failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Bulk export data
     */
    public function bulkExportData($dataType, $filters = [], $format = 'excel') {
        try {
            $data = [];
            $headers = [];
            
            switch ($dataType) {
                case 'teachers':
                    $data = $this->getTeachersExportData($filters);
                    $headers = ['Employee ID', 'Name', 'Email', 'Phone', 'Hire Date', 'Salary', 'Status'];
                    break;
                    
                case 'applications':
                    $data = $this->getApplicationsExportData($filters);
                    $headers = ['Candidate Name', 'Email', 'Phone', 'Position', 'Applied Date', 'Status'];
                    break;
                    
                case 'salaries':
                    $data = $this->getSalariesExportData($filters);
                    $headers = ['Employee ID', 'Teacher Name', 'Month', 'Year', 'Basic Salary', 'Net Salary', 'Status'];
                    break;
                    
                default:
                    throw new Exception('Invalid data type for export');
            }
            
            // Use ExportManager for actual export
            require_once 'includes/export-manager.php';
            $exportManager = new ExportManager();
            
            $filename = $dataType . '_export_' . date('Y-m-d');
            
            if ($format === 'pdf') {
                $exportManager->exportToPDF($data, $headers, $filename, ucfirst($dataType) . ' Export');
            } else {
                $exportManager->exportToExcel($data, $headers, $filename, ucfirst($dataType) . ' Export');
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get teachers data for export
     */
    private function getTeachersExportData($filters = []) {
        $whereConditions = [];
        $params = [];
        
        if (isset($filters['status'])) {
            $whereConditions[] = "status = ?";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['hire_date_from'])) {
            $whereConditions[] = "hire_date >= ?";
            $params[] = $filters['hire_date_from'];
        }
        
        if (isset($filters['hire_date_to'])) {
            $whereConditions[] = "hire_date <= ?";
            $params[] = $filters['hire_date_to'];
        }
        
        $whereClause = empty($whereConditions) ? '' : 'WHERE ' . implode(' AND ', $whereConditions);
        
        $query = "SELECT 
                    employee_id,
                    CONCAT(first_name, ' ', last_name) as full_name,
                    email,
                    phone,
                    hire_date,
                    salary,
                    status
                  FROM teachers 
                  {$whereClause}
                  ORDER BY first_name, last_name";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_NUM);
    }
    
    /**
     * Get applications data for export
     */
    private function getApplicationsExportData($filters = []) {
        $whereConditions = [];
        $params = [];
        
        if (isset($filters['status'])) {
            $whereConditions[] = "ca.status = ?";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['job_id'])) {
            $whereConditions[] = "ca.job_posting_id = ?";
            $params[] = $filters['job_id'];
        }
        
        if (isset($filters['date_from'])) {
            $whereConditions[] = "DATE(ca.application_date) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (isset($filters['date_to'])) {
            $whereConditions[] = "DATE(ca.application_date) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $whereClause = empty($whereConditions) ? '' : 'WHERE ' . implode(' AND ', $whereConditions);
        
        $query = "SELECT 
                    ca.candidate_name,
                    ca.email,
                    ca.phone,
                    jp.title as position,
                    ca.application_date,
                    ca.status
                  FROM cv_applications ca
                  LEFT JOIN job_postings jp ON ca.job_posting_id = jp.id
                  {$whereClause}
                  ORDER BY ca.application_date DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_NUM);
    }
    
    /**
     * Get salaries data for export
     */
    private function getSalariesExportData($filters = []) {
        $whereConditions = [];
        $params = [];
        
        if (isset($filters['month'])) {
            $whereConditions[] = "sd.month = ?";
            $params[] = $filters['month'];
        }
        
        if (isset($filters['year'])) {
            $whereConditions[] = "sd.year = ?";
            $params[] = $filters['year'];
        }
        
        if (isset($filters['status'])) {
            $whereConditions[] = "sd.status = ?";
            $params[] = $filters['status'];
        }
        
        $whereClause = empty($whereConditions) ? '' : 'WHERE ' . implode(' AND ', $whereConditions);
        
        $query = "SELECT 
                    t.employee_id,
                    CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
                    sd.month,
                    sd.year,
                    sd.basic_salary,
                    sd.net_salary,
                    sd.status
                  FROM salary_disbursements sd
                  LEFT JOIN teachers t ON sd.teacher_id = t.id
                  {$whereClause}
                  ORDER BY sd.year DESC, sd.month DESC, t.first_name";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_NUM);
    }
    
    /**
     * Send bulk status update emails
     */
    private function sendBulkStatusUpdateEmails($applicationIds, $status) {
        $emailService = new EmailService();
        
        $placeholders = str_repeat('?,', count($applicationIds) - 1) . '?';
        $query = "SELECT ca.*, jp.title as job_title 
                  FROM cv_applications ca 
                  LEFT JOIN job_postings jp ON ca.job_posting_id = jp.id 
                  WHERE ca.id IN ($placeholders)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($applicationIds);
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($applications as $application) {
            $this->sendStatusUpdateEmail($application, $status);
        }
    }
    
    /**
     * Send status update email
     */
    private function sendStatusUpdateEmail($application, $status) {
        $emailService = new EmailService();
        
        $subject = "Application Status Update - " . $application['job_title'];
        
        $statusMessages = [
            'shortlisted' => 'Congratulations! Your application has been shortlisted. We will contact you soon for the next steps.',
            'interviewed' => 'Thank you for your interview. We are currently reviewing all candidates and will get back to you soon.',
            'selected' => 'Congratulations! You have been selected for the position. Our HR team will contact you with further details.',
            'rejected' => 'Thank you for your interest in our position. Unfortunately, we have decided to proceed with other candidates. We wish you the best in your job search.'
        ];
        
        $body = "
        <h2>Application Status Update</h2>
        <p>Dear {$application['candidate_name']},</p>
        <p>We hope this email finds you well.</p>
        <p><strong>Position:</strong> {$application['job_title']}</p>
        <p><strong>Status:</strong> " . ucfirst($status) . "</p>
        <p>{$statusMessages[$status]}</p>
        <p>Thank you for your interest in " . APP_NAME . ".</p>
        <p>Best regards,<br>HR Department</p>
        ";
        
        return $emailService->sendEmail($application['email'], $subject, $body);
    }
    
    /**
     * Send bulk payment notifications
     */
    private function sendBulkPaymentNotifications($disbursementIds) {
        $emailService = new EmailService();
        
        $placeholders = str_repeat('?,', count($disbursementIds) - 1) . '?';
        $query = "SELECT 
                    sd.*,
                    t.first_name,
                    t.last_name,
                    t.email
                  FROM salary_disbursements sd
                  LEFT JOIN teachers t ON sd.teacher_id = t.id
                  WHERE sd.id IN ($placeholders)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($disbursementIds);
        $disbursements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($disbursements as $disbursement) {
            $subject = "Salary Payment Processed - " . date('F Y', mktime(0, 0, 0, $disbursement['month'], 1, $disbursement['year']));
            
            $body = "
            <h2>Salary Payment Notification</h2>
            <p>Dear {$disbursement['first_name']} {$disbursement['last_name']},</p>
            <p>Your salary for " . date('F Y', mktime(0, 0, 0, $disbursement['month'], 1, $disbursement['year'])) . " has been processed.</p>
            <p><strong>Net Amount:</strong> " . formatCurrency($disbursement['net_salary']) . "</p>
            <p><strong>Payment Date:</strong> " . formatDate($disbursement['payment_date'], 'M j, Y') . "</p>
            <p><strong>Payment Method:</strong> " . ucfirst(str_replace('_', ' ', $disbursement['payment_method'])) . "</p>
            <p>Thank you for your continued service.</p>
            <p>Best regards,<br>Accounts Department<br>" . APP_NAME . "</p>
            ";
            
            $emailService->sendEmail($disbursement['email'], $subject, $body);
        }
    }
    
    /**
     * Log bulk operation
     */
    private function logBulkOperation($operation, $details) {
        $query = "INSERT INTO system_logs (user_id, action, table_name, new_values, ip_address, user_agent) VALUES (?, ?, 'bulk_operations', ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            "Bulk Operation: {$operation}",
            json_encode($details),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }
    
    /**
     * Get bulk operation statistics
     */
    public function getBulkOperationStats($dateFrom = null, $dateTo = null) {
        $whereConditions = ["action LIKE 'Bulk Operation:%'"];
        $params = [];
        
        if ($dateFrom) {
            $whereConditions[] = "DATE(created_at) >= ?";
            $params[] = $dateFrom;
        }
        
        if ($dateTo) {
            $whereConditions[] = "DATE(created_at) <= ?";
            $params[] = $dateTo;
        }
        
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        
        $query = "SELECT 
                    action,
                    COUNT(*) as operation_count,
                    DATE(created_at) as operation_date
                  FROM system_logs 
                  {$whereClause}
                  GROUP BY action, DATE(created_at)
                  ORDER BY created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>