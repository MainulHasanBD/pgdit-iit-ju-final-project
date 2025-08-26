<?php
$pageTitle = 'Job Postings Management';
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

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create':
                $title = Security::sanitizeInput($_POST['title'] ?? '');
                $description = Security::sanitizeInput($_POST['description'] ?? '');
                $requirements = Security::sanitizeInput($_POST['requirements'] ?? '');
                $salaryRange = Security::sanitizeInput($_POST['salary_range'] ?? '');
                $postedDate = $_POST['posted_date'] ?? date('Y-m-d');
                $deadline = $_POST['deadline'] ?? null;
                $status = $_POST['status'] ?? 'active';
                
                if (empty($deadline)) $deadline = null;
                
                try {
                    $query = "INSERT INTO job_postings (title, description, requirements, salary_range, posted_date, deadline, status, posted_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$title, $description, $requirements, $salaryRange, $postedDate, $deadline, $status, $_SESSION['user_id']]);
                    
                    $message = 'Job posting created successfully!';
                    $messageType = 'success';
                } catch (PDOException $e) {
                    $message = 'Error creating job posting: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
                
            case 'update':
                $id = (int)$_POST['id'];
                $title = Security::sanitizeInput($_POST['title'] ?? '');
                $description = Security::sanitizeInput($_POST['description'] ?? '');
                $requirements = Security::sanitizeInput($_POST['requirements'] ?? '');
                $salaryRange = Security::sanitizeInput($_POST['salary_range'] ?? '');
                $postedDate = $_POST['posted_date'];
                $deadline = $_POST['deadline'] ?? null;
                $status = $_POST['status'];
                
                if (empty($deadline)) $deadline = null;
                
                try {
                    $query = "UPDATE job_postings SET title = ?, description = ?, requirements = ?, salary_range = ?, posted_date = ?, deadline = ?, status = ? WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$title, $description, $requirements, $salaryRange, $postedDate, $deadline, $status, $id]);
                    
                    $message = 'Job posting updated successfully!';
                    $messageType = 'success';
                } catch (PDOException $e) {
                    $message = 'Error updating job posting: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
                
            case 'delete':
                $id = (int)$_POST['id'];
                
                // Check if job posting has applications
                $checkQuery = "SELECT COUNT(*) FROM cv_applications WHERE job_posting_id = ?";
                $checkStmt = $conn->prepare($checkQuery);
                $checkStmt->execute([$id]);
                $applicationCount = $checkStmt->fetchColumn();
                
                if ($applicationCount > 0) {
                    $message = "Cannot delete job posting. It has {$applicationCount} application(s). Please archive it instead.";
                    $messageType = 'danger';
                } else {
                    try {
                        $query = "DELETE FROM job_postings WHERE id = ?";
                        $stmt = $conn->prepare($query);
                        $stmt->execute([$id]);
                        
                        $message = 'Job posting deleted successfully!';
                        $messageType = 'success';
                    } catch (PDOException $e) {
                        $message = 'Error deleting job posting: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'close_job':
                $id = (int)$_POST['id'];
                try {
                    $query = "UPDATE job_postings SET status = 'closed' WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$id]);
                    
                    $message = 'Job posting closed successfully!';
                    $messageType = 'success';
                } catch (PDOException $e) {
                    $message = 'Error closing job posting: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
        }
    }
}

// Get job postings with pagination and filters
$page = (int)($_GET['page'] ?? 1);
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';

$whereConditions = [];
$params = [];

if ($search) {
    $whereConditions[] = "(title LIKE ? OR description LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%"]);
}

if ($statusFilter) {
    $whereConditions[] = "status = ?";
    $params[] = $statusFilter;
}

$whereClause = empty($whereConditions) ? '' : 'WHERE ' . implode(' AND ', $whereConditions);

$countQuery = "SELECT COUNT(*) FROM job_postings $whereClause";
$countStmt = $conn->prepare($countQuery);
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();

$pagination = Pagination::paginate($totalRecords, $page);
$offset = $pagination['offset'];

$query = "SELECT 
            jp.*,
            u.username as posted_by_name,
            (SELECT COUNT(*) FROM cv_applications WHERE job_posting_id = jp.id) as application_count
          FROM job_postings jp
          LEFT JOIN users u ON jp.posted_by = u.id
          $whereClause
          ORDER BY jp.posted_date DESC
          LIMIT $offset, " . RECORDS_PER_PAGE;

