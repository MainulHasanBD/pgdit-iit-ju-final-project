<?php
$pageTitle = 'Teachers Management';
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../components/header.php';
require_once '../../components/sidebar.php';
require_once '../../includes/security.php';
require_once '../../includes/functions.php';

$auth = new Auth();
$auth->requireRole('hr');

$db = new Database();
$conn = $db->getConnection();

$message = '';
$messageType = '';

// Handle teacher status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_status':
                $teacherId = (int)$_POST['teacher_id'];
                $status = $_POST['status'] ?? '';
                $notes = Security::sanitizeInput($_POST['notes'] ?? '');
                
                if (in_array($status, ['active', 'inactive'])) {
                    try {
                        $conn->beginTransaction();
                        
                        // Update teacher status
                        $query = "UPDATE teachers SET status = ? WHERE id = ?";
                        $stmt = $conn->prepare($query);
                        $stmt->execute([$status, $teacherId]);
                        
                        // Update user status if exists
                        $userQuery = "UPDATE users SET status = ? WHERE id = (SELECT user_id FROM teachers WHERE id = ?)";
                        $userStmt = $conn->prepare($userQuery);
                        $userStmt->execute([$status, $teacherId]);
                        
                        $conn->commit();
                        
                        $message = 'Teacher status updated successfully!';
                        $messageType = 'success';
                    } catch (PDOException $e) {
                        $conn->rollBack();
                        $message = 'Error updating teacher status';
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'send_welcome_email':
                $teacherId = (int)$_POST['teacher_id'];
                
                // Get teacher details
                $teacherQuery = "SELECT t.*, u.username FROM teachers t LEFT JOIN users u ON t.user_id = u.id WHERE t.id = ?";
                $teacherStmt = $conn->prepare($teacherQuery);
                $teacherStmt->execute([$teacherId]);
                $teacher = $teacherStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($teacher) {
                    $emailService = new EmailService();
                    $tempPassword = bin2hex(random_bytes(8));
                    
                    // Update password
                    $newHash = password_hash($tempPassword, PASSWORD_DEFAULT);
                    $updateQuery = "UPDATE users SET password = ? WHERE id = ?";
                    $updateStmt = $conn->prepare($updateQuery);
                    $updateStmt->execute([$newHash, $teacher['user_id']]);
                    
                    // Send email
                    $result = $emailService->sendWelcomeEmail($teacher['email'], $teacher['first_name'] . ' ' . $teacher['last_name'], $tempPassword);
                    
                    if ($result) {
                        $message = 'Welcome email sent successfully with new credentials!';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to send welcome email';
                        $messageType = 'danger';
                    }
                }
                break;
        }
    }
}

// Get teachers with pagination and search
$page = (int)($_GET['page'] ?? 1);
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$subjectFilter = $_GET['subject'] ?? '';

$whereConditions = [];
$params = [];

