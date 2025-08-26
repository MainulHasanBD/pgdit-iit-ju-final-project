<?php
$pageTitle = 'Employee Onboarding';
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

// Handle onboarding operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'start_onboarding':
                $applicationId = (int)($_POST['application_id'] ?? 0);
                $candidateName = Security::sanitizeInput($_POST['candidate_name'] ?? '');
                $email = Security::sanitizeInput($_POST['email'] ?? '');
                $phone = Security::sanitizeInput($_POST['phone'] ?? '');
                $position = Security::sanitizeInput($_POST['position'] ?? '');
                $department = Security::sanitizeInput($_POST['department'] ?? '');
                $salary = (float)($_POST['salary'] ?? 0);
                $startDate = $_POST['start_date'] ?? date('Y-m-d');
                
                try {
                    $conn->beginTransaction();
                    
                    // Create onboarding record
                    $query = "INSERT INTO employee_onboarding (application_id, candidate_name, email, phone, position, department, salary, start_date, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$applicationId, $candidateName, $email, $phone, $position, $department, $salary, $startDate, $_SESSION['user_id']]);
                    
                    $onboardingId = $conn->lastInsertId();
                    
                    // Create default onboarding tasks
                    $defaultTasks = [
                        ['Documentation Collection', 'Collect CV, certificates, ID copy, and photos', 1],
                        ['System Account Setup', 'Create user account and email setup', 2],
                        ['Office Tour', 'Show office premises and introduce to colleagues', 3],
                        ['Policy Briefing', 'Explain company policies and procedures', 4],
                        ['Equipment Assignment', 'Assign necessary equipment and materials', 5],
                        ['Department Introduction', 'Introduce to department head and team members', 6],
                        ['Initial Training', 'Provide initial job-specific training', 7],
                        ['Probation Review Setup', 'Schedule probation review meetings', 8]
                    ];
                    
                    foreach ($defaultTasks as $task) {
                        $taskQuery = "INSERT INTO onboarding_tasks (onboarding_id, task_name, task_description, task_order, status) VALUES (?, ?, ?, ?, 'pending')";
                        $taskStmt = $conn->prepare($taskQuery);
                        $taskStmt->execute([$onboardingId, $task[0], $task[1], $task[2]]);
                    }
                    
                    // Update application status if provided
                    if ($applicationId > 0) {
                        $updateQuery = "UPDATE cv_applications SET status = 'selected' WHERE id = ?";
                        $updateStmt = $conn->prepare($updateQuery);
                        $updateStmt->execute([$applicationId]);
                    }
                    
                    $conn->commit();
                    
                    $message = 'Onboarding process started successfully!';
                    $messageType = 'success';
                    
                } catch (PDOException $e) {
                    $conn->rollBack();
                    $message = 'Error starting onboarding process';
                    $messageType = 'danger';
                }
                break;
                
            case 'update_task_status':
                $taskId = (int)$_POST['task_id'];
                $status = $_POST['task_status'] ?? 'pending';
                $notes = Security::sanitizeInput($_POST['task_notes'] ?? '');
                
                try {
                    $query = "UPDATE onboarding_tasks SET status = ?, notes = ?, completed_by = ?, completed_at = ? WHERE id = ?";
                    $completedAt = ($status === 'completed') ? date('Y-m-d H:i:s') : null;
                    $completedBy = ($status === 'completed') ? $_SESSION['user_id'] : null;
                    
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$status, $notes, $completedBy, $completedAt, $taskId]);
                    
                    // Check if all tasks are completed
                    $checkQuery = "SELECT onboarding_id FROM onboarding_tasks WHERE id = ?";
                    $checkStmt = $conn->prepare($checkQuery);
                    $checkStmt->execute([$taskId]);
                    $onboardingId = $checkStmt->fetchColumn();
                    
                    $allTasksQuery = "SELECT COUNT(*) as total, COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed FROM onboarding_tasks WHERE onboarding_id = ?";
                    $allTasksStmt = $conn->prepare($allTasksQuery);
                    $allTasksStmt->execute([$onboardingId]);
                    $taskStats = $allTasksStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($taskStats['total'] == $taskStats['completed']) {
                        $updateOnboardingQuery = "UPDATE employee_onboarding SET status = 'completed', completed_at = NOW() WHERE id = ?";
                        $updateOnboardingStmt = $conn->prepare($updateOnboardingQuery);
                        $updateOnboardingStmt->execute([$onboardingId]);
                    }
                    
                    $message = 'Task status updated successfully!';
                    $messageType = 'success';
                    
                } catch (PDOException $e) {
                    $message = 'Error updating task status';
                    $messageType = 'danger';
                }
                break;
                
            case 'update_onboarding_status':
    $onboardingId = (int)$_POST['onboarding_id'];
    $status = $_POST['onboarding_status'] ?? 'pending';
    $notes = Security::sanitizeInput($_POST['onboarding_notes'] ?? '');

    try {
        $conn->beginTransaction();

        $query = "UPDATE employee_onboarding SET status = ?, notes = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$status, $notes, $onboardingId]);

        if ($status === 'completed') {
            // Set completed_at
            $completeQuery = "UPDATE employee_onboarding SET completed_at = NOW() WHERE id = ?";
            $completeStmt = $conn->prepare($completeQuery);
            $completeStmt->execute([$onboardingId]);

            // Fetch onboarding details
            $detailsQuery = "SELECT * FROM employee_onboarding WHERE id = ?";
            $detailsStmt = $conn->prepare($detailsQuery);
            $detailsStmt->execute([$onboardingId]);
            $onboarding = $detailsStmt->fetch(PDO::FETCH_ASSOC);

            // Check if user already exists
            $userCheck = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $userCheck->execute([$onboarding['email']]);
            $existingUserId = $userCheck->fetchColumn();

            if (!$existingUserId) {
                // Create user with default password
                $defaultPassword = password_hash('Teacher@123', PASSWORD_DEFAULT); // CHANGE in production
                $userInsert = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'teacher')");
                $username = explode('@', $onboarding['email'])[0];
                $userInsert->execute([$username, $onboarding['email'], $defaultPassword]);
                $userId = $conn->lastInsertId();
            } else {
                $userId = $existingUserId;
            }

            // Check if teacher already exists
            $teacherCheck = $conn->prepare("SELECT id FROM teachers WHERE email = ?");
            $teacherCheck->execute([$onboarding['email']]);
            $existingTeacherId = $teacherCheck->fetchColumn();

            if (!$existingTeacherId) {
                $teacherInsert = $conn->prepare("INSERT INTO teachers (
                    user_id, employee_id, first_name, last_name, email, phone, address, hire_date, salary, status, created_from_cv_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)");

                $employeeId = 'EMP' . str_pad($onboardingId, 5, '0', STR_PAD_LEFT); // Example: EMP00001
                $fullName = explode(' ', $onboarding['candidate_name'], 2);
                $firstName = $fullName[0];
                $lastName = $fullName[1] ?? '';

                $teacherInsert->execute([
                    $userId,
                    $employeeId,
                    $firstName,
                    $lastName,
                    $onboarding['email'],
                    $onboarding['phone'],
                    null, // address
                    $onboarding['start_date'],
                    $onboarding['salary'],
                    $onboarding['application_id'] > 0 ? $onboarding['application_id'] : null
                ]);
            }
        }

        $conn->commit();
        $message = 'Onboarding status updated successfully!';
        $messageType = 'success';

    } catch (PDOException $e) {
        $conn->rollBack();
        $message = 'Error updating onboarding status';
        $messageType = 'danger';
    }
    break;

        }
    }
}

