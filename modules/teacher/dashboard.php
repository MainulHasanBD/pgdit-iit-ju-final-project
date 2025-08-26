<?php
$pageTitle = 'Teacher Dashboard';
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
    // Redirect to profile setup if teacher record doesn't exist
    header('Location: profile.php?setup=1');
    exit();
}

$teacherId = $teacher['id'];

// Get today's schedule
$todayDay = strtolower(date('l'));
$todayScheduleQuery = "SELECT 
                         cs.*,
                         s.name as subject_name,
                         s.code as subject_code,
                         c.name as classroom_name,
                         ta.status as attendance_status,
                         ta.check_in_time
                       FROM class_schedule cs
                       LEFT JOIN subjects s ON cs.subject_id = s.id
                       LEFT JOIN classrooms c ON cs.classroom_id = c.id
                       LEFT JOIN teacher_attendance ta ON cs.id = ta.schedule_id AND DATE(ta.date) = CURDATE()
                       WHERE cs.teacher_id = ? AND cs.day_of_week = ? AND cs.is_active = 1
                       ORDER BY cs.start_time";

$todayStmt = $conn->prepare($todayScheduleQuery);
$todayStmt->execute([$teacherId, $todayDay]);
$todayClasses = $todayStmt->fetchAll(PDO::FETCH_ASSOC);

// Get this week's attendance summary
$weekAttendanceQuery = "SELECT 
                          COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
                          COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count,
                          COUNT(CASE WHEN status = 'late' THEN 1 END) as late_count,
                          COUNT(*) as total_classes
                        FROM teacher_attendance 
                        WHERE teacher_id = ? 
                        AND date >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
                        AND date <= DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 6 DAY)";

$weekStmt = $conn->prepare($weekAttendanceQuery);
$weekStmt->execute([$teacherId]);
$weekAttendance = $weekStmt->fetch(PDO::FETCH_ASSOC);

$weekAttendanceRate = $weekAttendance['total_classes'] > 0 ? 
    round(($weekAttendance['present_count'] / $weekAttendance['total_classes']) * 100, 1) : 0;

// Get monthly statistics
$monthlyStatsQuery = "SELECT 
                        COUNT(CASE WHEN status = 'present' THEN 1 END) as monthly_present,
                        COUNT(CASE WHEN status = 'absent' THEN 1 END) as monthly_absent,
                        COUNT(CASE WHEN status = 'late' THEN 1 END) as monthly_late,
                        COUNT(*) as monthly_total
                      FROM teacher_attendance 
                      WHERE teacher_id = ? 
                      AND MONTH(date) = MONTH(CURDATE()) 
                      AND YEAR(date) = YEAR(CURDATE())";

$monthlyStmt = $conn->prepare($monthlyStatsQuery);
$monthlyStmt->execute([$teacherId]);
$monthlyStats = $monthlyStmt->fetch(PDO::FETCH_ASSOC);

$monthlyAttendanceRate = $monthlyStats['monthly_total'] > 0 ? 
    round(($monthlyStats['monthly_present'] / $monthlyStats['monthly_total']) * 100, 1) : 0;

// Get current salary status
$salaryQuery = "SELECT 
                  sd.*,
                  DATE_FORMAT(CONCAT(sd.year, '-', LPAD(sd.month, 2, '0'), '-01'), '%M %Y') as period_name
                FROM salary_disbursements sd 
                WHERE sd.teacher_id = ? 
                AND sd.month = MONTH(CURDATE()) 
                AND sd.year = YEAR(CURDATE())";

$salaryStmt = $conn->prepare($salaryQuery);
$salaryStmt->execute([$teacherId]);
$currentSalary = $salaryStmt->fetch(PDO::FETCH_ASSOC);

// Get total subjects and weekly hours
$subjectsQuery = "SELECT 
                    COUNT(DISTINCT s.id) as total_subjects,
                    COUNT(cs.id) as weekly_classes,
                    SUM(TIME_TO_SEC(TIMEDIFF(cs.end_time, cs.start_time)) / 3600) as weekly_hours
                  FROM class_schedule cs
                  LEFT JOIN subjects s ON cs.subject_id = s.id
                  WHERE cs.teacher_id = ? AND cs.is_active = 1";

$subjectsStmt = $conn->prepare($subjectsQuery);
$subjectsStmt->execute([$teacherId]);
$subjectStats = $subjectsStmt->fetch(PDO::FETCH_ASSOC);

