<?php
$pageTitle = 'My Attendance';
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../components/header.php';
require_once '../../components/sidebar.php';
require_once '../../config/database.php';
require_once '../../includes/security.php';
require_once '../../includes/functions.php';

$auth = new Auth();
$auth->requireRole('teacher');

$db = new Database();
$conn = $db->getConnection();

// Get teacher ID
$query = "SELECT id FROM teachers WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$teacherId = $stmt->fetchColumn();

$message = '';
$messageType = '';

// Handle attendance marking
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'mark_attendance') {
            $scheduleId = (int)$_POST['schedule_id'];
            $status = $_POST['status'] ?? 'present';
            $notes = Security::sanitizeInput($_POST['notes'] ?? '');
            
            try {
                // Check if attendance already marked for today
                $checkQuery = "SELECT id FROM teacher_attendance WHERE teacher_id = ? AND schedule_id = ? AND DATE(date) = CURDATE()";
                $checkStmt = $conn->prepare($checkQuery);
                $checkStmt->execute([$teacherId, $scheduleId]);
                
                if ($checkStmt->rowCount() > 0) {
                    // Update existing record
                    $query = "UPDATE teacher_attendance SET status = ?, notes = ?, updated_at = NOW() WHERE teacher_id = ? AND schedule_id = ? AND DATE(date) = CURDATE()";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$status, $notes, $teacherId, $scheduleId]);
                } else {
                    // Insert new record
                    $checkInTime = $status === 'present' ? date('Y-m-d H:i:s') : null;
                    $query = "INSERT INTO teacher_attendance (teacher_id, schedule_id, date, check_in_time, status, notes) VALUES (?, ?, CURDATE(), ?, ?, ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$teacherId, $scheduleId, $checkInTime, $status, $notes]);
                }
                
                $message = 'Attendance marked successfully!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error marking attendance';
                $messageType = 'danger';
            }
        }
    }
}

// Get current month and year
$currentMonth = (int)($_GET['month'] ?? date('n'));
$currentYear = (int)($_GET['year'] ?? date('Y'));

// Get attendance data for the month
$query = "SELECT 
            ta.*,
            cs.day_of_week,
            cs.start_time,
            cs.end_time,
            s.name as subject_name,
            s.code as subject_code,
            c.name as classroom_name
          FROM teacher_attendance ta
          LEFT JOIN class_schedule cs ON ta.schedule_id = cs.id
          LEFT JOIN subjects s ON cs.subject_id = s.id
          LEFT JOIN classrooms c ON cs.classroom_id = c.id
          WHERE ta.teacher_id = ? AND MONTH(ta.date) = ? AND YEAR(ta.date) = ?
          ORDER BY ta.date DESC, cs.start_time";

$stmt = $conn->prepare($query);
$stmt->execute([$teacherId, $currentMonth, $currentYear]);
$attendanceRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get attendance statistics for the month
$query = "SELECT 
            COUNT(CASE WHEN status = 'present' THEN 1 END) as present_days,
            COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_days,
            COUNT(CASE WHEN status = 'late' THEN 1 END) as late_days,
            COUNT(*) as total_days
          FROM teacher_attendance 
          WHERE teacher_id = ? AND MONTH(date) = ? AND YEAR(date) = ?";

$statsStmt = $conn->prepare($query);
$statsStmt->execute([$teacherId, $currentMonth, $currentYear]);
$monthlyStats = $statsStmt->fetch(PDO::FETCH_ASSOC);

$attendancePercentage = $monthlyStats['total_days'] > 0 ? 
    round(($monthlyStats['present_days'] / $monthlyStats['total_days']) * 100, 1) : 0;

