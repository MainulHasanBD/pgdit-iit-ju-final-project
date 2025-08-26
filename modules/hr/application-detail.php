<?php
$pageTitle = 'Application Details';
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../components/header.php';
require_once '../../components/sidebar.php';
require_once '../../includes/security.php';
require_once __DIR__ . '/../../includes/functions.php';
$auth = new Auth();
$auth->requireRole('hr');

$db = new Database();
$conn = $db->getConnection();

$applicationId = (int)($_GET['id'] ?? 0);

if (!$applicationId) {
    header('Location: applications.php');
    exit();
}

// Get application details
$query = "SELECT 
            ca.*,
            jp.title as job_title,
            jp.description as job_description,
            jp.requirements as job_requirements,
            jp.salary_range
          FROM cv_applications ca
          LEFT JOIN job_postings jp ON ca.job_posting_id = jp.id
          WHERE ca.id = ?";

$stmt = $conn->prepare($query);
$stmt->execute([$applicationId]);
$application = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$application) {
    header('Location: applications.php');
    exit();
}

$message = '';
$messageType = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_status':
                $status = $_POST['status'] ?? '';
                $notes = Security::sanitizeInput($_POST['notes'] ?? '');
                
                if (in_array($status, ['applied', 'shortlisted', 'interviewed', 'selected', 'rejected'])) {
                    try {
                        $query = "UPDATE cv_applications SET status = ?, notes = ? WHERE id = ?";
                        $stmt = $conn->prepare($query);
                        $stmt->execute([$status, $notes, $applicationId]);
                        
                        $message = 'Application status updated successfully!';
                        $messageType = 'success';
                        
                        // Refresh application data
                        $stmt = $conn->prepare($query);
                        $stmt->execute([$applicationId]);
                        $application = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                    } catch (PDOException $e) {
                        $message = 'Error updating application status';
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'schedule_interview':
                $interviewDate = $_POST['interview_date'] ?? '';
                $interviewTime = $_POST['interview_time'] ?? '';
                $interviewLocation = Security::sanitizeInput($_POST['interview_location'] ?? '');
                $interviewNotes = Security::sanitizeInput($_POST['interview_notes'] ?? '');
                
                try {
                    // Update status to interviewed
                    $query = "UPDATE cv_applications SET status = 'interviewed', notes = ? WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->execute(['Interview scheduled for ' . $interviewDate . ' at ' . $interviewTime . '. Location: ' . $interviewLocation, $applicationId]);
                    
                    // Here you could also insert into an interviews table if you have one
                    
                    $message = 'Interview scheduled successfully!';
                    $messageType = 'success';
                    
                } catch (PDOException $e) {
                    $message = 'Error scheduling interview';
                    $messageType = 'danger';
                }
                break;
                
            case 'create_teacher_profile':
                // Start onboarding process by creating teacher record
                try {
                    $conn->beginTransaction();
                    
                    // Generate employee ID
                    $employeeId = generateEmployeeId('TCH');
                    
                    // Create user account
                    $username = strtolower(str_replace(' ', '', $application['candidate_name']));
                    $password = bin2hex(random_bytes(8));
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    $userQuery = "INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, 'teacher', 'active')";
                    $userStmt = $conn->prepare($userQuery);
                    $userStmt->execute([$username, $application['email'], $hashedPassword]);
                    $userId = $conn->lastInsertId();
                    
                    // Create teacher profile
                    $teacherQuery = "INSERT INTO teachers (user_id, employee_id, first_name, last_name, email, phone, address, hire_date, status, created_from_cv_id) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), 'active', ?)";
                    $teacherStmt = $conn->prepare($teacherQuery);
                    
                    $nameParts = explode(' ', $application['candidate_name'], 2);
                    $firstName = $nameParts[0];
                    $lastName = $nameParts[1] ?? '';
                    
                    $teacherStmt->execute([
                        $userId,
                        $employeeId,
                        $firstName,
                        $lastName,
                        $application['email'],
                        $application['phone'],
                        $application['address'],
                        $applicationId
                    ]);
                    
                    // Update application status
                    $updateQuery = "UPDATE cv_applications SET status = 'selected' WHERE id = ?";
                    $updateStmt = $conn->prepare($updateQuery);
                    $updateStmt->execute([$applicationId]);
                    
                    $conn->commit();
                    
                    // Send welcome email with credentials
                    $emailService = new EmailService();
                    $emailService->sendWelcomeEmail($application['email'], $application['candidate_name'], $password);
                    
                    $message = 'Teacher profile created successfully! Welcome email sent with login credentials.';
                    $messageType = 'success';
                    
                } catch (PDOException $e) {
                    $conn->rollBack();
                    $message = 'Error creating teacher profile: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
        }
    }
}
?>