// Get recent announcements (simulated - you may want to create an announcements table)
$announcements = [
    [
        'title' => 'Monthly Staff Meeting',
        'message' => 'All staff members are required to attend the monthly meeting on Friday at 3 PM.',
        'date' => date('Y-m-d', strtotime('-2 days')),
        'type' => 'info'
    ],
    [
        'title' => 'Salary Processing Update',
        'message' => 'Salaries for this month will be processed by the 25th. Please ensure your attendance is up to date.',
        'date' => date('Y-m-d', strtotime('-5 days')),
        'type' => 'success'
    ]
];

// Get upcoming classes (next 3 classes)
$upcomingQuery = "SELECT 
                    cs.*,
                    s.name as subject_name,
                    s.code as subject_code,
                    c.name as classroom_name,
                    CASE 
                        WHEN cs.day_of_week = 'monday' THEN 1
                        WHEN cs.day_of_week = 'tuesday' THEN 2
                        WHEN cs.day_of_week = 'wednesday' THEN 3
                        WHEN cs.day_of_week = 'thursday' THEN 4
                        WHEN cs.day_of_week = 'friday' THEN 5
                        WHEN cs.day_of_week = 'saturday' THEN 6
                        WHEN cs.day_of_week = 'sunday' THEN 7
                    END as day_number
                  FROM class_schedule cs
                  LEFT JOIN subjects s ON cs.subject_id = s.id
                  LEFT JOIN classrooms c ON cs.classroom_id = c.id
                  WHERE cs.teacher_id = ? AND cs.is_active = 1
                  ORDER BY 
                    CASE 
                        WHEN day_number >= DAYOFWEEK(CURDATE()) THEN day_number
                        ELSE day_number + 7
                    END,
                    cs.start_time
                  LIMIT 3";