// Get today's classes that need attendance marking
$todayDay = strtolower(date('l'));
$query = "SELECT 
            cs.*,
            s.name as subject_name,
            s.code as subject_code,
            c.name as classroom_name,
            ta.status as attendance_status,
            ta.check_in_time,
            ta.notes
          FROM class_schedule cs
          LEFT JOIN subjects s ON cs.subject_id = s.id
          LEFT JOIN classrooms c ON cs.classroom_id = c.id
          LEFT JOIN teacher_attendance ta ON cs.id = ta.schedule_id AND DATE(ta.date) = CURDATE()
          WHERE cs.teacher_id = ? AND cs.day_of_week = ? AND cs.is_active = 1
          ORDER BY cs.start_time";

$todayStmt = $conn->prepare($query);
$todayStmt->execute([$teacherId, $todayDay]);
$todayClasses = $todayStmt->fetchAll(PDO::FETCH_ASSOC);

// Generate calendar data
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear);
$firstDayOfMonth = date('w', mktime(0, 0, 0, $currentMonth, 1, $currentYear));
$monthName = date('F Y', mktime(0, 0, 0, $currentMonth, 1, $currentYear));

// Organize attendance by date
$attendanceByDate = [];
foreach ($attendanceRecords as $record) {
    $date = date('j', strtotime($record['date']));
    if (!isset($attendanceByDate[$date])) {
        $attendanceByDate[$date] = [];
    }
    $attendanceByDate[$date][] = $record;
}
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>My Attendance</h2>
        <div class="d-flex align-items-center">
            <!-- Month Navigation -->
            <div class="month-navigation mr-3">
                <a href="?month=<?php echo $currentMonth == 1 ? 12 : $currentMonth - 1; ?>&year=<?php echo $currentMonth == 1 ? $currentYear - 1 : $currentYear; ?>" class="btn btn-outline">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <span class="mx-3 font-weight-bold"><?php echo $monthName; ?></span>
                <a href="?month=<?php echo $currentMonth == 12 ? 1 : $currentMonth + 1; ?>&year=<?php echo $currentMonth == 12 ? $currentYear + 1 : $currentYear; ?>" class="btn btn-outline">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <!-- Monthly Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card success">
                <div class="stat-number"><?php echo $monthlyStats['present_days']; ?></div>
                <div class="stat-label">Present Days</div>
                <i class="stat-icon fas fa-check"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card danger">
                <div class="stat-number"><?php echo $monthlyStats['absent_days']; ?></div>
                <div class="stat-label">Absent Days</div>
                <i class="stat-icon fas fa-times"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card warning">
                <div class="stat-number"><?php echo $monthlyStats['late_days']; ?></div>
                <div class="stat-label">Late Days</div>
                <i class="stat-icon fas fa-clock"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card info">
                <div class="stat-number"><?php echo $attendancePercentage; ?>%</div>
                <div class="stat-label">Attendance Rate</div>
                <i class="stat-icon fas fa-chart-line"></i>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Today's Classes -->
        <div class="col-md-4">
            <div class="material-card">
                <div class="card-header">
                    <h5 class="mb-0">Today's Classes</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($todayClasses)): ?>
                        <p class="text-muted text-center">No classes scheduled for today</p>
                    <?php else: ?>
                        <?php foreach ($todayClasses as $class): ?>
                            <div class="today-class mb-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="font-weight-bold"><?php echo htmlspecialchars($class['subject_name']); ?></div>
                                        <div class="text-muted small">
                                            <i class="fas fa-clock"></i>
                                            <?php echo date('g:i A', strtotime($class['start_time'])); ?> - 
                                            <?php echo date('g:i A', strtotime($class['end_time'])); ?>
                                        </div>
                                        <div class="text-muted small">
                                            <i class="fas fa-door-open"></i>
                                            <?php echo htmlspecialchars($class['classroom_name']); ?>
                                        </div>
                                        <?php if ($class['attendance_status']): ?>
                                            <div class="mt-2">
                                                <?php echo getStatusBadge($class['attendance_status']); ?>
                                                <?php if ($class['check_in_time']): ?>
                                                    <small class="text-muted d-block">
                                                        Checked in: <?php echo date('g:i A', strtotime($class['check_in_time'])); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <?php if (!$class['attendance_status']): ?>
                                            <button class="btn btn-sm btn-success" onclick="markAttendance(<?php echo $class['id']; ?>, 'present')">
                                                <i class="fas fa-check"></i> Present
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline" onclick="markAttendance(<?php echo $class['id']; ?>, '<?php echo $class['attendance_status']; ?>')">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php if ($class !== end($todayClasses)): ?>
                                <hr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Attendance Calendar -->
        <div class="col-md-8">
            <div class="material-card">
                <div class="card-header">
                    <h5 class="mb-0">Attendance Calendar - <?php echo $monthName; ?></h5>
                </div>
                <div class="card-body">
                    <div class="attendance-calendar">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Sun</th>
                                    <th>Mon</th>
                                    <th>Tue</th>
                                    <th>Wed</th>
                                    <th>Thu</th>
                                    <th>Fri</th>
                                    <th>Sat</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $dayCount = 1;
                                $totalCells = ceil(($daysInMonth + $firstDayOfMonth) / 7) * 7;
                                
                                for ($i = 0; $i < $totalCells; $i += 7) {
                                    echo '<tr>';
                                    for ($j = 0; $j < 7; $j++) {
                                        $cellIndex = $i + $j;
                                        
                                        if ($cellIndex < $firstDayOfMonth || $dayCount > $daysInMonth) {
                                            echo '<td class="calendar-cell empty"></td>';
                                        } else {
                                            $hasAttendance = isset($attendanceByDate[$dayCount]);
                                            $attendanceClass = '';
                                            $attendanceCount = 0;
                                            $presentCount = 0;
                                            
                                            if ($hasAttendance) {
                                                $attendanceCount = count($attendanceByDate[$dayCount]);
                                                $presentCount = count(array_filter($attendanceByDate[$dayCount], function($a) {
                                                    return $a['status'] === 'present';
                                                }));
                                                
                                                if ($presentCount === $attendanceCount) {
                                                    $attendanceClass = 'all-present';
                                                } elseif ($presentCount > 0) {
                                                    $attendanceClass = 'partial-present';
                                                } else {
                                                    $attendanceClass = 'all-absent';
                                                }
                                            }
                                            
                                            $isToday = ($dayCount == date('j') && $currentMonth == date('n') && $currentYear == date('Y'));
                                            $todayClass = $isToday ? 'today' : '';
                                            
                                            echo '<td class="calendar-cell ' . $attendanceClass . ' ' . $todayClass . '">';
                                            echo '<div class="calendar-date">' . $dayCount . '</div>';
                                            
                                            if ($hasAttendance) {
                                                echo '<div class="attendance-summary">';
                                                echo '<small>' . $presentCount . '/' . $attendanceCount . '</small>';
                                                echo '</div>';
                                            }
                                            
                                            echo '</td>';
                                            $dayCount++;
                                        }
                                    }
                                    echo '</tr>';
                                    
                                    if ($dayCount > $daysInMonth) break;
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Legend -->
                    <div class="calendar-legend mt-3">
                        <div class="d-flex justify-content-center gap-3">
                            <div class="legend-item">
                                <div class="legend-color all-present"></div>
                                <span>All Present</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color partial-present"></div>
                                <span>Partial</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color all-absent"></div>
                                <span>Absent</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color today"></div>
                                <span>Today</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Attendance Records -->
    <div class="material-card">
        <div class="card-header">
            <h5 class="mb-0">Recent Attendance Records</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Day</th>
                            <th>Subject</th>
                            <th>Time</th>
                            <th>Classroom</th>
                            <th>Check In</th>
                            <th>Status</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($attendanceRecords, 0, 15) as $record): ?>
                            <tr>
                                <td><?php echo formatDate($record['date'], 'M j, Y'); ?></td>
                                <td><?php echo ucfirst($record['day_of_week']); ?></td>
                                <td>
                                    <span class="badge badge-primary"><?php echo htmlspecialchars($record['subject_code']); ?></span>
                                    <?php echo htmlspecialchars($record['subject_name']); ?>
                                </td>
                                <td>
                                    <?php echo date('g:i A', strtotime($record['start_time'])); ?> - 
                                    <?php echo date('g:i A', strtotime($record['end_time'])); ?>
                                </td>
                                <td><?php echo htmlspecialchars($record['classroom_name']); ?></td>
                                <td>
                                    <?php echo $record['check_in_time'] ? date('g:i A', strtotime($record['check_in_time'])) : '-'; ?>
                                </td>
                                <td><?php echo getStatusBadge($record['status']); ?></td>
                                <td><?php echo htmlspecialchars($record['notes'] ?: '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Mark Attendance Modal -->
<div class="modal" id="attendanceModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h5 class="modal-title">Mark Attendance</h5>
            <button type="button" class="modal-close" data-dismiss="modal">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="mark_attendance">
                <input type="hidden" name="schedule_id" id="modalScheduleId">
                
                <div class="form-group">
                    <label class="form-label">Status *</label>
                    <select name="status" class="form-control" required>
                        <option value="present">Present</option>
                        <option value="absent">Absent</option>
                        <option value="late">Late</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Add any notes..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Mark Attendance</button>
            </div>
        </form>
    </div>
</div>

<style>
.stat-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: var(--shadow);
    position: relative;
    margin-bottom: 20px;
}

