<?php
$pageTitle = 'Teacher Profile';
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../components/header.php';
require_once '../../components/sidebar.php';
require_once '../../includes/security.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = new Database();
$conn = $db->getConnection();

$teacherId = (int)($_GET['id'] ?? 0);

if (!$teacherId) {
    header('Location: teachers.php');
    exit();
}

// Get teacher profile with user information
$query = "SELECT 
            t.*,
            u.username,
            u.email as user_email,
            u.last_login,
            u.status as user_status
          FROM teachers t
          LEFT JOIN users u ON t.user_id = u.id
          WHERE t.id = ?";

$stmt = $conn->prepare($query);
$stmt->execute([$teacherId]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    header('Location: teachers.php');
    exit();
}

// Get teacher's subjects
$subjectQuery = "SELECT DISTINCT s.id, s.name, s.code 
                 FROM class_schedule cs 
                 LEFT JOIN subjects s ON cs.subject_id = s.id 
                 WHERE cs.teacher_id = ? AND cs.is_active = 1";
$subjectStmt = $conn->prepare($subjectQuery);
$subjectStmt->execute([$teacherId]);
$teachingSubjects = $subjectStmt->fetchAll(PDO::FETCH_ASSOC);

// Get teacher's schedule
$scheduleQuery = "SELECT 
                    cs.*,
                    s.name as subject_name,
                    s.code as subject_code,
                    c.name as classroom_name
                  FROM class_schedule cs
                  LEFT JOIN subjects s ON cs.subject_id = s.id
                  LEFT JOIN classrooms c ON cs.classroom_id = c.id
                  WHERE cs.teacher_id = ? AND cs.is_active = 1
                  ORDER BY 
                    FIELD(cs.day_of_week, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'),
                    cs.start_time";
$scheduleStmt = $conn->prepare($scheduleQuery);
$scheduleStmt->execute([$teacherId]);
$teacherSchedule = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);

// Get attendance statistics (last 30 days)
$attendanceQuery = "SELECT 
                      COUNT(CASE WHEN status = 'present' THEN 1 END) as present_days,
                      COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_days,
                      COUNT(CASE WHEN status = 'late' THEN 1 END) as late_days,
                      COUNT(*) as total_days
                    FROM teacher_attendance 
                    WHERE teacher_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
$attendanceStmt = $conn->prepare($attendanceQuery);
$attendanceStmt->execute([$teacherId]);
$attendanceStats = $attendanceStmt->fetch(PDO::FETCH_ASSOC);

$attendanceRate = $attendanceStats['total_days'] > 0 ? 
    round(($attendanceStats['present_days'] / $attendanceStats['total_days']) * 100, 1) : 0;

// Get salary information
$salaryQuery = "SELECT * FROM salary_config WHERE teacher_id = ? AND is_active = 1";
$salaryStmt = $conn->prepare($salaryQuery);
$salaryStmt->execute([$teacherId]);
$salaryConfig = $salaryStmt->fetch(PDO::FETCH_ASSOC);

// Get recent salary disbursements
$disbursementQuery = "SELECT * FROM salary_disbursements WHERE teacher_id = ? ORDER BY year DESC, month DESC LIMIT 6";
$disbursementStmt = $conn->prepare($disbursementQuery);
$disbursementStmt->execute([$teacherId]);
$recentDisbursements = $disbursementStmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total teaching hours per week
$totalHours = 0;
foreach ($teacherSchedule as $class) {
    $start = strtotime($class['start_time']);
    $end = strtotime($class['end_time']);
    $totalHours += ($end - $start) / 3600;
}
?>