$upcomingStmt = $conn->prepare($upcomingQuery);
$upcomingStmt->execute([$teacherId]);
$upcomingClasses = $upcomingStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="main-content">
    <!-- Welcome Section -->
    <div class="welcome-section mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2>Welcome back, <?php echo htmlspecialchars($teacher['first_name']); ?>!</h2>
                <p class="text-white mb-0">Here's your teaching overview for <?php echo date('l, F j, Y'); ?></p>
            </div>
            <div class="teacher-info">
                <div class="text-right">
                    <div class="font-weight-bold"><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></div>
                    <div class="text-white"><?php echo htmlspecialchars($teacher['employee_id']); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="dashboard-stats">
        <div class="stat-card primary">
            <div class="stat-number"><?php echo count($todayClasses); ?></div>
            <div class="stat-label">Today's Classes</div>
            <div class="stat-sublabel"><?php echo $subjectStats['weekly_classes']; ?> this week</div>
            <i class="stat-icon fas fa-chalkboard"></i>
        </div>
        
        <div class="stat-card success">
            <div class="stat-number"><?php echo $weekAttendanceRate; ?>%</div>
            <div class="stat-label">Week Attendance</div>
            <div class="stat-sublabel"><?php echo $weekAttendance['present_count']; ?>/<?php echo $weekAttendance['total_classes']; ?> present</div>
            <i class="stat-icon fas fa-check-circle"></i>
        </div>
        
        <div class="stat-card info">
            <div class="stat-number"><?php echo $subjectStats['total_subjects'] ?? 0; ?></div>
            <div class="stat-label">Teaching Subjects</div>
            <div class="stat-sublabel"><?php echo number_format($subjectStats['weekly_hours'] ?? 0, 1); ?>h/week</div>
            <i class="stat-icon fas fa-book"></i>
        </div>
        
        <!-- <div class="stat-card warning">
            <div class="stat-number">
                <?php echo $currentSalary ? formatCurrency($currentSalary['net_salary']) : 'N/A'; ?>
            </div>
            <div class="stat-label">Current Month Salary</div>
            <div class="stat-sublabel">
                <?php echo $currentSalary ? getStatusBadge($currentSalary['status']) : 'Not processed'; ?>
            </div>
            <i class="stat-icon fas fa-money-bill"></i>
        </div> -->
    </div>

    <div class="row">
        <!-- Today's Schedule -->
        <div class="col-md-8">
            <div class="material-card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Today's Schedule</h5>
                        <a href="schedule.php" class="btn btn-sm btn-outline">View Full Schedule</a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($todayClasses)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <h6 class="text-muted">No classes scheduled for today</h6>
                            <p class="text-muted">Enjoy your day off!</p>
                        </div>
                    <?php else: ?>
                        <div class="today-schedule">
                            <?php foreach ($todayClasses as $class): ?>
                                <div class="schedule-item">
                                    <div class="schedule-time">
                                        <div class="time-start"><?php echo date('g:i A', strtotime($class['start_time'])); ?></div>
                                        <div class="time-end"><?php echo date('g:i A', strtotime($class['end_time'])); ?></div>
                                    </div>
                                    <div class="schedule-content">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($class['subject_name']); ?></h6>
                                                <div class="text-muted small">
                                                    <i class="fas fa-door-open"></i>
                                                    <?php echo htmlspecialchars($class['classroom_name']); ?>
                                                </div>
                                                <div class="text-muted small">
                                                    <i class="fas fa-code"></i>
                                                    <?php echo htmlspecialchars($class['subject_code']); ?>
                                                </div>
                                            </div>
                                            <div class="schedule-status">
                                                <?php if ($class['attendance_status']): ?>
                                                    <?php echo getStatusBadge($class['attendance_status']); ?>
                                                    <?php if ($class['check_in_time']): ?>
                                                        <div class="text-muted small">
                                                            Check-in: <?php echo date('g:i A', strtotime($class['check_in_time'])); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <a href="attendance.php" class="btn btn-sm btn-success">Mark Attendance</a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="material-card">
                <div class="card-header">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="quick-actions">
                        <a href="attendance.php" class="quick-action-btn">
                            <i class="fas fa-clock"></i>
                            <span>Mark Attendance</span>
                        </a>
                        <a href="schedule.php" class="quick-action-btn">
                            <i class="fas fa-calendar-alt"></i>
                            <span>View Schedule</span>
                        </a>
                        <a href="salary.php" class="quick-action-btn">
                            <i class="fas fa-money-bill"></i>
                            <span>Salary Info</span>
                        </a>
                        <a href="profile.php" class="quick-action-btn">
                            <i class="fas fa-user-edit"></i>
                            <span>Update Profile</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Information -->
        <div class="col-md-4">
            <!-- Attendance Summary -->
            <div class="material-card">
                <div class="card-header">
                    <h5 class="mb-0">Monthly Attendance</h5>
                </div>
                <div class="card-body">
                    <div class="attendance-summary">
                        <div class="attendance-rate">
                            <div class="rate-circle">
                                <span class="rate-number"><?php echo $monthlyAttendanceRate; ?>%</span>
                                <div class="rate-label">Attendance Rate</div>
                            </div>
                        </div>
                        
                        <div class="attendance-breakdown">
                            <div class="breakdown-item">
                                <div class="breakdown-color bg-success"></div>
                                <span>Present: <?php echo $monthlyStats['monthly_present']; ?></span>
                            </div>
                            <div class="breakdown-item">
                                <div class="breakdown-color bg-danger"></div>
                                <span>Absent: <?php echo $monthlyStats['monthly_absent']; ?></span>
                            </div>
                            <div class="breakdown-item">
                                <div class="breakdown-color bg-warning"></div>
                                <span>Late: <?php echo $monthlyStats['monthly_late']; ?></span>
                            </div>
                        </div>
                        
                        <a href="attendance.php" class="btn btn-outline btn-sm btn-block mt-3">
                            View Detailed Attendance
                        </a>
                    </div>
                </div>
            </div>

            <!-- Upcoming Classes -->
            <?php if (!empty($upcomingClasses)): ?>
                <div class="material-card">
                    <div class="card-header">
                        <h5 class="mb-0">Upcoming Classes</h5>
                    </div>
                    <div class="card-body">
                        <div class="upcoming-classes">
                            <?php foreach ($upcomingClasses as $class): ?>
                                <div class="upcoming-item">
                                    <div class="upcoming-day"><?php echo ucfirst($class['day_of_week']); ?></div>
                                    <div class="upcoming-details">
                                        <div class="upcoming-subject"><?php echo htmlspecialchars($class['subject_code']); ?></div>
                                        <div class="upcoming-time">
                                            <?php echo date('g:i A', strtotime($class['start_time'])); ?>
                                        </div>
                                        <div class="upcoming-room"><?php echo htmlspecialchars($class['classroom_name']); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Announcements -->
            <?php if (!empty($announcements)): ?>
                <div class="material-card">
                    <div class="card-header">
                        <h5 class="mb-0">Announcements</h5>
                    </div>
                    <div class="card-body">
                        <div class="announcements">
                            <?php foreach ($announcements as $announcement): ?>
                                <div class="announcement-item announcement-<?php echo $announcement['type']; ?>">
                                    <div class="announcement-title"><?php echo htmlspecialchars($announcement['title']); ?></div>
                                    <div class="announcement-message"><?php echo htmlspecialchars($announcement['message']); ?></div>
                                    <div class="announcement-date"><?php echo formatDate($announcement['date'], 'M j, Y'); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.welcome-section {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: white;
    padding: 24px;
    border-radius: 12px;
    margin-bottom: 24px;
}