$stmt = $conn->prepare($query);
$stmt->execute($params);
$jobPostings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get job posting for editing
$editJob = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editQuery = "SELECT * FROM job_postings WHERE id = ?";
    $editStmt = $conn->prepare($editQuery);
    $editStmt->execute([$editId]);
    $editJob = $editStmt->fetch(PDO::FETCH_ASSOC);
}

// Get statistics
$statsQuery = "SELECT 
                 COUNT(*) as total_jobs,
                 COUNT(CASE WHEN status = 'active' THEN 1 END) as active_jobs,
                 COUNT(CASE WHEN status = 'closed' THEN 1 END) as closed_jobs,
                 (SELECT COUNT(*) FROM cv_applications) as total_applications
               FROM job_postings";
$statsStmt = $conn->prepare($statsQuery);
$statsStmt->execute();
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Job Postings Management</h2>
        <div>
            <button class="btn btn-primary" onclick="showModal('jobModal')">
                <i class="fas fa-plus"></i> Post New Job
            </button>
            <a href="../common/reports.php?type=jobs" class="btn btn-info">
                <i class="fas fa-chart-bar"></i> Reports
            </a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <!-- <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_jobs']; ?></div>
                <div class="stat-label">Total Job Postings</div>
                <i class="stat-icon fas fa-briefcase"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card success">
                <div class="stat-number"><?php echo $stats['active_jobs']; ?></div>
                <div class="stat-label">Active Postings</div>
                <i class="stat-icon fas fa-check-circle"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card warning">
                <div class="stat-number"><?php echo $stats['closed_jobs']; ?></div>
                <div class="stat-label">Closed Postings</div>
                <i class="stat-icon fas fa-times-circle"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card info">
                <div class="stat-number"><?php echo $stats['total_applications']; ?></div>
                <div class="stat-label">Total Applications</div>
                <i class="stat-icon fas fa-file-alt"></i>
            </div>
        </div>
    </div> -->

    <!-- Search and Filter -->
    <div class="material-card mb-4">
        <div class="card-body">
            <form method="GET" class="row">
                <div class="col-md-6">
                    <input type="text" name="search" class="form-control" placeholder="Search job postings..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-4">
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="closed" <?php echo $statusFilter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Search</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Job Postings List -->
    <div class="material-card">
        <div class="card-header">
            <h5 class="mb-0">Job Postings (<?php echo $totalRecords; ?> total)</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($jobPostings)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-briefcase fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No job postings found</h5>
                    <p class="text-muted">Create your first job posting to start recruiting.</p>
                    <button class="btn btn-primary" onclick="showModal('jobModal')">
                        <i class="fas fa-plus"></i> Post New Job
                    </button>
                </div>
            <?php else: ?>
                <div class="job-postings-list">
                    <?php foreach ($jobPostings as $job): ?>
                        <div class="job-posting-item">
                            <div class="job-header">
                                <div class="job-title-section">
                                    <h6 class="job-title"><?php echo htmlspecialchars($job['title']); ?></h6>
                                    <div class="job-meta">
                                        <span class="text-muted">
                                            <i class="fas fa-calendar"></i>
                                            Posted: <?php echo formatDate($job['posted_date'], 'M j, Y'); ?>
                                        </span>
                                        <?php if ($job['deadline']): ?>
                                            <span class="text-muted ml-3">
                                                <i class="fas fa-clock"></i>
                                                Deadline: <?php echo formatDate($job['deadline'], 'M j, Y'); ?>
                                                <?php if (strtotime($job['deadline']) < time()): ?>
                                                    <span class="text-danger">(Expired)</span>
                                                <?php endif; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="job-status-section">
                                    <?php echo getStatusBadge($job['status']); ?>
                                    <span class="application-count ml-2">
                                        <i class="fas fa-users"></i>
                                        <?php echo $job['application_count']; ?> applications
                                    </span>
                                </div>
                            </div>
                            
                            <div class="job-content">
                                <div class="job-description">
                                    <?php echo nl2br(htmlspecialchars(substr($job['description'], 0, 200))); ?>
                                    <?php if (strlen($job['description']) > 200): ?>...<?php endif; ?>
                                </div>
                                
                                <?php if ($job['salary_range']): ?>
                                    <div class="job-salary">
                                        <i class="fas fa-money-bill text-success"></i>
                                        <strong>Salary: </strong><?php echo htmlspecialchars($job['salary_range']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="job-actions">
                                <a href="applications.php?job_id=<?php echo $job['id']; ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-eye"></i> View Applications (<?php echo $job['application_count']; ?>)
                                </a>
                                
                                <a href="?edit=<?php echo $job['id']; ?>" class="btn btn-sm btn-warning">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                
                                <?php if ($job['status'] === 'active'): ?>
                                    <button class="btn btn-sm btn-secondary" onclick="closeJob(<?php echo $job['id']; ?>)">
                                        <i class="fas fa-times"></i> Close
                                    </button>
                                <?php endif; ?>
                                
                                <button class="btn btn-sm btn-danger" onclick="deleteJob(<?php echo $job['id']; ?>, <?php echo $job['application_count']; ?>)">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                                
                                <a href="<?php echo BASE_URL; ?>public/apply.php#job-<?php echo $job['id']; ?>" target="_blank" class="btn btn-sm btn-success">
                                    <i class="fas fa-external-link-alt"></i> Public View
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
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

<!-- Job Posting Modal -->
<div class="modal" id="jobModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-header">
            <h5 class="modal-title"><?php echo $editJob ? 'Edit Job Posting' : 'Create New Job Posting'; ?></h5>
            <button type="button" class="modal-close" data-dismiss="modal">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="<?php echo $editJob ? 'update' : 'create'; ?>">
                <?php if ($editJob): ?>
                    <input type="hidden" name="id" value="<?php echo $editJob['id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label class="form-label">Job Title *</label>
                    <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($editJob['title'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Job Description *</label>
                    <textarea name="description" class="form-control" rows="6" required><?php echo htmlspecialchars($editJob['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Requirements</label>
                    <textarea name="requirements" class="form-control" rows="4"><?php echo htmlspecialchars($editJob['requirements'] ?? ''); ?></textarea>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Salary Range</label>
                            <input type="text" name="salary_range" class="form-control" value="<?php echo htmlspecialchars($editJob['salary_range'] ?? ''); ?>" placeholder="e.g., BDT 30,000 - 50,000">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <option value="active" <?php echo ($editJob['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="closed" <?php echo ($editJob['status'] ?? '') === 'closed' ? 'selected' : ''; ?>>Closed</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Posted Date</label>
                            <input type="date" name="posted_date" class="form-control" value="<?php echo $editJob['posted_date'] ?? date('Y-m-d'); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Application Deadline</label>
                            <input type="date" name="deadline" class="form-control" value="<?php echo $editJob['deadline'] ?? ''; ?>">
                            <small class="text-muted">Leave empty for no deadline</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <?php echo $editJob ? 'Update Job Posting' : 'Create Job Posting'; ?>
                </button>
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

.job-postings-list {
    padding: 0;
}

.job-posting-item {
    padding: 24px;
    border-bottom: 1px solid #f0f0f0;
    transition: background-color 0.3s ease;
}

.job-posting-item:hover {
    background-color: #f8f9fa;
}

.job-posting-item:last-child {
    border-bottom: none;
}

.job-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.job-title {
    color: var(--primary-color);
    margin-bottom: 8px;
    font-size: 18px;
}

.job-meta {
    font-size: 14px;
}

.job-status-section {
    text-align: right;
}

.application-count {
    background: #f8f9fa;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    color: var(--text-muted);
}

.job-content {
    margin-bottom: 20px;
}

.job-description {
    margin-bottom: 10px;
    line-height: 1.6;
}

.job-salary {
    color: var(--success-color);
    font-size: 14px;
}

.job-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.job-actions .btn {
    margin-bottom: 4px;
}

.ml-3 { margin-left: 1rem; }
.w-100 { width: 100%; }

@media (max-width: 768px) {
    .job-header {
        flex-direction: column;
        gap: 10px;
    }
    
    .job-actions {
        flex-direction: column;
    }
    
    .job-actions .btn {
        width: 100%;
    }
}
</style>

<script>
function deleteJob(id, applicationCount) {
    let message = 'Are you sure you want to delete this job posting?';
    if (applicationCount > 0) {
        message = `This job posting has ${applicationCount} application(s). Are you sure you want to delete it? This action cannot be undone.`;
    }
    
    if (confirm(message)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function closeJob(id) {
    if (confirm('Are you sure you want to close this job posting? No new applications will be accepted.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="close_job">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

<?php if ($editJob): ?>
    document.addEventListener('DOMContentLoaded', function() {
        showModal('jobModal');
    });
<?php endif; ?>
</script>

<?php require_once '../../components/footer.php'; ?>