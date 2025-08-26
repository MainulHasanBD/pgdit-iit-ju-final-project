<?php
$pageTitle = 'My Profile';
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../components/header.php';
require_once '../../components/sidebar.php';
require_once '../../includes/security.php';
require_once '../../includes/functions.php';

$auth = new Auth();
$auth->requireRole('teacher');

$db = new Database();
$conn = $db->getConnection();

$message = '';
$messageType = '';

// Get teacher profile
$query = "SELECT t.*, u.username, u.email as user_email 
          FROM teachers t 
          LEFT JOIN users u ON t.user_id = u.id 
          WHERE t.user_id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle profile updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_profile':
                $firstName = Security::sanitizeInput($_POST['first_name'] ?? '');
                $lastName = Security::sanitizeInput($_POST['last_name'] ?? '');
                $phone = Security::sanitizeInput($_POST['phone'] ?? '');
                $address = Security::sanitizeInput($_POST['address'] ?? '');
                $qualification = Security::sanitizeInput($_POST['qualification'] ?? '');
                
                // Handle profile picture upload
                $profilePicture = $teacher['profile_picture'] ?? '';
                if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                    $uploadResult = Security::uploadFile($_FILES['profile_picture'], PROFILE_UPLOAD_PATH, ['jpg', 'jpeg', 'png', 'gif']);
                    if ($uploadResult['success']) {
                        // Delete old profile picture
                        if ($profilePicture && file_exists($profilePicture)) {
                            unlink($profilePicture);
                        }
                        $profilePicture = $uploadResult['path'];
                    }
                }
                
                try {
                    if ($teacher) {
                        // Update existing teacher
                        $query = "UPDATE teachers SET first_name = ?, last_name = ?, phone = ?, address = ?, qualification = ?, profile_picture = ? WHERE user_id = ?";
                        $stmt = $conn->prepare($query);
                        $stmt->execute([$firstName, $lastName, $phone, $address, $qualification, $profilePicture, $_SESSION['user_id']]);
                    } else {
                        // Create new teacher profile
                        $employeeId = generateEmployeeId('TCH');
                        $query = "INSERT INTO teachers (user_id, employee_id, first_name, last_name, email, phone, address, qualification, profile_picture, hire_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())";
                        $stmt = $conn->prepare($query);
                        $stmt->execute([$_SESSION['user_id'], $employeeId, $firstName, $lastName, $_SESSION['email'], $phone, $address, $qualification, $profilePicture]);
                    }
                    
                    $message = 'Profile updated successfully!';
                    $messageType = 'success';
                    
                    // Refresh teacher data
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$_SESSION['user_id']]);
                    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                } catch (PDOException $e) {
                    $message = 'Error updating profile: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
                
            case 'change_password':
                $currentPassword = $_POST['current_password'] ?? '';
                $newPassword = $_POST['new_password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';
                
                if ($newPassword !== $confirmPassword) {
                    $message = 'New passwords do not match!';
                    $messageType = 'danger';
                } elseif (!Security::validatePassword($newPassword)) {
                    $message = 'Password must be at least 8 characters long!';
                    $messageType = 'danger';
                } else {
                    // Verify current password
                    $query = "SELECT password FROM users WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$_SESSION['user_id']]);
                    $currentHash = $stmt->fetchColumn();
                    
                    if (password_verify($currentPassword, $currentHash)) {
                        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                        $updateQuery = "UPDATE users SET password = ? WHERE id = ?";
                        $updateStmt = $conn->prepare($updateQuery);
                        $updateStmt->execute([$newHash, $_SESSION['user_id']]);
                        
                        $message = 'Password changed successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'Current password is incorrect!';
                        $messageType = 'danger';
                    }
                }
                break;
        }
    }
}

// Get teaching subjects
$subjectQuery = "SELECT DISTINCT s.name, s.code 
                 FROM class_schedule cs 
                 LEFT JOIN subjects s ON cs.subject_id = s.id 
                 WHERE cs.teacher_id = ? AND cs.is_active = 1";
