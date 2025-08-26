<?php
$pageTitle = 'Classrooms Management';
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
            case 'create':
                $name = Security::sanitizeInput($_POST['name'] ?? '');
                $capacity = (int)$_POST['capacity'];
                $location = Security::sanitizeInput($_POST['location'] ?? '');
                $equipment = Security::sanitizeInput($_POST['equipment'] ?? '');
                $status = $_POST['status'] ?? 'active';
                
                if ($name && $capacity > 0) {
                    try {
                        $query = "INSERT INTO classrooms (name, capacity, location, equipment, status, created_by) VALUES (?, ?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($query);
                        $stmt->execute([$name, $capacity, $location, $equipment, $status, $_SESSION['user_id']]);
                        $message = 'Classroom created successfully!';
                        $messageType = 'success';
                    } catch (PDOException $e) {
                        $message = 'Error creating classroom: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'update':
                $id = (int)$_POST['id'];
                $name = Security::sanitizeInput($_POST['name'] ?? '');
                $capacity = (int)$_POST['capacity'];
                $location = Security::sanitizeInput($_POST['location'] ?? '');
                $equipment = Security::sanitizeInput($_POST['equipment'] ?? '');
                $status = $_POST['status'] ?? 'active';
                
                try {
                    $query = "UPDATE classrooms SET name = ?, capacity = ?, location = ?, equipment = ?, status = ? WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$name, $capacity, $location, $equipment, $status, $id]);
                    $message = 'Classroom updated successfully!';
                    $messageType = 'success';
                } catch (PDOException $e) {
                    $message = 'Error updating classroom: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
                
            case 'delete':
                $id = (int)$_POST['id'];
                try {
                    // Check if classroom is being used in schedule
                    $checkQuery = "SELECT COUNT(*) FROM class_schedule WHERE classroom_id = ? AND is_active = 1";
                    $checkStmt = $conn->prepare($checkQuery);
                    $checkStmt->execute([$id]);
                    
                    if ($checkStmt->fetchColumn() > 0) {
                        $message = 'Cannot delete classroom: It is currently being used in class schedules';
                        $messageType = 'warning';
                    } else {
                        $query = "DELETE FROM classrooms WHERE id = ?";
                        $stmt = $conn->prepare($query);
                        $stmt->execute([$id]);
                        $message = 'Classroom deleted successfully!';
                        $messageType = 'success';
                    }
                } catch (PDOException $e) {
                    $message = 'Error deleting classroom: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
        }
    }
}

// Get classrooms with pagination and search
$page = (int)($_GET['page'] ?? 1);
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';

$whereConditions = [];
$params = [];

if ($search) {
    $whereConditions[] = "(name LIKE ? OR location LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%"]);
}

if ($statusFilter) {
    $whereConditions[] = "status = ?";
    $params[] = $statusFilter;
}

$whereClause = empty($whereConditions) ? '' : 'WHERE ' . implode(' AND ', $whereConditions);

$countQuery = "SELECT COUNT(*) FROM classrooms $whereClause";
$countStmt = $conn->prepare($countQuery);
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();

$pagination = Pagination::paginate($totalRecords, $page);
$offset = $pagination['offset'];

$query = "SELECT c.*, u.username as created_by_name,
          (SELECT COUNT(*) FROM class_schedule cs WHERE cs.classroom_id = c.id AND cs.is_active = 1) as active_classes
          FROM classrooms c 
          LEFT JOIN users u ON c.created_by = u.id 
          $whereClause 
          ORDER BY c.name 
          LIMIT $offset, " . RECORDS_PER_PAGE;

$stmt = $conn->prepare($query);
$stmt->execute($params);
$classrooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get classroom statistics
$statsQuery = "SELECT 
                 COUNT(*) as total_classrooms,
                 COUNT(CASE WHEN status = 'active' THEN 1 END) as active_classrooms,
                 COUNT(CASE WHEN status = 'maintenance' THEN 1 END) as maintenance_classrooms,
                 AVG(capacity) as avg_capacity,
                 SUM(capacity) as total_capacity
               FROM classrooms";