<div class="main-content">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="applications.php">Applications</a></li>
            <li class="breadcrumb-item active">Application Details</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Application Details</h2>
        <div>
            <a href="applications.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Applications
            </a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="row">
        <!-- Candidate Information -->
        <div class="col-md-8">
            <div class="material-card">
                <div class="card-header">
                    <h5 class="mb-0">Candidate Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-item">
                                <label>Full Name:</label>
                                <div class="font-weight-bold"><?php echo htmlspecialchars($application['candidate_name']); ?></div>
                            </div>
                            
                            <div class="info-item">
                                <label>Email Address:</label>
                                <div><?php echo htmlspecialchars($application['email']); ?></div>
                            </div>
                            
                            <div class="info-item">
                                <label>Phone Number:</label>
                                <div><?php echo htmlspecialchars($application['phone'] ?: 'Not provided'); ?></div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="info-item">
                                <label>Applied Position:</label>
                                <div class="font-weight-bold text-primary"><?php echo htmlspecialchars($application['job_title']); ?></div>
                            </div>
                            
                            <div class="info-item">
                                <label>Application Date:</label>
                                <div><?php echo formatDate($application['application_date'], 'M j, Y g:i A'); ?></div>
                            </div>
                            
                            <div class="info-item">
                                <label>Current Status:</label>
                                <div><?php echo getStatusBadge($application['status']); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($application['address']): ?>
                        <div class="info-item">
                            <label>Address:</label>
                            <div><?php echo nl2br(htmlspecialchars($application['address'])); ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Cover Letter -->
            <?php if ($application['cover_letter']): ?>
                <div class="material-card">
                    <div class="card-header">
                        <h5 class="mb-0">Cover Letter</h5>
                    </div>
                    <div class="card-body">
                        <div class="cover-letter">
                            <?php echo nl2br(htmlspecialchars($application['cover_letter'])); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Job Details -->
            <div class="material-card">
                <div class="card-header">
                    <h5 class="mb-0">Job Details</h5>
                </div>
                <div class="card-body">
                    <div class="job-details">
                        <h6><?php echo htmlspecialchars($application['job_title']); ?></h6>
                        
                        <?php if ($application['salary_range']): ?>
                            <p class="text-muted mb-2">
                                <i class="fas fa-money-bill"></i>
                                Salary: <?php echo htmlspecialchars($application['salary_range']); ?>
                            </p>
                        <?php endif; ?>
                        
                        <div class="job-description mb-3">
                            <strong>Description:</strong>
                            <div><?php echo nl2br(htmlspecialchars($application['job_description'])); ?></div>
                        </div>
                        
                        <?php if ($application['job_requirements']): ?>
                            <div class="job-requirements">
                                <strong>Requirements:</strong>
                                <div><?php echo nl2br(htmlspecialchars($application['job_requirements'])); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <?php if ($application['notes']): ?>
                <div class="material-card">
                    <div class="card-header">
                        <h5 class="mb-0">HR Notes</h5>
                    </div>
                    <div class="card-body">
                        <div class="notes-content">
                            <?php echo nl2br(htmlspecialchars($application['notes'])); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Actions Panel -->
        <div class="col-md-4">
            <!-- CV Download -->
            <?php if ($application['cv_file_path']): ?>
                <div class="material-card">
                    <div class="card-header">
                        <h5 class="mb-0">CV/Resume</h5>
                    </div>
                    <div class="card-body text-center">
                        <i class="fas fa-file-pdf fa-3x text-danger mb-3"></i>
                        <div>
                            <a href="<?php echo BASE_URL . $application['cv_file_path']; ?>" target="_blank" class="btn btn-success btn-block">
                                <i class="fas fa-download"></i> Download CV
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Status Update -->
            <div class="material-card">
                <div class="card-header">
                    <h5 class="mb-0">Update Status</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="update_status">
                        
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control" required>
                                <option value="applied" <?php echo $application['status'] === 'applied' ? 'selected' : ''; ?>>Applied</option>
                                <option value="shortlisted" <?php echo $application['status'] === 'shortlisted' ? 'selected' : ''; ?>>Shortlisted</option>
                                <option value="interviewed" <?php echo $application['status'] === 'interviewed' ? 'selected' : ''; ?>>Interviewed</option>
                                <option value="selected" <?php echo $application['status'] === 'selected' ? 'selected' : ''; ?>>Selected</option>
                                <option value="rejected" <?php echo $application['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="Add notes about this status change..."><?php echo htmlspecialchars($application['notes'] ?? ''); ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-save"></i> Update Status
                        </button>
                    </form>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="material-card">
                <div class="card-header">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <?php if ($application['status'] === 'shortlisted'): ?>
                        <button class="btn btn-warning btn-block mb-2" onclick="showModal('interviewModal')">
                            <i class="fas fa-calendar"></i> Schedule Interview
                        </button>
                    <?php endif; ?>
                    
                    <?php if ($application['status'] === 'selected'): ?>
                        <button class="btn btn-success btn-block mb-2" onclick="showModal('createTeacherModal')">
                            <i class="fas fa-user-plus"></i> Create Teacher Profile
                        </button>
                        <a href="onboarding.php?start=<?php echo $application['id']; ?>" class="btn btn-info btn-block mb-2">
                            <i class="fas fa-play"></i> Start Onboarding
                        </a>
                    <?php endif; ?>
                    
                    <a href="mailto:<?php echo $application['email']; ?>" class="btn btn-outline btn-block">
                        <i class="fas fa-envelope"></i> Send Email
                    </a>
                </div>
            </div>

            <!-- Application Timeline -->
            <div class="material-card">
                <div class="card-header">
                    <h5 class="mb-0">Application Timeline</h5>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-marker bg-primary"></div>
                            <div class="timeline-content">
                                <h6>Application Submitted</h6>
                                <p class="text-muted small"><?php echo formatDate($application['application_date'], 'M j, Y g:i A'); ?></p>
                            </div>
                        </div>
                        
                        <?php if (in_array($application['status'], ['shortlisted', 'interviewed', 'selected', 'rejected'])): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker bg-info"></div>
                                <div class="timeline-content">
                                    <h6>Status: <?php echo ucfirst($application['status']); ?></h6>
                                    <p class="text-muted small">Updated recently</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Interview Modal -->
<div class="modal" id="interviewModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h5 class="modal-title">Schedule Interview</h5>
            <button type="button" class="modal-close" data-dismiss="modal">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="schedule_interview">
                
                <div class="form-group">
                    <label class="form-label">Interview Date</label>
                    <input type="date" name="interview_date" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Interview Time</label>
                    <input type="time" name="interview_time" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Location/Platform</label>
                    <input type="text" name="interview_location" class="form-control" placeholder="Office address or video call link" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="interview_notes" class="form-control" rows="3" placeholder="Any additional instructions for the candidate..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning">Schedule Interview</button>
            </div>
        </form>
    </div>
</div>

<!-- Create Teacher Profile Modal -->
<div class="modal" id="createTeacherModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h5 class="modal-title">Create Teacher Profile</h5>
            <button type="button" class="modal-close" data-dismiss="modal">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="create_teacher_profile">
                
                <div class="alert alert-info">
                    <h6>This will:</h6>
                    <ul class="mb-0">
                        <li>Create a user account for the teacher</li>
                        <li>Generate login credentials</li>
                        <li>Send welcome email with credentials</li>
                        <li>Create teacher profile in the system</li>
                    </ul>
                </div>
                
                <div class="candidate-summary">
                    <h6>Candidate Information:</h6>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($application['candidate_name']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($application['email']); ?></p>
                    <p><strong>Position:</strong> <?php echo htmlspecialchars($application['job_title']); ?></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success" onclick="return confirm('Are you sure you want to create a teacher profile for this candidate?')">
                    Create Teacher Profile
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.info-item {
    margin-bottom: 20px;
}

.info-item label {
    font-weight: 600;
    color: var(--text-muted);
    margin-bottom: 5px;
    display: block;
}

.cover-letter {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    border-left: 4px solid var(--primary-color);
}

.job-details h6 {
    color: var(--primary-color);
    margin-bottom: 10px;
}

.notes-content {
    background: #fff9c4;
    padding: 15px;
    border-radius: 8px;
    border-left: 4px solid #ffeb3b;
}

.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e0e0e0;
}

.timeline-item {
    position: relative;
    margin-bottom: 25px;
}

.timeline-marker {
    position: absolute;
    left: -25px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid white;
}

.timeline-content h6 {
    margin-bottom: 5px;
    font-size: 14px;
}

.candidate-summary {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-top: 15px;
}
</style>

<?php require_once '../../components/footer.php'; ?>