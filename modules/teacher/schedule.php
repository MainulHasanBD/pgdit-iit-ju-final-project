<?php
$pageTitle = 'My Schedule';
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../components/header.php';
require_once '../../components/sidebar.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->requireRole('teacher');

$db = new Database();
$conn = $db->getConnection();

// Get teacher ID from session
$query = "SELECT id FROM teachers WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$teacherId = $stmt->fetchColumn();

if (!$teacherId) {
    header('Location: profile.php?setup=1');
    exit();
}

// Get current week's date range
$currentWeek = $_GET['week'] ?? date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('monday this week', strtotime($currentWeek)));
$weekEnd = date('Y-m-d', strtotime('sunday this week', strtotime($currentWeek)));

// Get teacher's schedule for the week
$query = "SELECT 
            cs.*,
            s.name as subject_name,
            s.code as subject_code,
            c.name as classroom_name,
            c.capacity as classroom_capacity
          FROM class_schedule cs
          LEFT JOIN subjects s ON cs.subject_id = s.id
          LEFT JOIN classrooms c ON cs.classroom_id = c.id
          WHERE cs.teacher_id = ? AND cs.is_active = 1
          ORDER BY 
            FIELD(cs.day_of_week, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'),
            cs.start_time";

$stmt = $conn->prepare($query);
$stmt->execute([$teacherId]);
$scheduleData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize schedule by day and time
$weekSchedule = [];
$days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
$timeSlots = [];

// Generate time slots from 8 AM to 6 PM
for ($hour = 8; $hour <= 18; $hour++) {
    $timeSlots[] = sprintf('%02d:00', $hour);
}

foreach ($days as $day) {
    $weekSchedule[$day] = [];
    foreach ($timeSlots as $time) {
        $weekSchedule[$day][$time] = null;
    }
}

// Populate schedule
foreach ($scheduleData as $class) {
    $day = $class['day_of_week'];
    $startHour = (int)date('H', strtotime($class['start_time']));
    $timeKey = sprintf('%02d:00', $startHour);
    
    if (isset($weekSchedule[$day][$timeKey])) {
        $weekSchedule[$day][$timeKey] = $class;
    }
}

// Get total weekly hours
$totalHours = 0;
foreach ($scheduleData as $class) {
    $start = strtotime($class['start_time']);
    $end = strtotime($class['end_time']);
    $totalHours += ($end - $start) / 3600;
}

// Get next 5 upcoming classes
$query = "SELECT 
            cs.*,
            s.name as subject_name,
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
          LIMIT 5";

$stmt = $conn->prepare($query);
$stmt->execute([$teacherId]);
$upcomingClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>My Teaching Schedule</h2>
        <div class="d-flex align-items-center">
            <div class="week-navigation mr-3">
                <a href="?week=<?php echo date('Y-m-d', strtotime($weekStart . ' -1 week')); ?>" class="btn btn-outline">
                    <i class="fas fa-chevron-left"></i> Previous Week
                </a>
                <span class="mx-3 font-weight-bold">
                    <?php echo date('M j', strtotime($weekStart)); ?> - <?php echo date('M j, Y', strtotime($weekEnd)); ?>
                </span>
                <a href="?week=<?php echo date('Y-m-d', strtotime($weekStart . ' +1 week')); ?>" class="btn btn-outline">
                    Next Week <i class="fas fa-chevron-right"></i>
                </a>
            </div>
            <a href="?week=<?php echo date('Y-m-d'); ?>" class="btn btn-primary">Today</a>
        </div>
    </div>

    <!-- Schedule Overview -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($scheduleData); ?></div>
                <div class="stat-label">Weekly Classes</div>
                <i class="stat-icon fas fa-chalkboard"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card info">
                <div class="stat-number"><?php echo number_format($totalHours, 1); ?></div>
                <div class="stat-label">Weekly Hours</div>
                <i class="stat-icon fas fa-clock"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card success">
                <div class="stat-number"><?php echo count(array_unique(array_column($scheduleData, 'subject_id'))); ?></div>
                <div class="stat-label">Subjects</div>
                <i class="stat-icon fas fa-book"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card warning">
                <div class="stat-number"><?php echo count(array_unique(array_column($scheduleData, 'classroom_id'))); ?></div>
                <div class="stat-label">Classrooms</div>
                <i class="stat-icon fas fa-door-open"></i>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Weekly Schedule Grid -->
        <div class="col-md-9">
            <div class="material-card">
                <div class="card-header">
                    <h5 class="mb-0">Weekly Schedule</h5>
                </div>
                <div class="card-body">
                    <div class="schedule-grid">
                        <div class="table-responsive">
                            <table class="table table-bordered schedule-table">
                                <thead>
                                    <tr>
                                        <th style="width: 100px;">Time</th>
                                        <?php foreach ($days as $day): ?>
                                            <th class="text-center day-header">
                                                <div><?php echo ucfirst($day); ?></div>
                                                <small class="text-muted">
                                                    <?php 
                                                    $dayDate = date('M j', strtotime($day . ' this week', strtotime($weekStart)));
                                                    echo $dayDate;
                                                    ?>
                                                </small>
                                            </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($timeSlots as $time): ?>
                                        <tr>
                                            <td class="time-slot">
                                                <?php echo date('g:i A', strtotime($time)); ?>
                                            </td>
                                            <?php foreach ($days as $day): ?>
                                                <td class="schedule-cell">
                                                    <?php if ($weekSchedule[$day][$time]): ?>
                                                        <?php $class = $weekSchedule[$day][$time]; ?>
                                                        <div class="class-block">
                                                            <div class="class-subject"><?php echo htmlspecialchars($class['subject_code']); ?></div>
                                                            <div class="class-room"><?php echo htmlspecialchars($class['classroom_name']); ?></div>
                                                            <div class="class-time">
                                                                <?php echo date('g:i', strtotime($class['start_time'])); ?> - 
                                                                <?php echo date('g:i A', strtotime($class['end_time'])); ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upcoming Classes -->
        <div class="col-md-3">
            <div class="material-card">
                <div class="card-header">
                    <h5 class="mb-0">Upcoming Classes</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($upcomingClasses)): ?>
                        <p class="text-muted text-center">No upcoming classes</p>
                    <?php else: ?>
                        <?php foreach ($upcomingClasses as $class): ?>
                            <div class="upcoming-class mb-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="font-weight-bold text-primary"><?php echo htmlspecialchars($class['subject_name']); ?></div>
                                        <div class="text-muted small">
                                            <i class="fas fa-calendar"></i>
                                            <?php echo ucfirst($class['day_of_week']); ?>
                                        </div>
                                        <div class="text-muted small">
                                            <i class="fas fa-clock"></i>
                                            <?php echo date('g:i A', strtotime($class['start_time'])); ?>
                                        </div>
                                        <div class="text-muted small">
                                            <i class="fas fa-door-open"></i>
                                            <?php echo htmlspecialchars($class['classroom_name']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php if ($class !== end($upcomingClasses)): ?>
                                <hr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="material-card">
                <div class="card-header">
                    <h5 class="mb-0">This Week</h5>
                </div>
                <div class="card-body">
                    <div class="stat-item mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Total Classes:</span>
                            <strong><?php echo count($scheduleData); ?></strong>
                        </div>
                    </div>
                    <div class="stat-item mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Teaching Hours:</span>
                            <strong><?php echo number_format($totalHours, 1); ?>h</strong>
                        </div>
                    </div>
                    <div class="stat-item mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Subjects:</span>
                            <strong><?php echo count(array_unique(array_column($scheduleData, 'subject_id'))); ?></strong>
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="d-flex justify-content-between">
                            <span>Classrooms:</span>
                            <strong><?php echo count(array_unique(array_column($scheduleData, 'classroom_id'))); ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.schedule-table {
    min-width: 800px;
}

.schedule-table th,
.schedule-table td {
    border: 1px solid #e0e0e0;
    vertical-align: top;
}

.time-slot {
    background: #f8f9fa;
    font-weight: 500;
    text-align: center;
    width: 100px;
}

.day-header {
    background: #f8f9fa;
    font-weight: 500;
}

.schedule-cell {
    height: 80px;
    width: 120px;
    position: relative;
    padding: 4px;
}

.class-block {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: white;
    border-radius: 6px;
    padding: 8px;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: center;
    text-align: center;
}

.class-subject {
    font-weight: bold;
    font-size: 12px;
    margin-bottom: 2px;
}

.class-room {
    font-size: 10px;
    opacity: 0.9;
    margin-bottom: 2px;
}

.class-time {
    font-size: 9px;
    opacity: 0.8;
}

.upcoming-class {
    border-left: 3px solid var(--primary-color);
    padding-left: 12px;
}

.week-navigation {
    display: flex;
    align-items: center;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: var(--shadow);
    position: relative;
    margin-bottom: 20px;
}

.stat-card.info { border-left: 4px solid var(--info-color); }
.stat-card.success { border-left: 4px solid var(--success-color); }
.stat-card.warning { border-left: 4px solid var(--warning-color); }

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

.stat-item {
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f0;
}

.stat-item:last-child {
    border-bottom: none;
}

@media (max-width: 768px) {
    .week-navigation {
        flex-direction: column;
        gap: 10px;
    }
    
    .schedule-cell {
        width: 80px;
        height: 60px;
    }
    
    .class-block {
        font-size: 10px;
    }
}
</style>

<?php require_once '../../components/footer.php'; ?>