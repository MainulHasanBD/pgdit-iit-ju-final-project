<?php
$pageTitle = 'Class Schedule Management';
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../components/header.php';
require_once '../../components/sidebar.php';
require_once '../../includes/security.php';
require_once '../../includes/functions.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = new Database();
$conn = $db->getConnection();

$message = '';
$messageType = '';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create_schedule':
                $subjectId = (int)$_POST['subject_id'];
                $teacherId = (int)$_POST['teacher_id'];
                $classroomId = (int)$_POST['classroom_id'];
                $dayOfWeek = $_POST['day_of_week'];
                $startTime = $_POST['start_time'];
                $endTime = $_POST['end_time'];
                
                // Validate time conflict
                $conflictQuery = "SELECT COUNT(*) FROM class_schedule 
                                  WHERE ((teacher_id = ? AND day_of_week = ? AND 
                                         ((start_time <= ? AND end_time > ?) OR 
                                          (start_time < ? AND end_time >= ?) OR 
                                          (start_time >= ? AND end_time <= ?))) OR
                                         (classroom_id = ? AND day_of_week = ? AND 
                                         ((start_time <= ? AND end_time > ?) OR 
                                          (start_time < ? AND end_time >= ?) OR 
                                          (start_time >= ? AND end_time <= ?))))
                                  AND is_active = 1";
                
                $conflictStmt = $conn->prepare($conflictQuery);
                $conflictStmt->execute([
                    $teacherId, $dayOfWeek, $startTime, $startTime, $endTime, $endTime, $startTime, $endTime,
                    $classroomId, $dayOfWeek, $startTime, $startTime, $endTime, $endTime, $startTime, $endTime
                ]);
                
                if ($conflictStmt->fetchColumn() > 0) {
                    $message = 'Conflict detected! Teacher or classroom is already scheduled at this time.';
                    $messageType = 'danger';
                } else {
                    try {
                        $query = "INSERT INTO class_schedule (subject_id, teacher_id, classroom_id, day_of_week, start_time, end_time, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($query);
                        $stmt->execute([$subjectId, $teacherId, $classroomId, $dayOfWeek, $startTime, $endTime, $_SESSION['user_id']]);
                        
                        $message = 'Class schedule created successfully!';
                        $messageType = 'success';
                    } catch (PDOException $e) {
                        $message = 'Error creating schedule: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'update_schedule':
                $id = (int)$_POST['id'];
                $subjectId = (int)$_POST['subject_id'];
                $teacherId = (int)$_POST['teacher_id'];
                $classroomId = (int)$_POST['classroom_id'];
                $dayOfWeek = $_POST['day_of_week'];
                $startTime = $_POST['start_time'];
                $endTime = $_POST['end_time'];
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                try {
                    $query = "UPDATE class_schedule SET subject_id = ?, teacher_id = ?, classroom_id = ?, day_of_week = ?, start_time = ?, end_time = ?, is_active = ? WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$subjectId, $teacherId, $classroomId, $dayOfWeek, $startTime, $endTime, $isActive, $id]);
                    
                    $message = 'Schedule updated successfully!';
                    $messageType = 'success';
                } catch (PDOException $e) {
                    $message = 'Error updating schedule: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
                
            case 'delete_schedule':
                $id = (int)$_POST['id'];
                try {
                    $query = "DELETE FROM class_schedule WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$id]);
                    
                    $message = 'Schedule deleted successfully!';
                    $messageType = 'success';
                } catch (PDOException $e) {
                    $message = 'Error deleting schedule: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
        }
    }
}

// Get current week's date range
$currentWeek = $_GET['week'] ?? date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('monday this week', strtotime($currentWeek)));
$weekEnd = date('Y-m-d', strtotime('sunday this week', strtotime($currentWeek)));

// Get schedule data for the week
$query = "SELECT 
            cs.*,
            s.name as subject_name,
            s.code as subject_code,
            CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
            c.name as classroom_name,
            c.capacity as classroom_capacity
          FROM class_schedule cs
          LEFT JOIN subjects s ON cs.subject_id = s.id
          LEFT JOIN teachers t ON cs.teacher_id = t.id
          LEFT JOIN classrooms c ON cs.classroom_id = c.id
          WHERE cs.is_active = 1
          ORDER BY 
            FIELD(cs.day_of_week, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'),
            cs.start_time";

$stmt = $conn->prepare($query);
$stmt->execute();
$scheduleData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get dropdown data
$subjectsQuery = "SELECT id, name, code FROM subjects ORDER BY name";
$subjectsStmt = $conn->prepare($subjectsQuery);
$subjectsStmt->execute();
$subjects = $subjectsStmt->fetchAll(PDO::FETCH_ASSOC);

$teachersQuery = "SELECT id, CONCAT(first_name, ' ', last_name) as name FROM teachers WHERE status = 'active' ORDER BY first_name, last_name";
$teachersStmt = $conn->prepare($teachersQuery);
$teachersStmt->execute();
$teachers = $teachersStmt->fetchAll(PDO::FETCH_ASSOC);

$classroomsQuery = "SELECT id, name, capacity FROM classrooms WHERE status = 'active' ORDER BY name";
$classroomsStmt = $conn->prepare($classroomsQuery);
$classroomsStmt->execute();
$classrooms = $classroomsStmt->fetchAll(PDO::FETCH_ASSOC);

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
        $weekSchedule[$day][$time] = [];
    }
}