.dashboard-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 24px;
    border-radius: 12px;
    box-shadow: var(--shadow);
    position: relative;
    overflow: hidden;
}

.stat-card.primary { border-left: 4px solid var(--primary-color); }
.stat-card.success { border-left: 4px solid var(--success-color); }
.stat-card.info { border-left: 4px solid var(--info-color); }
.stat-card.warning { border-left: 4px solid var(--warning-color); }

.stat-number {
    font-size: 32px;
    font-weight: bold;
    color: var(--primary-color);
    margin-bottom: 8px;
}

.stat-label {
    color: var(--text-color);
    font-weight: 500;
    margin-bottom: 4px;
}

.stat-sublabel {
    color: var(--text-muted);
    font-size: 14px;
}

.stat-icon {
    position: absolute;
    right: 20px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 48px;
    color: rgba(0,0,0,0.1);
}

.schedule-item {
    display: flex;
    padding: 16px 0;
    border-bottom: 1px solid #f0f0f0;
}

.schedule-item:last-child {
    border-bottom: none;
}

.schedule-time {
    width: 80px;
    text-align: center;
    margin-right: 20px;
}

.time-start {
    font-weight: bold;
    color: var(--primary-color);
}

.time-end {
    font-size: 12px;
    color: var(--text-muted);
}

.schedule-content {
    flex: 1;
}

.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 15px;
}

.quick-action-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 20px 15px;
    background: #f8f9fa;
    border-radius: 8px;
    text-decoration: none;
    color: var(--text-color);
    transition: all 0.3s ease;
}

.quick-action-btn:hover {
    background: var(--primary-color);
    color: white;
    transform: translateY(-2px);
}

.quick-action-btn i {
    font-size: 24px;
    margin-bottom: 8px;
}

.attendance-summary {
    text-align: center;
}

.rate-circle {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: conic-gradient(var(--success-color) <?php echo $monthlyAttendanceRate * 3.6; ?>deg, #f0f0f0 0deg);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    position: relative;
}

.rate-circle::before {
    content: '';
    position: absolute;
    width: 80px;
    height: 80px;
    background: white;
    border-radius: 50%;
}

.rate-number {
    font-size: 24px;
    font-weight: bold;
    color: var(--primary-color);
    z-index: 1;
}

.rate-label {
    font-size: 12px;
    color: var(--text-muted);
    z-index: 1;
}

.attendance-breakdown {
    text-align: left;
    margin-bottom: 15px;
}

.breakdown-item {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
}

.breakdown-color {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    margin-right: 8px;
}

.upcoming-item {
    display: flex;
    padding: 12px 0;
    border-bottom: 1px solid #f0f0f0;
}

.upcoming-item:last-child {
    border-bottom: none;
}

.upcoming-day {
    width: 60px;
    font-weight: bold;
    color: var(--primary-color);
    text-transform: uppercase;
    font-size: 12px;
}

.upcoming-details {
    flex: 1;
}

.upcoming-subject {
    font-weight: bold;
    margin-bottom: 2px;
}

.upcoming-time,
.upcoming-room {
    font-size: 12px;
    color: var(--text-muted);
}

.announcement-item {
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 12px;
    border-left: 4px solid;
}

.announcement-item.announcement-info {
    background: rgba(33, 150, 243, 0.1);
    border-left-color: var(--info-color);
}

.announcement-item.announcement-success {
    background: rgba(76, 175, 80, 0.1);
    border-left-color: var(--success-color);
}

.announcement-title {
    font-weight: bold;
    margin-bottom: 4px;
}

.announcement-message {
    font-size: 14px;
    margin-bottom: 8px;
}

.announcement-date {
    font-size: 12px;
    color: var(--text-muted);
}

@media (max-width: 768px) {
    .welcome-section {
        text-align: center;
    }
    
    .welcome-section .d-flex {
        flex-direction: column;
        gap: 15px;
    }
    
    .schedule-item {
        flex-direction: column;
        gap: 10px;
    }
    
    .schedule-time {
        width: auto;
        text-align: left;
        margin-right: 0;
    }
    
    .quick-actions {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<?php require_once '../../components/footer.php'; ?>