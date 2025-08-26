<?php
$pageTitle = 'Job Applications';
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
$emailService = new EmailService();

$message = '';
$messageType = '';

// Handle application status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_status':
                $applicationId = (int)$_POST['application_id'];
                $status = $_POST['status'] ?? '';
                $notes = Security::sanitizeInput($_POST['notes'] ?? '');
                
                if (in_array($status, ['applied', 'shortlisted', 'interviewed', 'selected', 'rejected'])) {
                    try {
                        $query = "UPDATE cv_applications SET status = ?, notes = ? WHERE id = ?";
                        $stmt = $conn->prepare($query);
                        $stmt->execute([$status, $notes, $applicationId]);
                        
                        // Get application details for email
                        $appQuery = "SELECT ca.*, jp.title as job_title 
                                     FROM cv_applications ca 
                                     LEFT JOIN job_postings jp ON ca.job_posting_id = jp.id 
                                     WHERE ca.id = ?";
                        $appStmt = $conn->prepare($appQuery);
                        $appStmt->execute([$applicationId]);
                        $application = $appStmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Send status update email
                        if ($application) {
                            //$this->sendStatusUpdateEmail($application, $status);
							sendStatusUpdateEmail($application, $status);
                        }
                        
                        $message = 'Application status updated successfully!';
                        $messageType = 'success';
                    } catch (PDOException $e) {
                        $message = 'Error updating application status';
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'bulk_status_update':
                $applicationIds = $_POST['application_ids'] ?? [];
                $status = $_POST['bulk_status'] ?? '';
                
                if (!empty($applicationIds) && in_array($status, ['shortlisted', 'interviewed', 'selected', 'rejected'])) {
                    try {
                        $placeholders = str_repeat('?,', count($applicationIds) - 1) . '?';
                        $query = "UPDATE cv_applications SET status = ? WHERE id IN ($placeholders)";
                        $params = array_merge([$status], $applicationIds);
                        $stmt = $conn->prepare($query);
                        $stmt->execute($params);
                        
                        $message = 'Bulk status update completed for ' . count($applicationIds) . ' applications';
                        $messageType = 'success';
                    } catch (PDOException $e) {
                        $message = 'Error in bulk status update';
                        $messageType = 'danger';
                    }
                }
                break;
        }
    }
}

// Get applications with filters
$page = (int)($_GET['page'] ?? 1);
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$jobFilter = $_GET['job_id'] ?? '';

$whereConditions = [];
$params = [];

if ($search) {
    $whereConditions[] = "(ca.candidate_name LIKE ? OR ca.email LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%"]);
}

if ($statusFilter) {
    $whereConditions[] = "ca.status = ?";
    $params[] = $statusFilter;
}

if ($jobFilter) {
    $whereConditions[] = "ca.job_posting_id = ?";
    $params[] = $jobFilter;
}

$whereClause = empty($whereConditions) ? '' : 'WHERE ' . implode(' AND ', $whereConditions);

$countQuery = "SELECT COUNT(*) FROM cv_applications ca $whereClause";
$countStmt = $conn->prepare($countQuery);
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();

$pagination = Pagination::paginate($totalRecords, $page);
$offset = $pagination['offset'];

$query = "SELECT 
            ca.*,
            jp.title as job_title,
            jp.salary_range
          FROM cv_applications ca
          LEFT JOIN job_postings jp ON ca.job_posting_id = jp.id
          $whereClause
          ORDER BY ca.application_date DESC 
          LIMIT $offset, " . RECORDS_PER_PAGE;

$stmt = $conn->prepare($query);
$stmt->execute($params);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get job postings for filter
$jobQuery = "SELECT id, title FROM job_postings ORDER BY title";
$jobStmt = $conn->prepare($jobQuery);
$jobStmt->execute();
$jobs = $jobStmt->fetchAll(PDO::FETCH_ASSOC);

