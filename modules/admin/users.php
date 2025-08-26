<?php
$pageTitle = 'User Management';
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
$emailService = new EmailService();

$message = '';
$messageType = '';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create':
                $username = Security::sanitizeInput($_POST['username'] ?? '');
                $email = Security::sanitizeInput($_POST['email'] ?? '');
                $role = $_POST['role'] ?? '';
                $status = $_POST['status'] ?? 'active';
                $password = $_POST['password'] ?? '';
                
                $errors = [];
                if (empty($username)) $errors[] = 'Username is required';
                if (empty($email) || !Security::validateEmail($email)) $errors[] = 'Valid email is required';
                if (empty($password) || !Security::validatePassword($password)) $errors[] = 'Password must be at least 8 characters';
                if (!in_array($role, ['admin', 'hr', 'teacher', 'accounts'])) $errors[] = 'Invalid role selected';
                
                // Check if username/email already exists
                $checkQuery = "SELECT id FROM users WHERE username = ? OR email = ?";
                $checkStmt = $conn->prepare($checkQuery);
                $checkStmt->execute([$username, $email]);
                if ($checkStmt->rowCount() > 0) {
                    $errors[] = 'Username or email already exists';
                }
                
                if (empty($errors)) {
                    try {
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $query = "INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($query);
                        $stmt->execute([$username, $email, $hashedPassword, $role, $status]);
                        
                        // Send welcome email
                        $emailService->sendWelcomeEmail($email, $username, $password);
                        
                        $message = 'User created successfully! Welcome email sent.';
                        $messageType = 'success';
                    } catch (PDOException $e) {
                        $message = 'Error creating user: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                } else {
                    $message = implode('<br>', $errors);
                    $messageType = 'danger';
                }
                break;
                
            case 'update':
                $id = (int)$_POST['id'];
                $username = Security::sanitizeInput($_POST['username'] ?? '');
                $email = Security::sanitizeInput($_POST['email'] ?? '');
                $role = $_POST['role'] ?? '';
                $status = $_POST['status'] ?? 'active';
                
                try {
                    $query = "UPDATE users SET username = ?, email = ?, role = ?, status = ? WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$username, $email, $role, $status, $id]);
                    $message = 'User updated successfully!';
                    $messageType = 'success';
                } catch (PDOException $e) {
                    $message = 'Error updating user: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
                
            case 'delete':
                $id = (int)$_POST['id'];
                if ($id == $_SESSION['user_id']) {
                    $message = 'Cannot delete your own account!';
                    $messageType = 'warning';
                } else {
                    try {
                        $query = "DELETE FROM users WHERE id = ?";
                        $stmt = $conn->prepare($query);
                        $stmt->execute([$id]);
                        $message = 'User deleted successfully!';
                        $messageType = 'success';
                    } catch (PDOException $e) {
                        $message = 'Error deleting user: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'reset_password':
                $id = (int)$_POST['id'];
                $newPassword = bin2hex(random_bytes(8));
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                
                try {
                    $query = "UPDATE users SET password = ? WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$hashedPassword, $id]);
                    
                    // Get user email
                    $emailQuery = "SELECT email, username FROM users WHERE id = ?";
                    $emailStmt = $conn->prepare($emailQuery);
                    $emailStmt->execute([$id]);
                    $user = $emailStmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Send password reset email
                    $emailService->sendWelcomeEmail($user['email'], $user['username'], $newPassword);
                    
                    $message = 'Password reset successfully! New password sent to user\'s email.';
                    $messageType = 'success';
                } catch (PDOException $e) {
                    $message = 'Error resetting password: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
        }
    }
}

// Get users with pagination and search
$page = (int)($_GET['page'] ?? 1);
$search = $_GET['search'] ?? '';
$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';

$whereConditions = [];
$params = [];

if ($search) {
    $whereConditions[] = "(username LIKE ? OR email LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%"]);
}

if ($roleFilter) {
    $whereConditions[] = "role = ?";
    $params[] = $roleFilter;
}

if ($statusFilter) {
    $whereConditions[] = "status = ?";
    $params[] = $statusFilter;
}

$whereClause = empty($whereConditions) ? '' : 'WHERE ' . implode(' AND ', $whereConditions);

$countQuery = "SELECT COUNT(*) FROM users $whereClause";
$countStmt = $conn->prepare($countQuery);
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();

