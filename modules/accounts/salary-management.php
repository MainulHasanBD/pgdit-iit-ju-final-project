<?php
$pageTitle = 'Salary Management';
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../components/header.php';
require_once '../../components/sidebar.php';
require_once '../../includes/security.php';
require_once '../../includes/functions.php';

$auth = new Auth();
$auth->requireRole('accounts');

$db = new Database();
$conn = $db->getConnection();

$message = '';
$messageType = '';

// Handle salary configuration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_salary_config':
                $teacherId = (int)$_POST['teacher_id'];
                $basicSalary = (float)$_POST['basic_salary'];
                $allowances = (float)$_POST['allowances'];
                $deductions = (float)$_POST['deductions'];
                $effectiveFrom = $_POST['effective_from'] ?? date('Y-m-d');
                
                try {
                    $conn->beginTransaction();
                    
                    // Deactivate previous configurations
                    $deactivateQuery = "UPDATE salary_config SET is_active = 0 WHERE teacher_id = ?";
                    $deactivateStmt = $conn->prepare($deactivateQuery);
                    $deactivateStmt->execute([$teacherId]);
                    
                    // Insert new configuration
                    $insertQuery = "INSERT INTO salary_config (teacher_id, basic_salary, allowances, deductions, effective_from, is_active, created_by) VALUES (?, ?, ?, ?, ?, 1, ?)";
                    $insertStmt = $conn->prepare($insertQuery);
                    $insertStmt->execute([$teacherId, $basicSalary, $allowances, $deductions, $effectiveFrom, $_SESSION['user_id']]);
                    
                    // Update teacher's base salary
                    $updateTeacherQuery = "UPDATE teachers SET salary = ? WHERE id = ?";
                    $updateTeacherStmt = $conn->prepare($updateTeacherQuery);
                    $updateTeacherStmt->execute([$basicSalary, $teacherId]);
                    
                    $conn->commit();
                    
                    $message = 'Salary configuration updated successfully!';
                    $messageType = 'success';
                    
                } catch (PDOException $e) {
                    $conn->rollBack();
                    $message = 'Error updating salary configuration';
                    $messageType = 'danger';
                }
                break;
        }
    }
}

// Get teachers with their current salary configurations
$query = "SELECT 
            t.id,
            t.employee_id,
            CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
            t.email,
            t.hire_date,
            t.status,
            sc.basic_salary,
            sc.allowances,
            sc.deductions,
            sc.effective_from,
            sc.is_active as config_active
          FROM teachers t
          LEFT JOIN salary_config sc ON t.id = sc.teacher_id AND sc.is_active = 1
          ORDER BY t.first_name, t.last_name";

$stmt = $conn->prepare($query);
$stmt->execute();
$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get salary statistics
$statsQuery = "SELECT 
                 COUNT(DISTINCT t.id) as total_teachers,
                 COUNT(DISTINCT CASE WHEN sc.id IS NOT NULL THEN t.id END) as configured_teachers,
                 AVG(sc.basic_salary) as avg_basic_salary,
                 SUM(sc.basic_salary) as total_basic_salary
               FROM teachers t
               LEFT JOIN salary_config sc ON t.id = sc.teacher_id AND sc.is_active = 1
               WHERE t.status = 'active'";

$statsStmt = $conn->prepare($statsQuery);
$statsStmt->execute();
$salaryStats = $statsStmt->fetch(PDO::FETCH_ASSOC);

