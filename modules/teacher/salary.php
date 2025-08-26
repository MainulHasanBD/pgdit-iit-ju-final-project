<?php
$pageTitle = 'My Salary Information';
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../components/header.php';
require_once '../../components/sidebar.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
$auth = new Auth();
$auth->requireRole('teacher');

$db = new Database();
$conn = $db->getConnection();

// Get teacher ID
$query = "SELECT id, first_name, last_name, employee_id FROM teachers WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    header('Location: profile.php?setup=1');
    exit();
}

// Get current salary configuration
$configQuery = "SELECT * FROM salary_config WHERE teacher_id = ? AND is_active = 1";
$configStmt = $conn->prepare($configQuery);
$configStmt->execute([$teacher['id']]);
$salaryConfig = $configStmt->fetch(PDO::FETCH_ASSOC);

// Get salary disbursement history
$historyQuery = "SELECT * FROM salary_disbursements WHERE teacher_id = ? ORDER BY year DESC, month DESC LIMIT 12";
$historyStmt = $conn->prepare($historyQuery);
$historyStmt->execute([$teacher['id']]);
$salaryHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

// Get current month's salary status
$currentMonth = date('n');
$currentYear = date('Y');

$currentQuery = "SELECT * FROM salary_disbursements WHERE teacher_id = ? AND month = ? AND year = ?";
$currentStmt = $conn->prepare($currentQuery);
$currentStmt->execute([$teacher['id'], $currentMonth, $currentYear]);
$currentSalary = $currentStmt->fetch(PDO::FETCH_ASSOC);

// Calculate YTD (Year to Date) earnings
$ytdQuery = "SELECT SUM(net_salary) as ytd_earnings FROM salary_disbursements WHERE teacher_id = ? AND year = ? AND status IN ('processed', 'paid')";
$ytdStmt = $conn->prepare($ytdQuery);
$ytdStmt->execute([$teacher['id'], $currentYear]);
$ytdEarnings = $ytdStmt->fetchColumn() ?: 0;

// Get attendance summary for current month (affects salary)
$attendanceQuery = "SELECT 
                      COUNT(CASE WHEN status = 'present' THEN 1 END) as present_days,
                      COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_days,
                      COUNT(CASE WHEN status = 'late' THEN 1 END) as late_days
                    FROM teacher_attendance 
                    WHERE teacher_id = ? AND MONTH(date) = ? AND YEAR(date) = ?";
$attendanceStmt = $conn->prepare($attendanceQuery);
$attendanceStmt->execute([$teacher['id'], $currentMonth, $currentYear]);
$attendanceData = $attendanceStmt->fetch(PDO::FETCH_ASSOC);