// Get onboarding records with filters
$page = (int)($_GET['page'] ?? 1);
$statusFilter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$whereConditions = [];
$params = [];

if ($statusFilter) {
    $whereConditions[] = "eo.status = ?";
    $params[] = $statusFilter;
}

if ($search) {
    $whereConditions[] = "(eo.candidate_name LIKE ? OR eo.email LIKE ? OR eo.position LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}

$whereClause = empty($whereConditions) ? '' : 'WHERE ' . implode(' AND ', $whereConditions);

$countQuery = "SELECT COUNT(*) FROM employee_onboarding eo $whereClause";
$countStmt = $conn->prepare($countQuery);
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();

$pagination = Pagination::paginate($totalRecords, $page);
$offset = $pagination['offset'];

$query = "SELECT 
            eo.*,
            u.username as created_by_name,
            (SELECT COUNT(*) FROM onboarding_tasks ot WHERE ot.onboarding_id = eo.id) as total_tasks,
            (SELECT COUNT(*) FROM onboarding_tasks ot WHERE ot.onboarding_id = eo.id AND ot.status = 'completed') as completed_tasks
          FROM employee_onboarding eo
          LEFT JOIN users u ON eo.created_by = u.id
          $whereClause
          ORDER BY eo.created_at DESC
          LIMIT $offset, " . RECORDS_PER_PAGE;

$stmt = $conn->prepare($query);
$stmt->execute($params);
$onboardingRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$statsQuery = "SELECT 
                 COUNT(*) as total_onboarding,
                 COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_onboarding,
                 COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_onboarding,
                 COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_onboarding
               FROM employee_onboarding";
$statsStmt = $conn->prepare($statsQuery);
$statsStmt->execute();
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Get recent applications ready for onboarding
$applicationsQuery = "SELECT 
                        ca.*,
                        jp.title as job_title,
                        jp.salary_range
                      FROM cv_applications ca
                      LEFT JOIN job_postings jp ON ca.job_posting_id = jp.id
                      WHERE ca.status = 'selected' 
                      AND NOT EXISTS (SELECT 1 FROM employee_onboarding eo WHERE eo.application_id = ca.id)
                      ORDER BY ca.application_date DESC
                      LIMIT 10";
$applicationsStmt = $conn->prepare($applicationsQuery);
$applicationsStmt->execute();
$readyApplications = $applicationsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Employee Onboarding</h2>
        <div>
            <button class="btn btn-primary" onclick="showModal('startOnboardingModal')">
                <i class="fas fa-user-plus"></i> Start Onboarding
            </button>
            <a href="../common/reports.php?type=onboarding" class="btn btn-info">
                <i class="fas fa-chart-bar"></i> Reports
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
                <div class="stat-number"><?php echo $stats['total_onboarding']; ?></div>
                <div class="stat-label">Total Onboarding</div>
                <i class="stat-icon fas fa-users"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card warning">
                <div class="stat-number"><?php echo $stats['pending_onboarding']; ?></div>
                <div class="stat-label">Pending</div>
                <i class="stat-icon fas fa-clock"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card info">
                <div class="stat-number"><?php echo $stats['in_progress_onboarding']; ?></div>
                <div class="stat-label">In Progress</div>
                <i class="stat-icon fas fa-spinner"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card success">
                <div class="stat-number"><?php echo $stats['completed_onboarding']; ?></div>
                <div class="stat-label">Completed</div>
                <i class="stat-icon fas fa-check-circle"></i>
            </div>
        </div>
    </div>

    <!-- Ready Applications Alert -->
    <?php if (!empty($readyApplications)): ?>
        <div class="alert alert-info">
            <h6><i class="fas fa-info-circle"></i> Applications Ready for Onboarding</h6>
            <p>You have <?php echo count($readyApplications); ?> selected candidate(s) ready to start onboarding process.</p>
            <button class="btn btn-sm btn-primary" onclick="showModal('startOnboardingModal')">
                Start Onboarding Process
            </button>
        </div>
    <?php endif; ?>

    <!-- Search and Filter -->
    <div class="material-card mb-4">
        <div class="card-body">
            <form method="GET" class="row">
                <div class="col-md-6">
                    <input type="text" name="search" class="form-control" placeholder="Search candidates..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-4">
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="in_progress" <?php echo $statusFilter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Search</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Onboarding Records -->
    <div class="material-card">
        <div class="card-header">
            <h5 class="mb-0">Onboarding Records (<?php echo $totalRecords; ?> total)</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($onboardingRecords)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No onboarding records found</h5>
                    <p class="text-muted">Start the onboarding process for selected candidates.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Candidate</th>
                                <th>Position</th>
                                <th>Department</th>
                                <th>Start Date</th>
                                <th>Progress</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($onboardingRecords as $record): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <div class="font-weight-bold"><?php echo htmlspecialchars($record['candidate_name']); ?></div>
                                            <div class="text-muted small"><?php echo htmlspecialchars($record['email']); ?></div>
                                            <?php if ($record['phone']): ?>
                                                <div class="text-muted small"><?php echo htmlspecialchars($record['phone']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="font-weight-bold"><?php echo htmlspecialchars($record['position']); ?></div>
                                        <?php if ($record['salary']): ?>
                                            <div class="text-muted small"><?php echo formatCurrency($record['salary']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($record['department'] ?: 'Not specified'); ?></td>
                                    <td><?php echo formatDate($record['start_date'], 'M j, Y'); ?></td>
                                    <td>
                                        <?php 
                                        $progress = $record['total_tasks'] > 0 ? round(($record['completed_tasks'] / $record['total_tasks']) * 100) : 0;
                                        ?>
                                        <div class="progress mb-1" style="height: 6px;">
                                            <div class="progress-bar bg-<?php echo $progress == 100 ? 'success' : ($progress >= 50 ? 'info' : 'warning'); ?>" 
                                                 style="width: <?php echo $progress; ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?php echo $record['completed_tasks']; ?>/<?php echo $record['total_tasks']; ?> tasks</small>
                                    </td>
                                    <td><?php echo getStatusBadge($record['status']); ?></td>
                                    <td><?php echo formatDate($record['created_at'], 'M j, Y'); ?></td>
                                    <td class="table-actions">
                                        <!-- <a href="onboarding-detail.php?id=<?php echo $record['id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i> View
                                        </a> -->
                                        <?php if ($record['status'] !== 'completed'): ?>
                                            <button class="btn btn-sm btn-warning" onclick="updateOnboardingStatus(<?php echo $record['id']; ?>, '<?php echo $record['status']; ?>')">
                                                <i class="fas fa-edit"></i> Update
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($pagination['total_pages'] > 1): ?>
            <div class="card-footer">
                <?php echo Pagination::generatePaginationHTML($pagination, '?search=' . urlencode($search) . '&status=' . urlencode($statusFilter)); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Start Onboarding Modal -->
<div class="modal" id="startOnboardingModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-header">
            <h5 class="modal-title">Start Onboarding Process</h5>
            <button type="button" class="modal-close" data-dismiss="modal">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="start_onboarding">
                
                <?php if (!empty($readyApplications)): ?>
                    <div class="form-group">
                        <label class="form-label">Select from Ready Applications</label>
                        <select id="applicationSelect" class="form-control" onchange="fillApplicationData()">
                            <option value="">Select an application...</option>
                            <?php foreach ($readyApplications as $app): ?>
                                <option value="<?php echo $app['id']; ?>" 
                                        data-name="<?php echo htmlspecialchars($app['candidate_name']); ?>"
                                        data-email="<?php echo htmlspecialchars($app['email']); ?>"
                                        data-phone="<?php echo htmlspecialchars($app['phone']); ?>"
                                        data-position="<?php echo htmlspecialchars($app['job_title']); ?>">
                                    <?php echo htmlspecialchars($app['candidate_name'] . ' - ' . $app['job_title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <hr>
                    <p class="text-muted">Or enter details manually:</p>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Candidate Name *</label>
                            <input type="text" name="candidate_name" id="candidateName" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" id="candidateEmail" class="form-control" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" id="candidatePhone" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Position *</label>
                            <input type="text" name="position" id="candidatePosition" class="form-control" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Department</label>
                            <select name="department" class="form-control">
                                <option value="">Select Department</option>
                                <option value="Academic">Academic</option>
                                <option value="Administration">Administration</option>
                                <option value="Finance">Finance</option>
                                <option value="Marketing">Marketing</option>
                                <option value="IT">IT</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Starting Salary</label>
                            <input type="number" name="salary" class="form-control" step="0.01" min="0">
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Start Date *</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <input type="hidden" name="application_id" id="applicationId" value="0">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Start Onboarding Process</button>
            </div>
        </form>
    </div>
</div>

<!-- Update Onboarding Status Modal -->
<div class="modal" id="updateStatusModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h5 class="modal-title">Update Onboarding Status</h5>
            <button type="button" class="modal-close" data-dismiss="modal">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="update_onboarding_status">
                <input type="hidden" name="onboarding_id" id="onboardingId">
                
                <div class="form-group">
                    <label class="form-label">Status *</label>
                    <select name="onboarding_status" id="onboardingStatus" class="form-control" required>
                        <option value="pending">Pending</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="onboarding_notes" class="form-control" rows="3" placeholder="Add notes about the status change..."></textarea>
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

.stat-card.warning { border-left: 4px solid var(--warning-color); }
.stat-card.info { border-left: 4px solid var(--info-color); }
.stat-card.success { border-left: 4px solid var(--success-color); }

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

.w-100 { width: 100%; }
</style>

<script>
function fillApplicationData() {
    const select = document.getElementById('applicationSelect');
    const option = select.options[select.selectedIndex];
    
    if (option.value) {
        document.getElementById('applicationId').value = option.value;
        document.getElementById('candidateName').value = option.dataset.name;
        document.getElementById('candidateEmail').value = option.dataset.email;
        document.getElementById('candidatePhone').value = option.dataset.phone;
        document.getElementById('candidatePosition').value = option.dataset.position;
    } else {
        document.getElementById('applicationId').value = '0';
        document.getElementById('candidateName').value = '';
        document.getElementById('candidateEmail').value = '';
        document.getElementById('candidatePhone').value = '';
        document.getElementById('candidatePosition').value = '';
    }
}

function updateOnboardingStatus(id, currentStatus) {
    document.getElementById('onboardingId').value = id;
    document.getElementById('onboardingStatus').value = currentStatus;
    showModal('updateStatusModal');
}
</script>

<?php require_once '../../components/footer.php'; ?>