$subjectStmt = $conn->prepare($subjectQuery);
$subjectStmt->execute([$teacher['id'] ?? 0]);
$teachingSubjects = $subjectStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>My Profile</h2>
        <div class="text-muted">
            Manage your personal information and account settings
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="row">
        <!-- Profile Information -->
        <div class="col-md-8">
            <div class="material-card">
                <div class="card-header">
                    <h5 class="mb-0">Profile Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">First Name *</label>
                                    <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($teacher['first_name'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Last Name *</label>
                                    <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($teacher['last_name'] ?? ''); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" value="<?php echo htmlspecialchars($_SESSION['email']); ?>" readonly>
                                    <small class="text-muted">Contact admin to change email</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($teacher['phone'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($teacher['address'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Qualification</label>
                            <textarea name="qualification" class="form-control" rows="2" placeholder="Your educational background and certifications"><?php echo htmlspecialchars($teacher['qualification'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Profile Picture</label>
                            <input type="file" name="profile_picture" class="form-control" accept="image/*">
                            <?php if (!empty($teacher['profile_picture'])): ?>
                                <div class="mt-2">
                                    <img src="<?php echo BASE_URL . $teacher['profile_picture']; ?>" alt="Profile" style="max-width: 100px; max-height: 100px; border-radius: 8px;">
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Profile Summary & Actions -->
        <div class="col-md-4">
            <div class="material-card">
                <div class="card-header">
                    <h5 class="mb-0">Profile Summary</h5>
                </div>
                <div class="card-body text-center">
                    <div class="profile-avatar mb-3">
                        <?php if (!empty($teacher['profile_picture'])): ?>
                            <img src="<?php echo BASE_URL . $teacher['profile_picture']; ?>" alt="Profile" class="rounded-circle" style="width: 100px; height: 100px; object-fit: cover;">
                        <?php else: ?>
                            <div class="avatar-placeholder">
                                <i class="fas fa-user fa-3x text-muted"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <h6><?php echo htmlspecialchars(($teacher['first_name'] ?? '') . ' ' . ($teacher['last_name'] ?? '')); ?></h6>
                    
                    <?php if (!empty($teacher['employee_id'])): ?>
                        <p class="text-muted mb-2">
                            <strong>ID:</strong> <?php echo htmlspecialchars($teacher['employee_id']); ?>
                        </p>
                    <?php endif; ?>
                    
                    <?php if (!empty($teacher['hire_date'])): ?>
                        <p class="text-muted mb-2">
                            <strong>Joined:</strong> <?php echo formatDate($teacher['hire_date'], 'M j, Y'); ?>
                        </p>
                    <?php endif; ?>
                    
                    <div class="profile-stats mt-3">
                        <div class="stat-item">
                            <strong><?php echo count($teachingSubjects); ?></strong>
                            <div class="text-muted small">Subjects Teaching</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Teaching Subjects -->
            <?php if (!empty($teachingSubjects)): ?>
                <div class="material-card">
                    <div class="card-header">
                        <h5 class="mb-0">Teaching Subjects</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($teachingSubjects as $subject): ?>
                            <span class="badge badge-primary mr-2 mb-2">
                                <?php echo htmlspecialchars($subject['code'] . ' - ' . $subject['name']); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Security Settings -->
            <div class="material-card">
                <div class="card-header">
                    <h5 class="mb-0">Security Settings</h5>
                </div>
                <div class="card-body">
                    <button class="btn btn-warning btn-block" onclick="showModal('passwordModal')">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div class="modal" id="passwordModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h5 class="modal-title">Change Password</h5>
            <button type="button" class="modal-close" data-dismiss="modal">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="change_password">
                
                <div class="form-group">
                    <label class="form-label">Current Password *</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">New Password *</label>
                    <input type="password" name="new_password" class="form-control" minlength="8" required>
                    <small class="text-muted">Minimum 8 characters</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Confirm New Password *</label>
                    <input type="password" name="confirm_password" class="form-control" minlength="8" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning">Change Password</button>
            </div>
        </form>
    </div>
</div>

<style>
.avatar-placeholder {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: #f8f9fa;
    border: 2px dashed #dee2e6;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
}

.profile-stats {
    border-top: 1px solid #e9ecef;
    padding-top: 15px;
}

.stat-item {
    margin-bottom: 10px;
}

.mr-2 { margin-right: 0.5rem; }
.mb-2 { margin-bottom: 0.5rem; }
.btn-block { width: 100%; }
</style>

<?php require_once '../../components/footer.php'; ?>