$attendanceRate = ($attendanceData['present_days'] + $attendanceData['absent_days'] + $attendanceData['late_days']) > 0 ? 
    round(($attendanceData['present_days'] / ($attendanceData['present_days'] + $attendanceData['absent_days'] + $attendanceData['late_days'])) * 100, 1) : 0;
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>My Salary Information</h2>
        <div class="text-muted">
            <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?> 
            (<?php echo htmlspecialchars($teacher['employee_id']); ?>)
        </div>
    </div>

    <?php if (!$salaryConfig): ?>
        <div class="alert alert-warning">
            <h5>Salary Configuration Pending</h5>
            <p>Your salary has not been configured yet. Please contact the accounts department for assistance.</p>
        </div>
    <?php else: ?>
        
        <!-- Salary Overview Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="salary-card basic">
                    <div class="salary-amount"><?php echo formatCurrency($salaryConfig['basic_salary']); ?></div>
                    <div class="salary-label">Basic Salary</div>
                    <i class="salary-icon fas fa-money-bill"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="salary-card allowances">
                    <div class="salary-amount"><?php echo formatCurrency($salaryConfig['allowances']); ?></div>
                    <div class="salary-label">Allowances</div>
                    <i class="salary-icon fas fa-plus-circle"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="salary-card deductions">
                    <div class="salary-amount"><?php echo formatCurrency($salaryConfig['deductions']); ?></div>
                    <div class="salary-label">Deductions</div>
                    <i class="salary-icon fas fa-minus-circle"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="salary-card net">
                    <?php $netSalary = $salaryConfig['basic_salary'] + $salaryConfig['allowances'] - $salaryConfig['deductions']; ?>
                    <div class="salary-amount"><?php echo formatCurrency($netSalary); ?></div>
                    <div class="salary-label">Net Salary</div>
                    <i class="salary-icon fas fa-calculator"></i>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Current Month Status -->
            <div class="col-md-6">
                <div class="material-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <?php echo date('F Y'); ?> Salary Status
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($currentSalary): ?>
                            <div class="salary-breakdown">
                                <div class="breakdown-item">
                                    <span>Basic Salary:</span>
                                    <strong><?php echo formatCurrency($currentSalary['basic_salary']); ?></strong>
                                </div>
                                <div class="breakdown-item">
                                    <span>Allowances:</span>
                                    <strong class="text-success">+<?php echo formatCurrency($currentSalary['allowances']); ?></strong>
                                </div>
                                <div class="breakdown-item">
                                    <span>Deductions:</span>
                                    <strong class="text-danger">-<?php echo formatCurrency($currentSalary['deductions']); ?></strong>
                                </div>
                                <?php if ($currentSalary['attendance_bonus'] > 0): ?>
                                    <div class="breakdown-item">
                                        <span>Attendance Bonus:</span>
                                        <strong class="text-success">+<?php echo formatCurrency($currentSalary['attendance_bonus']); ?></strong>
                                    </div>
                                <?php endif; ?>
                                <?php if ($currentSalary['attendance_penalty'] > 0): ?>
                                    <div class="breakdown-item">
                                        <span>Attendance Penalty:</span>
                                        <strong class="text-danger">-<?php echo formatCurrency($currentSalary['attendance_penalty']); ?></strong>
                                    </div>
                                <?php endif; ?>
                                <hr>
                                <div class="breakdown-item total">
                                    <span>Net Salary:</span>
                                    <strong class="text-primary"><?php echo formatCurrency($currentSalary['net_salary']); ?></strong>
                                </div>
                                <div class="mt-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span>Status:</span>
                                        <?php echo getStatusBadge($currentSalary['status']); ?>
                                    </div>
                                    <?php if ($currentSalary['payment_date']): ?>
                                        <div class="d-flex justify-content-between align-items-center mt-2">
                                            <span>Payment Date:</span>
                                            <strong><?php echo formatDate($currentSalary['payment_date'], 'M j, Y'); ?></strong>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-clock fa-3x mb-3"></i>
                                <p>Salary for <?php echo date('F Y'); ?> has not been processed yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Attendance Impact -->
            <div class="col-md-6">
                <div class="material-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            Attendance Impact (<?php echo date('F'); ?>)
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="attendance-summary">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span>Attendance Rate:</span>
                                <div>
                                    <strong class="<?php echo $attendanceRate >= 95 ? 'text-success' : ($attendanceRate >= 80 ? 'text-warning' : 'text-danger'); ?>">
                                        <?php echo $attendanceRate; ?>%
                                    </strong>
                                </div>
                            </div>
                            
                            <div class="progress mb-3">
                                <div class="progress-bar <?php echo $attendanceRate >= 95 ? 'bg-success' : ($attendanceRate >= 80 ? 'bg-warning' : 'bg-danger'); ?>" 
                                     style="width: <?php echo $attendanceRate; ?>%"></div>
                            </div>
                            
                            <div class="attendance-details">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Present Days:</span>
                                    <span class="text-success"><?php echo $attendanceData['present_days']; ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Absent Days:</span>
                                    <span class="text-danger"><?php echo $attendanceData['absent_days']; ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-3">
                                    <span>Late Days:</span>
                                    <span class="text-warning"><?php echo $attendanceData['late_days']; ?></span>
                                </div>
                            </div>
                            
                            <div class="attendance-bonus-info">
                                <div class="alert alert-info">
                                    <small>
                                        <strong>Bonus Structure:</strong><br>
                                        • 100% attendance: +BDT 2,000<br>
                                        • ≥95% attendance: +BDT 1,000<br>
                                        • &lt;80% attendance: -BDT 500/absent day
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Year-to-Date Summary -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="material-card">
                    <div class="card-header">
                        <h5 class="mb-0"><?php echo $currentYear; ?> Year-to-Date Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="ytd-stat">
                                    <div class="ytd-amount"><?php echo formatCurrency($ytdEarnings); ?></div>
                                    <div class="ytd-label">Total Earnings</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="ytd-stat">
                                    <div class="ytd-amount"><?php echo formatCurrency($ytdEarnings / 12); ?></div>
                                    <div class="ytd-label">Monthly Average</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="ytd-stat">
                                    <div class="ytd-amount"><?php echo count($salaryHistory); ?></div>
                                    <div class="ytd-label">Payments Received</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Salary History -->
        <div class="material-card">
            <div class="card-header">
                <h5 class="mb-0">Salary History</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Period</th>
                                <th>Basic Salary</th>
                                <th>Allowances</th>
                                <th>Deductions</th>
                                <th>Bonus/Penalty</th>
                                <th>Net Amount</th>
                                <th>Status</th>
                                <th>Payment Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($salaryHistory)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted">No salary records found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($salaryHistory as $record): ?>
                                    <tr>
                                        <td>
                                            <?php echo date('M Y', mktime(0, 0, 0, $record['month'], 1, $record['year'])); ?>
                                        </td>
                                        <td><?php echo formatCurrency($record['basic_salary']); ?></td>
                                        <td class="text-success">
                                            <?php echo $record['allowances'] > 0 ? '+' . formatCurrency($record['allowances']) : '-'; ?>
                                        </td>
                                        <td class="text-danger">
                                            <?php echo $record['deductions'] > 0 ? '-' . formatCurrency($record['deductions']) : '-'; ?>
                                        </td>
                                        <td>
                                            <?php if ($record['attendance_bonus'] > 0): ?>
                                                <span class="text-success">+<?php echo formatCurrency($record['attendance_bonus']); ?></span><br>
                                            <?php endif; ?>
                                            <?php if ($record['attendance_penalty'] > 0): ?>
                                                <span class="text-danger">-<?php echo formatCurrency($record['attendance_penalty']); ?></span>
                                            <?php endif; ?>
                                            <?php if ($record['attendance_bonus'] == 0 && $record['attendance_penalty'] == 0): ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="font-weight-bold">
                                            <?php echo formatCurrency($record['net_salary']); ?>
                                        </td>
                                        <td><?php echo getStatusBadge($record['status']); ?></td>
                                        <td>
                                            <?php echo $record['payment_date'] ? formatDate($record['payment_date'], 'M j, Y') : 'Pending'; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>