// Send status update email function
function sendStatusUpdateEmail($application, $status) {
    global $emailService;
    
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
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Job Applications</h2>
        <div>
            <button class="btn btn-warning" onclick="showModal('bulkUpdateModal')">
                <i class="fas fa-edit"></i> Bulk Update
            </button>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="material-card mb-4">
        <div class="card-body">
            <form method="GET" class="row">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="Search candidates..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="applied" <?php echo $statusFilter === 'applied' ? 'selected' : ''; ?>>Applied</option>
                        <option value="shortlisted" <?php echo $statusFilter === 'shortlisted' ? 'selected' : ''; ?>>Shortlisted</option>
                        <option value="interviewed" <?php echo $statusFilter === 'interviewed' ? 'selected' : ''; ?>>Interviewed</option>
                        <option value="selected" <?php echo $statusFilter === 'selected' ? 'selected' : ''; ?>>Selected</option>
                        <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="job_id" class="form-control">
                        <option value="">All Positions</option>
                        <?php foreach ($jobs as $job): ?>
                            <option value="<?php echo $job['id']; ?>" <?php echo $jobFilter == $job['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($job['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Applications List -->
    <div class="material-card">
        <div class="card-header">
            <h5 class="mb-0">Applications (<?php echo $totalRecords; ?> total)</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" id="selectAll" onchange="toggleAllApplications()">
                            </th>
                            <th>Candidate</th>
                            <th>Position</th>
                            <th>Applied Date</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $app): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="application_ids[]" value="<?php echo $app['id']; ?>" class="application-checkbox">
                                </td>
                                <td>
                                    <div>
                                        <div class="font-weight-bold"><?php echo htmlspecialchars($app['candidate_name']); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($app['email']); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($app['phone']); ?></div>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <div><?php echo htmlspecialchars($app['job_title']); ?></div>
                                        <?php if ($app['salary_range']): ?>
                                            <small class="text-muted"><?php echo htmlspecialchars($app['salary_range']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo formatDate($app['application_date'], 'M j, Y g:i A'); ?></td>
                                <td><?php echo getStatusBadge($app['status']); ?></td>
                                <td class="table-actions">
                                    <a href="application-detail.php?id=<?php echo $app['id']; ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <button class="btn btn-sm btn-warning" onclick="updateApplicationStatus(<?php echo $app['id']; ?>, '<?php echo $app['status']; ?>')">
                                        <i class="fas fa-edit"></i> Update
                                    </button>
                                    <?php if ($app['cv_file_path']): ?>
                                        <a href="<?php echo BASE_URL . $app['cv_file_path']; ?>" target="_blank" class="btn btn-sm btn-success">
                                            <i class="fas fa-download"></i> CV
                                        </a>
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
                <?php echo Pagination::generatePaginationHTML($pagination, '?search=' . urlencode($search) . '&status=' . urlencode($statusFilter) . '&job_id=' . urlencode($jobFilter)); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal" id="updateStatusModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h5 class="modal-title">Update Application Status</h5>
            <button type="button" class="modal-close" data-dismiss="modal">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="application_id" id="modalApplicationId">
                
                <div class="form-group">
                    <label class="form-label">Status *</label>
                    <select name="status" class="form-control" required>
                        <option value="applied">Applied</option>
                        <option value="shortlisted">Shortlisted</option>
                        <option value="interviewed">Interviewed</option>
                        <option value="selected">Selected</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Add any notes about this status change..."></textarea>
                </div>
                
                <div class="alert alert-info">
                    <strong>Note:</strong> The candidate will receive an email notification about this status change.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Status</button>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Update Modal -->
<div class="modal" id="bulkUpdateModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h5 class="modal-title">Bulk Status Update</h5>
            <button type="button" class="modal-close" data-dismiss="modal">&times;</button>
        </div>
        <form method="POST" id="bulkUpdateForm">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="bulk_status_update">
                
                <div class="form-group">
                    <label class="form-label">New Status *</label>
                    <select name="bulk_status" class="form-control" required>
                        <option value="">Select Status</option>
                        <option value="shortlisted">Shortlisted</option>
                        <option value="interviewed">Interviewed</option>
                        <option value="selected">Selected</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                
                <div class="alert alert-warning">
                    <strong>Warning:</strong> This will update the status of all selected applications and send email notifications to candidates.
                </div>
                
                <div id="selectedApplicationsCount" class="text-muted">
                    No applications selected
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning" id="bulkUpdateBtn" disabled>Update Selected</button>
            </div>
        </form>
    </div>
</div>

<script>
function updateApplicationStatus(applicationId, currentStatus) {
    document.getElementById('modalApplicationId').value = applicationId;
    document.querySelector('#updateStatusModal select[name="status"]').value = currentStatus;
    showModal('updateStatusModal');
}

function toggleAllApplications() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.application-checkbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
    
    updateBulkUpdateButton();
}

function updateBulkUpdateButton() {
    const selectedCheckboxes = document.querySelectorAll('.application-checkbox:checked');
    const count = selectedCheckboxes.length;
    const button = document.getElementById('bulkUpdateBtn');
    const countDiv = document.getElementById('selectedApplicationsCount');
    
    if (count > 0) {
        button.disabled = false;
        countDiv.textContent = `${count} application(s) selected`;
        
        // Add selected IDs to form
        const form = document.getElementById('bulkUpdateForm');
        // Remove existing hidden inputs
        const existingInputs = form.querySelectorAll('input[name="application_ids[]"]');
        existingInputs.forEach(input => input.remove());
        
        // Add new hidden inputs
        selectedCheckboxes.forEach(checkbox => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'application_ids[]';
            input.value = checkbox.value;
            form.appendChild(input);
        });
    } else {
        button.disabled = true;
        countDiv.textContent = 'No applications selected';
    }
}

// Add event listeners to checkboxes
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.application-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateBulkUpdateButton);
    });
});
</script>

<?php require_once '../../components/footer.php'; ?>