$statsStmt = $conn->prepare($statsQuery);
$statsStmt->execute();
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Get classroom for editing
$editClassroom = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editQuery = "SELECT * FROM classrooms WHERE id = ?";
    $editStmt = $conn->prepare($editQuery);
    $editStmt->execute([$editId]);
    $editClassroom = $editStmt->fetch(PDO::FETCH_ASSOC);
}
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Classrooms Management</h2>
        <button class="btn btn-primary" onclick="showModal('classroomModal')">
            <i class="fas fa-plus"></i> Add Classroom
        </button>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <!-- <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_classrooms']; ?></div>
                <div class="stat-label">Total Classrooms</div>
                <i class="stat-icon fas fa-door-open"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card success">
                <div class="stat-number"><?php echo $stats['active_classrooms']; ?></div>
                <div class="stat-label">Active</div>
                <i class="stat-icon fas fa-check-circle"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card warning">
                <div class="stat-number"><?php echo $stats['maintenance_classrooms']; ?></div>
                <div class="stat-label">Under Maintenance</div>
                <i class="stat-icon fas fa-tools"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card info">
                <div class="stat-number"><?php echo $stats['total_capacity']; ?></div>
                <div class="stat-label">Total Capacity</div>
                <i class="stat-icon fas fa-users"></i>
            </div>
        </div>
    </div> -->

    <!-- Search and Filter -->
    <div class="material-card mb-4">
        <div class="card-body">
            <form method="GET" class="row">
                <div class="col-md-6">
                    <input type="text" name="search" class="form-control" placeholder="Search classrooms..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="maintenance" <?php echo $statusFilter === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">Search</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Classrooms Table -->
    <div class="material-card">
        <div class="card-header">
            <h5 class="mb-0">Classrooms List (<?php echo $totalRecords; ?> total)</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Location</th>
                            <th>Capacity</th>
                            <th>Equipment</th>
                            <th>Active Classes</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($classrooms as $classroom): ?>
                            <tr>
                                <td>
                                    <div class="font-weight-bold"><?php echo htmlspecialchars($classroom['name']); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($classroom['location'] ?: 'Not specified'); ?></td>
                                <td>
                                    <span class="badge badge-info">
                                        <i class="fas fa-users"></i> <?php echo $classroom['capacity']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($classroom['equipment']): ?>
                                        <span title="<?php echo htmlspecialchars($classroom['equipment']); ?>">
                                            <?php echo strlen($classroom['equipment']) > 30 ? substr(htmlspecialchars($classroom['equipment']), 0, 30) . '...' : htmlspecialchars($classroom['equipment']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">None specified</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($classroom['active_classes'] > 0): ?>
                                        <span class="badge badge-success"><?php echo $classroom['active_classes']; ?> classes</span>
                                    <?php else: ?>
                                        <span class="text-muted">No classes</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo getStatusBadge($classroom['status']); ?></td>
                                <td><?php echo htmlspecialchars($classroom['created_by_name'] ?: 'System'); ?></td>
                                <td class="table-actions">
                                    <a href="?edit=<?php echo $classroom['id']; ?>" class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <!-- <button class="btn btn-sm btn-info" onclick="viewClassroom(<?php echo $classroom['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button> -->
                                    <?php if ($classroom['active_classes'] == 0): ?>
                                        <button class="btn btn-sm btn-danger" onclick="deleteClassroom(<?php echo $classroom['id']; ?>)">
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
                <?php echo Pagination::generatePaginationHTML($pagination, '?search=' . urlencode($search) . '&status=' . urlencode($statusFilter)); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Classroom Modal -->
<div class="modal" id="classroomModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h5 class="modal-title"><?php echo $editClassroom ? 'Edit Classroom' : 'Add New Classroom'; ?></h5>
            <button type="button" class="modal-close" data-dismiss="modal">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="<?php echo $editClassroom ? 'update' : 'create'; ?>">
                <?php if ($editClassroom): ?>
                    <input type="hidden" name="id" value="<?php echo $editClassroom['id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label class="form-label">Classroom Name *</label>
                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($editClassroom['name'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Capacity *</label>
                    <input type="number" name="capacity" class="form-control" min="1" max="200" value="<?php echo $editClassroom['capacity'] ?? 30; ?>" required>
                    <small class="text-muted">Maximum number of students</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Location</label>
                    <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($editClassroom['location'] ?? ''); ?>" placeholder="Building, Floor, Room number">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Equipment & Facilities</label>
                    <textarea name="equipment" class="form-control" rows="3" placeholder="Projector, Whiteboard, Air conditioning, etc."><?php echo htmlspecialchars($editClassroom['equipment'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="active" <?php echo ($editClassroom['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($editClassroom['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="maintenance" <?php echo ($editClassroom['status'] ?? '') === 'maintenance' ? 'selected' : ''; ?>>Under Maintenance</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <?php echo $editClassroom ? 'Update Classroom' : 'Create Classroom'; ?>
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

.table-actions .btn {
    margin-right: 5px;
}
</style>

<script>
function deleteClassroom(id) {
    if (confirm('Are you sure you want to delete this classroom? This action cannot be undone.')) {
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

function viewClassroom(id) {
    // You can implement a detailed view modal here
    window.location.href = `classroom-detail.php?id=${id}`;
}

<?php if ($editClassroom): ?>
    document.addEventListener('DOMContentLoaded', function() {
        showModal('classroomModal');
    });
<?php endif; ?>
</script>

<?php require_once '../../components/footer.php'; ?>