.stat-card.success { border-left: 4px solid var(--success-color); }
.stat-card.danger { border-left: 4px solid var(--danger-color); }
.stat-card.warning { border-left: 4px solid var(--warning-color); }
.stat-card.info { border-left: 4px solid var(--info-color); }

.stat-number {
    font-size: 28px;
    font-weight: bold;
    color: var(--primary-color);
}

.stat-label {
    color: var(--text-muted);
    margin-top: 5px;
}

.stat-icon {
    position: absolute;
    right: 20px;
    top: 20px;
    font-size: 32px;
    color: rgba(0,0,0,0.1);
}

.today-class {
    border-left: 3px solid var(--primary-color);
    padding-left: 12px;
}

.attendance-calendar .table {
    margin-bottom: 0;
}

.calendar-cell {
    width: 14.28%;
    height: 80px;
    vertical-align: top;
    position: relative;
    border: 1px solid #e0e0e0;
}

.calendar-cell.empty {
    background: #f8f9fa;
}

.calendar-cell.today {
    background: rgba(25, 118, 210, 0.1);
    border-color: var(--primary-color);
}

.calendar-cell.all-present {
    background: rgba(76, 175, 80, 0.1);
}

.calendar-cell.partial-present {
    background: rgba(255, 152, 0, 0.1);
}

.calendar-cell.all-absent {
    background: rgba(244, 67, 54, 0.1);
}

.calendar-date {
    font-weight: bold;
    padding: 5px;
}

.attendance-summary {
    position: absolute;
    bottom: 5px;
    right: 5px;
    font-size: 10px;
}

.calendar-legend {
    display: flex;
    justify-content: center;
    gap: 20px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 5px;
}

.legend-color {
    width: 20px;
    height: 20px;
    border-radius: 4px;
    border: 1px solid #e0e0e0;
}

.legend-color.all-present { background: rgba(76, 175, 80, 0.3); }
.legend-color.partial-present { background: rgba(255, 152, 0, 0.3); }
.legend-color.all-absent { background: rgba(244, 67, 54, 0.3); }
.legend-color.today { background: rgba(25, 118, 210, 0.3); }

.gap-3 { gap: 1rem; }
</style>

<script>
function markAttendance(scheduleId, currentStatus) {
    document.getElementById('modalScheduleId').value = scheduleId;
    
    if (currentStatus && currentStatus !== 'undefined') {
        document.querySelector('#attendanceModal select[name="status"]').value = currentStatus;
    }
    
    showModal('attendanceModal');
}
</script>

<?php require_once '../../components/footer.php'; ?>