$configuredPercentage = $salaryStats['total_teachers'] > 0 ? 
    round(($salaryStats['configured_teachers'] / $salaryStats['total_teachers']) * 100, 1) : 0;
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Salary Management</h2>
        <div>
            <a href="disbursements.php" class="btn btn-info">
                <i class="fas fa-money-bill-wave"></i> View Disbursements
            </a>
            <a href="bulk-operations.php" class="btn btn-warning">
                <i class="fas fa-cogs"></i> Bulk Operations
            </a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <!-- Salary Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-number"><?php echo $salaryStats['total_teachers']; ?></div>
                <div class="stat-label">Total Teachers</div>
                <i class="stat-icon fas fa-users"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card success">
                <div class="stat-number"><?php echo $salaryStats['configured_teachers']; ?></div>
                <div class="stat-label">Configured</div>
                <i class="stat-icon fas fa-check-circle"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card info">
                <div class="stat-number"><?php echo $configuredPercentage; ?>%</div>
                <div class="stat-label">Configuration Rate</div>
                <i class="stat-icon fas fa-percentage"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card warning">
                <div class="stat-number"><?php echo formatCurrency($salaryStats['avg_basic_salary'] ?? 0); ?></div>
                <div class="stat-label">Average Salary</div>
                <i class="stat-icon fas fa-chart-line"></i>
            </div>
        </div>
    </div>

    <!-- Teachers Salary Configuration -->
    <div class="material-card">
        <div class="card-header">
            <h5 class="mb-0">Teachers Salary Configuration</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Employee ID</th>
                            <th>Teacher Name</th>
                            <th>Basic Salary</th>
                            <th>Allowances</th>
                            <th>Deductions</th>
                            <th>Net Salary</th>
                            <th>Effective From</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teachers as $teacher): ?>
                            <tr>
                                <td>
                                    <span class="badge badge-secondary"><?php echo htmlspecialchars($teacher['employee_id']); ?></span>
                                </td>
                                <td>
                                    <div>
                                        <div class="font-weight-bold"><?php echo htmlspecialchars($teacher['teacher_name']); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($teacher['email']); ?></div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($teacher['basic_salary']): ?>
                                        <span class="text-success font-weight-bold">
                                            <?php echo formatCurrency($teacher['basic_salary']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">Not configured</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($teacher['allowances']): ?>
                                        <span class="text-info">
                                            <?php echo formatCurrency($teacher['allowances']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($teacher['deductions']): ?>
                                        <span class="text-danger">
                                            <?php echo formatCurrency($teacher['deductions']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($teacher['basic_salary']): ?>
                                        <?php $netSalary = $teacher['basic_salary'] + $teacher['allowances'] - $teacher['deductions']; ?>
                                        <span class="text-primary font-weight-bold">
                                            <?php echo formatCurrency($netSalary); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($teacher['effective_from']): ?>
                                        <?php echo formatDate($teacher['effective_from'], 'M j, Y'); ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($teacher['config_active']): ?>
                                        <span class="badge badge-success">Configured</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Needs Setup</span>
                                    <?php endif; ?>
                                </td>
                                <td class="table-actions">
                                    <button class="btn btn-sm btn-primary" onclick="configureSalary(<?php echo $teacher['id']; ?>, '<?php echo htmlspecialchars($teacher['teacher_name']); ?>', <?php echo $teacher['basic_salary'] ?? 0; ?>, <?php echo $teacher['allowances'] ?? 0; ?>, <?php echo $teacher['deductions'] ?? 0; ?>)">
                                        <i class="fas fa-cog"></i> Configure
                                    </button>
                                    <?php if ($teacher['config_active']): ?>
                                        <a href="salary-history.php?teacher_id=<?php echo $teacher['id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-history"></i> History
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Salary Configuration Modal -->
<div class="modal" id="salaryConfigModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h5 class="modal-title">Configure Salary - <span id="modalTeacherName"></span></h5>
            <button type="button" class="modal-close" data-dismiss="modal">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="update_salary_config">
                <input type="hidden" name="teacher_id" id="modalTeacherId">
                
                <div class="form-group">
                    <label class="form-label">Basic Salary *</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text">BDT</span>
                        </div>
                        <input type="number" name="basic_salary" id="modalBasicSalary" class="form-control" step="0.01" min="0" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Allowances</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text">BDT</span>
                        </div>
                        <input type="number" name="allowances" id="modalAllowances" class="form-control" step="0.01" min="0" value="0">
                    </div>
                    <small class="text-muted">Transport, meal, or other allowances</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Deductions</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text">BDT</span>
                        </div>
                        <input type="number" name="deductions" id="modalDeductions" class="form-control" step="0.01" min="0" value="0">
                    </div>
                    <small class="text-muted">Tax, insurance, or other deductions</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Effective From</label>
                    <input type="date" name="effective_from" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="salary-preview mt-3 p-3 bg-light rounded">
                    <h6>Salary Calculation Preview:</h6>
                    <div class="d-flex justify-content-between">
                        <span>Basic Salary:</span>
                        <span id="previewBasic">BDT 0.00</span>
                    </div>
                    <div class="d-flex justify-content-between text-success">
                        <span>+ Allowances:</span>
                        <span id="previewAllowances">BDT 0.00</span>
                    </div>
                    <div class="d-flex justify-content-between text-danger">
                        <span>- Deductions:</span>
                        <span id="previewDeductions">BDT 0.00</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between font-weight-bold">
                        <span>Net Salary:</span>
                        <span id="previewNet" class="text-primary">BDT 0.00</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Configuration</button>
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
.stat-card.info { border-left: 4px solid var(--info-color); }
.stat-card.warning { border-left: 4px solid var(--warning-color); }

.stat-number {
    font-size: 24px;
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

.input-group-text {
    background: #f8f9fa;
    border-color: #e0e0e0;
}

.salary-preview {
    border: 1px solid #e0e0e0;
}
</style>

<script>
function configureSalary(teacherId, teacherName, basicSalary, allowances, deductions) {
    document.getElementById('modalTeacherId').value = teacherId;
    document.getElementById('modalTeacherName').textContent = teacherName;
    document.getElementById('modalBasicSalary').value = basicSalary;
    document.getElementById('modalAllowances').value = allowances;
    document.getElementById('modalDeductions').value = deductions;
    
    updateSalaryPreview();
    showModal('salaryConfigModal');
}

function updateSalaryPreview() {
    const basic = parseFloat(document.getElementById('modalBasicSalary').value) || 0;
    const allowances = parseFloat(document.getElementById('modalAllowances').value) || 0;
    const deductions = parseFloat(document.getElementById('modalDeductions').value) || 0;
    const net = basic + allowances - deductions;
    
    document.getElementById('previewBasic').textContent = 'BDT ' + basic.toFixed(2);
    document.getElementById('previewAllowances').textContent = 'BDT ' + allowances.toFixed(2);
    document.getElementById('previewDeductions').textContent = 'BDT ' + deductions.toFixed(2);
    document.getElementById('previewNet').textContent = 'BDT ' + net.toFixed(2);
}

// Add event listeners for real-time preview updates
document.addEventListener('DOMContentLoaded', function() {
    ['modalBasicSalary', 'modalAllowances', 'modalDeductions'].forEach(function(id) {
        document.getElementById(id).addEventListener('input', updateSalaryPreview);
    });
});
</script>

<?php require_once '../../components/footer.php'; ?>