if ($search) {
    $whereConditions[] = "(CONCAT(t.first_name, ' ', t.last_name) LIKE ? OR t.employee_id LIKE ? OR t.email LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}

if ($statusFilter) {
    $whereConditions[] = "t.status = ?";
    $params[] = $statusFilter;
}

$whereClause = empty($whereConditions) ? '' : 'WHERE ' . implode(' AND ', $whereConditions);

$countQuery = "SELECT COUNT(*) FROM teachers t $whereClause";
$countStmt = $conn->prepare($countQuery);
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();

$pagination = Pagination::paginate($totalRecords, $page);
$offset = $pagination['offset'];

$query = "SELECT 
            t.*,
            u.username,
            u.last_login,
            (SELECT COUNT(*) FROM class_schedule cs WHERE cs.teacher_id = t.id AND cs.is_active = 1) as active_classes,
            (SELECT GROUP_CONCAT(DISTINCT s.code SEPARATOR ', ') 
             FROM class_schedule cs 
             LEFT JOIN subjects s ON cs.subject_id = s.id 
             WHERE cs.teacher_id = t.id AND cs.is_active = 1) as teaching_subjects
          FROM teachers t
          LEFT JOIN users u ON t.user_id = u.id
          $whereClause
          ORDER BY t.first_name, t.last_name
          LIMIT $offset, " . RECORDS_PER_PAGE;

$stmt = $conn->prepare($query);
$stmt->execute($params);
$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get HR statistics
$statsQuery = "SELECT 
                 COUNT(*) as total_teachers,
                 COUNT(CASE WHEN status = 'active' THEN 1 END) as active_teachers,
                 COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_teachers,
                 COUNT(CASE WHEN hire_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as new_hires
               FROM teachers";
$statsStmt = $conn->prepare($statsQuery);
$statsStmt->execute();
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Teachers Management</h2>
        <div>
            <a href="../common/reports.php?type=teachers" class="btn btn-info">
                <i class="fas fa-download"></i> Export Report
            </a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_teachers']; ?></div>
                <div class="stat-label">Total Teachers</div>
                <i class="stat-icon fas fa-chalkboard-teacher"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card success">
                <div class="stat-number"><?php echo $stats['active_teachers']; ?></div>
                <div class="stat-label">Active Teachers</div>
                <i class="stat-icon fas fa-check-circle"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card warning">
                <div class="stat-number"><?php echo $stats['inactive_teachers']; ?></div>
                <div class="stat-label">Inactive Teachers</div>
                <i class="stat-icon fas fa-pause-circle"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card info">
                <div class="stat-number"><?php echo $stats['new_hires']; ?></div>
                <div class="stat-label">New Hires (30 days)</div>
                <i class="stat-icon fas fa-user-plus"></i>
            </div>
        </div>
    </div>

    <!-- Search and Filter -->
    <div class="material-card mb-4">
        <div class="card-body">
            <form method="GET" class="row">
                <div class="col-md-6">
                    <input type="text" name="search" class="form-control" placeholder="Search teachers..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">Search</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Teachers Table -->
    <div class="material-card">
        <div class="card-header">
            <h5 class="mb-0">Teachers List (<?php echo $totalRecords; ?> total)</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Teacher</th>
                            <th>Employee ID</th>
                            <th>Contact</th>
                            <th>Hire Date</th>
                            <th>Teaching Subjects</th>
                            <th>Active Classes</th>
                            <th>Last Login</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teachers as $teacher): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="teacher-avatar">
                                            <?php if ($teacher['profile_picture']): ?>
                                                <img src="<?php echo BASE_URL . $teacher['profile_picture']; ?>" alt="Profile" class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="avatar-placeholder">
                                                    <?php echo strtoupper(substr($teacher['first_name'], 0, 1) . substr($teacher['last_name'], 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="ml-3">
                                            <div class="font-weight-bold">
                                                <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                            </div>
                                            <?php if ($teacher['username']): ?>
                                                <small class="text-muted">@<?php echo htmlspecialchars($teacher['username']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-secondary"><?php echo htmlspecialchars($teacher['employee_id']); ?></span>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($teacher['email']); ?></div>
                                    <?php if ($teacher['phone']): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($teacher['phone']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($teacher['hire_date']): ?>
                                        <?php echo formatDate($teacher['hire_date'], 'M j, Y'); ?>
                                        <?php
                                        $daysSinceHire = (time() - strtotime($teacher['hire_date'])) / (60 * 60 * 24);
                                        if ($daysSinceHire <= 30):
                                        ?>
                                            <br><small class="badge badge-success">New</small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Not set</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($teacher['teaching_subjects']): ?>
                                        <div class="teaching-subjects">
                                            <?php
                                            $subjects = explode(', ', $teacher['teaching_subjects']);
                                            foreach (array_slice($subjects, 0, 3) as $subject):
                                            ?>
                                                <span class="badge badge-primary"><?php echo htmlspecialchars($subject); ?></span>
                                            <?php endforeach; ?>
                                            <?php if (count($subjects) > 3): ?>
                                                <span class="badge badge-light">+<?php echo count($subjects) - 3; ?> more</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">No subjects assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($teacher['active_classes'] > 0): ?>
                                        <span class="badge badge-info"><?php echo $teacher['active_classes']; ?> classes</span>
                                    <?php else: ?>
                                        <span class="text-muted">No classes</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($teacher['last_login']): ?>
                                        <span title="<?php echo formatDate($teacher['last_login'], 'M j, Y g:i A'); ?>">
                                            <?php echo formatDate($teacher['last_login'], 'M j'); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">Never</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo getStatusBadge($teacher['status']); ?></td>
                                <td class="table-actions">
                                    <!-- <button class="btn btn-sm btn-info" onclick="viewTeacher(<?php echo $teacher['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button> -->
                                    <button class="btn btn-sm btn-warning" onclick="updateStatus(<?php echo $teacher['id']; ?>, '<?php echo $teacher['status']; ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if (!$teacher['last_login'] || $teacher['status'] === 'inactive'): ?>
                                        <button class="btn btn-sm btn-success" onclick="sendWelcomeEmail(<?php echo $teacher['id']; ?>)">
                                            <i class="fas fa-envelope"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($pagination['total_pages'] > 1): ?>
            <div class="card-footer">
                <?php echo Pagination::generatePaginationHTML($pagination, '?search=' . urlencode($search) . '&status=' . urlencode($statusFilter)); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal" id="statusModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h5 class="modal-title">Update Teacher Status</h5>
            <button type="button" class="modal-close" data-dismiss="modal">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="teacher_id" id="modalTeacherId">
                
                <div class="form-group">
                    <label class="form-label">Status *</label>
                    <select name="status" class="form-control" required>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Reason for status change..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Status</button>
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

.avatar-placeholder {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--primary-color);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.teaching-subjects .badge {
    margin-right: 4px;
    margin-bottom: 2px;
}

.ml-3 {
    margin-left: 1rem;
}

.table-actions .btn {
    margin-right: 5px;
}
</style>

<script>
function viewTeacher(id) {
    window.location.href = `teacher-profile.php?id=${id}`;
}

function updateStatus(teacherId, currentStatus) {
    document.getElementById('modalTeacherId').value = teacherId;
    document.querySelector('#statusModal select[name="status"]').value = currentStatus;
    showModal('statusModal');
}

function sendWelcomeEmail(teacherId) {
    if (confirm('This will generate new login credentials and send a welcome email. Continue?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="send_welcome_email">
            <input type="hidden" name="teacher_id" value="${teacherId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php require_once '../../components/footer.php'; ?>