<div class="main-content">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="teachers.php">Teachers</a></li>
            <li class="breadcrumb-item active">Teacher Profile</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Teacher Profile</h2>
        <div>
            <a href="teachers.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Teachers
            </a>
            <a href="teachers.php?edit=<?php echo $teacher['id']; ?>" class="btn btn-warning">
                <i class="fas fa-edit"></i> Edit Profile
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Profile Information -->
        <div class="col-md-4">
            <div class="material-card">
                <div class="card-body text-center">
                    <div class="profile-avatar mb-3">
                        <?php if (!empty($teacher['profile_picture'])): ?>
                            <img src="<?php echo BASE_URL . $teacher['profile_picture']; ?>" alt="Profile" class="rounded-circle" style="width: 120px; height: 120px; object-fit: cover;">
                        <?php else: ?>
                            <div class="avatar-placeholder">
                                <i class="fas fa-user fa-4x text-muted"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <h4><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></h4>
                    
                    <div class="profile-details">
                        <div class="detail-item">
                            <strong>Employee ID:</strong>
                            <span class="badge badge-secondary"><?php echo htmlspecialchars($teacher['employee_id']); ?></span>
                        </div>
                        
                        <div class="detail-item">
                            <strong>Email:</strong>
                            <div><?php echo htmlspecialchars($teacher['email']); ?></div>
                        </div>
                        
                        <div class="detail-item">
                            <strong>Phone:</strong>
                            <div><?php echo htmlspecialchars($teacher['phone'] ?: 'Not provided'); ?></div>
                        </div>
                        
                        <div class="detail-item">
                            <strong>Hire Date:</strong>
                            <div><?php echo $teacher['hire_date'] ? formatDate($teacher['hire_date'], 'M j, Y') : 'N/A'; ?></div>
                        </div>
                        
                        <div class="detail-item">
                            <strong>Status:</strong>
                            <div><?php echo getStatusBadge($teacher['status']); ?></div>
                        </div>
                        
                        <?php if ($teacher['last_login']): ?>
                            <div class="detail-item">
                                <strong>Last Login:</strong>
                                <div><?php echo formatDate($teacher['last_login'], 'M j, Y g:i A'); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="material-card">
                <div class="card-header">
                    <h5 class="mb-0">Quick Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="stat-item">
                        <div class="d-flex justify-content-between">
                            <span>Teaching Subjects:</span>
                            <strong><?php echo count($teachingSubjects); ?></strong>
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="d-flex justify-content-between">
                            <span>Weekly Classes:</span>
                            <strong><?php echo count($teacherSchedule); ?></strong>
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="d-flex justify-content-between">
                            <span>Weekly Hours:</span>
                            <strong><?php echo number_format($totalHours, 1); ?>h</strong>
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="d-flex justify-content-between">
                            <span>Attendance Rate:</span>
                            <strong class="<?php echo $attendanceRate >= 95 ? 'text-success' : ($attendanceRate >= 80 ? 'text-warning' : 'text-danger'); ?>">
                                <?php echo $attendanceRate; ?>%
                            </strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Information -->
        <div class="col-md-8">
            <!-- Personal Information -->
            <div class="material-card">
                <div class="card-header">
                    <h5 class="mb-0">Personal Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-group">
                                <label>Full Name:</label>
                                <div><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></div>
                            </div>
                            
                            <div class="info-group">
                                <label>Email Address:</label>
                                <div><?php echo htmlspecialchars($teacher['email']); ?></div>
                            </div>
                            
                            <div class="info-group">
                                <label>Phone Number:</label>
                                <div><?php echo htmlspecialchars($teacher['phone'] ?: 'Not provided'); ?></div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="info-group">
                                <label>Employee ID:</label>
                                <div><?php echo htmlspecialchars($teacher['employee_id']); ?></div>
                            </div>
                            
                            <div class="info-group">
                                <label>Hire Date:</label>
                                <div><?php echo $teacher['hire_date'] ? formatDate($teacher['hire_date'], 'M j, Y') : 'N/A'; ?></div>
                            </div>
                            
                            <div class="info-group">
                                <label>Current Salary:</label>
                                <div class="text-success font-weight-bold">
                                    <?php echo $teacher['salary'] ? formatCurrency($teacher['salary']) : 'Not configured'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($teacher['address']): ?>
                        <div class="info-group">
                            <label>Address:</label>
                            <div><?php echo nl2br(htmlspecialchars($teacher['address'])); ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($teacher['qualification']): ?>
                        <div class="info-group">
                            <label>Qualification:</label>
                            <div><?php echo nl2br(htmlspecialchars($teacher['qualification'])); ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Teaching Information -->
            <div class="material-card">
                <div class="card-header">
                    <h5 class="mb-0">Teaching Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Teaching Subjects</h6>
                            <?php if (!empty($teachingSubjects)): ?>
                                <?php foreach ($teachingSubjects as $subject): ?>
                                    <span class="badge badge-primary mr-2 mb-2">
                                        <?php echo htmlspecialchars($subject['code'] . ' - ' . $subject['name']); ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted">No subjects assigned</p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6">
                            <h6>Schedule Overview</h6>
                            <div class="schedule-summary">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Total Classes:</span>
                                    <strong><?php echo count($teacherSchedule); ?></strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Total Hours/Week:</span>
                                    <strong><?php echo number_format($totalHours, 1); ?> hours</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Active Days:</span>
                                    <strong><?php echo count(array_unique(array_column($teacherSchedule, 'day_of_week'))); ?> days</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Weekly Schedule -->
            <?php if (!empty($teacherSchedule)): ?>
                <div class="material-card">
                    <div class="card-header">
                        <h5 class="mb-0">Weekly Schedule</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Day</th>
                                        <th>Time</th>
                                        <th>Subject</th>
                                        <th>Classroom</th>
                                        <th>Duration</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teacherSchedule as $class): ?>
                                        <tr>
                                            <td><?php echo ucfirst($class['day_of_week']); ?></td>
                                            <td>
                                                <?php echo date('g:i A', strtotime($class['start_time'])); ?> - 
                                                <?php echo date('g:i A', strtotime($class['end_time'])); ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-primary"><?php echo htmlspecialchars($class['subject_code']); ?></span>
                                                <?php echo htmlspecialchars($class['subject_name']); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($class['classroom_name']); ?></td>
                                            <td>
                                                <?php 
                                                $duration = (strtotime($class['end_time']) - strtotime($class['start_time'])) / 3600;
                                                echo number_format($duration, 1) . 'h';
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Attendance Summary -->
            <div class="material-card">
                <div class="card-header">
                    <h5 class="mb-0">Attendance Summary (Last 30 Days)</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="attendance-stats">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Present Days:</span>
                                    <span class="text-success font-weight-bold"><?php echo $attendanceStats['present_days']; ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Absent Days:</span>
                                    <span class="text-danger font-weight-bold"><?php echo $attendanceStats['absent_days']; ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Late Days:</span>
                                    <span class="text-warning font-weight-bold"><?php echo $attendanceStats['late_days']; ?></span>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <span>Attendance Rate:</span>
                                    <span class="font-weight-bold <?php echo $attendanceRate >= 95 ? 'text-success' : ($attendanceRate >= 80 ? 'text-warning' : 'text-danger'); ?>">
                                        <?php echo $attendanceRate; ?>%
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="progress-container">
                                <label>Attendance Rate</label>
                                <div class="progress mb-2">
                                    <div class="progress-bar <?php echo $attendanceRate >= 95 ? 'bg-success' : ($attendanceRate >= 80 ? 'bg-warning' : 'bg-danger'); ?>" 
                                         style="width: <?php echo $attendanceRate; ?>%"></div>
                                </div>
                                <small class="text-muted">
                                    <?php if ($attendanceRate >= 95): ?>
                                        Excellent attendance record
                                    <?php elseif ($attendanceRate >= 80): ?>
                                        Good attendance record
                                    <?php else: ?>
                                        Needs improvement
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Salary Information -->
            <?php if ($salaryConfig): ?>
                <div class="material-card">
                    <div class="card-header">
                        <h5 class="mb-0">Salary Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="salary-breakdown">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Basic Salary:</span>
                                        <strong><?php echo formatCurrency($salaryConfig['basic_salary']); ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Allowances:</span>
                                        <strong class="text-success">+<?php echo formatCurrency($salaryConfig['allowances']); ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Deductions:</span>
                                        <strong class="text-danger">-<?php echo formatCurrency($salaryConfig['deductions']); ?></strong>
                                    </div>
                                    <hr>
                                    <div class="d-flex justify-content-between">
                                        <span>Net Salary:</span>
                                        <strong class="text-primary">
                                            <?php echo formatCurrency($salaryConfig['basic_salary'] + $salaryConfig['allowances'] - $salaryConfig['deductions']); ?>
                                        </strong>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="salary-info">
                                    <div class="info-item">
                                        <label>Effective From:</label>
                                        <div><?php echo formatDate($salaryConfig['effective_from'], 'M j, Y'); ?></div>
                                    </div>
                                    <div class="info-item">
                                        <label>Configuration Status:</label>
                                        <div><?php echo $salaryConfig['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>'; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($recentDisbursements)): ?>
                            <h6 class="mt-4 mb-3">Recent Salary Payments</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Period</th>
                                            <th>Net Amount</th>
                                            <th>Status</th>
                                            <th>Payment Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentDisbursements as $disbursement): ?>
                                            <tr>
                                                <td><?php echo date('F Y', mktime(0, 0, 0, $disbursement['month'], 1, $disbursement['year'])); ?></td>
                                                <td class="font-weight-bold"><?php echo formatCurrency($disbursement['net_salary']); ?></td>
                                                <td><?php echo getStatusBadge($disbursement['status']); ?></td>
                                                <td><?php echo $disbursement['payment_date'] ? formatDate($disbursement['payment_date'], 'M j, Y') : 'Pending'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="material-card">
                    <div class="card-header">
                        <h5 class="mb-0">Salary Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <h6>Salary Not Configured</h6>
                            <p>This teacher's salary has not been configured yet. Please contact the accounts department to set up salary configuration.</p>
                            <a href="../accounts/salary-management.php" class="btn btn-warning btn-sm">
                                <i class="fas fa-cog"></i> Configure Salary
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.avatar-placeholder {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: #f8f9fa;
    border: 2px dashed #dee2e6;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
}

.profile-details {
    text-align: left;
    margin-top: 20px;
}

.detail-item {
    margin-bottom: 15px;
    padding-bottom: 8px;
    border-bottom: 1px solid #f0f0f0;
}

.detail-item:last-child {
    border-bottom: none;
}

.detail-item strong {
    color: var(--text-muted);
    font-size: 12px;
    display: block;
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-group {
    margin-bottom: 20px;
}

.info-group label {
    font-weight: 600;
    color: var(--text-muted);
    margin-bottom: 5px;
    display: block;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-item {
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f0;
}

.stat-item:last-child {
    border-bottom: none;
}

.schedule-summary {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
}

.attendance-stats {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
}

.salary-breakdown {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
}

.progress {
    height: 8px;
    border-radius: 4px;
}

.mr-2 { margin-right: 0.5rem; }
.mb-2 { margin-bottom: 0.5rem; }
</style>

<?php require_once '../../components/footer.php'; ?>