// Populate schedule
foreach ($scheduleData as $class) {
    $day = $class['day_of_week'];
    $startHour = (int)date('H', strtotime($class['start_time']));
    $timeKey = sprintf('%02d:00', $startHour);
    
    if (isset($weekSchedule[$day][$timeKey])) {
        $weekSchedule[$day][$timeKey][] = $class;
    }
}

// Get edit schedule if ID provided
$editSchedule = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editQuery = "SELECT * FROM class_schedule WHERE id = ?";
    $editStmt = $conn->prepare($editQuery);
    $editStmt->execute([$editId]);
    $editSchedule = $editStmt->fetch(PDO::FETCH_ASSOC);
}
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Class Schedule Management</h2>
        <div>
            <button class="btn btn-primary" onclick="showModal('scheduleModal')">
                <i class="fas fa-plus"></i> Add Schedule
            </button>
            <button class="btn btn-info" onclick="showModal('bulkScheduleModal')">
                <i class="fas fa-calendar"></i> Bulk Schedule
            </button>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <!-- Schedule Overview -->
    <!-- <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($scheduleData); ?></div>
                <div class="stat-label">Total Classes</div>
                <i class="stat-icon fas fa-calendar-alt"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card success">
                <div class="stat-number"><?php echo count(array_unique(array_column($scheduleData, 'teacher_id'))); ?></div>
                <div class="stat-label">Active Teachers</div>
                <i class="stat-icon fas fa-chalkboard-teacher"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card info">
                <div class="stat-number"><?php echo count(array_unique(array_column($scheduleData, 'classroom_id'))); ?></div>
                <div class="stat-label">Used Classrooms</div>
                <i class="stat-icon fas fa-door-open"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card warning">
                <div class="stat-number"><?php echo count(array_unique(array_column($scheduleData, 'subject_id'))); ?></div>
                <div class="stat-label">Subjects</div>
                <i class="stat-icon fas fa-book"></i>
            </div>
        </div>
    </div> -->

    <!-- Weekly Schedule Grid -->
    <div class="material-card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Weekly Schedule</h5>
                <div class="week-navigation">
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
            </div>
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
                                            <?php foreach ($weekSchedule[$day][$time] as $class): ?>
                                                <div class="class-block" data-id="<?php echo $class['id']; ?>">
                                                    <div class="class-subject"><?php echo htmlspecialchars($class['subject_code']); ?></div>
                                                    <div class="class-teacher"><?php echo htmlspecialchars($class['teacher_name']); ?></div>
                                                    <div class="class-room"><?php echo htmlspecialchars($class['classroom_name']); ?></div>
                                                    <div class="class-time">
                                                        <?php echo date('g:i', strtotime($class['start_time'])); ?> - 
                                                        <?php echo date('g:i A', strtotime($class['end_time'])); ?>
                                                    </div>
                                                    <div class="class-actions">
                                                        <button class="btn btn-xs btn-warning" onclick="editSchedule(<?php echo $class['id']; ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-xs btn-danger" onclick="deleteSchedule(<?php echo $class['id']; ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
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

    <!-- Schedule List -->
    <div class="material-card">
        <div class="card-header">
            <h5 class="mb-0">All Schedules</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Teacher</th>
                            <th>Classroom</th>
                            <th>Day</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scheduleData as $schedule): ?>
                            <tr>
                                <td>
                                    <span class="badge badge-primary"><?php echo htmlspecialchars($schedule['subject_code']); ?></span>
                                    <?php echo htmlspecialchars($schedule['subject_name']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($schedule['teacher_name']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($schedule['classroom_name']); ?>
                                    <small class="text-muted">(Cap: <?php echo $schedule['classroom_capacity']; ?>)</small>
                                </td>
                                <td><?php echo ucfirst($schedule['day_of_week']); ?></td>
                                <td>
                                    <?php echo date('g:i A', strtotime($schedule['start_time'])); ?> - 
                                    <?php echo date('g:i A', strtotime($schedule['end_time'])); ?>
                                </td>
                                <td>
                                    <?php echo $schedule['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>'; ?>
                                </td>
                                <td class="table-actions">
                                    <button class="btn btn-sm btn-warning" onclick="editSchedule(<?php echo $schedule['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteSchedule(<?php echo $schedule['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Modal -->
<div class="modal" id="scheduleModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-header">
            <h5 class="modal-title"><?php echo $editSchedule ? 'Edit Schedule' : 'Add New Schedule'; ?></h5>
            <button type="button" class="modal-close" data-dismiss="modal">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="<?php echo $editSchedule ? 'update_schedule' : 'create_schedule'; ?>">
                <?php if ($editSchedule): ?>
                    <input type="hidden" name="id" value="<?php echo $editSchedule['id']; ?>">
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Subject *</label>
                            <select name="subject_id" class="form-control" required>
                                <option value="">Select Subject</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?php echo $subject['id']; ?>" <?php echo ($editSchedule['subject_id'] ?? '') == $subject['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($subject['code'] . ' - ' . $subject['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Teacher *</label>
                            <select name="teacher_id" class="form-control" required>
                                <option value="">Select Teacher</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>" <?php echo ($editSchedule['teacher_id'] ?? '') == $teacher['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($teacher['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Classroom *</label>
                            <select name="classroom_id" class="form-control" required>
                                <option value="">Select Classroom</option>
                                <?php foreach ($classrooms as $classroom): ?>
                                    <option value="<?php echo $classroom['id']; ?>" <?php echo ($editSchedule['classroom_id'] ?? '') == $classroom['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($classroom['name'] . ' (Capacity: ' . $classroom['capacity'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Day of Week *</label>
                            <select name="day_of_week" class="form-control" required>
                                <option value="">Select Day</option>
                                <option value="monday" <?php echo ($editSchedule['day_of_week'] ?? '') === 'monday' ? 'selected' : ''; ?>>Monday</option>
                                <option value="tuesday" <?php echo ($editSchedule['day_of_week'] ?? '') === 'tuesday' ? 'selected' : ''; ?>>Tuesday</option>
                                <option value="wednesday" <?php echo ($editSchedule['day_of_week'] ?? '') === 'wednesday' ? 'selected' : ''; ?>>Wednesday</option>
                                <option value="thursday" <?php echo ($editSchedule['day_of_week'] ?? '') === 'thursday' ? 'selected' : ''; ?>>Thursday</option>
                                <option value="friday" <?php echo ($editSchedule['day_of_week'] ?? '') === 'friday' ? 'selected' : ''; ?>>Friday</option>
                                <option value="saturday" <?php echo ($editSchedule['day_of_week'] ?? '') === 'saturday' ? 'selected' : ''; ?>>Saturday</option>
                                <option value="sunday" <?php echo ($editSchedule['day_of_week'] ?? '') === 'sunday' ? 'selected' : ''; ?>>Sunday</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Start Time *</label>
                            <input type="time" name="start_time" class="form-control" value="<?php echo htmlspecialchars($editSchedule['start_time'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">End Time *</label>
                            <input type="time" name="end_time" class="form-control" value="<?php echo htmlspecialchars($editSchedule['end_time'] ?? ''); ?>" required>
                        </div>
                    </div>
                </div>
                
                <?php if ($editSchedule): ?>
                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" id="is_active" class="form-check-input" <?php echo $editSchedule['is_active'] ? 'checked' : ''; ?>>
                            <label for="is_active" class="form-check-label">Active</label>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <?php echo $editSchedule ? 'Update Schedule' : 'Create Schedule'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.schedule-table {
    min-width: 1000px;
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
    height: 120px;
    width: 150px;
    position: relative;
    padding: 4px;
}

.class-block {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: white;
    border-radius: 6px;
    padding: 8px;
    margin-bottom: 4px;
    position: relative;
    cursor: pointer;
}

.class-subject {
    font-weight: bold;
    font-size: 12px;
    margin-bottom: 2px;
}

.class-teacher,
.class-room {
    font-size: 10px;
    opacity: 0.9;
    margin-bottom: 1px;
}

.class-time {
    font-size: 9px;
    opacity: 0.8;
    margin-bottom: 4px;
}

.class-actions {
    position: absolute;
    top: 2px;
    right: 2px;
}

.btn-xs {
    padding: 2px 6px;
    font-size: 10px;
    line-height: 1;
    border-radius: 3px;
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

.stat-card.success { border-left: 4px solid var(--success-color); }
.stat-card.info { border-left: 4px solid var(--info-color); }
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

.table-actions .btn {
    margin-right: 5px;
}

@media (max-width: 768px) {
    .schedule-cell {
        width: 120px;
        height: 100px;
    }
    
    .class-block {
        font-size: 10px;
        padding: 4px;
    }
    
    .week-navigation {
        flex-direction: column;
        gap: 10px;
    }
}
</style>

<script>
function editSchedule(id) {
    window.location.href = '?edit=' + id;
}

function deleteSchedule(id) {
    if (confirm('Are you sure you want to delete this schedule? This will affect attendance tracking.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="delete_schedule">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

<?php if ($editSchedule): ?>
    document.addEventListener('DOMContentLoaded', function() {
        showModal('scheduleModal');
    });
<?php endif; ?>
</script>

<?php require_once '../../components/footer.php'; ?>