<style>
.salary-card {
    background: white;
    padding: 24px;
    border-radius: 12px;
    box-shadow: var(--shadow);
    position: relative;
    margin-bottom: 20px;
    overflow: hidden;
}

.salary-card.basic { border-left: 4px solid #2196f3; }
.salary-card.allowances { border-left: 4px solid #4caf50; }
.salary-card.deductions { border-left: 4px solid #f44336; }
.salary-card.net { border-left: 4px solid #ff9800; }

.salary-amount {
    font-size: 28px;
    font-weight: bold;
    color: var(--primary-color);
    margin-bottom: 8px;
}

.salary-label {
    color: var(--text-muted);
    font-size: 14px;
}

.salary-icon {
    position: absolute;
    right: 20px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 36px;
    color: rgba(0,0,0,0.1);
}

.salary-breakdown .breakdown-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f0;
}

.breakdown-item:last-child {
    border-bottom: none;
}

.breakdown-item.total {
    font-size: 18px;
    font-weight: bold;
    border-top: 2px solid #e0e0e0;
    margin-top: 8px;
    padding-top: 12px;
}

.attendance-summary .progress {
    height: 8px;
    border-radius: 4px;
}

.ytd-stat {
    text-align: center;
    padding: 20px;
    border: 1px solid #f0f0f0;
    border-radius: 8px;
    margin-bottom: 20px;
}

.ytd-amount {
    font-size: 24px;
    font-weight: bold;
    color: var(--primary-color);
    margin-bottom: 8px;
}

.ytd-label {
    color: var(--text-muted);
    font-size: 14px;
}

@media (max-width: 768px) {
    .salary-amount {
        font-size: 20px;
    }
    
    .salary-icon {
        font-size: 24px;
    }
}
</style>

<?php require_once '../../components/footer.php'; ?>