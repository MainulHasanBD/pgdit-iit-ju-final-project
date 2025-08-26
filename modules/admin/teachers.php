<?php
// Start output buffering to avoid any "headers already sent" errors
ob_start(); 

$pageTitle = 'Teachers Management';
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../components/header.php';
require_once '../../components/sidebar.php';
require_once '../../includes/security.php';
require_once '../../includes/functions.php';

$auth = new Auth();
$auth->requireRole('admin'); // Ensure the user has the 'hr' role

$db = new Database();
$conn = $db->getConnection();

$message = '';
$messageType = '';

// Handle teacher status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['csrf_token']) && Security::validateCSRFToken($_POST['csrf_token'])) {
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
                        $message = 'Error updating teacher status: ' . $e->getMessage();
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
    } else {
        $message = 'Invalid CSRF token';
        $messageType = 'danger';
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
        <!-- Statistic cards for total teachers, active teachers, etc. -->
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
                                <!-- Teacher data display logic -->
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
    <!-- Modal content -->
</div>

<script>
// JavaScript functions for handling actions like viewing teacher profile, updating status, etc.
</script>

<?php require_once '../../components/footer.php'; ?>

<?php
// End the output buffering
ob_end_flush();
?>