$pagination = Pagination::paginate($totalRecords, $page);
$offset = $pagination['offset'];

$query = "SELECT * FROM users $whereClause ORDER BY created_at DESC LIMIT $offset, " . RECORDS_PER_PAGE;
$stmt = $conn->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user for editing
$editUser = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editQuery = "SELECT * FROM users WHERE id = ?";
    $editStmt = $conn->prepare($editQuery);
    $editStmt->execute([$editId]);
    $editUser = $editStmt->fetch(PDO::FETCH_ASSOC);
}
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>User Management</h2>
        <button class="btn btn-primary" onclick="showModal('userModal')">
            <i class="fas fa-user-plus"></i> Add User
        </button>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <!-- Search and Filter -->
    <div class="material-card mb-4">
        <div class="card-body">
            <form method="GET" class="row">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <select name="role" class="form-control">
                        <option value="">All Roles</option>
                        <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="hr" <?php echo $roleFilter === 'hr' ? 'selected' : ''; ?>>HR</option>
                        <option value="teacher" <?php echo $roleFilter === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                        <option value="accounts" <?php echo $roleFilter === 'accounts' ? 'selected' : ''; ?>>Accounts</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Search</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Users Table -->
    <div class="material-card">
        <div class="card-header">
            <h5 class="mb-0">Users List (<?php echo $totalRecords; ?> total)</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Created</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <!-- <div class="user-avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mr-3" style="width: 40px; height: 40px;">
                                            <?php echo strtoupper(substr($user['username'], 0, 2)); ?>
                                        </div> -->
                                        <div>
                                            <div class="font-weight-bold"><?php echo htmlspecialchars($user['username']); ?></div>
                                            <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                                <small class="text-muted">(You)</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'hr' ? 'primary' : ($user['role'] === 'teacher' ? 'success' : 'warning')); ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td><?php echo getStatusBadge($user['status']); ?></td>
                                <td><?php echo $user['last_login'] ? formatDate($user['last_login'], 'M j, g:i A') : 'Never'; ?></td>
                                <td><?php echo formatDate($user['created_at'], 'M j, Y'); ?></td>
                                <td class="table-actions">
                                    <a href="?edit=<?php echo $user['id']; ?>" class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button class="btn btn-sm btn-info" onclick="resetPassword(<?php echo $user['id']; ?>)">
                                        <i class="fas fa-key"></i>
                                    </button>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <button class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $user['id']; ?>)">
                                            <i class="fas fa-trash"></i>
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
                <?php echo Pagination::generatePaginationHTML($pagination, '?search=' . urlencode($search) . '&role=' . urlencode($roleFilter) . '&status=' . urlencode($statusFilter)); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- User Modal -->
<div class="modal" id="userModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h5 class="modal-title"><?php echo $editUser ? 'Edit User' : 'Add New User'; ?></h5>
            <button type="button" class="modal-close" data-dismiss="modal">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="<?php echo $editUser ? 'update' : 'create'; ?>">
                <?php if ($editUser): ?>
                    <input type="hidden" name="id" value="<?php echo $editUser['id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label class="form-label">Username *</label>
                    <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($editUser['username'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($editUser['email'] ?? ''); ?>" required>
                </div>
                
                <?php if (!$editUser): ?>
                    <div class="form-group">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-control" minlength="8" required>
                        <small class="text-muted">Minimum 8 characters</small>
                    </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label class="form-label">Role *</label>
                    <select name="role" class="form-control" required>
                        <option value="">Select Role</option>
                        <option value="admin" <?php echo ($editUser['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="hr" <?php echo ($editUser['role'] ?? '') === 'hr' ? 'selected' : ''; ?>>HR Manager</option>
                        <option value="teacher" <?php echo ($editUser['role'] ?? '') === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                        <option value="accounts" <?php echo ($editUser['role'] ?? '') === 'accounts' ? 'selected' : ''; ?>>Accounts</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="active" <?php echo ($editUser['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($editUser['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <?php echo $editUser ? 'Update User' : 'Create User'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function deleteUser(id) {
    if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
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

function resetPassword(id) {
    if (confirm('Are you sure you want to reset this user\'s password? A new password will be sent to their email.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

<?php if ($editUser): ?>
    document.addEventListener('DOMContentLoaded', function() {
        showModal('userModal');
    });
<?php endif; ?>
</script>

<?php require_once '../../components/footer.php'; ?>