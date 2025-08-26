<?php
$pageTitle = 'Subjects Management';
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../components/header.php';
require_once '../../components/sidebar.php';
require_once '../../includes/security.php';
require_once '../../includes/Pagination.php';
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
            case 'create':
                $name = Security::sanitizeInput($_POST['name'] ?? '');
                $code = Security::sanitizeInput($_POST['code'] ?? '');
                $description = Security::sanitizeInput($_POST['description'] ?? '');
                
                if ($name && $code) {
                    try {
                        $query = "INSERT INTO subjects (name, code, description, created_by) VALUES (?, ?, ?, ?)";
                        $stmt = $conn->prepare($query);
                        $stmt->execute([$name, $code, $description, $_SESSION['user_id']]);
                        $message = 'Subject created successfully!';
                        $messageType = 'success';
                    } catch (PDOException $e) {
                        $message = 'Error creating subject: Subject code already exists';
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'update':
                $id = (int)$_POST['id'];
                $name = Security::sanitizeInput($_POST['name'] ?? '');
                $code = Security::sanitizeInput($_POST['code'] ?? '');
                $description = Security::sanitizeInput($_POST['description'] ?? '');
                
                try {
                    $query = "UPDATE subjects SET name = ?, code = ?, description = ? WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$name, $code, $description, $id]);
                    $message = 'Subject updated successfully!';
                    $messageType = 'success';
                } catch (PDOException $e) {
                    $message = 'Error updating subject: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
                
            case 'delete':
                $id = (int)$_POST['id'];
                try {
                    $query = "DELETE FROM subjects WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$id]);
                    $message = 'Subject deleted successfully!';
                    $messageType = 'success';
                } catch (PDOException $e) {
                    $message = 'Error deleting subject: Cannot delete subject that is being used';
                    $messageType = 'danger';
                }
                break;
        }
    }
}

// Get subjects with pagination
$page = (int)($_GET['page'] ?? 1);
$search = $_GET['search'] ?? '';

$whereClause = $search ? "WHERE name LIKE ? OR code LIKE ?" : "";
$params = $search ? ["%$search%", "%$search%"] : [];

$countQuery = "SELECT COUNT(*) FROM subjects $whereClause";
$countStmt = $conn->prepare($countQuery);
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();

$pagination = Pagination::paginate($totalRecords, $page);
$offset = $pagination['offset'];

$query = "SELECT s.*, u.username as created_by_name 
          FROM subjects s 
          LEFT JOIN users u ON s.created_by = u.id 
          $whereClause 
          ORDER BY s.name 
          LIMIT $offset, " . RECORDS_PER_PAGE;
$stmt = $conn->prepare($query);
$stmt->execute($params);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get subject for editing if ID provided
$editSubject = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editQuery = "SELECT * FROM subjects WHERE id = ?";
    $editStmt = $conn->prepare($editQuery);
    $editStmt->execute([$editId]);
    $editSubject = $editStmt->fetch(PDO::FETCH_ASSOC);
}
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Subjects Management</h2>
        <button class="btn btn-primary" onclick="showModal('subjectModal')">
            <i class="fas fa-plus"></i> Add Subject
        </button>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <!-- Search -->
    <div class="material-card mb-4">
        <div class="card-body">
            <form method="GET" class="d-flex">
                <input type="text" name="search" class="form-control" placeholder="Search subjects..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-primary ml-2">Search</button>
                <?php if ($search): ?>
                    <a href="subjects.php" class="btn btn-secondary ml-2">Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Subjects Table -->
    <div class="material-card">
        <div class="card-header">
            <h5 class="mb-0">Subjects List (<?php echo $totalRecords; ?> total)</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Created By</th>
                            <th>Created Date</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subjects as $subject): ?>
                            <tr>
                                <td><span class="badge badge-primary"><?php echo htmlspecialchars($subject['code']); ?></span></td>
                                <td><?php echo htmlspecialchars($subject['name']); ?></td>
                                <td><?php echo htmlspecialchars($subject['description'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($subject['created_by_name'] ?: 'System'); ?></td>
                                <td><?php echo date('M j, Y', strtotime($subject['created_at'])); ?></td>
                                <td class="table-actions">
                                    <a href="?edit=<?php echo $subject['id']; ?>" class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button class="btn btn-sm btn-danger" onclick="deleteSubject(<?php echo $subject['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($pagination['total_pages'] > 1): ?>
            <div class="card-footer">
                <?php echo Pagination::generatePaginationHTML($pagination, '?search=' . urlencode($search)); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Subject Modal -->
<div class="modal" id="subjectModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h5 class="modal-title"><?php echo $editSubject ? 'Edit Subject' : 'Add New Subject'; ?></h5>
            <button type="button" class="modal-close" data-dismiss="modal">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="<?php echo $editSubject ? 'update' : 'create'; ?>">
                <?php if ($editSubject): ?>
                    <input type="hidden" name="id" value="<?php echo $editSubject['id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label class="form-label">Subject Code *</label>
                    <input type="text" name="code" class="form-control" value="<?php echo htmlspecialchars($editSubject['code'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Subject Name *</label>
                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($editSubject['name'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($editSubject['description'] ?? ''); ?></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <?php echo $editSubject ? 'Update Subject' : 'Create Subject'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function deleteSubject(id) {
    if (confirm('Are you sure you want to delete this subject? This will affect all associated teachers and schedules.')) {
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

<?php if ($editSubject): ?>
    document.addEventListener('DOMContentLoaded', function() {
        showModal('subjectModal');
    });
<?php endif; ?>
</script>

<style>
.table-actions {
    white-space: nowrap;
}

.table-actions .btn {
    margin-right: 5px;
}

.ml-2 {
    margin-left: 8px;
}
</style>

<?php require_once '../../components/footer.php'; ?>